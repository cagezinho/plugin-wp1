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
        ?>
        <div class="fu-content-header">
            <h2 class="fu-content-title"><?php esc_html_e('Exportar Posts com Conteúdo Completo', 'ferramentas-upload'); ?></h2>
            <p class="fu-content-description"><?php esc_html_e('Clique no botão abaixo para exportar um arquivo CSV contendo todos os posts publicados com informações completas.', 'ferramentas-upload'); ?></p>
        </div>

        <div class="fu-form">
            <div class="fu-form-section">
                <h4><?php esc_html_e('Informações Exportadas', 'ferramentas-upload'); ?></h4>
                <p class="fu-content-description">
                    <?php esc_html_e('As colunas no CSV serão:', 'ferramentas-upload'); ?>
                </p>
                <ul>
                    <li><strong><?php esc_html_e('ID do Post', 'ferramentas-upload'); ?></strong></li>
                    <li><strong><?php esc_html_e('Título do Post', 'ferramentas-upload'); ?></strong></li>
                    <li><strong><?php esc_html_e('URL do Post', 'ferramentas-upload'); ?></strong></li>
                    <li><strong><?php esc_html_e('Categorias', 'ferramentas-upload'); ?></strong></li>
                    <li><strong><?php esc_html_e('Conteúdo HTML', 'ferramentas-upload'); ?></strong></li>
                    <li><strong><?php esc_html_e('Resumo/Excerpt', 'ferramentas-upload'); ?></strong></li>
                </ul>
                
                <div class="fu-form-notice">
                    <p><strong><?php esc_html_e('Nota:', 'ferramentas-upload'); ?></strong></p>
                    <ul>
                        <li><?php esc_html_e('Se um post tiver múltiplas categorias, elas serão listadas na mesma célula, separadas por vírgula.', 'ferramentas-upload'); ?></li>
                        <li><?php esc_html_e('O conteúdo HTML inclui todas as tags e formatação original do post.', 'ferramentas-upload'); ?></li>
                        <li><?php esc_html_e('Se um post não tiver excerpt definido, será gerado automaticamente a partir do conteúdo.', 'ferramentas-upload'); ?></li>
                    </ul>
                </div>

                <form method="post">
                    <input type="hidden" name="<?php echo esc_attr(FU_PREFIX . 'action'); ?>" value="export_posts_categories">
                    <?php wp_nonce_field(FU_EXPORT_POSTS_NONCE_ACTION, FU_EXPORT_POSTS_NONCE_FIELD); ?>
                    
                    <button type="submit" class="fu-button fu-button-primary">
                        <?php esc_html_e('Exportar Posts para CSV', 'ferramentas-upload'); ?>
                    </button>
                </form>
            </div>
        </div>
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
}