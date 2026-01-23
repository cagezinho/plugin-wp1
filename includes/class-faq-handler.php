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
            return new WP_Error('no_faq', __('Não foi possível gerar FAQ a partir da resposta da IA.', 'ferramentas-upload'));
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
        $full_prompt .= "Título do Post: " . $title . "\n\n";
        $full_prompt .= "Conteúdo do Post:\n" . $content . "\n\n";
        $full_prompt .= "Resumo: " . $excerpt . "\n\n";
        $full_prompt .= "Por favor, gere perguntas e respostas relevantes baseadas no conteúdo acima. Retorne APENAS um JSON válido no formato:\n";
        $full_prompt .= '{"faq": [{"question": "Pergunta 1", "answer": "Resposta 1"}, {"question": "Pergunta 2", "answer": "Resposta 2"}]}';

        return $full_prompt;
    }

    /**
     * Retorna o prompt padrão caso o usuário não tenha configurado
     */
    private function get_default_prompt() {
        return "Você é um especialista em SEO e criação de conteúdo. Seu trabalho é criar perguntas e respostas (FAQ) relevantes e úteis baseadas no conteúdo fornecido. As perguntas devem ser naturais e as respostas devem ser claras e informativas. Crie entre 3 e 8 perguntas e respostas.";
    }

    /**
     * Chama a API do IA Studio (suporta OpenAI e Google Gemini)
     */
    private function call_ia_studio_api($prompt) {
        // Detecta se é chave do Google (começa com AIza)
        $is_google_api = (strpos($this->api_key, 'AIza') === 0);
        
        if ($is_google_api) {
            // API do Google Gemini - tentando diferentes modelos e versões
            // Primeiro tenta gemini-1.5-flash com v1, se falhar pode tentar outros
            $api_url = 'https://generativelanguage.googleapis.com/v1/models/gemini-pro:generateContent?key=' . $this->api_key;
            
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
                    'maxOutputTokens' => 2000
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
        } else {
            // API do OpenAI (padrão)
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

        // Detecta se é resposta do Google Gemini
        $is_google_api = (strpos($this->api_key, 'AIza') === 0);
        
        if ($is_google_api) {
            // Formato de resposta do Google Gemini
            if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return new WP_Error('invalid_response', __('Resposta inválida da API do Google.', 'ferramentas-upload'));
            }
            return $data['candidates'][0]['content']['parts'][0]['text'];
        } else {
            // Formato de resposta do OpenAI
            if (!isset($data['choices'][0]['message']['content'])) {
                return new WP_Error('invalid_response', __('Resposta inválida da API.', 'ferramentas-upload'));
            }
            return $data['choices'][0]['message']['content'];
        }
    }

    /**
     * Parseia a resposta da IA e extrai o FAQ
     */
    private function parse_faq_response($response) {
        // Tenta extrair JSON da resposta (pode estar dentro de markdown code blocks)
        $json_match = array();
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $response, $json_match)) {
            $json_str = $json_match[1];
        } elseif (preg_match('/(\{.*\})/s', $response, $json_match)) {
            $json_str = $json_match[1];
        } else {
            $json_str = $response;
        }

        $data = json_decode($json_str, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Se não conseguir parsear JSON, tenta extrair manualmente
            return $this->parse_faq_manual($response);
        }

        // Verifica se tem a estrutura esperada
        if (isset($data['faq']) && is_array($data['faq'])) {
            return $data['faq'];
        }

        // Tenta outras estruturas possíveis
        if (isset($data['questions']) && is_array($data['questions'])) {
            return $data['questions'];
        }

        if (is_array($data) && isset($data[0]['question'])) {
            return $data;
        }

        return $this->parse_faq_manual($response);
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
