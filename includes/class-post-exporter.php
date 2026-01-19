<?php
/**
 * Classe responsável pela exportação de posts e categorias
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ferramentas_Upload_Post_Exporter {
    private $selected_fields = array();
    
    public function export_posts_categories() {
        // Garante que a constante FU_TEXT_DOMAIN está definida
        if (!defined('FU_TEXT_DOMAIN')) {
            define('FU_TEXT_DOMAIN', 'ferramentas-upload');
        }

        // Obtém os campos selecionados
        $this->selected_fields = isset($_POST['export_fields']) && is_array($_POST['export_fields']) 
            ? array_map('sanitize_key', $_POST['export_fields']) 
            : $this->get_default_fields();
        
        // Se nenhum campo foi selecionado, usa os padrões
        if (empty($this->selected_fields)) {
            $this->selected_fields = $this->get_default_fields();
        }

        $filename = 'posts_com_conteudo_completo-' . date('Y-m-d_H-i-s') . '.csv';

        $this->set_headers($filename);
        $output = $this->open_output_stream();
        $this->write_csv_header($output);
        $this->write_posts_data($output);
        
        fclose($output);
        exit;
    }
    
    private function get_default_fields() {
        return array('meta_title', 'meta_description', 'post_title', 'post_html', 'author', 'publish_date', 'url');
    }

    private function set_headers($filename) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    private function open_output_stream() {
        $output = fopen('php://output', 'w');
        // Adicionar BOM para melhor compatibilidade com Excel em UTF-8
        fwrite($output, "\xEF\xBB\xBF");
        return $output;
    }

    private function write_csv_header($output) {
        $headers = array();
        
        // Mapeia os campos selecionados para os cabeçalhos
        $field_labels = array(
            'meta_title' => __('Meta Title', FU_TEXT_DOMAIN),
            'meta_description' => __('Meta Description', FU_TEXT_DOMAIN),
            'post_title' => __('Título do Post', FU_TEXT_DOMAIN),
            'post_html' => __('HTML do Post', FU_TEXT_DOMAIN),
            'author' => __('Autor', FU_TEXT_DOMAIN),
            'publish_date' => __('Data de Publicação', FU_TEXT_DOMAIN),
            'url' => __('URL', FU_TEXT_DOMAIN)
        );
        
        foreach ($this->selected_fields as $field) {
            if (isset($field_labels[$field])) {
                $headers[] = $field_labels[$field];
            }
        }
        
        fputcsv($output, $headers);
    }

    private function write_posts_data($output) {
        $posts_query = new WP_Query($this->get_query_args());

        if ($posts_query->have_posts()) {
            while ($posts_query->have_posts()) {
                $posts_query->the_post();
                $this->write_post_row($output);
            }
            wp_reset_postdata();
        }
    }

    private function get_query_args() {
        return [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'no_found_rows'  => true, // Otimização, não precisamos de paginação
            'update_post_meta_cache' => false, // Otimização
            'update_post_term_cache' => false, // Otimização (get_the_category fará seu próprio cache)
        ];
    }

    private function write_post_row($output) {
        $post_id = get_the_ID();
        $row_data = array();
        
        foreach ($this->selected_fields as $field) {
            switch ($field) {
                case 'meta_title':
                    $row_data[] = $this->get_meta_title($post_id);
                    break;
                case 'meta_description':
                    $row_data[] = $this->get_meta_description($post_id);
                    break;
                case 'post_title':
                    $row_data[] = get_the_title();
                    break;
                case 'post_html':
                    $row_data[] = $this->get_post_content($post_id);
                    break;
                case 'author':
                    $row_data[] = $this->get_post_author($post_id);
                    break;
                case 'publish_date':
                    $row_data[] = $this->get_publish_date($post_id);
                    break;
                case 'url':
                    $row_data[] = get_permalink();
                    break;
                default:
                    $row_data[] = '';
                    break;
            }
        }
        
        fputcsv($output, $row_data);
    }

    private function get_post_categories($post_id) {
        $categories = get_the_category($post_id);
        $category_names = array();

        if (!empty($categories)) {
            foreach ($categories as $category) {
                $category_names[] = $category->name;
            }
        }

        return implode(', ', $category_names);
    }

    private function get_post_content($post_id) {
        try {
            $content = get_post_field('post_content', $post_id);
            
            // Remove quebras de linha desnecessárias para CSV
            $content = str_replace(["\r\n", "\r", "\n"], ' ', $content);
            
            // Remove espaços múltiplos
            $content = preg_replace('/\s+/', ' ', $content);
            
            // Escapa aspas duplas para CSV
            $content = str_replace('"', '""', $content);
            
            return trim($content);
        } catch (Exception $e) {
            return __('Erro ao obter conteúdo', FU_TEXT_DOMAIN);
        }
    }

    private function get_post_excerpt($post_id) {
        try {
            $excerpt = get_post_field('post_excerpt', $post_id);
            
            // Se não há excerpt, gera um automaticamente
            if (empty($excerpt)) {
                $content = get_post_field('post_content', $post_id);
                $excerpt = wp_trim_words(strip_tags($content), 55, '...');
            }
            
            // Remove quebras de linha desnecessárias para CSV
            $excerpt = str_replace(["\r\n", "\r", "\n"], ' ', $excerpt);
            
            // Remove espaços múltiplos
            $excerpt = preg_replace('/\s+/', ' ', $excerpt);
            
            // Escapa aspas duplas para CSV
            $excerpt = str_replace('"', '""', $excerpt);
            
            return trim($excerpt);
        } catch (Exception $e) {
            return __('Erro ao obter resumo', FU_TEXT_DOMAIN);
        }
    }
    
    private function get_meta_title($post_id) {
        try {
            $meta_title = get_post_meta($post_id, '_yoast_wpseo_title', true);
            
            // Se não há meta title do Yoast, usa o título do post
            if (empty($meta_title)) {
                $meta_title = get_the_title($post_id);
            }
            
            // Remove quebras de linha e espaços múltiplos
            $meta_title = str_replace(["\r\n", "\r", "\n"], ' ', $meta_title);
            $meta_title = preg_replace('/\s+/', ' ', $meta_title);
            $meta_title = str_replace('"', '""', $meta_title);
            
            return trim($meta_title);
        } catch (Exception $e) {
            return '';
        }
    }
    
    private function get_meta_description($post_id) {
        try {
            $meta_desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
            
            // Se não há meta description do Yoast, tenta gerar um do conteúdo
            if (empty($meta_desc)) {
                $content = get_post_field('post_content', $post_id);
                $meta_desc = wp_trim_words(strip_tags($content), 30, '...');
            }
            
            // Remove quebras de linha e espaços múltiplos
            $meta_desc = str_replace(["\r\n", "\r", "\n"], ' ', $meta_desc);
            $meta_desc = preg_replace('/\s+/', ' ', $meta_desc);
            $meta_desc = str_replace('"', '""', $meta_desc);
            
            return trim($meta_desc);
        } catch (Exception $e) {
            return '';
        }
    }
    
    private function get_post_author($post_id) {
        try {
            $author_id = get_post_field('post_author', $post_id);
            $author = get_userdata($author_id);
            
            if ($author) {
                return $author->display_name;
            }
            
            return '';
        } catch (Exception $e) {
            return '';
        }
    }
    
    private function get_publish_date($post_id) {
        try {
            $date = get_post_field('post_date', $post_id);
            
            if ($date) {
                // Formata a data no formato brasileiro: DD/MM/YYYY HH:MM
                return date('d/m/Y H:i', strtotime($date));
            }
            
            return '';
        } catch (Exception $e) {
            return '';
        }
    }
}