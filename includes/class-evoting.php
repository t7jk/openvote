<?php
defined( 'ABSPATH' ) || exit;

class Evoting {

    private Evoting_Loader $loader;

    public function __construct() {
        $this->loader = new Evoting_Loader();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_rest_hooks();
        $this->define_block_hooks();
    }

    private function set_locale(): void {
        $i18n = new Evoting_I18n();
        $this->loader->add_action( 'plugins_loaded', $i18n, 'load_plugin_textdomain' );
    }

    private function define_admin_hooks(): void {
        require_once EVOTING_PLUGIN_DIR . 'admin/class-evoting-admin.php';
        require_once EVOTING_PLUGIN_DIR . 'admin/class-evoting-admin-polls.php';

        $admin = new Evoting_Admin();
        $this->loader->add_action( 'admin_menu', $admin, 'add_menu_pages' );
        $this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_styles' );
        $this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_scripts' );

        $polls = new Evoting_Admin_Polls();
        $this->loader->add_action( 'admin_init', $polls, 'handle_form_submission' );
    }

    private function define_public_hooks(): void {
        require_once EVOTING_PLUGIN_DIR . 'public/class-evoting-public.php';

        $public = new Evoting_Public();
        $this->loader->add_action( 'wp_enqueue_scripts', $public, 'enqueue_styles' );
        $this->loader->add_action( 'wp_enqueue_scripts', $public, 'enqueue_scripts' );
    }

    private function define_rest_hooks(): void {
        require_once EVOTING_PLUGIN_DIR . 'rest-api/class-evoting-rest-controller.php';

        $rest = new Evoting_Rest_Controller();
        $this->loader->add_action( 'rest_api_init', $rest, 'register_routes' );
    }

    private function define_block_hooks(): void {
        $this->loader->add_action( 'init', $this, 'register_blocks' );
    }

    public function register_blocks(): void {
        register_block_type( EVOTING_PLUGIN_DIR . 'blocks/evoting-poll' );
    }

    public function run(): void {
        $this->loader->run();
    }
}
