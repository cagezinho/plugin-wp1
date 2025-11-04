<?php
/**
 * Classe responsável pelo processamento das meta tags SEO do Yoast
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ferramentas_Upload_SERP_Handler {
    private $success_count = 0;
    private $error_log = array();
    private $warning_log = array();
    private $row_num = 0;

    public function process_upload() {
        try {
            // Verifica se a constante FU_TEXT_DOMAIN está definida
            if (!defined('FU_TEXT_DOMAIN')) {
                define('FU_TEXT_DOMAIN', 'ferramentas-upload');
            }

            if (!$this->check_yoast_dependency()) {
                return;
            }

            if (!$this->validate_upload()) {
                return;
            }

            $file = $_FILES['fu_serp_csv_file'];
            if (!$this->validate_file_type($file)) {
                return;
            }

            // Aumenta limites de tempo e memória
            @set_time_limit(300);
            @ini_set('memory_limit', '256M');
            
            // Tenta definir locale, mas não falha se não conseguir
            @setlocale(LC_ALL, 'pt_BR.UTF-8', 'pt_BR', 'Portuguese_Brazil', 'Portuguese');

            $this->process_csv_file($file['tmp_name']);
            $this->show_results();
            $this->cleanup($file['tmp_name']);
            
        } catch (Exception $e) {
            $this->show_error(__('Erro inesperado durante o processamento: ', FU_TEXT_DOMAIN) . $e->getMessage());
        }
    }

    private function check_yoast_dependency() {
        // Verifica se a função is_plugin_active está disponível
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $yoast_active = (
            is_plugin_active('wordpress-seo/wp-seo.php') ||
            is_plugin_active('wordpress-seo-premium/wp-seo-premium.php') ||
            // Fallback para versões recentes do Yoast (v24+) ou ambientes onde a checagem por slug falhe
            defined('WPSEO_VERSION') ||
            class_exists('WPSEO_Meta') ||
            class_exists('Yoast\\WP\\SEO\\Main')
        );

        if (!$yoast_active) {
            $this->show_error(sprintf(
                __('Erro: O plugin %s precisa estar ativo para executar esta ação.', FU_TEXT_DOMAIN),
                '<strong>Yoast SEO</strong>'
            ));
            return false;
        }
        return true;
    }

    private function validate_upload() {
        if (!isset($_FILES['fu_serp_csv_file']) || $_FILES['fu_serp_csv_file']['error'] !== UPLOAD_ERR_OK) {
            $this->show_error(__('Erro no upload do arquivo CSV de SERP. Código:', FU_TEXT_DOMAIN) . ' ' .
                             esc_html($_FILES['fu_serp_csv_file']['error']));
            return false;
        }
        return true;
    }

    private function validate_file_type($file) {
        $file_info = wp_check_filetype($file['name']);
        if (strtolower($file_info['ext']) !== 'csv') {
            $this->show_error(__('Tipo de arquivo inválido. Por favor, envie um arquivo .csv.', FU_TEXT_DOMAIN));
            return false;
        }
        return true;
    }

    private function process_csv_file($file_path) {
        if (($handle = fopen($file_path, 'r')) !== FALSE) {
            while (($data = fgetcsv($handle, 0, ',')) !== FALSE) {
                $this->row_num++;

                if ($this->row_num == 1) { // Pula cabeçalho
                    continue;
                }

                $this->process_csv_row($data);
            }
            fclose($handle);
        } else {
            $this->show_error(__('Não foi possível abrir o arquivo CSV para leitura.', FU_TEXT_DOMAIN));
        }
    }

    private function process_csv_row($data) {
        // Verifica encoding e converte se necessário
        $data = $this->ensure_utf8_encoding($data);

        if (count($data) < 3) {
            $this->error_log[] = sprintf(
                __('Linha %d: Formato inválido (esperado: URL, Título, Descrição). Linha ignorada.', FU_TEXT_DOMAIN),
                $this->row_num
            );
            return;
        }

        $url = isset($data[0]) ? trim($data[0]) : '';
        $new_title = isset($data[1]) ? trim($data[1]) : null;
        $new_desc = isset($data[2]) ? trim($data[2]) : null;

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            $this->error_log[] = sprintf(
                __('Linha %d: URL inválida ou vazia (\'%s\'). Linha ignorada.', FU_TEXT_DOMAIN),
                $this->row_num,
                esc_html($url)
            );
            return;
        }

        $this->update_post_meta_data($url, $new_title, $new_desc);
    }

    private function ensure_utf8_encoding($data) {
        try {
            // Verifica se as funções mb_* estão disponíveis
            if (!function_exists('mb_check_encoding') || !function_exists('mb_detect_encoding') || !function_exists('mb_convert_encoding')) {
                return $data;
            }

            $original_data_string = implode(',', $data);
            if (!mb_check_encoding($original_data_string, 'UTF-8')) {
                return array_map(function($item) {
                    $detected_encoding = mb_detect_encoding($item, 'UTF-8, ISO-8859-1', true);
                    if ($detected_encoding && $detected_encoding !== 'UTF-8') {
                        return mb_convert_encoding($item, 'UTF-8', $detected_encoding);
                    }
                    return $item;
                }, $data);
            }
            return $data;
        } catch (Exception $e) {
            // Se houver erro no encoding, retorna os dados originais
            return $data;
        }
    }

    private function update_post_meta_data($url, $new_title, $new_desc) {
        $post_id = $this->get_post_id_from_url($url);

        if ($post_id > 0) {
            $yoast_title_key = '_yoast_wpseo_title';
            $yoast_desc_key = '_yoast_wpseo_metadesc';
            $updated_meta = false;

            if ($new_title !== null) {
                update_post_meta($post_id, $yoast_title_key, sanitize_text_field($new_title));
                $updated_meta = true;
            }

            if ($new_desc !== null) {
                update_post_meta($post_id, $yoast_desc_key, sanitize_textarea_field($new_desc));
                $updated_meta = true;
            }

            if ($updated_meta) {
                $this->success_count++;
            } else {
                $this->warning_log[] = sprintf(
                    __('Linha %d: URL \'%s\' (ID: %d) encontrada, mas nenhum título ou descrição foi fornecido para atualização no CSV.', FU_TEXT_DOMAIN),
                    $this->row_num,
                    esc_url($url),
                    $post_id
                );
            }
        } else {
            $this->error_log[] = sprintf(
                __('Linha %d: Post/Página não encontrado para a URL \'%s\'. Verifique se a URL está correta e existe no site.', FU_TEXT_DOMAIN),
                $this->row_num,
                esc_url($url)
            );
        }
    }

    /**
     * Busca o ID do post/page pela URL, incluindo páginas, homepage e outros tipos de conteúdo
     */
    private function get_post_id_from_url($url) {
        // Remove query strings e fragmentos da URL para busca mais precisa
        $clean_url = preg_replace('/[?#].*$/', '', $url);
        
        // Primeiro tenta usar url_to_postid (funciona para a maioria dos casos)
        $post_id = url_to_postid($clean_url);
        
        if ($post_id > 0) {
            return $post_id;
        }

        // Extrai o path da URL e compara com a homepage
        $parsed_url = parse_url($clean_url);
        $home_url_parsed = parse_url(home_url());
        
        // Normaliza URLs removendo protocolo e domínio para comparação
        $url_normalized = isset($parsed_url['path']) ? trim($parsed_url['path'], '/') : '';
        $home_path_normalized = isset($home_url_parsed['path']) ? trim($home_url_parsed['path'], '/') : '';
        
        // Se a URL normalizada for igual à homepage ou estiver vazia, pode ser a homepage
        if (empty($url_normalized) || $url_normalized === $home_path_normalized) {
            // Verifica se a homepage é uma página estática
            $home_page_id = get_option('page_on_front');
            if ($home_page_id) {
                return (int) $home_page_id;
            }
            // Se não for página estática, retorna 0 (homepage com posts recentes não tem ID específico)
            // Mas podemos usar o ID da página de posts se configurada
            $posts_page_id = get_option('page_for_posts');
            if ($posts_page_id) {
                return (int) $posts_page_id;
            }
            return 0;
        }
        
        $path = $url_normalized;

        // Remove a base do WordPress se existir
        $home_path = parse_url(home_url(), PHP_URL_PATH);
        if ($home_path && strpos($path, trim($home_path, '/')) === 0) {
            $path = substr($path, strlen(trim($home_path, '/')));
            $path = trim($path, '/');
        }

        // Busca por slug em todos os tipos de post (posts, pages, e custom post types)
        $post_types = get_post_types(array('public' => true), 'names');
        
        // Remove attachment do array para evitar falsos positivos
        $post_types = array_diff($post_types, array('attachment'));
        
        // Primeiro tenta buscar pelo path completo (útil para páginas hierárquicas)
        $args = array(
            'name' => $path,
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids'
        );
        
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            $post_id = $query->posts[0];
            wp_reset_postdata();
            return (int) $post_id;
        }
        
        // Se não encontrou pelo path completo, tenta apenas o último segmento (slug final)
        $slug = basename($path);
        if ($slug !== $path) {
            $args = array(
                'name' => $slug,
                'post_type' => $post_types,
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'fields' => 'ids'
            );
            
            $query = new WP_Query($args);
            
            if ($query->have_posts()) {
                $post_id = $query->posts[0];
                wp_reset_postdata();
                return (int) $post_id;
            }
        }
        
        // Como último recurso, busca direto no banco por slug
        // Isso pode ser útil para URLs com estrutura customizada
        global $wpdb;
        
        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
            WHERE post_name = %s 
            AND post_status = 'publish' 
            AND post_type IN ('" . implode("','", array_map('esc_sql', $post_types)) . "')
            LIMIT 1",
            $slug
        ));
        
        if ($post_id) {
            return (int) $post_id;
        }

        return 0;
    }

    private function show_results() {
        if ($this->success_count > 0) {
            echo '<div class="fu-notice success"><p>' .
                 sprintf(
                     esc_html__('%d registro(s) de SERP atualizado(s) com sucesso.', FU_TEXT_DOMAIN),
                     $this->success_count
                 ) .
                 '</p></div>';
        } else {
            if (empty($this->error_log) && empty($this->warning_log)) {
                echo '<div class="fu-notice warning"><p>' .
                     esc_html__('Nenhum registro de SERP foi atualizado (nenhum dado válido encontrado no CSV ou posts correspondentes).', FU_TEXT_DOMAIN) .
                     '</p></div>';
            }
        }

        $this->show_warnings();
        $this->show_errors();
    }

    private function show_warnings() {
        if (!empty($this->warning_log)) {
            echo '<div class="fu-notice warning"><p><strong>' .
                 esc_html__('Avisos encontrados:', FU_TEXT_DOMAIN) .
                 '</strong></p><ul>';
            foreach ($this->warning_log as $warning) {
                echo '<li>' . wp_kses_post($warning) . '</li>';
            }
            echo '</ul></div>';
        }
    }

    private function show_errors() {
        if (!empty($this->error_log)) {
            echo '<div class="fu-notice error"><p><strong>' .
                 esc_html__('Erros encontrados durante o processamento:', FU_TEXT_DOMAIN) .
                 '</strong></p><ul>';
            foreach ($this->error_log as $error) {
                echo '<li>' . wp_kses_post($error) . '</li>';
            }
            echo '</ul></div>';
        }
    }

    private function show_error($message) {
        echo '<div class="fu-notice error"><p>' . wp_kses_post($message) . '</p></div>';
    }

    private function cleanup($file_path) {
        if (file_exists($file_path)) {
            @unlink($file_path);
        }
    }
}