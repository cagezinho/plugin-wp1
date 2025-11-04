<?php
/**
 * Classe responsável pelo processamento do texto alternativo das imagens
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ferramentas_Upload_Alt_Text_Handler {
    private $updated_count = 0;
    private $skipped_count = 0;
    private $not_found_count = 0;
    private $posts_updated_count = 0;
    private $errors = array();
    private $row_number = 0;

    public function process_upload() {
        try {
            // Verifica se a constante FU_TEXT_DOMAIN está definida
            if (!defined('FU_TEXT_DOMAIN')) {
                define('FU_TEXT_DOMAIN', 'ferramentas-upload');
            }

            if (!isset($_FILES['fu_alt_csv_file']) || $_FILES['fu_alt_csv_file']['error'] !== UPLOAD_ERR_OK) {
                $this->show_error(__('Erro no upload do arquivo CSV de Alt Text. Verifique as permissões ou o tamanho do arquivo.', FU_TEXT_DOMAIN));
                return;
            }

            $file = $_FILES['fu_alt_csv_file'];
            if (!$this->validate_file($file)) {
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

    private function validate_file($file) {
        try {
            $file_type = $file['type'];
            if (empty($file_type)) {
                if (function_exists('finfo_open')) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    if ($finfo) {
                        $file_type = finfo_file($finfo, $file['tmp_name']);
                        finfo_close($finfo);
                    }
                }
            }

            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_mime_types = ['text/csv', 'application/vnd.ms-excel', 'text/plain', 'application/csv'];

            if (!in_array($file_type, $allowed_mime_types) && $file_ext !== 'csv') {
                $this->show_error(__('Tipo de arquivo inválido ou extensão não permitida. Por favor, envie um arquivo .csv.', FU_TEXT_DOMAIN));
                return false;
            }

            return true;
        } catch (Exception $e) {
            $this->show_error(__('Erro na validação do arquivo: ', FU_TEXT_DOMAIN) . $e->getMessage());
            return false;
        }
    }

    private function process_csv_file($file_path) {
        try {
            if (!file_exists($file_path)) {
                $this->show_error(__('Arquivo temporário não encontrado.', FU_TEXT_DOMAIN));
                return;
            }

            if (($handle = fopen($file_path, 'r')) !== FALSE) {
                // Pula cabeçalho se existir
                $header = fgetcsv($handle, 0, ",");
                $this->row_number++;

                while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
                    $this->row_number++;
                    $this->process_csv_row($data);
                }

                fclose($handle);
            } else {
                $this->show_error(__('Não foi possível abrir o arquivo CSV para leitura.', FU_TEXT_DOMAIN));
            }
        } catch (Exception $e) {
            $this->show_error(__('Erro ao processar arquivo CSV: ', FU_TEXT_DOMAIN) . $e->getMessage());
        }
    }

    private function process_csv_row($data) {
        try {
            // Verifica se $data é válido
            if (!is_array($data)) {
                $this->errors[] = sprintf(__('Linha %d: Dados inválidos.', FU_TEXT_DOMAIN), $this->row_number);
                $this->skipped_count++;
                return;
            }

            // Verifica encoding e converte se necessário
            $data = $this->ensure_utf8_encoding($data);

            if (count($data) < 2) {
                $this->errors[] = sprintf(__('Linha %d: Número insuficiente de colunas. Verifique se o CSV está separado por vírgulas.', FU_TEXT_DOMAIN), $this->row_number);
                $this->skipped_count++;
                return;
            }

            $image_url = isset($data[0]) ? trim($data[0]) : '';
            $alt_text = isset($data[1]) ? trim($data[1]) : '';

            if (empty($image_url)) {
                $this->errors[] = sprintf(__('Linha %d: URL da imagem está vazia.', FU_TEXT_DOMAIN), $this->row_number);
                $this->skipped_count++;
                return;
            }

            $this->update_image_alt_text($image_url, $alt_text);
        } catch (Exception $e) {
            $this->errors[] = sprintf(__('Linha %d: Erro ao processar linha: %s', FU_TEXT_DOMAIN), $this->row_number, $e->getMessage());
            $this->skipped_count++;
        }
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

    private function update_image_alt_text($image_url, $alt_text) {
        try {
            // Verifica se a função attachment_url_to_postid existe
            if (!function_exists('attachment_url_to_postid')) {
                $this->errors[] = sprintf(__('Linha %d: Função attachment_url_to_postid não disponível.', FU_TEXT_DOMAIN), $this->row_number);
                $this->skipped_count++;
                return;
            }

            $attachment_id = attachment_url_to_postid($image_url);

            if ($attachment_id) {
                if (get_post_type($attachment_id) === 'attachment') {
                    update_post_meta($attachment_id, '_wp_attachment_image_alt', wp_kses_post($alt_text));
                    $this->updated_count++;
                    
                    $updated_posts = $this->update_posts_with_image($attachment_id, $image_url, $alt_text);
                    $this->posts_updated_count += $updated_posts;
                } else {
                    $this->errors[] = sprintf(__('Linha %d: URL encontrada (%s), mas não é um anexo da biblioteca de mídia.', FU_TEXT_DOMAIN), $this->row_number, esc_url($image_url));
                    $this->not_found_count++;
                }
            } else {
                $this->errors[] = sprintf(__('Linha %d: Imagem não encontrada na biblioteca de mídia para a URL: %s', FU_TEXT_DOMAIN), $this->row_number, esc_url($image_url));
                $this->not_found_count++;
            }
        } catch (Exception $e) {
            $this->errors[] = sprintf(__('Linha %d: Erro ao atualizar imagem: %s', FU_TEXT_DOMAIN), $this->row_number, $e->getMessage());
            $this->skipped_count++;
        }
    }

    private function update_posts_with_image($attachment_id, $image_url, $alt_text) {
        try {
            $count = 0;
            $filename = basename($image_url);
            
            // Busca posts que podem conter a imagem
            $post_ids = $this->find_posts_with_image($attachment_id, $filename);
            
            if (empty($post_ids)) {
                return 0;
            }
            
            foreach ($post_ids as $post_id) {
                $count += $this->update_post_content($post_id, $attachment_id, $alt_text);
            }
            
            return $count;
        } catch (Exception $e) {
            $this->errors[] = sprintf(__('Erro ao atualizar posts com imagem: %s', FU_TEXT_DOMAIN), $e->getMessage());
            return 0;
        }
    }

    private function find_posts_with_image($attachment_id, $filename) {
        try {
            // Busca por posts que têm a imagem como thumbnail
            $posts_query_args = [
                'post_type'      => ['post', 'page'],
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                's'              => $filename,
                'meta_query'     => [
                    [
                        'key'     => '_thumbnail_id',
                        'value'   => $attachment_id,
                        'compare' => '=',
                    ],
                ],
                'fields' => 'ids'
            ];
            $ids_thumbnail = get_posts($posts_query_args);

            // Busca mais ampla por posts que podem conter a imagem no conteúdo
            $posts_query_args_content = [
                'post_type'      => ['post', 'page'],
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                's'              => $filename,
                'fields'         => 'ids'
            ];
            $ids_content = get_posts($posts_query_args_content);

            return array_unique(array_merge($ids_thumbnail, $ids_content));
        } catch (Exception $e) {
            $this->errors[] = sprintf(__('Erro ao buscar posts com imagem: %s', FU_TEXT_DOMAIN), $e->getMessage());
            return [];
        }
    }

    private function update_post_content($post_id, $attachment_id, $alt_text) {
        try {
            $content = get_post_field('post_content', $post_id);
            if (empty($content)) {
                return 0;
            }

            $new_content = $content;
            $updated_in_this_post = false;
            $count = 0;

            // Atualiza imagens com classe wp-image-ID
            $pattern_wp_class = '/<img[^>]*class=(["\'])(?:[^"\\']*\s)?wp-image-' . intval($attachment_id) . '(?:\s[^"\\']*)?\1[^>]*>/i';
            if (preg_match_all($pattern_wp_class, $content, $matches_wp_class)) {
                foreach ($matches_wp_class[0] as $img_tag) {
                    $count += $this->update_img_tag($img_tag, $alt_text, $new_content, $updated_in_this_post);
                }
            }

            // Atualiza o post se houve alterações
            if ($updated_in_this_post && $new_content !== $content) {
                wp_update_post([
                    'ID' => $post_id,
                    'post_content' => $new_content
                ]);
            }

            return $count;
        } catch (Exception $e) {
            $this->errors[] = sprintf(__('Erro ao atualizar conteúdo do post %d: %s', FU_TEXT_DOMAIN), $post_id, $e->getMessage());
            return 0;
        }
    }

    private function update_img_tag($img_tag, $alt_text, &$new_content, &$updated_in_this_post) {
        try {
            $original_img_tag = $img_tag;
            
            if (preg_match('/alt=/i', $img_tag)) {
                $img_tag = preg_replace('/alt=(["\'])(?:[^"\\']*)?\1/i', 'alt="' . esc_attr($alt_text) . '"', $img_tag, 1);
            } else {
                $img_tag = str_replace('<img ', '<img alt="' . esc_attr($alt_text) . '" ', $img_tag);
            }
            
            if ($img_tag !== $original_img_tag) {
                $new_content = str_replace($original_img_tag, $img_tag, $new_content);
                $updated_in_this_post = true;
                return 1;
            }
            
            return 0;
        } catch (Exception $e) {
            $this->errors[] = sprintf(__('Erro ao atualizar tag de imagem: %s', FU_TEXT_DOMAIN), $e->getMessage());
            return 0;
        }
    }

    private function show_results() {
        try {
            echo '<div class="fu-notice success"><p>';
            printf(
                esc_html(_n(
                    'Processamento concluído! %d imagem atualizada na biblioteca.',
                    'Processamento concluído! %d imagens atualizadas na biblioteca.',
                    $this->updated_count,
                    FU_TEXT_DOMAIN
                )) . ' ',
                $this->updated_count
            );
            printf(
                esc_html(_n(
                    '%d instância de imagem atualizada em posts/páginas.',
                    '%d instâncias de imagens atualizadas em posts/páginas.',
                    $this->posts_updated_count,
                    FU_TEXT_DOMAIN
                )) . ' ',
                $this->posts_updated_count
            );
            printf(
                esc_html(_n(
                    '%d URL não encontrada na biblioteca.',
                    '%d URLs não encontradas na biblioteca.',
                    $this->not_found_count,
                    FU_TEXT_DOMAIN
                )) . ' ',
                $this->not_found_count
            );
            printf(
                esc_html(_n(
                    '%d linha pulada (vazia ou formato incorreto).',
                    '%d linhas puladas (vazias ou formato incorreto).',
                    $this->skipped_count,
                    FU_TEXT_DOMAIN
                )),
                $this->skipped_count
            );
            echo '</p></div>';

            if (!empty($this->errors)) {
                echo '<div class="fu-notice warning"><p><strong>' .
                     esc_html__('Detalhes dos erros/avisos encontrados:', FU_TEXT_DOMAIN) .
                     '</strong></p><ul>';
                foreach ($this->errors as $error) {
                    echo '<li>' . wp_kses_post($error) . '</li>';
                }
                echo '</ul></div>';
            }
        } catch (Exception $e) {
            echo '<div class="fu-notice error"><p>' . esc_html__('Erro ao exibir resultados: ', FU_TEXT_DOMAIN) . esc_html($e->getMessage()) . '</p></div>';
        }
    }

    private function show_error($message) {
        try {
            echo '<div class="fu-notice error"><p>' . esc_html($message) . '</p></div>';
        } catch (Exception $e) {
            // Fallback simples se houver erro
            echo '<div class="fu-notice error"><p>Erro no sistema</p></div>';
        }
    }

    private function cleanup($file_path) {
        try {
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
        } catch (Exception $e) {
            // Ignora erros de limpeza
        }
    }
}