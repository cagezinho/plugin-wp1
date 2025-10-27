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
        echo '</div>'; // Fecha .wrap
    }

    private function render_page_header() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
    }

    private function render_tabs() {
        echo '<h2 class="nav-tab-wrapper">';
        $this->render_tab('alt_text', __('Atualizar Texto Alt', 'ferramentas-upload'));
        $this->render_tab('serp', __('Atualizar SERP Yoast', 'ferramentas-upload'));
        $this->render_tab('export_posts', __('Exportar Posts e Categorias', 'ferramentas-upload'));
        $this->render_tab('trash_posts', __('Mover Posts para Lixeira', 'ferramentas-upload'));
        $this->render_tab('recategorize_posts', __('Recategorizar Posts', 'ferramentas-upload'));
        echo '</h2>';
    }

    private function render_tab($tab_id, $tab_title) {
        $class = $this->active_tab === $tab_id ? ' nav-tab-active' : '';
        printf(
            '<a href="?page=%s&tab=%s" class="nav-tab%s">%s</a>',
            esc_attr(FU_PAGE_SLUG),
            esc_attr($tab_id),
            esc_attr($class),
            esc_html($tab_title)
        );
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
        <div id="alt-text-updater" class="tab-content">
            <h3><?php esc_html_e('Atualizar Texto Alternativo (Alt Text) de Imagens', 'ferramentas-upload'); ?></h3>
            <p><?php esc_html_e('Faça o upload de um arquivo CSV para atualizar o texto alternativo das imagens em massa.', 'ferramentas-upload'); ?></p>
            <p>
                <?php 
                esc_html_e('O CSV deve ter duas colunas: ', 'ferramentas-upload');
                echo '<strong>' . esc_html__('Image URL', 'ferramentas-upload') . '</strong> ';
                esc_html_e('e', 'ferramentas-upload');
                echo ' <strong>' . esc_html__('Alt Text', 'ferramentas-upload') . '</strong>. ';
                esc_html_e('A primeira linha (cabeçalho) será ignorada.', 'ferramentas-upload');
                ?>
            </p>
            <p><strong><?php esc_html_e('Importante:', 'ferramentas-upload'); ?></strong> <?php esc_html_e('Use a URL completa da imagem como ela aparece na Biblioteca de Mídia.', 'ferramentas-upload'); ?></p>
            <p><?php esc_html_e('Este processo irá atualizar o texto alt da imagem e também em todos os posts/páginas onde a imagem é usada.', 'ferramentas-upload'); ?></p>

            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="<?php echo esc_attr(FU_PREFIX . 'action'); ?>" value="update_alt_text">
                <?php wp_nonce_field(FU_ALT_NONCE_ACTION, FU_ALT_NONCE_FIELD); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <label for="fu_alt_csv_file"><?php esc_html_e('Arquivo CSV (Alt Text)', 'ferramentas-upload'); ?></label>
                        </th>
                        <td>
                            <input type="file" id="fu_alt_csv_file" name="fu_alt_csv_file" accept=".csv" required>
                            <p class="description"><?php esc_html_e('Selecione o arquivo CSV com as URLs das imagens e os textos alternativos.', 'ferramentas-upload'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Processar CSV de Alt Text', 'ferramentas-upload'), 'primary', 'fu_submit_alt'); ?>
            </form>
        </div>
        <?php
    }

    private function render_serp_tab() {
        ?>
        <div id="serp-updater" class="tab-content">
            <h3><?php esc_html_e('Atualizar Título e Descrição SEO (Yoast)', 'ferramentas-upload'); ?></h3>
            <?php
            // Verifica se a função is_plugin_active está disponível
            if (!function_exists('is_plugin_active')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            
            $yoast_active = is_plugin_active('wordpress-seo/wp-seo.php') || is_plugin_active('wordpress-seo-premium/wp-seo-premium.php');
            
            if (!$yoast_active) {
                echo '<div class="notice notice-error"><p>' .
                     sprintf(
                         esc_html__('Erro: O plugin %s precisa estar ativo para usar esta funcionalidade.', 'ferramentas-upload'),
                         '<strong>Yoast SEO</strong>'
                     ) .
                     '</p></div>';
                return;
            }
            ?>
            <p><?php esc_html_e('Faça upload de um arquivo CSV para atualizar os Títulos e Meta Descrições gerenciados pelo Yoast SEO.', 'ferramentas-upload'); ?></p>
            <p><?php esc_html_e('Este plugin foi desenvolvido para sobrescrever os metadados de SERP definidos pelo Yoast SEO.', 'ferramentas-upload'); ?></p>
            <p><?php esc_html_e('O CSV deve ter 3 colunas, nesta ordem:', 'ferramentas-upload'); ?> <strong><?php esc_html_e('URL', 'ferramentas-upload'); ?></strong>, <strong><?php esc_html_e('Novo Título', 'ferramentas-upload'); ?></strong>, <strong><?php esc_html_e('Nova Descrição', 'ferramentas-upload'); ?></strong>.</p>
            <p><strong><?php esc_html_e('Importante:', 'ferramentas-upload'); ?></strong> <?php esc_html_e('A primeira linha do CSV será ignorada (cabeçalho).', 'ferramentas-upload'); ?></p>
            <p><strong><?php esc_html_e('Atenção:', 'ferramentas-upload'); ?></strong> <?php esc_html_e('Faça um backup do seu banco de dados antes de executar atualizações em massa.', 'ferramentas-upload'); ?></p>

            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="<?php echo esc_attr(FU_PREFIX . 'action'); ?>" value="update_serp">
                <?php wp_nonce_field(FU_SERP_NONCE_ACTION, FU_SERP_NONCE_FIELD); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <label for="fu_serp_csv_file"><?php esc_html_e('Arquivo CSV (SERP)', 'ferramentas-upload'); ?></label>
                        </th>
                        <td>
                            <input type="file" id="fu_serp_csv_file" name="fu_serp_csv_file" accept=".csv" required>
                            <p class="description"><?php esc_html_e('Selecione o arquivo CSV com as URLs, Títulos e Descrições.', 'ferramentas-upload'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Processar CSV de SERP', 'ferramentas-upload'), 'primary', 'fu_submit_serp'); ?>
            </form>
            <a href="<?php echo esc_url(plugins_url('includes/modelo-serps-yoast.csv', dirname(__FILE__))); ?>" download>
                <button>Modelo da planilha</button>
            </a>
        </div>
        <?php
    }

    private function render_export_posts_tab() {
        ?>
        <div id="export-posts-categories" class="tab-content">
            <h3><?php esc_html_e('Exportar Posts com Conteúdo Completo para CSV', 'ferramentas-upload'); ?></h3>
            <p><?php esc_html_e('Clique no botão abaixo para exportar um arquivo CSV contendo todos os posts publicados com informações completas.', 'ferramentas-upload'); ?></p>
            <p>
                <?php 
                esc_html_e('As colunas no CSV serão:', 'ferramentas-upload');
                echo ' <strong>' . esc_html__('ID do Post', 'ferramentas-upload') . '</strong>, ';
                echo '<strong>' . esc_html__('Título do Post', 'ferramentas-upload') . '</strong>, ';
                echo '<strong>' . esc_html__('URL do Post', 'ferramentas-upload') . '</strong>, ';
                echo '<strong>' . esc_html__('Categorias', 'ferramentas-upload') . '</strong>, ';
                echo '<strong>' . esc_html__('Conteúdo HTML', 'ferramentas-upload') . '</strong>, ';
                echo '<strong>' . esc_html__('Resumo/Excerpt', 'ferramentas-upload') . '</strong>.';
                ?>
            </p>
            <p><?php esc_html_e('Se um post tiver múltiplas categorias, elas serão listadas na mesma célula, separadas por vírgula.', 'ferramentas-upload'); ?></p>
            <p><strong><?php esc_html_e('Nota:', 'ferramentas-upload'); ?></strong> <?php esc_html_e('O conteúdo HTML inclui todas as tags e formatação original do post. Se um post não tiver excerpt definido, será gerado automaticamente a partir do conteúdo.', 'ferramentas-upload'); ?></p>

            <form method="post">
                <input type="hidden" name="<?php echo esc_attr(FU_PREFIX . 'action'); ?>" value="export_posts_categories">
                <?php wp_nonce_field(FU_EXPORT_POSTS_NONCE_ACTION, FU_EXPORT_POSTS_NONCE_FIELD); ?>
                <?php submit_button(__('Exportar Posts para CSV', 'ferramentas-upload'), 'primary', 'fu_submit_export_posts'); ?>
            </form>
        </div>
        <?php
    }

    private function render_trash_posts_tab() {
        ?>
        <div id="trash-posts" class="tab-content">
            <h3><?php esc_html_e('Mover Posts para Lixeira', 'ferramentas-upload'); ?></h3>
            <p><?php esc_html_e('Faça upload de um arquivo CSV contendo as URLs dos posts que você deseja mover para a lixeira.', 'ferramentas-upload'); ?></p>
            <p><?php esc_html_e('O CSV deve ter apenas uma coluna com as URLs dos posts.', 'ferramentas-upload'); ?></p>
            <p><strong><?php esc_html_e('Importante:', 'ferramentas-upload'); ?></strong></p>
            <ul>
                <li><?php esc_html_e('A primeira linha (cabeçalho) será ignorada.', 'ferramentas-upload'); ?></li>
                <li><?php esc_html_e('Os posts serão movidos para a lixeira, não excluídos permanentemente.', 'ferramentas-upload'); ?></li>
                <li><?php esc_html_e('Você pode restaurar os posts da lixeira posteriormente se necessário.', 'ferramentas-upload'); ?></li>
                <li><?php esc_html_e('Faça backup do seu banco de dados antes de executar esta operação.', 'ferramentas-upload'); ?></li>
            </ul>

            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="<?php echo esc_attr(FU_PREFIX . 'action'); ?>" value="trash_posts">
                <?php wp_nonce_field(FU_TRASH_NONCE_ACTION, FU_TRASH_NONCE_FIELD); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <label for="fu_trash_csv_file"><?php esc_html_e('Arquivo CSV (URLs)', 'ferramentas-upload'); ?></label>
                        </th>
                        <td>
                            <input type="file" id="fu_trash_csv_file" name="fu_trash_csv_file" accept=".csv" required>
                            <p class="description"><?php esc_html_e('Selecione o arquivo CSV com as URLs dos posts que deseja mover para a lixeira.', 'ferramentas-upload'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Processar CSV e Mover Posts para Lixeira', 'ferramentas-upload'), 'primary', 'fu_submit_trash'); ?>
            </form>
            <a href="<?php echo esc_url(plugins_url('includes/modelo-exclusao-post.csv', dirname(__FILE__))); ?>" download>
                <button>Modelo da planilha</button>
            </a>
        </div>
        <?php
    }

    private function render_recategorize_posts_tab() {
        ?>
        <div id="recategorize-posts" class="tab-content">
            <h3><?php esc_html_e('Recategorizar Posts', 'ferramentas-upload'); ?></h3>
            <p><?php esc_html_e('Faça upload de um arquivo CSV contendo as URLs dos posts e suas novas categorias.', 'ferramentas-upload'); ?></p>
            <p>
                <?php 
                esc_html_e('O CSV deve ter duas colunas: ', 'ferramentas-upload');
                echo '<strong>' . esc_html__('URL do Post', 'ferramentas-upload') . '</strong> ';
                esc_html_e('e', 'ferramentas-upload');
                echo ' <strong>' . esc_html__('Categorias', 'ferramentas-upload') . '</strong>.';
                ?>
            </p>
            <p><strong><?php esc_html_e('Importante:', 'ferramentas-upload'); ?></strong></p>
            <ul>
                <li><?php esc_html_e('A primeira linha (cabeçalho) será ignorada.', 'ferramentas-upload'); ?></li>
                <li><?php esc_html_e('Caso deseja recategorizar o post em mais de uma categoria, basta apenas separar elas entre , (vírgulas) na mesma célula. Exemplo: (Categoria 1 , Cagetoria 2).', 'ferramentas-upload'); ?></li>
                <li><?php esc_html_e('Categorias que não existem serão criadas automaticamente.', 'ferramentas-upload'); ?></li>
                <li><?php esc_html_e('As categorias existentes do post serão substituídas pelas novas categorias.', 'ferramentas-upload'); ?></li>
                <li><?php esc_html_e('Faça backup do seu banco de dados antes de executar esta operação.', 'ferramentas-upload'); ?></li>
            </ul>

            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="<?php echo esc_attr(FU_PREFIX . 'action'); ?>" value="recategorize_posts">
                <?php wp_nonce_field(FU_CATEGORY_NONCE_ACTION, FU_CATEGORY_NONCE_FIELD); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <label for="fu_category_csv_file"><?php esc_html_e('Arquivo CSV (URLs e Categorias)', 'ferramentas-upload'); ?></label>
                        </th>
                        <td>
                            <input type="file" id="fu_category_csv_file" name="fu_category_csv_file" accept=".csv" required>
                            <p class="description"><?php esc_html_e('Selecione o arquivo CSV com as URLs dos posts e suas novas categorias.', 'ferramentas-upload'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Processar CSV e Recategorizar Posts', FU_TEXT_DOMAIN), 'primary', 'fu_submit_category'); ?>
            </form>
            <a href="<?php echo esc_url(plugins_url('includes/modelo-para-recat.csv', dirname(__FILE__))); ?>" download>
                <button>Modelo da planilha</button>
            </a>
        </div>
        <?php
    }
}