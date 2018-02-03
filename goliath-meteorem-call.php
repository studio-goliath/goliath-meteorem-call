<?php
/**
 * Plugin Name: Goliath Meteorem Call
 * Description: Récupération de la météo via l'API meteorem (http://prevision.meteorem.com/wsmeteo/wsmeteo.asmx)
 * Author: Studio Goliath
 * Author URI: http://studio-goliath.fr/
 * Version: 1.0
 */

require_once( plugin_dir_path( __FILE__ ) . '/admin/meteorem-settings.php' );

class Meteorem_Call {

    private $wsdl_url;
    private $id;

    public function __construct() {

        $this->wsdl_url = 'http://prevision.meteorem.com/wsmeteo/wsmeteo.asmx?WSDL';
        $this->id = '17000';

        $meteorem_option = get_option( 'meteorem_option' );
        if ( $meteorem_option && $meteorem_option['url'] ) {
            $this->wsdl_url = $meteorem_option['url'];
        }
        if ( $meteorem_option && $meteorem_option['id'] ) {
            $this->id = $meteorem_option['id'];
        }

    }

    /**
     * Retourne l'url du WSDL
     *
     * @return string
     */
    protected function getWsdlUrl() {
        return $this->wsdl_url;
    }

    /**
     * Retourne l'id
     *
     * @return string
     */
    protected function getId() {
        return $this->id;
    }

    /**
     * Retourne le code langue sur 2 caractères
     *
     * @return bool|string
     */
    public function getLang() {
        $wp_local = get_locale();

        return substr( $wp_local, 0, 2 );
    }

    /**
     * Retourne la date courante (DateTime)
     *
     * @return DateTime|null
     */
    public function getCurrentDate() {

        $time_zone = get_option( 'timezone_string' );
        $date = null;

        if ( $time_zone ) {
            $date = new DateTime( 'now', new DateTimeZone( get_option( 'timezone_string' ) ) );
        } else {
            $date = new DateTime( 'now' );
        }

        return $date;

    }

    /**
     * Retourne la date courante (possibilité d'ajouter de jours) au format attendu par meteorem.
     *
     * @param int $addDays
     * @param string $defaultHour
     * @return string
     * @throws Exception
     */
    protected function getDate( $addDays = 0, $defaultHour = "06:00" ) {

        $date = $this->getCurrentDate();

        if ( $addDays ) {
            $date->add( new DateInterval("P{$addDays}D" ) );
        }

        return $date->format( 'd/m/Y' ) . " " . $defaultHour;

    }

    /**
     * Retourne vrai si la date courante correspond au jour à partir de la valeur EPHEM (ex: "08:29/18:03")
     *
     * @param $ephem
     * @return bool
     */
    protected function isDayTime( $ephem ) {
        $dates = explode( '/', $ephem );
        $current = $this->getCurrentDate();
        $day = true;
        if ( is_array($dates) && count( $dates ) >= 2 ) {
            if ( $current < new DateTime( $current->format( 'Y-m-d' ) . $dates[0] ) || $current > new DateTime( $current->format( 'Y-m-d' ) . $dates[1] ) ) {
                $day = false;
            }
        }

        return $day;
    }

    /**
     * Permet de retourner l'échéance "la plus proche" de l'heure actuelle en vérifiant que le 1er paramètre n'est pas "N.C."
     *
     * @param $echeances
     * @return null
     */
    protected function getBestEcheance( $echeances ) {
        if ( $echeances && count( $echeances ) ) {
            $date = $this->getCurrentDate();
            $best = $echeances[0];

            foreach ( $echeances as $echeance ) {
                $d = new DateTime( $echeance->echeance );
                if ( $d <= $date ) {
                    if ( $echeance->Previsions->Prevision[0]->valeur !== 'N.C.' ) {
                        $best = $echeance;
                    }
                }
            }

            return $best;
        }

        return null;
    }

    /**
     * Retourne un array contenant les principales données du temps de la localisation ($lat, $lng).
     * Le $slug permet de créer un transient unique par $slug / langue.
     *
     * @param $lat
     * @param $lng
     * @param $slug
     * @return array
     * @throws Exception
     */
    public function getWeather( $lat, $lng, $slug ) {

        $lang = $this->getLang();
        $transient_name = $lang . "_" . $slug;

        // Si on a pas de transient pour cette destination et ce code langue on fait un appel à meteorem puis on enregistre un transient avec les résultats
        if ( false === ( $result = get_transient( $transient_name ) ) ) {
            $result = array(
                'date'      => '',
                'wind'      => '',
                'temp'      => '',
                'weather'   => '',
                'day'       => true,
                'log'       => false
            );

            // Variables disponibles : CODE,WEATHER,WINDIR,WINDKMH,WINDBFT,GUST,GALE,PRMSL,TEMP,TMPRSTI,RHM,EPHEM,SAINT (attention : certaines provoquent une erreur)
            $variables = array(
                'WINDKMH'   => '',
                'TEMP'      => '',
                'WEATHER'   => '',
                'EPHEM'     => '',
            );

            try {
                $client = new SoapClient( $this->getWsdlUrl() );

                $params = array(
                    'id'            => $this->getId(),
                    'lat'           => $lat,
                    'lon'           => $lng,
                    'variables'     => implode( ',', array_keys( $variables ) ),
                    'gdhDebut'      => $this->getDate(),
                    'gdhFin'        => $this->getDate( 1 ),
                    'intervalle'    => '1',
                    'langue'        => $lang,
                );

                $meteo = $client->CalculPrevisionLanguage($params)->CalculPrevisionLanguageResult;

                if ( $meteo && $meteo->Echeances && $meteo->Echeances->Echeance && count( $meteo->Echeances->Echeance ) ) {
                    if ( $meteo->Echeances->Echeance[0]->Previsions && $meteo->Echeances->Echeance[0]->Previsions->Prevision && count( $meteo->Echeances->Echeance[0]->Previsions->Prevision ) ) {
                        $echeance = $this->getBestEcheance( $meteo->Echeances->Echeance );
                        if ( $echeance ) {
                            $result['date'] = $echeance->echeance;
                            foreach ( $echeance->Previsions->Prevision as $prevision ) {
                                foreach ( $variables as $key => $variable ) {
                                    if ( strpos( $prevision->parametre, $key ) === 0 && $prevision->valeur !== 'N.C.' ) {
                                        $variables[$key] = $prevision->valeur;
                                    }
                                }
                            }
                        }
                    }

                    $result['wind'] = $variables['WINDKMH'];
                    $result['temp'] = $variables['TEMP'];
                    $result['weather'] = $variables['WEATHER'];

                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        $result['log'] = json_encode( $meteo );
                    }

                    if ( $variables['EPHEM'] ) {
                        $result['day'] = $this->isDayTime( $variables['EPHEM'] );
                    }

                    set_transient( $transient_name, $result, 0.5 * HOUR_IN_SECONDS );
                }

            } catch (SoapFault $fault) {
                $error = "SOAP Fault: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring})";
                error_log( $error );
                trigger_error( $error, E_USER_WARNING );
            }
        }

        return $result;

    }

}