<?php
defined( 'ABSPATH' ) || exit;

class Openvote {

    private Openvote_Loader $loader;

    public function __construct() {
        $this->loader = new Openvote_Loader();
        add_action( 'admin_init', [ 'Openvote_Activator', 'maybe_upgrade' ], 1 );
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_rest_hooks();
        $this->define_block_hooks();
    }

    private function set_locale(): void {
        $i18n = new Openvote_I18n();
        $this->loader->add_action( 'plugins_loaded', $i18n, 'load_plugin_textdomain' );
    }

    private function define_admin_hooks(): void {
        require_once OPENVOTE_PLUGIN_DIR . 'includes/class-openvote-mailer.php';
        Openvote_Mailer::register_hooks();

        require_once OPENVOTE_PLUGIN_DIR . 'admin/class-openvote-admin.php';
        require_once OPENVOTE_PLUGIN_DIR . 'admin/class-openvote-polls-list.php';
        require_once OPENVOTE_PLUGIN_DIR . 'admin/class-openvote-admin-polls.php';
        require_once OPENVOTE_PLUGIN_DIR . 'admin/class-openvote-admin-settings.php';
        require_once OPENVOTE_PLUGIN_DIR . 'admin/class-openvote-admin-roles.php';
        require_once OPENVOTE_PLUGIN_DIR . 'admin/class-openvote-admin-uninstall.php';
        require_once OPENVOTE_PLUGIN_DIR . 'admin/class-openvote-admin-surveys.php';
        require_once OPENVOTE_PLUGIN_DIR . 'admin/class-openvote-surveys-list.php';
        require_once OPENVOTE_PLUGIN_DIR . 'models/class-openvote-survey.php';

        $admin = new Openvote_Admin();
        $this->loader->add_action( 'admin_menu', $admin, 'add_menu_pages' );
        $this->loader->add_action( 'admin_menu', $admin, 'style_menu_for_restricted_roles', 999 );
        $this->loader->add_action( 'admin_bar_menu', $admin, 'customize_admin_bar_logo', 999 );
        $this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_menu_restrict_script' );
        $this->loader->add_action( 'admin_init', $admin, 'handle_results_pdf_download', 1 );
        $this->loader->add_action( 'admin_init', $admin, 'handle_openvote_get_actions', 5 );
        $this->loader->add_action( 'admin_init', $admin, 'handle_bulk_polls_action', 5 );
        $this->loader->add_action( 'admin_init', $admin, 'handle_openvote_surveys_get_actions', 5 );
        $this->loader->add_action( 'admin_init', $admin, 'redirect_openvote_new' );
        $this->loader->add_action( 'admin_notices', $admin, 'render_brand_header' );
        $this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_styles' );
        $this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_scripts' );

        $polls = new Openvote_Admin_Polls();
        $this->loader->add_action( 'admin_init', $polls, 'handle_form_submission' );

        $settings = new Openvote_Admin_Settings();
        $this->loader->add_action( 'admin_init', $settings, 'handle_form_submission' );

        $roles = new Openvote_Admin_Roles();
        $this->loader->add_action( 'admin_init', $roles, 'handle_form_submission' );

        $uninstall = new Openvote_Admin_Uninstall();
        $this->loader->add_action( 'admin_init', $uninstall, 'handle_form_submission' );

        $surveys_admin = new Openvote_Admin_Surveys();
        $this->loader->add_action( 'admin_init', $surveys_admin, 'handle_form_submission' );
    }

    private function define_public_hooks(): void {
        require_once OPENVOTE_PLUGIN_DIR . 'public/class-openvote-public.php';
        require_once OPENVOTE_PLUGIN_DIR . 'includes/class-openvote-vote-page.php';
        require_once OPENVOTE_PLUGIN_DIR . 'includes/class-openvote-law-page.php';
        require_once OPENVOTE_PLUGIN_DIR . 'includes/class-openvote-survey-page.php';

        $public = new Openvote_Public();
        $this->loader->add_action( 'wp_enqueue_scripts', $public, 'enqueue_styles' );
        $this->loader->add_action( 'wp_enqueue_scripts', $public, 'enqueue_scripts' );

        // Strona ankiet: rewrite + template (klasy statyczne — rejestrujemy wprost).
        add_filter( 'query_vars',       [ 'Openvote_Survey_Page', 'register_query_var' ] );
        add_action( 'init',             [ 'Openvote_Survey_Page', 'add_rewrite_rule' ] );
        add_filter( 'template_include', [ 'Openvote_Survey_Page', 'filter_template' ] );
        add_filter( 'body_class',       [ 'Openvote_Survey_Page', 'add_body_class' ] );
    }

    private function define_rest_hooks(): void {
        require_once OPENVOTE_PLUGIN_DIR . 'rest-api/class-openvote-rest-controller.php';
        require_once OPENVOTE_PLUGIN_DIR . 'rest-api/class-openvote-groups-rest-controller.php';
        require_once OPENVOTE_PLUGIN_DIR . 'rest-api/class-openvote-surveys-rest-controller.php';

        $rest = new Openvote_Rest_Controller();
        $this->loader->add_action( 'rest_api_init', $rest, 'register_routes' );

        $groups_rest = new Openvote_Groups_Rest_Controller();
        $this->loader->add_action( 'rest_api_init', $groups_rest, 'register_routes' );

        $surveys_rest = new Openvote_Surveys_Rest_Controller();
        $this->loader->add_action( 'rest_api_init', $surveys_rest, 'register_routes' );
    }

    private function define_block_hooks(): void {
        $this->loader->add_action( 'init', $this, 'register_blocks' );
    }

    public function register_blocks(): void {
        // Istniejący blok pojedynczego głosowania.
        register_block_type( OPENVOTE_PLUGIN_DIR . 'blocks/openvote-poll' );

        // Nowy blok zakładek głosowań (bez procesu budowania — vanilla JS).
        wp_register_script(
            'openvote-voting-tabs-editor',
            OPENVOTE_PLUGIN_URL . 'blocks/openvote-voting-tabs/editor.js',
            [ 'wp-blocks', 'wp-element' ],
            OPENVOTE_VERSION,
            false
        );

        register_block_type( 'openvote/voting-tabs', [
            'editor_script'   => 'openvote-voting-tabs-editor',
            'render_callback' => [ $this, 'render_voting_tabs_block' ],
        ] );

        // Blok formularza ankiety.
        wp_register_script(
            'openvote-survey-form-editor',
            OPENVOTE_PLUGIN_URL . 'blocks/openvote-survey-form/editor.js',
            [ 'wp-blocks', 'wp-element' ],
            OPENVOTE_VERSION,
            false
        );

        register_block_type( 'openvote/survey-form', [
            'editor_script'   => 'openvote-survey-form-editor',
            'render_callback' => [ $this, 'render_survey_form_block' ],
        ] );

        // Blok zgłoszeń ankiet (nie spam).
        wp_register_script(
            'openvote-survey-responses-editor',
            OPENVOTE_PLUGIN_URL . 'blocks/openvote-survey-responses/editor.js',
            [ 'wp-blocks', 'wp-element' ],
            OPENVOTE_VERSION,
            false
        );

        register_block_type( 'openvote/survey-responses', [
            'editor_script'   => 'openvote-survey-responses-editor',
            'render_callback' => [ $this, 'render_survey_responses_block' ],
        ] );
    }

    /**
     * Server-side render callback dla bloku openvote/voting-tabs.
     */
    public function render_voting_tabs_block( array $attributes, string $content ): string {
        ob_start();
        include OPENVOTE_PLUGIN_DIR . 'blocks/openvote-voting-tabs/render.php';
        return ob_get_clean();
    }

    /**
     * Server-side render callback dla bloku openvote/survey-form.
     */
    public function render_survey_form_block( array $attributes, string $content ): string {
        ob_start();
        include OPENVOTE_PLUGIN_DIR . 'blocks/openvote-survey-form/render.php';
        return ob_get_clean();
    }

    /**
     * Server-side render callback dla bloku openvote/survey-responses.
     */
    public function render_survey_responses_block( array $attributes, string $content ): string {
        ob_start();
        include OPENVOTE_PLUGIN_DIR . 'blocks/openvote-survey-responses/render.php';
        return ob_get_clean();
    }

    /**
     * Publiczny alias — używany przez survey-page.php fallback.
     */
    public static function render_survey_form_block_static( array $attributes, string $content ): string {
        ob_start();
        include OPENVOTE_PLUGIN_DIR . 'blocks/openvote-survey-form/render.php';
        return ob_get_clean();
    }

    public function run(): void {
        $this->loader->run();
    }
}
