<?php
/**
 * Classe principal do plugin que carrega todas as funcionalidades
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ferramentas_Upload_Loader {
    private static $instance = null;

    const PREFIX = 'fu_';
    const TEXT_DOMAIN = 'ferramentas-upload';
    const PAGE_SLUG = 'ferramentas-upload';
    const PLUGIN_PATH = FU_PLUGIN_PATH;

    // Nonces para Alt Text
    const ALT_NONCE_ACTION = 'fu_update_alt_text';
    const ALT_NONCE_FIELD = 'fu_alt_text_nonce';

    // Nonces para SERP
    const SERP_NONCE_ACTION = 'fu_update_serp';
    const SERP_NONCE_FIELD = 'fu_serp_nonce';

    // Nonces para Export Posts
    const EXPORT_POSTS_NONCE_ACTION = 'fu_export_posts';
    const EXPORT_POSTS_NONCE_FIELD = 'fu_export_posts_nonce';

    // Nonces para Trash Posts
    const TRASH_NONCE_ACTION = 'fu_trash_posts';
    const TRASH_NONCE_FIELD = 'fu_trash_posts_nonce';

    // Nonces para Category Posts
    const CATEGORY_NONCE_ACTION = 'fu_recategorize_posts';
    const CATEGORY_NONCE_FIELD = 'fu_category_posts_nonce';

    private function __construct() {
        $this->define_constants();
        $this->init_hooks();
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function define_constants() {
        if (!defined('FU_PREFIX')) {
            define('FU_PREFIX', self::PREFIX);
        }
        if (!defined('FU_TEXT_DOMAIN')) {
            define('FU_TEXT_DOMAIN', self::TEXT_DOMAIN);
        }
        if (!defined('FU_PAGE_SLUG')) {
            define('FU_PAGE_SLUG', self::PAGE_SLUG);
        }

        // Alt Text
        if (!defined('FU_ALT_NONCE_ACTION')) {
            define('FU_ALT_NONCE_ACTION', self::ALT_NONCE_ACTION);
        }
        if (!defined('FU_ALT_NONCE_FIELD')) {
            define('FU_ALT_NONCE_FIELD', self::ALT_NONCE_FIELD);
        }

        // SERP
        if (!defined('FU_SERP_NONCE_ACTION')) {
            define('FU_SERP_NONCE_ACTION', self::SERP_NONCE_ACTION);
        }
        if (!defined('FU_SERP_NONCE_FIELD')) {
            define('FU_SERP_NONCE_FIELD', self::SERP_NONCE_FIELD);
        }

        // Export Posts
        if (!defined('FU_EXPORT_POSTS_NONCE_ACTION')) {
            define('FU_EXPORT_POSTS_NONCE_ACTION', self::EXPORT_POSTS_NONCE_ACTION);
        }
        if (!defined('FU_EXPORT_POSTS_NONCE_FIELD')) {
            define('FU_EXPORT_POSTS_NONCE_FIELD', self::EXPORT_POSTS_NONCE_FIELD);
        }

        // Trash Posts
        if (!defined('FU_TRASH_NONCE_ACTION')) {
            define('FU_TRASH_NONCE_ACTION', self::TRASH_NONCE_ACTION);
        }
        if (!defined('FU_TRASH_NONCE_FIELD')) {
            define('FU_TRASH_NONCE_FIELD', self::TRASH_NONCE_FIELD);
        }

        // Category Posts
        if (!defined('FU_CATEGORY_NONCE_ACTION')) {
            define('FU_CATEGORY_NONCE_ACTION', self::CATEGORY_NONCE_ACTION);
        }
        if (!defined('FU_CATEGORY_NONCE_FIELD')) {
            define('FU_CATEGORY_NONCE_FIELD', self::CATEGORY_NONCE_FIELD);
        }
    }

    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'process_admin_actions'));
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Ferramentas de Upload', 'ferramentas-upload'),
            __('Ferramentas de Upload', 'ferramentas-upload'),
            'manage_options',
            FU_PAGE_SLUG,
            array($this, 'render_admin_page'),
            'dashicons-upload'
        );
    }

    public function render_admin_page() {
        require_once FU_PLUGIN_PATH . 'includes/class-admin-page.php';
        $admin_page = new Ferramentas_Upload_Admin_Page();
        $admin_page->render();
    }

    public function process_admin_actions() {
        if (!isset($_POST[FU_PREFIX . 'action'])) {
            return;
        }

        $action = sanitize_key($_POST[FU_PREFIX . 'action']);

        if ($action === 'export_posts_categories' && isset($_POST[FU_EXPORT_POSTS_NONCE_FIELD])) {
            check_admin_referer(FU_EXPORT_POSTS_NONCE_ACTION, FU_EXPORT_POSTS_NONCE_FIELD);
            require_once FU_PLUGIN_PATH . 'includes/class-post-exporter.php';
            $exporter = new Ferramentas_Upload_Post_Exporter();
            $exporter->export_posts_categories();
            exit;
        }
    }
}