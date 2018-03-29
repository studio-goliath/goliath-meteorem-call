<?php
/**
 * Meteorem settings
 *
 * id
 *
 */

class MeteoremSettings {

    private $options;

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
    }

    public function add_plugin_page() {
        add_options_page(
            'Meteorem settings',
            'Meteorem',
            'manage_options',
            'meteorem-settings',
            array( $this, 'create_admin_page' )
        );
    }

    public function create_admin_page() {
        $this->options = get_option( 'meteorem_option' );
        ?>
        <div class="wrap">
            <h1><?php _e( 'Meteorem ', 'goliath-meteorem-call' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'meteorem_group' );
                do_settings_sections( 'meteorem-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function page_init() {
        register_setting(
            'meteorem_group', // Option group
            'meteorem_option', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'setting_section_id', // ID
            __( 'WebService settings', 'goliath-meteorem-call' ), // Title
            null, // Callback
            'meteorem-settings' // Page
        );

        add_settings_field(
            'id', // ID
            __( 'ID', 'goliath-meteorem-call' ), // Title
            array( $this, 'id_callback' ), // Callback
            'meteorem-settings', // Page
            'setting_section_id' // Section
        );

        add_settings_field(
            'url',
            __( 'URL', 'goliath-meteorem-call' ), // Title
            array( $this, 'url_callback' ),
            'meteorem-settings',
            'setting_section_id'
        );
    }

    public function sanitize( $input ) {
        $new_input = array();
        if( isset( $input['id'] ) )
            $new_input['id'] = absint( $input['id'] );

        if( isset( $input['url'] ) )
            $new_input['url'] = sanitize_text_field( $input['url'] );

        return $new_input;
    }

    public function id_callback() {
        printf(
            '<input type="text" id="id" name="meteorem_option[id]" value="%s" />',
            isset( $this->options['id'] ) ? esc_attr( $this->options['id']) : ''
        );
    }

    public function url_callback() {
        printf(
            '<input class="widefat" type="text" id="url" name="meteorem_option[url]" placeholder="https://domain.com/webservice.asmx?WSDL" value="%s" />',
            isset( $this->options['url'] ) ? esc_attr( $this->options['url']) : ''
        );
    }
}

if( is_admin() ) {
    $meteorem_settings_page = new MeteoremSettings();
}
