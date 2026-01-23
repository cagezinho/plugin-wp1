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
