<?php
/**
 * Classe responsável pela exportação de posts e categorias
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ferramentas_Upload_Post_Exporter {
    private $selected_fields = array();
        /** @var string XPath opcional para extrair apenas parte do HTML de cada post. */
        private $export_xpath = '';
        /** @var bool Se true, aplica XPath no HTML da página completa (fetch da URL); senão usa só o conteúdo do post. */
        private $export_xpath_full_page = false;

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

        // XPath opcional para extração (ex.: //h2 | //h3 ou XPath do DevTools)
        $this->export_xpath = isset($_POST['fu_export_xpath']) ? sanitize_text_field(wp_unslash($_POST['fu_export_xpath'])) : '';
        $this->export_xpath = trim($this->export_xpath);
        $this->export_xpath_full_page = !empty($_POST['fu_export_xpath_full_page']);

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
            'url' => __('URL', FU_TEXT_DOMAIN),
            'canonical_url' => __('URL Canônica', FU_TEXT_DOMAIN),
            'featured_image' => __('Imagem Destacada', FU_TEXT_DOMAIN),
            'internal_links_count' => __('Contagem de Links Internos', FU_TEXT_DOMAIN),
            'post_status' => __('Status do Post', FU_TEXT_DOMAIN)
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
                    if (!empty($this->export_xpath)) {
                        $row_data[] = $this->get_post_content_by_xpath($post_id);
                    } else {
                        $row_data[] = $this->get_post_content($post_id);
                    }
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
                case 'canonical_url':
                    $row_data[] = $this->get_canonical_url($post_id);
                    break;
                case 'featured_image':
                    $row_data[] = $this->get_featured_image_url($post_id);
                    break;
                case 'internal_links_count':
                    $row_data[] = $this->get_internal_links_count($post_id);
                    break;
                case 'post_status':
                    $row_data[] = $this->get_post_status($post_id);
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

    /**
     * Extrai conteúdo do post aplicando um XPath.
     * Se export_xpath_full_page estiver ativo, usa o HTML da página completa (fetch da URL).
     * Caso contrário, usa apenas o conteúdo bruto do post. Fallback: conteúdo completo.
     *
     * @param int $post_id ID do post.
     * @return string Texto extraído (um por linha) ou conteúdo completo em fallback.
     */
    private function get_post_content_by_xpath($post_id) {
        $xpath_expr = $this->export_xpath;
        if (empty($xpath_expr)) {
            return $this->get_post_content($post_id);
        }

        if ($this->export_xpath_full_page) {
            $content = $this->fetch_full_page_html($post_id);
        } else {
            $content = get_post_field('post_content', $post_id);
            if (!empty($content)) {
                $content = '<div id="fu-xpath-root">' . $content . '</div>';
            }
        }

        if (empty($content)) {
            return $this->get_post_content($post_id);
        }

        $result = $this->apply_xpath_to_html($content, $xpath_expr);
        if ($result !== null) {
            return $result;
        }

        return $this->get_post_content($post_id);
    }

    /**
     * Busca o HTML da página completa do post pela URL (mesmo que o DevTools mostra).
     *
     * @param int $post_id ID do post.
     * @return string HTML da página ou string vazia em caso de falha.
     */
    private function fetch_full_page_html($post_id) {
        $url = get_permalink($post_id);
        if (empty($url)) {
            return '';
        }

        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'sslverify' => true,
            'user-agent' => 'Ferramentas-Upload-Export/1.0',
        ));

        if (is_wp_error($response)) {
            return '';
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return '';
        }

        $body = wp_remote_retrieve_body($response);
        return is_string($body) ? $body : '';
    }

    /**
     * Aplica XPath ao HTML e retorna o texto dos nós encontrados (um por linha).
     *
     * @param string $html HTML completo (página ou fragmento).
     * @param string $xpath_expr Expressão XPath.
     * @return string|null Texto extraído ou null se falhar.
     */
    private function apply_xpath_to_html($html, $xpath_expr) {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $loaded = @$dom->loadHTML(
            mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'),
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        if (!$loaded) {
            return null;
        }

        $xpath = new DOMXPath($dom);
        $nodes = @$xpath->query($xpath_expr);

        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $parts = array();
        foreach ($nodes as $node) {
            $text = trim($node->textContent);
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        if (empty($parts)) {
            return null;
        }

        $result = implode("\n", $parts);
        $result = str_replace(["\r\n", "\r"], "\n", $result);
        $result = str_replace('"', '""', $result);

        return trim($result);
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
    
    private function get_canonical_url($post_id) {
        try {
            // Tenta obter do Yoast SEO primeiro
            $canonical = get_post_meta($post_id, '_yoast_wpseo_canonical', true);
            
            // Se não houver canonical do Yoast, usa a URL do post
            if (empty($canonical)) {
                $canonical = get_permalink($post_id);
            }
            
            return esc_url_raw($canonical);
        } catch (Exception $e) {
            return get_permalink($post_id);
        }
    }
    
    private function get_featured_image_url($post_id) {
        try {
            $thumbnail_id = get_post_thumbnail_id($post_id);
            
            if ($thumbnail_id) {
                $image_url = wp_get_attachment_image_url($thumbnail_id, 'full');
                return $image_url ? esc_url_raw($image_url) : '';
            }
            
            return '';
        } catch (Exception $e) {
            return '';
        }
    }
    
    private function get_internal_links_count($post_id) {
        try {
            $content = get_post_field('post_content', $post_id);
            
            if (empty($content)) {
                return 0;
            }
            
            // Obtém o domínio do site
            $site_url = home_url();
            $parsed_url = parse_url($site_url);
            $site_domain = isset($parsed_url['host']) ? $parsed_url['host'] : '';
            
            if (empty($site_domain)) {
                return 0;
            }
            
            // Remove o www. para comparação
            $site_domain = preg_replace('/^www\./', '', $site_domain);
            
            // Conta links no conteúdo
            $internal_links = 0;
            
            // Regex para encontrar todos os links <a href="...">
            preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);
            
            if (!empty($matches[1])) {
                foreach ($matches[1] as $url) {
                    // Remove espaços e quebras de linha
                    $url = trim($url);
                    
                    // Ignora links vazios, âncoras (#) e javascript:
                    if (empty($url) || $url[0] === '#' || strpos($url, 'javascript:') === 0) {
                        continue;
                    }
                    
                    // Se é um link relativo (começa com /), é interno
                    if ($url[0] === '/') {
                        $internal_links++;
                        continue;
                    }
                    
                    // Parse da URL para verificar o domínio
                    $parsed_link = parse_url($url);
                    
                    if (isset($parsed_link['host'])) {
                        $link_domain = preg_replace('/^www\./', '', $parsed_link['host']);
                        
                        // Compara domínios (case insensitive)
                        if (strtolower($link_domain) === strtolower($site_domain)) {
                            $internal_links++;
                        }
                    }
                }
            }
            
            return $internal_links;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    private function get_post_status($post_id) {
        try {
            $status = get_post_status($post_id);
            
            // Traduz os status mais comuns
            $status_labels = array(
                'publish' => __('Publicado', FU_TEXT_DOMAIN),
                'draft' => __('Rascunho', FU_TEXT_DOMAIN),
                'pending' => __('Pendente', FU_TEXT_DOMAIN),
                'private' => __('Privado', FU_TEXT_DOMAIN),
                'trash' => __('Lixeira', FU_TEXT_DOMAIN),
                'future' => __('Agendado', FU_TEXT_DOMAIN)
            );
            
            return isset($status_labels[$status]) ? $status_labels[$status] : $status;
        } catch (Exception $e) {
            return '';
        }
    }
}