<?php
/**
 * Classe responsável pelo processamento de FAQ structured data com IA Studio
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ferramentas_Upload_FAQ_Handler {
    private $api_key;
    private $prompt;
    private $api_url;

    public function __construct() {
        // Chave de API deve ser configurada pelo usuário (não hardcoded por segurança)
        $this->api_key = get_option('fu_faq_api_key', '');
        $this->prompt = get_option('fu_faq_prompt', '');
        // URL da API - padrão OpenAI, mas pode ser configurada
        $this->api_url = get_option('fu_faq_api_url', 'https://api.openai.com/v1/chat/completions');
    }

    /**
     * Gera FAQ para um post usando a API do IA Studio
     */
    public function generate_faq_for_post($post_id) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Chave de API não configurada. Configure na página de FAQ.', 'ferramentas-upload'));
        }

        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('post_not_found', __('Post não encontrado.', 'ferramentas-upload'));
        }

        // Prepara o conteúdo do post
        $post_content = strip_tags($post->post_content);
        $post_title = $post->post_title;
        $post_excerpt = !empty($post->post_excerpt) ? $post->post_excerpt : wp_trim_words($post_content, 30);

        // Monta o prompt completo
        $full_prompt = $this->build_prompt($post_title, $post_content, $post_excerpt);

        // Chama a API do IA Studio
        $response = $this->call_ia_studio_api($full_prompt);

        if (is_wp_error($response)) {
            return $response;
        }

        // Parseia a resposta e extrai o FAQ
        $faq_data = $this->parse_faq_response($response);

        if (empty($faq_data)) {
            // Mostra parte da resposta para debug (primeiros 500 caracteres)
            $debug_response = substr($response, 0, 500);
            return new WP_Error('no_faq', sprintf(
                __('Não foi possível gerar FAQ a partir da resposta da IA. Resposta recebida (primeiros 500 caracteres): %s', 'ferramentas-upload'),
                esc_html($debug_response)
            ));
        }

        return array(
            'faq_data' => $faq_data,
            'post_id' => $post_id,
            'post_title' => $post_title
        );
    }

    /**
     * Constrói o prompt completo para a IA
     */
    private function build_prompt($title, $content, $excerpt) {
        $base_prompt = !empty($this->prompt) ? $this->prompt : $this->get_default_prompt();
        
        $full_prompt = $base_prompt . "\n\n";
        $full_prompt .= "=== CONTEÚDO PARA ANÁLISE ===\n\n";
        $full_prompt .= "Título: " . $title . "\n\n";
        $full_prompt .= "Conteúdo Completo:\n" . $content . "\n\n";
        $full_prompt .= "Resumo: " . $excerpt . "\n\n";
        $full_prompt .= "=== INSTRUÇÕES FINAIS ===\n";
        $full_prompt .= "Analise o conteúdo acima seguindo todas as regras estabelecidas.\n";
        $full_prompt .= "Identifique FAQ explícito, implícito ou se não há conteúdo elegível.\n";
        $full_prompt .= "Retorne APENAS um JSON válido no formato:\n";
        $full_prompt .= '{"faq": [{"question": "Pergunta 1", "answer": "Resposta 1"}, {"question": "Pergunta 2", "answer": "Resposta 2"}]}';
        $full_prompt .= "\n\nSe não houver conteúdo elegível, retorne: {\"faq\": []}";

        return $full_prompt;
    }

    /**
     * Retorna o prompt padrão caso o usuário não tenha configurado
     */
    private function get_default_prompt() {
        return "Você é um especialista em SEO técnico e dados estruturados.

Sua tarefa é:
1. Analisar o conteúdo fornecido
2. Identificar se há FAQ explícito, implícito ou nenhum
3. Gerar JSON-LD válido seguindo Schema.org
4. Nunca inventar informações
5. Seguir estritamente as diretrizes de rich results do Google

REGRAS OBRIGATÓRIAS:
✅ O que PODE:
- Reescrever respostas mantendo sentido original
- Resumir respostas longas
- Unificar perguntas redundantes

❌ O que NÃO PODE:
- Inventar perguntas não respondidas no texto
- Criar respostas que não existam implicitamente
- Fazer conteúdo promocional
- Gerar FAQ para páginas não informativas

Se não houver conteúdo elegível, informe claramente e NÃO gere JSON-LD.

Retorne APENAS um JSON válido no formato:
{\"faq\": [{\"question\": \"Pergunta 1\", \"answer\": \"Resposta 1\"}, {\"question\": \"Pergunta 2\", \"answer\": \"Resposta 2\"}]}

Crie entre 3 e 10 perguntas e respostas baseadas no conteúdo fornecido.";
    }

    /**
     * Retorna o prompt para análise de posts com URLs (nova funcionalidade)
     */
    private function get_analysis_prompt() {
        $custom_prompt = get_option('fu_faq_prompt', '');
        if (!empty($custom_prompt)) {
            return $custom_prompt;
        }
        
        return "Você é um especialista em análise de conteúdo web e extração de FAQ estruturado.

Sua tarefa é analisar o conteúdo HTML fornecido e identificar se ele contém FAQ elegível seguindo EXATAMENTE estas condições:

CONDIÇÃO 1 - Subtítulos em formato de perguntas:
- Verifique se o post possui subtítulos (tags H2, H3, H4, etc.) que estão em formato de PERGUNTAS (terminam com ?)
- Se encontrar MAIS DE UMA pergunta como subtítulo:
  * Cada subtítulo que for uma pergunta deve virar uma pergunta no FAQ
  * O parágrafo IMEDIATAMENTE seguinte a cada subtítulo-pergunta será interpretado como a resposta
  * Se o parágrafo for muito extenso (mais de 300 palavras), crie um resumo conciso mantendo as informações principais
  * Retorne no formato: {\"faq\": [{\"question\": \"Pergunta do subtítulo\", \"answer\": \"Resposta do parágrafo seguinte\"}]}

CONDIÇÃO 2 - Estrutura de FAQ na página:
- Se a CONDIÇÃO 1 não for atendida, verifique se existe uma estrutura de FAQ em algum momento da página
- Procure por seções como \"Perguntas Frequentes\", \"FAQ\", ou estruturas HTML que contenham perguntas e respostas
- Essas estruturas podem estar FORA do HTML principal do post (em widgets, sidebars, etc.)
- Se encontrar, extraia todas as perguntas e respostas dessa seção
  * Retorne no formato: {\"faq\": [{\"question\": \"Pergunta encontrada\", \"answer\": \"Resposta encontrada\"}]}

CONDIÇÃO 3 - Nenhuma condição atendida:
- Se NENHUMA das condições acima for atendida, retorne: {\"faq\": []}

REGRAS IMPORTANTES:
- NUNCA invente perguntas ou respostas que não existam no conteúdo
- NUNCA crie FAQ se não houver conteúdo elegível
- Para respostas longas, sempre crie um resumo conciso (máximo 300 palavras)
- Mantenha o sentido original das respostas
- Se encontrar múltiplas perguntas na CONDIÇÃO 1, inclua TODAS no resultado

Retorne APENAS um JSON válido no formato especificado acima. Nada mais.";
    }

    /**
     * Processa CSV com URLs de posts e analisa cada um
     */
    public function process_urls_csv($csv_file_path) {
        if (!file_exists($csv_file_path)) {
            return new WP_Error('file_not_found', __('Arquivo CSV não encontrado.', 'ferramentas-upload'));
        }

        $urls = array();
        $handle = fopen($csv_file_path, 'r');
        
        if ($handle === false) {
            return new WP_Error('file_read_error', __('Erro ao ler arquivo CSV.', 'ferramentas-upload'));
        }

        // Pula a primeira linha (cabeçalho)
        $header = fgetcsv($handle);
        
        // Lê as URLs
        while (($row = fgetcsv($handle)) !== false) {
            if (!empty($row[0])) {
                $url = trim($row[0]);
                if (!empty($url)) {
                    $urls[] = $url;
                }
            }
        }
        
        fclose($handle);

        if (empty($urls)) {
            return new WP_Error('no_urls', __('Nenhuma URL encontrada no CSV.', 'ferramentas-upload'));
        }

        // Analisa cada URL
        $results = array();
        foreach ($urls as $url) {
            $analysis = $this->analyze_post_by_url($url);
            if (!is_wp_error($analysis) && !empty($analysis['faq'])) {
                $results[] = array(
                    'url' => $url,
                    'post_id' => $analysis['post_id'],
                    'post_title' => $analysis['post_title'],
                    'faq' => $analysis['faq']
                );
            }
        }

        return $results;
    }

    /**
     * Analisa um post pela URL
     */
    private function analyze_post_by_url($url) {
        // Extrai o post ID ou slug da URL
        $post_id = url_to_postid($url);
        
        if ($post_id === 0) {
            // Tenta encontrar pelo slug
            $parsed_url = parse_url($url);
            if ($parsed_url && isset($parsed_url['path'])) {
                $path = trim($parsed_url['path'], '/');
                $path_parts = explode('/', $path);
                $slug = end($path_parts);
                
                if (!empty($slug)) {
                    $post = get_page_by_path($slug, OBJECT, array('post', 'page'));
                    if ($post) {
                        $post_id = $post->ID;
                    }
                }
            }
        }

        if ($post_id === 0) {
            return new WP_Error('post_not_found', sprintf(__('Post não encontrado para URL: %s', 'ferramentas-upload'), $url));
        }

        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('post_not_found', sprintf(__('Post não encontrado para URL: %s', 'ferramentas-upload'), $url));
        }

        // Obtém o conteúdo HTML do post
        $post_content = $post->post_content;
        $post_title = $post->post_title;
        
        // Aplica os filtros do WordPress para obter o conteúdo renderizado
        $full_html = apply_filters('the_content', $post_content);
        
        // Se ainda estiver vazio, usa o conteúdo bruto
        if (empty($full_html)) {
            $full_html = $post_content;
        }
        
        // Adiciona informações sobre a estrutura HTML para ajudar na análise
        // Extrai subtítulos (H2, H3, H4) e parágrafos
        $html_structure = $this->extract_html_structure($full_html);

        // Monta o prompt de análise
        $analysis_prompt = $this->get_analysis_prompt();
        $full_prompt = $analysis_prompt . "\n\n";
        $full_prompt .= "=== CONTEÚDO PARA ANÁLISE ===\n\n";
        $full_prompt .= "Título: " . $post_title . "\n\n";
        $full_prompt .= "Conteúdo HTML Completo:\n" . $full_html . "\n\n";
        
        // Adiciona estrutura extraída se disponível
        if (!empty($html_structure)) {
            $full_prompt .= "Estrutura de Subtítulos Encontrada:\n" . $html_structure . "\n\n";
        }
        
        $full_prompt .= "=== INSTRUÇÕES FINAIS ===\n";
        $full_prompt .= "Analise o conteúdo HTML acima seguindo EXATAMENTE as 3 condições especificadas.\n";
        $full_prompt .= "Retorne APENAS um JSON válido no formato: {\"faq\": [{\"question\": \"...\", \"answer\": \"...\"}]}\n";
        $full_prompt .= "Se nenhuma condição for atendida, retorne: {\"faq\": []}";

        // Chama a API
        $response = $this->call_ia_studio_api($full_prompt);

        if (is_wp_error($response)) {
            return $response;
        }

        // Parseia a resposta
        $faq_data = $this->parse_faq_response($response);

        return array(
            'post_id' => $post_id,
            'post_title' => $post_title,
            'url' => $url,
            'faq' => $faq_data
        );
    }

    /**
     * Extrai estrutura HTML (subtítulos e parágrafos) para ajudar na análise
     */
    private function extract_html_structure($html) {
        $structure = array();
        
        // Remove scripts e styles
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi', '', $html);
        $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/gi', '', $html);
        
        // Extrai subtítulos (H2, H3, H4)
        preg_match_all('/<h[2-4][^>]*>(.*?)<\/h[2-4]>/i', $html, $headings);
        
        if (!empty($headings[1])) {
            foreach ($headings[1] as $index => $heading_text) {
                $heading_text = strip_tags($heading_text);
                $heading_text = trim($heading_text);
                
                // Verifica se é uma pergunta
                if (substr($heading_text, -1) === '?') {
                    $structure[] = array(
                        'type' => 'question_heading',
                        'text' => $heading_text,
                        'index' => $index
                    );
                } else {
                    $structure[] = array(
                        'type' => 'heading',
                        'text' => $heading_text,
                        'index' => $index
                    );
                }
            }
        }
        
        // Se encontrou subtítulos em formato de perguntas, retorna informação útil
        $question_headings = array_filter($structure, function($item) {
            return $item['type'] === 'question_heading';
        });
        
        if (count($question_headings) > 1) {
            $info = "Encontrados " . count($question_headings) . " subtítulos em formato de pergunta:\n";
            foreach ($question_headings as $item) {
                $info .= "- " . $item['text'] . "\n";
            }
            return $info;
        }
        
        return '';
    }

    /**
     * Gera CSV com os resultados da análise
     */
    public function generate_results_csv($results) {
        if (empty($results)) {
            return new WP_Error('no_results', __('Nenhum resultado para exportar.', 'ferramentas-upload'));
        }

        $filename = 'faq-analysis-results-' . date('Y-m-d-H-i-s') . '.csv';
        $filepath = sys_get_temp_dir() . '/' . $filename;

        $handle = fopen($filepath, 'w');
        if ($handle === false) {
            return new WP_Error('file_write_error', __('Erro ao criar arquivo CSV.', 'ferramentas-upload'));
        }

        // Cabeçalho
        fputcsv($handle, array('URL', 'Post ID', 'Post Title', 'Question', 'Answer'));

        // Dados
        foreach ($results as $result) {
            if (!empty($result['faq']) && is_array($result['faq'])) {
                foreach ($result['faq'] as $faq_item) {
                    fputcsv($handle, array(
                        $result['url'],
                        $result['post_id'],
                        $result['post_title'],
                        $faq_item['question'],
                        $faq_item['answer']
                    ));
                }
            }
        }

        fclose($handle);

        return array(
            'filepath' => $filepath,
            'filename' => $filename
        );
    }

    /**
     * Processa CSV revisado e aplica nos posts
     */
    public function process_reviewed_csv($csv_file_path) {
        if (!file_exists($csv_file_path)) {
            return new WP_Error('file_not_found', __('Arquivo CSV não encontrado.', 'ferramentas-upload'));
        }

        $handle = fopen($csv_file_path, 'r');
        if ($handle === false) {
            return new WP_Error('file_read_error', __('Erro ao ler arquivo CSV.', 'ferramentas-upload'));
        }

        // Lê cabeçalho
        $header = fgetcsv($handle);
        
        // Verifica se o cabeçalho tem o formato esperado
        if (empty($header) || count($header) < 5) {
            fclose($handle);
            return new WP_Error('invalid_format', __('Formato de CSV inválido. Esperado: URL, Post ID, Post Title, Question, Answer', 'ferramentas-upload'));
        }
        
        // Agrupa FAQ por post
        $posts_faq = array();
        $line_number = 1; // Contador de linhas para mensagens de erro
        
        while (($row = fgetcsv($handle)) !== false) {
            $line_number++;
            
            // Ignora linhas vazias
            if (empty(array_filter($row))) {
                continue;
            }
            
            if (count($row) < 5) {
                continue; // Linha incompleta, pula
            }
            
            $url = trim($row[0]);
            $post_id = intval($row[1]);
            $question = trim($row[3]);
            $answer = trim($row[4]);
            
            // Valida dados obrigatórios
            if (empty($url) || $post_id <= 0 || empty($question) || empty($answer)) {
                continue; // Dados inválidos, pula
            }
            
            // Verifica se o post existe
            $post = get_post($post_id);
            if (!$post) {
                continue; // Post não existe, pula
            }
            
            if (!isset($posts_faq[$post_id])) {
                $posts_faq[$post_id] = array();
            }
            
            $posts_faq[$post_id][] = array(
                'question' => $question,
                'answer' => $answer
            );
        }
        
        fclose($handle);

        if (empty($posts_faq)) {
            return new WP_Error('no_data', __('Nenhum dado válido encontrado no CSV.', 'ferramentas-upload'));
        }

        // Aplica FAQ em cada post
        $applied = 0;
        $errors = array();
        
        foreach ($posts_faq as $post_id => $faq_data) {
            $result = $this->save_faq_to_post($post_id, $faq_data);
            if (is_wp_error($result)) {
                $errors[] = sprintf(__('Erro ao aplicar FAQ no post ID %d: %s', 'ferramentas-upload'), $post_id, $result->get_error_message());
            } else {
                $applied++;
            }
        }

        return array(
            'applied' => $applied,
            'errors' => $errors
        );
    }

    /**
     * Chama a API do IA Studio (suporta OpenAI, Google Gemini e outras APIs compatíveis)
     */
    private function call_ia_studio_api($prompt) {
        // Detecta tipo de API pela chave ou URL
        $is_google_api = (strpos($this->api_key, 'AIza') === 0);
        $is_openai_api = (strpos($this->api_url, 'openai.com') !== false);
        
        // Se for Google API, tenta usar formato do Google
        if ($is_google_api && !$is_openai_api) {
            // API do Google Gemini - tentando gemini-3-flash-preview primeiro, depois fallback
            $models_to_try = array(
                'gemini-3-flash-preview',
                'gemini-1.5-flash',
                'gemini-1.5-pro',
                'gemini-pro'
            );
            
            $last_error = null;
            
            foreach ($models_to_try as $model) {
                // Tenta v1beta primeiro
                $api_url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $this->api_key;
            
                $body = array(
                    'contents' => array(
                        array(
                            'parts' => array(
                                array(
                                    'text' => $prompt
                                )
                            )
                        )
                    ),
                    'generationConfig' => array(
                        'temperature' => 0.7,
                        'maxOutputTokens' => 4000
                    )
                );

                $args = array(
                    'method' => 'POST',
                    'headers' => array(
                        'Content-Type' => 'application/json'
                    ),
                    'body' => wp_json_encode($body),
                    'timeout' => 60
                );

                $response = wp_remote_request($api_url, $args);
                
                if (is_wp_error($response)) {
                    $last_error = $response->get_error_message();
                    continue;
                }
                
                $response_code = wp_remote_retrieve_response_code($response);
                $response_body = wp_remote_retrieve_body($response);
                
                if ($response_code === 200) {
                    $data = json_decode($response_body, true);
                    
                    if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                        return $data['candidates'][0]['content']['parts'][0]['text'];
                    }
                } elseif ($response_code === 404) {
                    // Modelo não encontrado, tenta próximo
                    continue;
                } else {
                    $error_data = json_decode($response_body, true);
                    $last_error = isset($error_data['error']['message']) ? $error_data['error']['message'] : __('Erro desconhecido da API.', 'ferramentas-upload');
                    continue;
                }
            }
            
            // Se chegou aqui, nenhum modelo funcionou
            if ($last_error) {
                return new WP_Error('api_error', sprintf(__('Erro da API do Google Gemini: %s', 'ferramentas-upload'), $last_error));
            }
            return new WP_Error('api_error', __('Nenhum modelo do Google Gemini disponível.', 'ferramentas-upload'));
        } else {
            // API compatível com OpenAI (OpenAI, IA Studio, etc.)
            $body = array(
                'model' => 'gpt-4',
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => $prompt
                    )
                ),
                'temperature' => 0.7,
                'max_tokens' => 2000
            );

            $args = array(
                'method' => 'POST',
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->api_key
                ),
                'body' => wp_json_encode($body),
                'timeout' => 60
            );
            
            $api_url = $this->api_url;
        }

        $response = wp_remote_request($api_url, $args);

        if (is_wp_error($response)) {
            return new WP_Error('api_error', __('Erro ao conectar com a API: ', 'ferramentas-upload') . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : __('Erro desconhecido da API.', 'ferramentas-upload');
            return new WP_Error('api_error', sprintf(__('Erro da API (código %d): %s', 'ferramentas-upload'), $response_code, $error_message));
        }

        $data = json_decode($response_body, true);

        // Formato de resposta do OpenAI (Google já foi tratado acima)
        if (!isset($data['choices'][0]['message']['content'])) {
            return new WP_Error('invalid_response', __('Resposta inválida da API.', 'ferramentas-upload'));
        }
        return $data['choices'][0]['message']['content'];
    }

    /**
     * Parseia a resposta da IA e extrai o FAQ - Nova abordagem robusta
     */
    private function parse_faq_response($response) {
        // Remove espaços em branco no início e fim
        $response = trim($response);
        
        // Decodifica HTML entities (como &quot; para ")
        $response = html_entity_decode($response, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Estratégia 1: Tenta extrair JSON de dentro de code blocks markdown
        $json_str = null;
        if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/s', $response, $matches)) {
            $json_str = $matches[1];
        }
        // Estratégia 2: Procura por qualquer objeto JSON completo
        elseif (preg_match('/(\{[\s\S]{20,}\})/s', $response, $matches)) {
            $json_str = $matches[1];
        }
        // Estratégia 3: Se a resposta inteira parece ser JSON
        elseif (preg_match('/^[\s\n]*\{/s', $response) && preg_match('/\}[\s\n]*$/s', $response)) {
            $json_str = $response;
        }
        
        // Se encontrou JSON, tenta parsear
        if ($json_str !== null) {
            $json_str = trim($json_str);
            
            // Tenta parsear diretamente
            $data = json_decode($json_str, true);
            
            // Se falhou, tenta limpar mais
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Remove texto antes do primeiro {
                $json_str = preg_replace('/^[^{]*/', '', $json_str);
                // Encontra o último } válido contando chaves
                $brace_count = 0;
                $last_brace_pos = -1;
                for ($i = 0; $i < strlen($json_str); $i++) {
                    if ($json_str[$i] === '{') $brace_count++;
                    if ($json_str[$i] === '}') {
                        $brace_count--;
                        if ($brace_count === 0) {
                            $last_brace_pos = $i;
                            break;
                        }
                    }
                }
                if ($last_brace_pos > 0) {
                    $json_str = substr($json_str, 0, $last_brace_pos + 1);
                }
                $data = json_decode($json_str, true);
            }
            
            // Se conseguiu parsear, tenta extrair FAQ
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                $faq_data = $this->extract_faq_from_data($data);
                if (!empty($faq_data)) {
                    return $faq_data;
                }
            }
        }
        
        // Se todas as estratégias falharam, tenta parsing manual
        return $this->parse_faq_manual($response);
    }
    
    /**
     * Extrai FAQ de diferentes estruturas de dados
     */
    private function extract_faq_from_data($data) {
        $faq_array = array();
        
        // Estrutura 1: json_ld.mainEntity (formato retornado pela IA)
        if (isset($data['json_ld']['mainEntity']) && is_array($data['json_ld']['mainEntity'])) {
            foreach ($data['json_ld']['mainEntity'] as $item) {
                if (isset($item['name']) && isset($item['acceptedAnswer']['text'])) {
                    $faq_array[] = array(
                        'question' => $this->clean_text($item['name']),
                        'answer' => $this->clean_text($item['acceptedAnswer']['text'])
                    );
                }
            }
            if (!empty($faq_array)) {
                return $faq_array;
            }
        }
        
        // Estrutura 2: FAQPage Schema.org direto
        if (isset($data['@type']) && $data['@type'] === 'FAQPage' && isset($data['mainEntity']) && is_array($data['mainEntity'])) {
            foreach ($data['mainEntity'] as $item) {
                if (isset($item['name']) && isset($item['acceptedAnswer']['text'])) {
                    $faq_array[] = array(
                        'question' => $this->clean_text($item['name']),
                        'answer' => $this->clean_text($item['acceptedAnswer']['text'])
                    );
                }
            }
            if (!empty($faq_array)) {
                return $faq_array;
            }
        }
        
        // Estrutura 3: Formato simples com 'faq'
        if (isset($data['faq']) && is_array($data['faq'])) {
            foreach ($data['faq'] as $item) {
                if (isset($item['question']) && isset($item['answer'])) {
                    $faq_array[] = array(
                        'question' => $this->clean_text($item['question']),
                        'answer' => $this->clean_text($item['answer'])
                    );
                }
            }
            if (!empty($faq_array)) {
                return $faq_array;
            }
        }
        
        // Estrutura 4: Array direto de perguntas
        if (is_array($data) && isset($data[0])) {
            foreach ($data as $item) {
                if (isset($item['question']) && isset($item['answer'])) {
                    $faq_array[] = array(
                        'question' => $this->clean_text($item['question']),
                        'answer' => $this->clean_text($item['answer'])
                    );
                } elseif (isset($item['name']) && isset($item['acceptedAnswer']['text'])) {
                    $faq_array[] = array(
                        'question' => $this->clean_text($item['name']),
                        'answer' => $this->clean_text($item['acceptedAnswer']['text'])
                    );
                }
            }
            if (!empty($faq_array)) {
                return $faq_array;
            }
        }
        
        // Estrutura 5: questions
        if (isset($data['questions']) && is_array($data['questions'])) {
            foreach ($data['questions'] as $item) {
                if (isset($item['question']) && isset($item['answer'])) {
                    $faq_array[] = array(
                        'question' => $this->clean_text($item['question']),
                        'answer' => $this->clean_text($item['answer'])
                    );
                }
            }
            if (!empty($faq_array)) {
                return $faq_array;
            }
        }
        
        return array();
    }
    
    /**
     * Limpa texto removendo HTML entities e espaços extras
     */
    private function clean_text($text) {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim($text);
        return $text;
    }

    /**
     * Parseia FAQ manualmente quando JSON não está disponível
     */
    private function parse_faq_manual($text) {
        $faq = array();
        $lines = explode("\n", $text);
        $current_question = '';
        $current_answer = '';

        foreach ($lines as $line) {
            $line = trim($line);
            
            // Detecta perguntas (linhas que terminam com ? ou começam com Q:, Pergunta:, etc)
            if (preg_match('/^(?:Q:|Pergunta:|P:|\d+[\.\)])\s*(.+[?])$/i', $line, $matches)) {
                // Salva FAQ anterior se existir
                if (!empty($current_question) && !empty($current_answer)) {
                    $faq[] = array(
                        'question' => $current_question,
                        'answer' => trim($current_answer)
                    );
                }
                $current_question = $matches[1];
                $current_answer = '';
            }
            // Detecta respostas (linhas que começam com R:, Resposta:, A:, etc)
            elseif (preg_match('/^(?:R:|Resposta:|A:)\s*(.+)$/i', $line, $matches)) {
                $current_answer = $matches[1];
            }
            // Se já temos uma pergunta, acumula como resposta
            elseif (!empty($current_question)) {
                if (!empty($current_answer)) {
                    $current_answer .= ' ' . $line;
                } else {
                    $current_answer = $line;
                }
            }
        }

        // Adiciona o último FAQ
        if (!empty($current_question) && !empty($current_answer)) {
            $faq[] = array(
                'question' => $current_question,
                'answer' => trim($current_answer)
            );
        }

        return $faq;
    }

    /**
     * Salva FAQ no post
     */
    public function save_faq_to_post($post_id, $faq_data) {
        if (empty($faq_data) || !is_array($faq_data)) {
            return new WP_Error('invalid_data', __('Dados de FAQ inválidos.', 'ferramentas-upload'));
        }

        // Valida e sanitiza os dados
        $validated_faq = array();
        foreach ($faq_data as $item) {
            if (!empty($item['question']) && !empty($item['answer'])) {
                $validated_faq[] = array(
                    'question' => sanitize_text_field($item['question']),
                    'answer' => wp_kses_post($item['answer'])
                );
            }
        }

        if (empty($validated_faq)) {
            return new WP_Error('no_valid_faq', __('Nenhum FAQ válido encontrado.', 'ferramentas-upload'));
        }

        // Salva no post meta
        $result = update_post_meta($post_id, '_fu_faq_structured_data', $validated_faq);

        if ($result === false) {
            return new WP_Error('save_failed', __('Erro ao salvar FAQ no post.', 'ferramentas-upload'));
        }

        return true;
    }

    /**
     * Obtém FAQ de um post
     */
    public function get_faq_from_post($post_id) {
        return get_post_meta($post_id, '_fu_faq_structured_data', true);
    }

    /**
     * Remove FAQ de um post
     */
    public function remove_faq_from_post($post_id) {
        return delete_post_meta($post_id, '_fu_faq_structured_data');
    }
}
