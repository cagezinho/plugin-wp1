<?php
/**
 * Classe responsável por recategorizar posts via CSV
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ferramentas_Upload_Post_Category_Handler {
    private $success_count = 0;
    private $error_log = array();
    private $warning_log = array();
    private $row_num = 0;
    private $created_categories = array();

    public function process_upload() {
        if (!current_user_can('manage_categories') || !current_user_can('edit_posts')) {
            wp_die(__('Você não tem permissão para gerenciar categorias e editar posts.', FU_TEXT_DOMAIN));
        }

        if (!$this->validate_upload()) {
            return;
        }

        $file = $_FILES['fu_category_csv_file'];
        if (!$this->validate_file_type($file)) {
            return;
        }

        @set_time_limit(300);
        setlocale(LC_ALL, 'pt_BR.UTF-8', 'pt_BR', 'Portuguese_Brazil', 'Portuguese');

        $this->process_csv_file($file['tmp_name']);
        $this->show_results();
        $this->cleanup($file['tmp_name']);
    }

    private function validate_upload() {
        if (!isset($_FILES['fu_category_csv_file'])) {
            $this->show_error(__('Nenhum arquivo foi enviado.', FU_TEXT_DOMAIN));
            return false;
        }

        if ($_FILES['fu_category_csv_file']['error'] !== UPLOAD_ERR_OK) {
            $this->show_error(__('Erro no upload do arquivo CSV. Verifique as permissões ou o tamanho do arquivo.', FU_TEXT_DOMAIN));
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
        $data = $this->ensure_utf8_encoding($data);

        if (empty($data[0]) || empty($data[1])) {
            $this->error_log[] = sprintf(
                __('Linha %d: URL ou categoria vazia. Linha ignorada.', FU_TEXT_DOMAIN),
                $this->row_num
            );
            return;
        }

        $url = trim($data[0]);
        $categories = array_map('trim', explode(',', $data[1]));

        // Verifica se a URL é do site atual
        $site_url = get_site_url();
        if (strpos($url, $site_url) !== 0) {
            $this->error_log[] = sprintf(
                __('Linha %d: URL inválida ou externa: %s', FU_TEXT_DOMAIN),
                $this->row_num,
                esc_url($url)
            );
            return;
        }

        $this->update_post_categories($url, $categories);
    }

    private function ensure_utf8_encoding($data) {
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
    }

    private function update_post_categories($url, $categories) {
        $post_id = url_to_postid($url);

        if ($post_id > 0) {
            $post = get_post($post_id);
            if ($post) {
                $category_ids = array();
                
                foreach ($categories as $category_name) {
                    $category_id = $this->get_or_create_category($category_name);
                    if ($category_id) {
                        $category_ids[] = $category_id;
                    }
                }

                if (!empty($category_ids)) {
                    wp_set_post_categories($post_id, $category_ids, false);
                    $this->success_count++;
                }
            } else {
                $this->error_log[] = sprintf(
                    __('Linha %d: Post com ID %d não encontrado.', FU_TEXT_DOMAIN),
                    $this->row_num,
                    $post_id
                );
            }
        } else {
            $this->error_log[] = sprintf(
                __('Linha %d: Nenhum post encontrado para a URL: %s', FU_TEXT_DOMAIN),
                $this->row_num,
                esc_url($url)
            );
        }
    }

    private function get_or_create_category($category_name) {
        // Verifica se já criamos esta categoria nesta sessão
        if (isset($this->created_categories[$category_name])) {
            return $this->created_categories[$category_name];
        }

        // Procura a categoria pelo nome
        $category = get_term_by('name', $category_name, 'category');
        
        if ($category) {
            $this->created_categories[$category_name] = $category->term_id;
            return $category->term_id;
        }

        // Cria nova categoria
        $new_category = wp_insert_term($category_name, 'category');
        
        if (is_wp_error($new_category)) {
            $this->error_log[] = sprintf(
                __('Linha %d: Erro ao criar categoria "%s": %s', FU_TEXT_DOMAIN),
                $this->row_num,
                esc_html($category_name),
                $new_category->get_error_message()
            );
            return false;
        }

        $this->created_categories[$category_name] = $new_category['term_id'];
        $this->warning_log[] = sprintf(
            __('Nova categoria criada: %s', FU_TEXT_DOMAIN),
            esc_html($category_name)
        );

        return $new_category['term_id'];
    }

    private function show_results() {
        if ($this->success_count > 0) {
            echo '<div class="notice notice-success is-dismissible"><p>' .
                 sprintf(
                     esc_html__('%d post(s) recategorizado(s) com sucesso.', FU_TEXT_DOMAIN),
                     $this->success_count
                 ) .
                 '</p></div>';
        } else {
            if (empty($this->error_log) && empty($this->warning_log)) {
                echo '<div class="notice notice-warning is-dismissible"><p>' .
                     esc_html__('Nenhum post foi recategorizado.', FU_TEXT_DOMAIN) .
                     '</p></div>';
            }
        }

        $this->show_warnings();
        $this->show_errors();
    }

    private function show_warnings() {
        if (!empty($this->warning_log)) {
            echo '<div class="notice notice-warning is-dismissible"><p><strong>' .
                 esc_html__('Avisos:', FU_TEXT_DOMAIN) .
                 '</strong></p><ul>';
            foreach ($this->warning_log as $warning) {
                echo '<li>' . wp_kses_post($warning) . '</li>';
            }
            echo '</ul></div>';
        }
    }

    private function show_errors() {
        if (!empty($this->error_log)) {
            echo '<div class="notice notice-error is-dismissible"><p><strong>' .
                 esc_html__('Erros encontrados:', FU_TEXT_DOMAIN) .
                 '</strong></p><ul>';
            foreach ($this->error_log as $error) {
                echo '<li>' . wp_kses_post($error) . '</li>';
            }
            echo '</ul></div>';
        }
    }

    private function show_error($message) {
        echo '<div class="notice notice-error is-dismissible"><p>' . wp_kses_post($message) . '</p></div>';
    }

    private function cleanup($file_path) {
        if (file_exists($file_path)) {
            @unlink($file_path);
        }
    }
}