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

    // Nonces para FAQ
    const FAQ_NONCE_ACTION = 'fu_faq_action';
    const FAQ_NONCE_FIELD = 'fu_faq_nonce';
    const FAQ_AJAX_NONCE_ACTION = 'fu_faq_ajax';
    const FAQ_AJAX_NONCE_FIELD = 'fu_faq_ajax_nonce';

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

        // FAQ
        if (!defined('FU_FAQ_NONCE_ACTION')) {
            define('FU_FAQ_NONCE_ACTION', self::FAQ_NONCE_ACTION);
        }
        if (!defined('FU_FAQ_NONCE_FIELD')) {
            define('FU_FAQ_NONCE_FIELD', self::FAQ_NONCE_FIELD);
        }
        if (!defined('FU_FAQ_AJAX_NONCE_ACTION')) {
            define('FU_FAQ_AJAX_NONCE_ACTION', self::FAQ_AJAX_NONCE_ACTION);
        }
        if (!defined('FU_FAQ_AJAX_NONCE_FIELD')) {
            define('FU_FAQ_AJAX_NONCE_FIELD', self::FAQ_AJAX_NONCE_FIELD);
        }
    }

    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'process_admin_actions'));
        add_action('wp_ajax_fu_generate_faq', array($this, 'ajax_generate_faq'));
        add_action('wp_ajax_fu_apply_faq', array($this, 'ajax_apply_faq'));
        add_action('wp_ajax_fu_process_urls_csv', array($this, 'ajax_process_urls_csv'));
        add_action('wp_ajax_fu_apply_reviewed_csv', array($this, 'ajax_apply_reviewed_csv'));
        add_action('wp_head', array($this, 'output_faq_structured_data'));
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

        if ($action === 'save_faq_settings' && isset($_POST[FU_FAQ_NONCE_FIELD])) {
            check_admin_referer(FU_FAQ_NONCE_ACTION, FU_FAQ_NONCE_FIELD);
            $api_key = isset($_POST['fu_faq_api_key']) ? sanitize_text_field($_POST['fu_faq_api_key']) : '';
            $prompt = isset($_POST['fu_faq_prompt']) ? wp_kses_post($_POST['fu_faq_prompt']) : '';
            $api_url = isset($_POST['fu_faq_api_url']) ? esc_url_raw($_POST['fu_faq_api_url']) : 'https://api.openai.com/v1/chat/completions';
            
            update_option('fu_faq_api_key', $api_key);
            update_option('fu_faq_prompt', $prompt);
            update_option('fu_faq_api_url', $api_url);
            
            wp_redirect(add_query_arg(array('tab' => 'faq', 'settings_saved' => '1'), admin_url('admin.php?page=' . FU_PAGE_SLUG)));
            exit;
        }
    }

    public function ajax_generate_faq() {
        check_ajax_referer(FU_FAQ_AJAX_NONCE_ACTION, 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permissão negada.', 'ferramentas-upload')));
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if ($post_id <= 0) {
            wp_send_json_error(array('message' => __('ID do post inválido.', 'ferramentas-upload')));
        }

        require_once FU_PLUGIN_PATH . 'includes/class-faq-handler.php';
        $handler = new Ferramentas_Upload_FAQ_Handler();
        $result = $handler->generate_faq_for_post($post_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success($result);
    }

    public function ajax_apply_faq() {
        check_ajax_referer(FU_FAQ_AJAX_NONCE_ACTION, 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permissão negada.', 'ferramentas-upload')));
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $faq_data = isset($_POST['faq_data']) ? json_decode(stripslashes($_POST['faq_data']), true) : array();
        
        if ($post_id <= 0 || empty($faq_data)) {
            wp_send_json_error(array('message' => __('Dados inválidos.', 'ferramentas-upload')));
        }

        require_once FU_PLUGIN_PATH . 'includes/class-faq-handler.php';
        $handler = new Ferramentas_Upload_FAQ_Handler();
        $result = $handler->save_faq_to_post($post_id, $faq_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array('message' => __('FAQ aplicado com sucesso!', 'ferramentas-upload')));
    }

    public function ajax_process_urls_csv() {
        check_ajax_referer(FU_FAQ_AJAX_NONCE_ACTION, 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permissão negada.', 'ferramentas-upload')));
        }

        if (empty($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array('message' => __('Erro ao fazer upload do arquivo.', 'ferramentas-upload')));
        }

        $uploaded_file = $_FILES['csv_file']['tmp_name'];
        
        require_once FU_PLUGIN_PATH . 'includes/class-faq-handler.php';
        $handler = new Ferramentas_Upload_FAQ_Handler();
        
        // Processa o CSV
        $results = $handler->process_urls_csv($uploaded_file);
        
        if (is_wp_error($results)) {
            wp_send_json_error(array('message' => $results->get_error_message()));
        }

        if (empty($results)) {
            wp_send_json_error(array('message' => __('Nenhum post elegível encontrado nas URLs fornecidas.', 'ferramentas-upload')));
        }

        // Gera CSV de resultados
        $csv_result = $handler->generate_results_csv($results);
        
        if (is_wp_error($csv_result)) {
            wp_send_json_error(array('message' => $csv_result->get_error_message()));
        }

        // Lê o arquivo e envia como download
        $file_content = file_get_contents($csv_result['filepath']);
        $file_content_base64 = base64_encode($file_content);
        
        // Remove arquivo temporário
        unlink($csv_result['filepath']);

        wp_send_json_success(array(
            'message' => sprintf(__('Análise concluída! %d posts elegíveis encontrados.', 'ferramentas-upload'), count($results)),
            'filename' => $csv_result['filename'],
            'file_content' => $file_content_base64,
            'results_count' => count($results)
        ));
    }

    public function ajax_apply_reviewed_csv() {
        check_ajax_referer(FU_FAQ_AJAX_NONCE_ACTION, 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permissão negada.', 'ferramentas-upload')));
        }

        if (empty($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array('message' => __('Erro ao fazer upload do arquivo.', 'ferramentas-upload')));
        }

        $uploaded_file = $_FILES['csv_file']['tmp_name'];
        
        require_once FU_PLUGIN_PATH . 'includes/class-faq-handler.php';
        $handler = new Ferramentas_Upload_FAQ_Handler();
        
        // Processa o CSV revisado
        $result = $handler->process_reviewed_csv($uploaded_file);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        $message = sprintf(__('FAQ aplicado com sucesso em %d post(s).', 'ferramentas-upload'), $result['applied']);
        
        if (!empty($result['errors'])) {
            $message .= ' ' . sprintf(__('Erros: %d', 'ferramentas-upload'), count($result['errors']));
        }

        wp_send_json_success(array(
            'message' => $message,
            'applied' => $result['applied'],
            'errors' => $result['errors']
        ));
    }

    public function output_faq_structured_data() {
        if (!is_singular()) {
            return;
        }

        global $post;
        $faq_data = get_post_meta($post->ID, '_fu_faq_structured_data', true);
        
        if (empty($faq_data) || !is_array($faq_data)) {
            return;
        }

        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array()
        );

        foreach ($faq_data as $faq) {
            if (!empty($faq['question']) && !empty($faq['answer'])) {
                $schema['mainEntity'][] = array(
                    '@type' => 'Question',
                    'name' => sanitize_text_field($faq['question']),
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => wp_kses_post($faq['answer'])
                    )
                );
            }
        }

        if (!empty($schema['mainEntity'])) {
            echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
        }
    }
}