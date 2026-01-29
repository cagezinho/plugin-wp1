<?php
/**
 * Classe responsável pela renderização da página de administração
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ferramentas_Upload_Admin_Page {
    private $active_tab;

    public function __construct() {
        $this->active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'alt_text';
    }

    public function render() {
        // Processar ações que exibem mensagens na página
        if (isset($_POST[FU_PREFIX . 'action'])) {
            $action = sanitize_key($_POST[FU_PREFIX . 'action']);
            
            switch ($action) {
                case 'update_alt_text':
                    if (isset($_POST[FU_ALT_NONCE_FIELD])) {
                        check_admin_referer(FU_ALT_NONCE_ACTION, FU_ALT_NONCE_FIELD);
                        require_once FU_PLUGIN_PATH . 'includes/class-alt-text-handler.php';
                        $handler = new Ferramentas_Upload_Alt_Text_Handler();
                        $handler->process_upload();
                    }
                    break;
                    
                case 'update_serp':
                    if (isset($_POST[FU_SERP_NONCE_FIELD])) {
                        check_admin_referer(FU_SERP_NONCE_ACTION, FU_SERP_NONCE_FIELD);
                        require_once FU_PLUGIN_PATH . 'includes/class-serp-handler.php';
                        $handler = new Ferramentas_Upload_SERP_Handler();
                        $handler->process_upload();
                    }
                    break;

                case 'trash_posts':
                    if (isset($_POST[FU_TRASH_NONCE_FIELD])) {
                        check_admin_referer(FU_TRASH_NONCE_ACTION, FU_TRASH_NONCE_FIELD);
                        require_once FU_PLUGIN_PATH . 'includes/class-post-trash-handler.php';
                        $handler = new Ferramentas_Upload_Post_Trash_Handler();
                        $handler->process_upload();
                    }
                    break;

                case 'recategorize_posts':
                    if (isset($_POST[FU_CATEGORY_NONCE_FIELD])) {
                        check_admin_referer(FU_CATEGORY_NONCE_ACTION, FU_CATEGORY_NONCE_FIELD);
                        require_once FU_PLUGIN_PATH . 'includes/class-post-category-handler.php';
                        $handler = new Ferramentas_Upload_Post_Category_Handler();
                        $handler->process_upload();
                    }
                    break;

                case 'export_posts_categories':
                    if (isset($_POST[FU_EXPORT_POSTS_NONCE_FIELD])) {
                        check_admin_referer(FU_EXPORT_POSTS_NONCE_ACTION, FU_EXPORT_POSTS_NONCE_FIELD);
                        require_once FU_PLUGIN_PATH . 'includes/class-post-exporter.php';
                        $exporter = new Ferramentas_Upload_Post_Exporter();
                        $exporter->export_posts_categories();
                    }
                    break;
            }
        }

        $this->render_page_header();
        $this->render_tabs();
        $this->render_active_tab_content();
        echo '</div>'; // Fecha .fu-content
        echo '</div>'; // Fecha .fu-main-container
        echo '</div>'; // Fecha .ferramentas-upload-wrapper
    }

    private function render_page_header() {
        // Enfileira os estilos customizados
        wp_enqueue_style('fu-admin-styles', plugins_url('includes/admin-styles.css', dirname(__FILE__)), array(), '1.0.0');
        
        echo '<div class="ferramentas-upload-wrapper">';
        echo '<div class="fu-header">';
        echo '<h1>';
        echo '<span class="fu-logo">';
        echo '<svg viewBox="0 0 500 500" xmlns="http://www.w3.org/2000/svg">';
        echo '<circle cx="250" cy="250" r="200" fill="#a4286a"/>';
        echo '<path d="M250 150 L350 350 L150 350 Z" fill="white"/>';
        echo '</svg>';
        echo '</span>';
        echo esc_html(get_admin_page_title());
        echo '</h1>';
        echo '</div>';
    }

    private function render_tabs() {
        echo '<div class="fu-main-container">';
        echo '<div class="fu-sidebar">';
        echo '<div class="fu-sidebar-header">';
        echo '<h2 class="fu-sidebar-title">' . esc_html__('Ferramentas', 'ferramentas-upload') . '</h2>';
        echo '<p class="fu-sidebar-subtitle">' . esc_html__('Selecione uma ferramenta', 'ferramentas-upload') . '</p>';
        echo '</div>';
        echo '<ul class="fu-nav-tabs">';
        $this->render_tab('alt_text', __('Atualizar Texto Alt', 'ferramentas-upload'));
        $this->render_tab('serp', __('Atualizar SERP Yoast', 'ferramentas-upload'));
        $this->render_tab('export_posts', __('Exportar Posts', 'ferramentas-upload'));
        $this->render_tab('trash_posts', __('Mover para Lixeira', 'ferramentas-upload'));
        $this->render_tab('recategorize_posts', __('Recategorizar Posts', 'ferramentas-upload'));
        $this->render_tab('faq', __('FAQ Estruturado', 'ferramentas-upload'));
        echo '</ul>';
        echo '</div>';
        echo '<div class="fu-content">';
    }

    private function render_tab($tab_id, $tab_title) {
        $class = $this->active_tab === $tab_id ? ' active' : '';
        echo '<li class="fu-nav-tab">';
        printf(
            '<a href="?page=%s&tab=%s" class="%s">%s</a>',
            esc_attr(FU_PAGE_SLUG),
            esc_attr($tab_id),
            esc_attr($class),
            esc_html($tab_title)
        );
        echo '</li>';
    }

    private function render_active_tab_content() {
        switch ($this->active_tab) {
            case 'alt_text':
                $this->render_alt_text_tab();
                break;
            case 'serp':
                $this->render_serp_tab();
                break;
            case 'export_posts':
                $this->render_export_posts_tab();
                break;
            case 'trash_posts':
                $this->render_trash_posts_tab();
                break;
            case 'recategorize_posts':
                $this->render_recategorize_posts_tab();
                break;
            case 'faq':
                $this->render_faq_tab();
                break;
        }
    }

    private function render_alt_text_tab() {
        ?>
        <div class="fu-content-header">
            <h2 class="fu-content-title"><?php esc_html_e('Atualizar Texto Alternativo (Alt Text) de Imagens', 'ferramentas-upload'); ?></h2>
            <p class="fu-content-description"><?php esc_html_e('Faça o upload de um arquivo CSV para atualizar o texto alternativo das imagens em massa.', 'ferramentas-upload'); ?></p>
        </div>

        <div class="fu-form">
            <div class="fu-form-section">
                <h4><?php esc_html_e('Como funciona', 'ferramentas-upload'); ?></h4>
                <p class="fu-content-description">
                    <?php 
                    esc_html_e('O CSV deve ter duas colunas: ', 'ferramentas-upload');
                    echo '<strong>' . esc_html__('Image URL', 'ferramentas-upload') . '</strong> ';
                    esc_html_e('e', 'ferramentas-upload');
                    echo ' <strong>' . esc_html__('Alt Text', 'ferramentas-upload') . '</strong>. ';
                    ?>
                </p>
                
                <div class="fu-form-notice">
                    <p><strong><?php esc_html_e('Importante:', 'ferramentas-upload'); ?></strong></p>
                    <ul>
                        <li><?php esc_html_e('Use a URL completa da imagem como ela aparece na Biblioteca de Mídia.', 'ferramentas-upload'); ?></li>
                        <li><?php esc_html_e('A primeira linha (cabeçalho) será ignorada.', 'ferramentas-upload'); ?></li>
                        <li><?php esc_html_e('Este processo irá atualizar o texto alt da imagem e também em todos os posts/páginas onde a imagem é usada.', 'ferramentas-upload'); ?></li>
                    </ul>
                </div>

                <form method="post" enctype="multipart/form-data" class="fu-form">
                    <input type="hidden" name="<?php echo esc_attr(FU_PREFIX . 'action'); ?>" value="update_alt_text">
                    <?php wp_nonce_field(FU_ALT_NONCE_ACTION, FU_ALT_NONCE_FIELD); ?>
                    
                    <div class="fu-form-group">
                        <label for="fu_alt_csv_file" class="fu-form-label">
                            <?php esc_html_e('Arquivo CSV', 'ferramentas-upload'); ?>
                        </label>
                        <input type="file" id="fu_alt_csv_file" name="fu_alt_csv_file" accept=".csv" required class="fu-form-input">
                        <p class="fu-form-description">
                            <?php esc_html_e('Selecione o arquivo CSV com as URLs das imagens e os textos alternativos.', 'ferramentas-upload'); ?>
                        </p>
                    </div>

                    <button type="submit" class="fu-button fu-button-primary">
                        <?php esc_html_e('Processar CSV de Alt Text', 'ferramentas-upload'); ?>
                    </button>
                </form>
            </div>
        </div>
        <?php
    }

    private function render_serp_tab() {
        // Verifica se a função is_plugin_active está disponível
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $yoast_active = (
            is_plugin_active('wordpress-seo/wp-seo.php') ||
            is_plugin_active('wordpress-seo-premium/wp-seo-premium.php') ||
            // Fallback para versões recentes do Yoast (v24+) ou cenários em que a função acima falhe
            defined('WPSEO_VERSION') ||
            class_exists('WPSEO_Meta') ||
            class_exists('Yoast\\WP\\SEO\\Main')
        );
        ?>
        <div class="fu-content-header">
            <h2 class="fu-content-title"><?php esc_html_e('Atualizar Título e Descrição SEO (Yoast)', 'ferramentas-upload'); ?></h2>
            <p class="fu-content-description"><?php esc_html_e('Faça upload de um arquivo CSV para atualizar os Títulos e Meta Descrições gerenciados pelo Yoast SEO.', 'ferramentas-upload'); ?></p>
        </div>

        <?php if (!$yoast_active): ?>
            <div class="fu-form-notice error">
                <p>
                    <?php 
                    printf(
                        esc_html__('Erro: O plugin %s precisa estar ativo para usar esta funcionalidade.', 'ferramentas-upload'),
                        '<strong>Yoast SEO</strong>'
                    );
                    ?>
                </p>
            </div>
        <?php else: ?>
            <div class="fu-form">
                <div class="fu-form-section">
                    <h4><?php esc_html_e('Como funciona', 'ferramentas-upload'); ?></h4>
                    <p class="fu-content-description">
                        <?php esc_html_e('Este plugin foi desenvolvido para sobrescrever os metadados de SERP definidos pelo Yoast SEO.', 'ferramentas-upload'); ?>
                    </p>
                    <p class="fu-content-description">
                        <?php esc_html_e('O CSV deve ter 3 colunas, nesta ordem:', 'ferramentas-upload'); ?>
                        <strong><?php esc_html_e('URL', 'ferramentas-upload'); ?></strong>, 
                        <strong><?php esc_html_e('Novo Título', 'ferramentas-upload'); ?></strong>, 
                        <strong><?php esc_html_e('Nova Descrição', 'ferramentas-upload'); ?></strong>.
                    </p>
                    
                    <div class="fu-form-notice warning">
                        <p><strong><?php esc_html_e('Importante:', 'ferramentas-upload'); ?></strong></p>
                        <ul>
                            <li><?php esc_html_e('A primeira linha do CSV será ignorada (cabeçalho).', 'ferramentas-upload'); ?></li>
                            <li><?php esc_html_e('Faça um backup do seu banco de dados antes de executar atualizações em massa.', 'ferramentas-upload'); ?></li>
                        </ul>
                    </div>

                    <form method="post" enctype="multipart/form-data" class="fu-form">
                        <input type="hidden" name="<?php echo esc_attr(FU_PREFIX . 'action'); ?>" value="update_serp">
                        <?php wp_nonce_field(FU_SERP_NONCE_ACTION, FU_SERP_NONCE_FIELD); ?>
                        
                        <div class="fu-form-group">
                            <label for="fu_serp_csv_file" class="fu-form-label">
                                <?php esc_html_e('Arquivo CSV', 'ferramentas-upload'); ?>
                            </label>
                            <input type="file" id="fu_serp_csv_file" name="fu_serp_csv_file" accept=".csv" required class="fu-form-input">
                            <p class="fu-form-description">
                                <?php esc_html_e('Selecione o arquivo CSV com as URLs, Títulos e Descrições.', 'ferramentas-upload'); ?>
                            </p>
                        </div>

                        <button type="submit" class="fu-button fu-button-primary">
                            <?php esc_html_e('Processar CSV de SERP', 'ferramentas-upload'); ?>
                        </button>
                    </form>

                    <a href="<?php echo esc_url(plugins_url('includes/modelo-serps-yoast.csv', dirname(__FILE__))); ?>" download class="fu-button-download">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                            <path d="M8.5 11.5L12 8H9V4H8V8H5L8.5 11.5Z"/>
                            <path d="M3 13H13V14H3V13Z"/>
                        </svg>
                        <?php esc_html_e('Baixar Modelo da Planilha', 'ferramentas-upload'); ?>
                    </a>
                </div>
            </div>
        <?php endif; ?>
        <?php
    }

    private function render_export_posts_tab() {
        // Enfileira jQuery se necessário
        wp_enqueue_script('jquery');
        ?>
        <div class="fu-content-header">
            <h2 class="fu-content-title"><?php esc_html_e('Exportar Posts com Conteúdo Completo', 'ferramentas-upload'); ?></h2>
            <p class="fu-content-description"><?php esc_html_e('Selecione os campos que deseja exportar e clique no botão abaixo para gerar um arquivo CSV.', 'ferramentas-upload'); ?></p>
        </div>

        <div class="fu-form">
            <div class="fu-form-section">
                <h4><?php esc_html_e('Selecione os Campos para Exportação', 'ferramentas-upload'); ?></h4>
                <p class="fu-content-description">
                    <?php esc_html_e('Marque os campos que deseja incluir no arquivo CSV exportado:', 'ferramentas-upload'); ?>
                </p>
                
                <form method="post" id="fu-export-form">
                    <input type="hidden" name="<?php echo esc_attr(FU_PREFIX . 'action'); ?>" value="export_posts_categories">
                    <?php wp_nonce_field(FU_EXPORT_POSTS_NONCE_ACTION, FU_EXPORT_POSTS_NONCE_FIELD); ?>
                    
                    <div class="fu-export-options" style="margin: 20px 0;">
                        <div class="fu-checkbox-group" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                            <label style="display: flex; align-items: center; cursor: pointer; padding: 10px; border: 1px solid #ddd; border-radius: 4px; transition: background-color 0.2s;">
                                <input type="checkbox" name="export_fields[]" value="meta_title" checked style="margin-right: 10px;">
                                <span><strong><?php esc_html_e('Meta Title', 'ferramentas-upload'); ?></strong></span>
                            </label>
                            
                            <label style="display: flex; align-items: center; cursor: pointer; padding: 10px; border: 1px solid #ddd; border-radius: 4px; transition: background-color 0.2s;">
                                <input type="checkbox" name="export_fields[]" value="meta_description" checked style="margin-right: 10px;">
                                <span><strong><?php esc_html_e('Meta Description', 'ferramentas-upload'); ?></strong></span>
                            </label>
                            
                            <label style="display: flex; align-items: center; cursor: pointer; padding: 10px; border: 1px solid #ddd; border-radius: 4px; transition: background-color 0.2s;">
                                <input type="checkbox" name="export_fields[]" value="post_title" checked style="margin-right: 10px;">
                                <span><strong><?php esc_html_e('Título do Post', 'ferramentas-upload'); ?></strong></span>
                            </label>
                            
                            <label style="display: flex; align-items: center; cursor: pointer; padding: 10px; border: 1px solid #ddd; border-radius: 4px; transition: background-color 0.2s;">
                                <input type="checkbox" name="export_fields[]" value="post_html" checked style="margin-right: 10px;">
                                <span><strong><?php esc_html_e('HTML do Post', 'ferramentas-upload'); ?></strong></span>
                            </label>
                            
                            <label style="display: flex; align-items: center; cursor: pointer; padding: 10px; border: 1px solid #ddd; border-radius: 4px; transition: background-color 0.2s;">
                                <input type="checkbox" name="export_fields[]" value="author" checked style="margin-right: 10px;">
                                <span><strong><?php esc_html_e('Autor', 'ferramentas-upload'); ?></strong></span>
                            </label>
                            
                            <label style="display: flex; align-items: center; cursor: pointer; padding: 10px; border: 1px solid #ddd; border-radius: 4px; transition: background-color 0.2s;">
                                <input type="checkbox" name="export_fields[]" value="publish_date" checked style="margin-right: 10px;">
                                <span><strong><?php esc_html_e('Data de Publicação', 'ferramentas-upload'); ?></strong></span>
                            </label>
                            
                            <label style="display: flex; align-items: center; cursor: pointer; padding: 10px; border: 1px solid #ddd; border-radius: 4px; transition: background-color 0.2s;">
                                <input type="checkbox" name="export_fields[]" value="url" checked style="margin-right: 10px;">
                                <span><strong><?php esc_html_e('URL', 'ferramentas-upload'); ?></strong></span>
                            </label>
                            
                            <label style="display: flex; align-items: center; cursor: pointer; padding: 10px; border: 1px solid #ddd; border-radius: 4px; transition: background-color 0.2s;">
                                <input type="checkbox" name="export_fields[]" value="canonical_url" style="margin-right: 10px;">
                                <span><strong><?php esc_html_e('URL Canônica', 'ferramentas-upload'); ?></strong></span>
                            </label>
                            
                            <label style="display: flex; align-items: center; cursor: pointer; padding: 10px; border: 1px solid #ddd; border-radius: 4px; transition: background-color 0.2s;">
                                <input type="checkbox" name="export_fields[]" value="featured_image" style="margin-right: 10px;">
                                <span><strong><?php esc_html_e('Imagem Destacada', 'ferramentas-upload'); ?></strong></span>
                            </label>
                            
                            <label style="display: flex; align-items: center; cursor: pointer; padding: 10px; border: 1px solid #ddd; border-radius: 4px; transition: background-color 0.2s;">
                                <input type="checkbox" name="export_fields[]" value="internal_links_count" style="margin-right: 10px;">
                                <span><strong><?php esc_html_e('Contagem de Links Internos', 'ferramentas-upload'); ?></strong></span>
                            </label>
                            
                            <label style="display: flex; align-items: center; cursor: pointer; padding: 10px; border: 1px solid #ddd; border-radius: 4px; transition: background-color 0.2s;">
                                <input type="checkbox" name="export_fields[]" value="post_status" style="margin-right: 10px;">
                                <span><strong><?php esc_html_e('Status do Post', 'ferramentas-upload'); ?></strong></span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="fu-form-notice" style="margin: 20px 0;">
                        <p><strong><?php esc_html_e('Nota:', 'ferramentas-upload'); ?></strong></p>
                        <ul>
                            <li><?php esc_html_e('Selecione pelo menos um campo para exportar.', 'ferramentas-upload'); ?></li>
                            <li><?php esc_html_e('Meta Title e Meta Description são obtidos do Yoast SEO, se disponível.', 'ferramentas-upload'); ?></li>
                            <li><?php esc_html_e('O conteúdo HTML inclui todas as tags e formatação original do post.', 'ferramentas-upload'); ?></li>
                        </ul>
                    </div>

                    <button type="submit" class="fu-button fu-button-primary" id="fu-export-button">
                        <?php esc_html_e('Exportar Posts para CSV', 'ferramentas-upload'); ?>
                    </button>
                </form>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Validação: pelo menos um campo deve estar selecionado
            $('#fu-export-form').on('submit', function(e) {
                var checked = $('input[name="export_fields[]"]:checked').length;
                if (checked === 0) {
                    e.preventDefault();
                    alert('<?php echo esc_js(__('Por favor, selecione pelo menos um campo para exportar.', 'ferramentas-upload')); ?>');
                    return false;
                }
            });
            
            // Efeito hover nos labels
            $('.fu-checkbox-group label').hover(
                function() { $(this).css('background-color', '#f5f5f5'); },
                function() { $(this).css('background-color', 'transparent'); }
            );
        });
        </script>
        <?php
    }

    private function render_trash_posts_tab() {
        ?>
        <div class="fu-content-header">
            <h2 class="fu-content-title"><?php esc_html_e('Mover Posts para Lixeira', 'ferramentas-upload'); ?></h2>
            <p class="fu-content-description"><?php esc_html_e('Faça upload de um arquivo CSV contendo as URLs dos posts que você deseja mover para a lixeira.', 'ferramentas-upload'); ?></p>
        </div>

        <div class="fu-form">
            <div class="fu-form-section">
                <h4><?php esc_html_e('Como funciona', 'ferramentas-upload'); ?></h4>
                <p class="fu-content-description">
                    <?php esc_html_e('O CSV deve ter apenas uma coluna com as URLs dos posts.', 'ferramentas-upload'); ?>
                </p>
                
                <div class="fu-form-notice warning">
                    <p><strong><?php esc_html_e('Importante:', 'ferramentas-upload'); ?></strong></p>
                    <ul>
                        <li><?php esc_html_e('A primeira linha (cabeçalho) será ignorada.', 'ferramentas-upload'); ?></li>
                        <li><?php esc_html_e('Os posts serão movidos para a lixeira, não excluídos permanentemente.', 'ferramentas-upload'); ?></li>
                        <li><?php esc_html_e('Você pode restaurar os posts da lixeira posteriormente se necessário.', 'ferramentas-upload'); ?></li>
                        <li><?php esc_html_e('Faça backup do seu banco de dados antes de executar esta operação.', 'ferramentas-upload'); ?></li>
                    </ul>
                </div>

                <form method="post" enctype="multipart/form-data" class="fu-form">
                    <input type="hidden" name="<?php echo esc_attr(FU_PREFIX . 'action'); ?>" value="trash_posts">
                    <?php wp_nonce_field(FU_TRASH_NONCE_ACTION, FU_TRASH_NONCE_FIELD); ?>
                    
                    <div class="fu-form-group">
                        <label for="fu_trash_csv_file" class="fu-form-label">
                            <?php esc_html_e('Arquivo CSV', 'ferramentas-upload'); ?>
                        </label>
                        <input type="file" id="fu_trash_csv_file" name="fu_trash_csv_file" accept=".csv" required class="fu-form-input">
                        <p class="fu-form-description">
                            <?php esc_html_e('Selecione o arquivo CSV com as URLs dos posts que deseja mover para a lixeira.', 'ferramentas-upload'); ?>
                        </p>
                    </div>

                    <button type="submit" class="fu-button fu-button-primary">
                        <?php esc_html_e('Processar CSV e Mover Posts para Lixeira', 'ferramentas-upload'); ?>
                    </button>
                </form>

                <a href="<?php echo esc_url(plugins_url('includes/modelo-exclusao-post.csv', dirname(__FILE__))); ?>" download class="fu-button-download">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                        <path d="M8.5 11.5L12 8H9V4H8V8H5L8.5 11.5Z"/>
                        <path d="M3 13H13V14H3V13Z"/>
                    </svg>
                    <?php esc_html_e('Baixar Modelo da Planilha', 'ferramentas-upload'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    private function render_recategorize_posts_tab() {
        ?>
        <div class="fu-content-header">
            <h2 class="fu-content-title"><?php esc_html_e('Recategorizar Posts', 'ferramentas-upload'); ?></h2>
            <p class="fu-content-description"><?php esc_html_e('Faça upload de um arquivo CSV contendo as URLs dos posts e suas novas categorias.', 'ferramentas-upload'); ?></p>
        </div>

        <div class="fu-form">
            <div class="fu-form-section">
                <h4><?php esc_html_e('Como funciona', 'ferramentas-upload'); ?></h4>
                <p class="fu-content-description">
                    <?php 
                    esc_html_e('O CSV deve ter duas colunas: ', 'ferramentas-upload');
                    echo '<strong>' . esc_html__('URL do Post', 'ferramentas-upload') . '</strong> ';
                    esc_html_e('e', 'ferramentas-upload');
                    echo ' <strong>' . esc_html__('Categorias', 'ferramentas-upload') . '</strong>.';
                    ?>
                </p>
                
                <div class="fu-form-notice">
                    <p><strong><?php esc_html_e('Importante:', 'ferramentas-upload'); ?></strong></p>
                    <ul>
                        <li><?php esc_html_e('A primeira linha (cabeçalho) será ignorada.', 'ferramentas-upload'); ?></li>
                        <li><?php esc_html_e('Caso deseja recategorizar o post em mais de uma categoria, basta apenas separar elas entre , (vírgulas) na mesma célula. Exemplo: (Categoria 1 , Categoria 2).', 'ferramentas-upload'); ?></li>
                        <li><?php esc_html_e('Categorias que não existem serão criadas automaticamente.', 'ferramentas-upload'); ?></li>
                        <li><?php esc_html_e('As categorias existentes do post serão substituídas pelas novas categorias.', 'ferramentas-upload'); ?></li>
                        <li><?php esc_html_e('Faça backup do seu banco de dados antes de executar esta operação.', 'ferramentas-upload'); ?></li>
                    </ul>
                </div>

                <form method="post" enctype="multipart/form-data" class="fu-form">
                    <input type="hidden" name="<?php echo esc_attr(FU_PREFIX . 'action'); ?>" value="recategorize_posts">
                    <?php wp_nonce_field(FU_CATEGORY_NONCE_ACTION, FU_CATEGORY_NONCE_FIELD); ?>
                    
                    <div class="fu-form-group">
                        <label for="fu_category_csv_file" class="fu-form-label">
                            <?php esc_html_e('Arquivo CSV', 'ferramentas-upload'); ?>
                        </label>
                        <input type="file" id="fu_category_csv_file" name="fu_category_csv_file" accept=".csv" required class="fu-form-input">
                        <p class="fu-form-description">
                            <?php esc_html_e('Selecione o arquivo CSV com as URLs dos posts e suas novas categorias.', 'ferramentas-upload'); ?>
                        </p>
                    </div>

                    <button type="submit" class="fu-button fu-button-primary">
                        <?php esc_html_e('Processar CSV e Recategorizar Posts', 'ferramentas-upload'); ?>
                    </button>
                </form>

                <a href="<?php echo esc_url(plugins_url('includes/modelo-para-recat.csv', dirname(__FILE__))); ?>" download class="fu-button-download">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                        <path d="M8.5 11.5L12 8H9V4H8V8H5L8.5 11.5Z"/>
                        <path d="M3 13H13V14H3V13Z"/>
                    </svg>
                    <?php esc_html_e('Baixar Modelo da Planilha', 'ferramentas-upload'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    private function render_faq_tab() {
        // Verifica se as configurações foram salvas
        $settings_saved = isset($_GET['settings_saved']) && $_GET['settings_saved'] == '1';
        
        // Obtém valores salvos
        $api_key = get_option('fu_faq_api_key', '');
        $prompt = get_option('fu_faq_prompt', '');
        $api_url = get_option('fu_faq_api_url', 'https://api.openai.com/v1/chat/completions');
        
        // Enfileira scripts necessários
        wp_enqueue_script('jquery');
        
        // Adiciona ajaxurl se não estiver definido
        ?>
        <script type="text/javascript">
        var ajaxurl = ajaxurl || '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
        </script>
        <?php
        ?>
        <div class="fu-content-header">
            <h2 class="fu-content-title"><?php esc_html_e('FAQ Estruturado com IA', 'ferramentas-upload'); ?></h2>
            <p class="fu-content-description"><?php esc_html_e('Gere automaticamente dados estruturados de FAQ para seus posts usando IA Studio.', 'ferramentas-upload'); ?></p>
        </div>

        <?php if ($settings_saved): ?>
            <div class="fu-notice success">
                <p><?php esc_html_e('Configurações salvas com sucesso!', 'ferramentas-upload'); ?></p>
            </div>
        <?php endif; ?>

        <div class="fu-form">
            <div class="fu-form-section">
                <h4><?php esc_html_e('Configurações da API', 'ferramentas-upload'); ?></h4>
                <p class="fu-content-description">
                    <?php esc_html_e('Configure sua chave de API do IA Studio e o prompt personalizado para geração de FAQ.', 'ferramentas-upload'); ?>
                </p>

                <form method="post" class="fu-form">
                    <input type="hidden" name="<?php echo esc_attr(FU_PREFIX . 'action'); ?>" value="save_faq_settings">
                    <?php wp_nonce_field(FU_FAQ_NONCE_ACTION, FU_FAQ_NONCE_FIELD); ?>
                    
                    <div class="fu-form-group">
                        <label for="fu_faq_api_url" class="fu-form-label">
                            <?php esc_html_e('URL da API', 'ferramentas-upload'); ?>
                        </label>
                        <input 
                            type="url" 
                            id="fu_faq_api_url" 
                            name="fu_faq_api_url" 
                            value="<?php echo esc_attr($api_url); ?>" 
                            placeholder="https://api.openai.com/v1/chat/completions"
                            class="fu-form-input"
                        >
                        <p class="fu-form-description">
                            <?php esc_html_e('URL do endpoint da API. Padrão: OpenAI. Para IA Studio ou outras APIs compatíveis com formato OpenAI, use a URL completa do endpoint. Exemplo: https://api.ia.studio/v1/chat/completions', 'ferramentas-upload'); ?>
                        </p>
                        <div class="fu-form-notice" style="margin-top: 10px;">
                            <p><strong><?php esc_html_e('Nota:', 'ferramentas-upload'); ?></strong></p>
                            <ul>
                                <li><?php esc_html_e('Para IA Studio: use a URL completa do endpoint fornecido pela documentação.', 'ferramentas-upload'); ?></li>
                                <li><?php esc_html_e('Para OpenAI: deixe o padrão ou use https://api.openai.com/v1/chat/completions', 'ferramentas-upload'); ?></li>
                                <li><?php esc_html_e('Para Google AI Studio: deixe o padrão - será detectado automaticamente pela chave.', 'ferramentas-upload'); ?></li>
                            </ul>
                        </div>
                    </div>

                    <div class="fu-form-group">
                        <label for="fu_faq_api_key" class="fu-form-label">
                            <?php esc_html_e('Chave de API', 'ferramentas-upload'); ?>
                        </label>
                        <input 
                            type="text" 
                            id="fu_faq_api_key" 
                            name="fu_faq_api_key" 
                            value="<?php echo esc_attr($api_key); ?>" 
                            placeholder="<?php esc_attr_e('Cole sua chave de API aqui', 'ferramentas-upload'); ?>"
                            class="fu-form-input"
                        >
                        <p class="fu-form-description">
                            <?php esc_html_e('Insira sua chave de API. Cada usuário deve adicionar sua própria chave.', 'ferramentas-upload'); ?>
                        </p>
                    </div>

                    <div class="fu-form-group">
                        <label for="fu_faq_prompt" class="fu-form-label">
                            <?php esc_html_e('Prompt Personalizado', 'ferramentas-upload'); ?>
                        </label>
                        <textarea 
                            id="fu_faq_prompt" 
                            name="fu_faq_prompt" 
                            rows="6" 
                            class="fu-form-input"
                            placeholder="<?php esc_attr_e('Deixe em branco para usar o prompt padrão. O prompt será enviado junto com o título e conteúdo do post.', 'ferramentas-upload'); ?>"
                        ><?php echo esc_textarea($prompt); ?></textarea>
                        <p class="fu-form-description">
                            <?php esc_html_e('Configure um prompt personalizado para orientar a geração de FAQ. Se deixar em branco, será usado um prompt padrão otimizado.', 'ferramentas-upload'); ?>
                        </p>
                    </div>

                    <button type="submit" class="fu-button fu-button-primary">
                        <?php esc_html_e('Salvar Configurações', 'ferramentas-upload'); ?>
                    </button>
                </form>
            </div>

            <div class="fu-form-section" style="margin-top: 30px;">
                <h4><?php esc_html_e('Análise em Massa de Posts (Nova Funcionalidade)', 'ferramentas-upload'); ?></h4>
                <p class="fu-content-description">
                    <?php esc_html_e('Faça upload de um CSV com URLs de posts para análise automática. O sistema irá identificar posts elegíveis e gerar um CSV com perguntas e respostas para revisão.', 'ferramentas-upload'); ?>
                </p>

                <div class="fu-form-notice" style="margin: 20px 0;">
                    <p><strong><?php esc_html_e('Como funciona:', 'ferramentas-upload'); ?></strong></p>
                    <ol>
                        <li><?php esc_html_e('Faça upload de um CSV com uma coluna contendo URLs dos posts (primeira linha será ignorada como cabeçalho).', 'ferramentas-upload'); ?></li>
                        <li><?php esc_html_e('O sistema analisará cada post verificando:', 'ferramentas-upload'); ?>
                            <ul>
                                <li><?php esc_html_e('Se possui subtítulos em formato de perguntas (cada subtítulo vira pergunta e o parágrafo seguinte vira resposta).', 'ferramentas-upload'); ?></li>
                                <li><?php esc_html_e('Se possui estrutura de FAQ na página (fora do HTML principal).', 'ferramentas-upload'); ?></li>
                                <li><?php esc_html_e('Se nenhuma condição for atendida, o post será ignorado.', 'ferramentas-upload'); ?></li>
                            </ul>
                        </li>
                        <li><?php esc_html_e('Um CSV será gerado com todos os posts elegíveis e suas perguntas/respostas.', 'ferramentas-upload'); ?></li>
                        <li><?php esc_html_e('Revise o CSV, faça alterações se necessário e faça upload novamente para aplicar nos posts.', 'ferramentas-upload'); ?></li>
                    </ol>
                </div>

                <form id="fu_process_urls_form" enctype="multipart/form-data" style="margin: 20px 0;">
                    <?php wp_nonce_field(FU_FAQ_AJAX_NONCE_ACTION, FU_FAQ_AJAX_NONCE_FIELD); ?>
                    
                    <div class="fu-form-group">
                        <label for="fu_urls_csv_file" class="fu-form-label">
                            <?php esc_html_e('CSV com URLs dos Posts', 'ferramentas-upload'); ?>
                        </label>
                        <input type="file" id="fu_urls_csv_file" name="csv_file" accept=".csv" required class="fu-form-input">
                        <p class="fu-form-description">
                            <?php esc_html_e('CSV com uma coluna contendo as URLs completas dos posts do WordPress.', 'ferramentas-upload'); ?>
                        </p>
                    </div>

                    <button type="submit" class="fu-button fu-button-primary" id="fu_process_urls_btn">
                        <?php esc_html_e('Processar URLs e Gerar CSV', 'ferramentas-upload'); ?>
                    </button>
                </form>

                <a href="<?php echo esc_url(plugins_url('includes/modelo-urls-faq.csv', dirname(__FILE__))); ?>" download class="fu-button-download" style="margin-top: 10px; display: inline-block;">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" style="vertical-align: middle; margin-right: 5px;">
                        <path d="M8.5 11.5L12 8H9V4H8V8H5L8.5 11.5Z"/>
                        <path d="M3 13H13V14H3V13Z"/>
                    </svg>
                    <?php esc_html_e('Baixar Modelo da Planilha (URLs)', 'ferramentas-upload'); ?>
                </a>

                <div id="fu_urls_loading" style="display: none; margin-top: 20px;">
                    <p><?php esc_html_e('Processando URLs... Isso pode levar alguns minutos. Aguarde.', 'ferramentas-upload'); ?></p>
                </div>

                <div id="fu_urls_result" style="display: none; margin-top: 20px;"></div>

                <div class="fu-form-section" style="margin-top: 40px; border-top: 1px solid #ddd; padding-top: 30px;">
                    <h4><?php esc_html_e('Aplicar CSV Revisado', 'ferramentas-upload'); ?></h4>
                    <p class="fu-content-description">
                        <?php esc_html_e('Após revisar e editar o CSV gerado, faça upload novamente para aplicar os FAQs nos posts.', 'ferramentas-upload'); ?>
                    </p>

                    <form id="fu_apply_reviewed_form" enctype="multipart/form-data" style="margin: 20px 0;">
                        <?php wp_nonce_field(FU_FAQ_AJAX_NONCE_ACTION, FU_FAQ_AJAX_NONCE_FIELD); ?>
                        
                        <div class="fu-form-group">
                            <label for="fu_reviewed_csv_file" class="fu-form-label">
                                <?php esc_html_e('CSV Revisado', 'ferramentas-upload'); ?>
                            </label>
                            <input type="file" id="fu_reviewed_csv_file" name="csv_file" accept=".csv" required class="fu-form-input">
                            <p class="fu-form-description">
                                <?php esc_html_e('CSV revisado com as colunas: URL, Post ID, Post Title, Question, Answer.', 'ferramentas-upload'); ?>
                            </p>
                        </div>

                        <button type="submit" class="fu-button fu-button-primary" id="fu_apply_reviewed_btn">
                            <?php esc_html_e('Aplicar FAQ nos Posts', 'ferramentas-upload'); ?>
                        </button>
                    </form>

                    <div id="fu_reviewed_loading" style="display: none; margin-top: 20px;">
                        <p><?php esc_html_e('Aplicando FAQs... Aguarde.', 'ferramentas-upload'); ?></p>
                    </div>

                    <div id="fu_reviewed_result" style="display: none; margin-top: 20px;"></div>
                </div>
            </div>

            <div class="fu-form-section" style="margin-top: 30px; border-top: 1px solid #ddd; padding-top: 30px;">
                <h4><?php esc_html_e('Gerar FAQ para Post Individual (Funcionalidade Antiga)', 'ferramentas-upload'); ?></h4>
                <p class="fu-content-description">
                    <?php esc_html_e('Selecione um post para gerar FAQ automaticamente. Você poderá revisar antes de aplicar.', 'ferramentas-upload'); ?>
                </p>

                <div class="fu-form-group">
                    <label for="fu_faq_post_select" class="fu-form-label">
                        <?php esc_html_e('Selecionar Post', 'ferramentas-upload'); ?>
                    </label>
                    <select id="fu_faq_post_select" class="fu-form-input" style="width: 100%;">
                        <option value=""><?php esc_html_e('-- Selecione um post --', 'ferramentas-upload'); ?></option>
                        <?php
                        $posts = get_posts(array(
                            'post_type' => 'post',
                            'post_status' => 'publish',
                            'numberposts' => 100,
                            'orderby' => 'date',
                            'order' => 'DESC'
                        ));
                        foreach ($posts as $post) {
                            echo '<option value="' . esc_attr($post->ID) . '">' . esc_html($post->post_title) . ' (ID: ' . $post->ID . ')</option>';
                        }
                        ?>
                    </select>
                    <p class="fu-form-description">
                        <?php esc_html_e('Selecione o post para o qual deseja gerar FAQ.', 'ferramentas-upload'); ?>
                    </p>
                </div>

                <button type="button" id="fu_generate_faq_btn" class="fu-button fu-button-primary" disabled>
                    <?php esc_html_e('Gerar FAQ', 'ferramentas-upload'); ?>
                </button>

                <div id="fu_faq_loading" style="display: none; margin-top: 20px;">
                    <p><?php esc_html_e('Gerando FAQ... Aguarde.', 'ferramentas-upload'); ?></p>
                </div>

                <div id="fu_faq_review_panel" style="display: none; margin-top: 30px;">
                    <h4><?php esc_html_e('Revisão do FAQ Gerado', 'ferramentas-upload'); ?></h4>
                    <p class="fu-content-description">
                        <?php esc_html_e('Revise as perguntas e respostas geradas. Você pode editar antes de aplicar.', 'ferramentas-upload'); ?>
                    </p>
                    
                    <div id="fu_faq_items_container" style="margin: 20px 0;">
                        <!-- FAQ items serão inseridos aqui via JavaScript -->
                    </div>

                    <div class="fu-form-notice">
                        <p><strong><?php esc_html_e('Importante:', 'ferramentas-upload'); ?></strong></p>
                        <ul>
                            <li><?php esc_html_e('O FAQ será adicionado como dados estruturados (Schema.org) no post.', 'ferramentas-upload'); ?></li>
                            <li><?php esc_html_e('Os dados estruturados aparecerão automaticamente no código HTML do post.', 'ferramentas-upload'); ?></li>
                            <li><?php esc_html_e('Você pode editar as perguntas e respostas antes de aplicar.', 'ferramentas-upload'); ?></li>
                        </ul>
                    </div>

                    <button type="button" id="fu_apply_faq_btn" class="fu-button fu-button-primary">
                        <?php esc_html_e('Aplicar FAQ ao Post', 'ferramentas-upload'); ?>
                    </button>
                    <button type="button" id="fu_cancel_faq_btn" class="fu-button" style="margin-left: 10px;">
                        <?php esc_html_e('Cancelar', 'ferramentas-upload'); ?>
                    </button>
                </div>

                <div id="fu_faq_success_message" style="display: none; margin-top: 20px;" class="fu-notice success">
                    <p></p>
                </div>

                <div id="fu_faq_error_message" style="display: none; margin-top: 20px;" class="fu-notice error">
                    <p></p>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var currentPostId = null;
            var currentFaqData = null;

            // Processa CSV de URLs
            $('#fu_process_urls_form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = new FormData();
                var fileInput = $('#fu_urls_csv_file')[0];
                
                if (!fileInput.files.length) {
                    alert('<?php echo esc_js(__('Por favor, selecione um arquivo CSV.', 'ferramentas-upload')); ?>');
                    return;
                }
                
                formData.append('action', 'fu_process_urls_csv');
                formData.append('csv_file', fileInput.files[0]);
                formData.append('nonce', '<?php echo wp_create_nonce(FU_FAQ_AJAX_NONCE_ACTION); ?>');
                
                $('#fu_urls_loading').show();
                $('#fu_urls_result').hide();
                $('#fu_process_urls_btn').prop('disabled', true);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        $('#fu_urls_loading').hide();
                        $('#fu_process_urls_btn').prop('disabled', false);
                        
                        if (response.success) {
                            var message = response.data.message;
                            var filesHtml = '';
                            
                            // Baixa CSV de sucessos se existir
                            if (response.data.files && response.data.files.success) {
                                var successFile = response.data.files.success;
                                var blob = base64ToBlob(successFile.file_content, 'text/csv');
                                var url = window.URL.createObjectURL(blob);
                                var a = document.createElement('a');
                                a.href = url;
                                a.download = successFile.filename;
                                document.body.appendChild(a);
                                a.click();
                                window.URL.revokeObjectURL(url);
                                document.body.removeChild(a);
                                
                                filesHtml += '<p><strong><?php echo esc_js(__('CSV de Sucessos:', 'ferramentas-upload')); ?></strong> ' + successFile.filename + ' (<?php echo esc_js(__('baixado automaticamente', 'ferramentas-upload')); ?>)</p>';
                            }
                            
                            // Baixa CSV de erros se existir
                            if (response.data.files && response.data.files.errors) {
                                var errorFile = response.data.files.errors;
                                var blob = base64ToBlob(errorFile.file_content, 'text/csv');
                                var url = window.URL.createObjectURL(blob);
                                var a = document.createElement('a');
                                a.href = url;
                                a.download = errorFile.filename;
                                document.body.appendChild(a);
                                a.click();
                                window.URL.revokeObjectURL(url);
                                document.body.removeChild(a);
                                
                                filesHtml += '<p><strong><?php echo esc_js(__('CSV de Erros:', 'ferramentas-upload')); ?></strong> ' + errorFile.filename + ' (<?php echo esc_js(__('baixado automaticamente', 'ferramentas-upload')); ?>)</p>';
                                filesHtml += '<p class="fu-form-description"><?php echo esc_js(__('Revise o CSV de erros para entender por que algumas URLs não geraram FAQ. Use essas informações para ajustar o prompt se necessário.', 'ferramentas-upload')); ?></p>';
                            }
                            
                            $('#fu_urls_result').html(
                                '<div class="fu-notice success">' +
                                '<p>' + message + '</p>' +
                                filesHtml +
                                '<p><?php echo esc_js(__('Revise os CSVs e faça upload do CSV de sucessos na seção "Aplicar CSV Revisado" para aplicar os FAQs nos posts.', 'ferramentas-upload')); ?></p>' +
                                '</div>'
                            ).show();
                        } else {
                            $('#fu_urls_result').html(
                                '<div class="fu-notice error">' +
                                '<p>' + (response.data.message || '<?php echo esc_js(__('Erro ao processar CSV.', 'ferramentas-upload')); ?>') + '</p>' +
                                '</div>'
                            ).show();
                        }
                    },
                    error: function() {
                        $('#fu_urls_loading').hide();
                        $('#fu_process_urls_btn').prop('disabled', false);
                        $('#fu_urls_result').html(
                            '<div class="fu-notice error">' +
                            '<p><?php echo esc_js(__('Erro ao conectar com o servidor.', 'ferramentas-upload')); ?></p>' +
                            '</div>'
                        ).show();
                    }
                });
            });

            // Aplica CSV revisado
            $('#fu_apply_reviewed_form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = new FormData();
                var fileInput = $('#fu_reviewed_csv_file')[0];
                
                if (!fileInput.files.length) {
                    alert('<?php echo esc_js(__('Por favor, selecione um arquivo CSV.', 'ferramentas-upload')); ?>');
                    return;
                }
                
                formData.append('action', 'fu_apply_reviewed_csv');
                formData.append('csv_file', fileInput.files[0]);
                formData.append('nonce', '<?php echo wp_create_nonce(FU_FAQ_AJAX_NONCE_ACTION); ?>');
                
                $('#fu_reviewed_loading').show();
                $('#fu_reviewed_result').hide();
                $('#fu_apply_reviewed_btn').prop('disabled', true);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        $('#fu_reviewed_loading').hide();
                        $('#fu_apply_reviewed_btn').prop('disabled', false);
                        
                        if (response.success) {
                            var message = response.data.message;
                            if (response.data.errors && response.data.errors.length > 0) {
                                message += '<ul>';
                                response.data.errors.forEach(function(error) {
                                    message += '<li>' + escapeHtml(error) + '</li>';
                                });
                                message += '</ul>';
                            }
                            
                            $('#fu_reviewed_result').html(
                                '<div class="fu-notice success">' +
                                '<p>' + message + '</p>' +
                                '</div>'
                            ).show();
                        } else {
                            $('#fu_reviewed_result').html(
                                '<div class="fu-notice error">' +
                                '<p>' + (response.data.message || '<?php echo esc_js(__('Erro ao aplicar CSV.', 'ferramentas-upload')); ?>') + '</p>' +
                                '</div>'
                            ).show();
                        }
                    },
                    error: function() {
                        $('#fu_reviewed_loading').hide();
                        $('#fu_apply_reviewed_btn').prop('disabled', false);
                        $('#fu_reviewed_result').html(
                            '<div class="fu-notice error">' +
                            '<p><?php echo esc_js(__('Erro ao conectar com o servidor.', 'ferramentas-upload')); ?></p>' +
                            '</div>'
                        ).show();
                    }
                });
            });

            // Função auxiliar para converter base64 em blob
            function base64ToBlob(base64, mimeType) {
                var byteCharacters = atob(base64);
                var byteNumbers = new Array(byteCharacters.length);
                for (var i = 0; i < byteCharacters.length; i++) {
                    byteNumbers[i] = byteCharacters.charCodeAt(i);
                }
                var byteArray = new Uint8Array(byteNumbers);
                return new Blob([byteArray], {type: mimeType});
            }

            // Habilita botão quando post é selecionado
            $('#fu_faq_post_select').on('change', function() {
                var postId = $(this).val();
                $('#fu_generate_faq_btn').prop('disabled', !postId);
                currentPostId = postId;
                $('#fu_faq_review_panel').hide();
                $('#fu_faq_success_message').hide();
                $('#fu_faq_error_message').hide();
            });

            // Gera FAQ
            $('#fu_generate_faq_btn').on('click', function() {
                if (!currentPostId) {
                    alert('<?php echo esc_js(__('Por favor, selecione um post.', 'ferramentas-upload')); ?>');
                    return;
                }

                $('#fu_faq_loading').show();
                $('#fu_faq_review_panel').hide();
                $('#fu_faq_error_message').hide();
                $('#fu_faq_success_message').hide();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fu_generate_faq',
                        post_id: currentPostId,
                        nonce: '<?php echo wp_create_nonce(FU_FAQ_AJAX_NONCE_ACTION); ?>'
                    },
                    success: function(response) {
                        $('#fu_faq_loading').hide();
                        
                        if (response.success) {
                            currentFaqData = response.data.faq_data;
                            renderFaqReview(response.data.faq_data);
                            $('#fu_faq_review_panel').show();
                        } else {
                            $('#fu_faq_error_message p').text(response.data.message || '<?php echo esc_js(__('Erro ao gerar FAQ.', 'ferramentas-upload')); ?>');
                            $('#fu_faq_error_message').show();
                        }
                    },
                    error: function() {
                        $('#fu_faq_loading').hide();
                        $('#fu_faq_error_message p').text('<?php echo esc_js(__('Erro ao conectar com o servidor.', 'ferramentas-upload')); ?>');
                        $('#fu_faq_error_message').show();
                    }
                });
            });

            // Renderiza painel de revisão
            function renderFaqReview(faqData) {
                var container = $('#fu_faq_items_container');
                container.empty();

                if (!faqData || faqData.length === 0) {
                    container.html('<p><?php echo esc_js(__('Nenhum FAQ gerado.', 'ferramentas-upload')); ?></p>');
                    return;
                }

                faqData.forEach(function(item, index) {
                    var itemHtml = '<div class="fu-faq-item" style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">';
                    itemHtml += '<div class="fu-form-group">';
                    itemHtml += '<label><strong><?php echo esc_js(__('Pergunta', 'ferramentas-upload')); ?> ' + (index + 1) + ':</strong></label>';
                    itemHtml += '<input type="text" class="fu-faq-question fu-form-input" value="' + escapeHtml(item.question || '') + '" data-index="' + index + '" style="width: 100%; margin-top: 5px;">';
                    itemHtml += '</div>';
                    itemHtml += '<div class="fu-form-group" style="margin-top: 10px;">';
                    itemHtml += '<label><strong><?php echo esc_js(__('Resposta', 'ferramentas-upload')); ?>:</strong></label>';
                    itemHtml += '<textarea class="fu-faq-answer fu-form-input" data-index="' + index + '" rows="4" style="width: 100%; margin-top: 5px;">' + escapeHtml(item.answer || '') + '</textarea>';
                    itemHtml += '</div>';
                    itemHtml += '<button type="button" class="fu-remove-faq-item fu-button" style="margin-top: 10px;" data-index="' + index + '"><?php echo esc_js(__('Remover', 'ferramentas-upload')); ?></button>';
                    itemHtml += '</div>';
                    container.append(itemHtml);
                });

                // Adiciona botão para adicionar novo item
                container.append('<button type="button" id="fu_add_faq_item" class="fu-button" style="margin-top: 10px;"><?php echo esc_js(__('+ Adicionar Pergunta/Resposta', 'ferramentas-upload')); ?></button>');

                // Event listeners
                $('.fu-faq-question, .fu-faq-answer').on('input', updateFaqData);
                $('.fu-remove-faq-item').on('click', function() {
                    var index = $(this).data('index');
                    removeFaqItem(index);
                });
                $('#fu_add_faq_item').on('click', addNewFaqItem);
            }

            function updateFaqData() {
                var index = $(this).data('index');
                if ($(this).hasClass('fu-faq-question')) {
                    currentFaqData[index].question = $(this).val();
                } else if ($(this).hasClass('fu-faq-answer')) {
                    currentFaqData[index].answer = $(this).val();
                }
            }

            function removeFaqItem(index) {
                currentFaqData.splice(index, 1);
                renderFaqReview(currentFaqData);
            }

            function addNewFaqItem() {
                currentFaqData.push({
                    question: '',
                    answer: ''
                });
                renderFaqReview(currentFaqData);
            }

            function escapeHtml(text) {
                var map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return text.replace(/[&<>"']/g, function(m) { return map[m]; });
            }

            // Aplica FAQ
            $('#fu_apply_faq_btn').on('click', function() {
                if (!currentPostId || !currentFaqData) {
                    alert('<?php echo esc_js(__('Nenhum FAQ para aplicar.', 'ferramentas-upload')); ?>');
                    return;
                }

                // Atualiza dados dos campos editados
                $('.fu-faq-question, .fu-faq-answer').each(function() {
                    var index = $(this).data('index');
                    if ($(this).hasClass('fu-faq-question')) {
                        currentFaqData[index].question = $(this).val();
                    } else if ($(this).hasClass('fu-faq-answer')) {
                        currentFaqData[index].answer = $(this).val();
                    }
                });

                // Remove itens vazios
                currentFaqData = currentFaqData.filter(function(item) {
                    return item.question && item.answer;
                });

                if (currentFaqData.length === 0) {
                    alert('<?php echo esc_js(__('Adicione pelo menos uma pergunta e resposta válida.', 'ferramentas-upload')); ?>');
                    return;
                }

                $('#fu_apply_faq_btn').prop('disabled', true).text('<?php echo esc_js(__('Aplicando...', 'ferramentas-upload')); ?>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fu_apply_faq',
                        post_id: currentPostId,
                        faq_data: JSON.stringify(currentFaqData),
                        nonce: '<?php echo wp_create_nonce(FU_FAQ_AJAX_NONCE_ACTION); ?>'
                    },
                    success: function(response) {
                        $('#fu_apply_faq_btn').prop('disabled', false).text('<?php echo esc_js(__('Aplicar FAQ ao Post', 'ferramentas-upload')); ?>');
                        
                        if (response.success) {
                            $('#fu_faq_success_message p').text(response.data.message || '<?php echo esc_js(__('FAQ aplicado com sucesso!', 'ferramentas-upload')); ?>');
                            $('#fu_faq_success_message').show();
                            $('#fu_faq_review_panel').hide();
                        } else {
                            $('#fu_faq_error_message p').text(response.data.message || '<?php echo esc_js(__('Erro ao aplicar FAQ.', 'ferramentas-upload')); ?>');
                            $('#fu_faq_error_message').show();
                        }
                    },
                    error: function() {
                        $('#fu_apply_faq_btn').prop('disabled', false).text('<?php echo esc_js(__('Aplicar FAQ ao Post', 'ferramentas-upload')); ?>');
                        $('#fu_faq_error_message p').text('<?php echo esc_js(__('Erro ao conectar com o servidor.', 'ferramentas-upload')); ?>');
                        $('#fu_faq_error_message').show();
                    }
                });
            });

            // Cancela revisão
            $('#fu_cancel_faq_btn').on('click', function() {
                $('#fu_faq_review_panel').hide();
                currentFaqData = null;
            });
        });
        </script>
        <?php
    }
}