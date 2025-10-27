<?php
/**
 * Classe responsável pela exportação de posts e categorias
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ferramentas_Upload_Post_Exporter {
    public function export_posts_categories() {
        // Garante que a constante FU_TEXT_DOMAIN está definida
        if (!defined('FU_TEXT_DOMAIN')) {
            define('FU_TEXT_DOMAIN', 'ferramentas-upload');
        }

        $filename = 'posts_com_conteudo_completo-' . date('Y-m-d_H-i-s') . '.csv';

        $this->set_headers($filename);
        $output = $this->open_output_stream();
        $this->write_csv_header($output);
        $this->write_posts_data($output);
        
        fclose($output);
        exit;
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
        // Debug temporário - remover após confirmar funcionamento
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Ferramentas Upload] Escrevendo cabeçalho CSV com 6 colunas');
        }

        fputcsv($output, [
            __('ID do Post', FU_TEXT_DOMAIN),
            __('Título do Post', FU_TEXT_DOMAIN),
            __('URL do Post', FU_TEXT_DOMAIN),
            __('Categorias', FU_TEXT_DOMAIN),
            __('Conteúdo HTML', FU_TEXT_DOMAIN),
            __('Resumo/Excerpt', FU_TEXT_DOMAIN)
        ]);
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
        $post_title = get_the_title();
        $post_url = get_permalink();
        $categories_string = $this->get_post_categories($post_id);
        $post_content = $this->get_post_content($post_id);
        $post_excerpt = $this->get_post_excerpt($post_id);

        // Debug temporário - remover após confirmar funcionamento
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Ferramentas Upload] Post ID ' . $post_id . ' - Conteúdo HTML: ' . (empty($post_content) ? 'VAZIO' : 'OK'));
        }

        fputcsv($output, [
            $post_id,
            $post_title,
            $post_url,
            $categories_string,
            $post_content,
            $post_excerpt
        ]);
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
}