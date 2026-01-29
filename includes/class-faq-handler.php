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

        // Valida o FAQ extraído
        $validation = $this->validate_faq_data($faq_data, $post_content);
        if (is_wp_error($validation)) {
            return $validation;
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
        return "Você é um especialista em SEO técnico e dados estruturados Schema.org.

Sua tarefa é analisar o conteúdo fornecido e gerar dados estruturados de FAQ (FAQPage) seguindo estritamente as regras abaixo.

=== REGRAS FUNDAMENTAIS ===

1. IDIOMA:
   - O FAQ deve ser gerado NO MESMO IDIOMA do conteúdo fornecido
   - Se o conteúdo for em inglês, todas as perguntas e respostas devem ser em inglês
   - Se o conteúdo for em português, todas as perguntas e respostas devem ser em português
   - Se o conteúdo for em espanhol, todas as perguntas e respostas devem ser em espanhol
   - IMPORTANTE: Analise CADA conteúdo individualmente e mantenha seu idioma original
   - NUNCA traduza o conteúdo
   - NUNCA ignore conteúdo em um idioma diferente - processe cada conteúdo no seu próprio idioma

2. ANÁLISE DO CONTEÚDO - DUAS CONDIÇÕES:

   CONDIÇÃO A - FAQ EXPLÍCITO DETECTADO:
   Se o conteúdo contém uma seção claramente identificada como:
   - \"Perguntas Frequentes\" / \"FAQ\" / \"Frequently Asked Questions\"
   - \"Perguntas e Respostas\" / \"Questions and Answers\"
   - Ou qualquer seção com estrutura clara de pergunta + resposta
   
   ➡️ AÇÃO: Extraia EXATAMENTE as perguntas e respostas dessa seção
   - Use o texto EXATO das perguntas encontradas
   - Use o texto EXATO das respostas encontradas
   - Não reescreva, não resuma, não modifique
   - Mantenha a formatação e estrutura original

   CONDIÇÃO B - FAQ IMPLÍCITO (SEM SEÇÃO ESPECÍFICA):
   Se NÃO há seção de FAQ explícita no conteúdo:
   
   ➡️ AÇÃO: Procure APENAS por títulos que já são perguntas
   - Procure por títulos (H2, H3, H4) que terminem EXATAMENTE com ponto de interrogação '?'
   - CRÍTICO: Se NÃO encontrar títulos terminando com '?', retorne IMEDIATAMENTE json_ld com mainEntity vazio
   - CRÍTICO: NUNCA transforme títulos que não são perguntas em perguntas
   - CRÍTICO: NUNCA crie perguntas a partir de títulos informativos como \"Different type of certifications\" ou \"Types of hazards\"
   - Se encontrar títulos em formato de pergunta (terminando com '?'), use-os como perguntas
   - Use os parágrafos IMEDIATAMENTE abaixo de cada título-pergunta como respostas
   - Se encontrar MENOS de 2 títulos com '?', retorne json_ld com mainEntity vazio
   - Se encontrar 2 ou mais títulos com '?', processe TODOS eles
   - Mantenha o texto dos parágrafos EXATAMENTE como está - com resalva de que se o parágrafo for extremamente extenso (mais de 500 palavras), pode considerar realizar um resumo daquela resposta

3. VALIDAÇÃO DE TÍTULOS - REGRAS CRÍTICAS:
   - ANTES de usar um título como pergunta, verifique se termina EXATAMENTE com '?'
   - Se o título já é uma pergunta (termina com ?): use diretamente
   - Se o título NÃO termina com '?': DESCONSIDERE COMPLETAMENTE - não transforme, não crie pergunta
   - NUNCA transforme títulos informativos em perguntas (ex: \"Different type of certifications\" → NÃO vira \"What are the different types of certifications?\")
   - NUNCA invente perguntas que não tenham resposta no texto
   - NUNCA use títulos que não tenham parágrafo explicativo abaixo
   - Se o conteúdo for apenas informativo sem perguntas explícitas: retorne json_ld com mainEntity vazio

4. PRESERVAÇÃO DO TEXTO:
   - Mantenha TODO o texto original
   - Resuma respostas extremamente longas
   - Não adicione informações que não existam no conteúdo
   - Preserve a formatação e estrutura do texto original

5. ESTRUTURA DO RETORNO:
   Retorne APENAS um JSON válido no formato:
   {
     \"status\": \"faq_detectado\" ou \"faq_implicito\" ou \"nao_elegivel\",
     \"tipo_faq\": \"explicito\" ou \"implicito\" ou \"nenhum\",
     \"idioma\": \"pt-BR\" ou \"en\" ou outro detectado,
     \"quantidade_perguntas\": número,
     \"json_ld\": {
       \"@context\": \"https://schema.org\",
       \"@type\": \"FAQPage\",
       \"mainEntity\": [
         {
           \"@type\": \"Question\",
           \"name\": \"Pergunta exata do texto\",
           \"acceptedAnswer\": {
             \"@type\": \"Answer\",
             \"text\": \"Resposta exata do texto\"
           }
         }
       ]
     }
   }

6. CASOS ESPECIAIS - QUANDO NÃO GERAR FAQ:
   - Se o conteúdo for apenas informativo sem perguntas explícitas (ex: artigo sobre \"Different type of certifications\", \"Types of hazards\", etc.)
   - Se não houver títulos terminando com '?' E não houver seção de FAQ explícita
   - Se encontrar MENOS de 2 títulos com '?'
   - Se o conteúdo não tiver estrutura de pergunta e resposta clara
   - Em TODOS esses casos: retorne {\"status\": \"nao_elegivel\", \"tipo_faq\": \"nenhum\", \"idioma\": \"detectado\", \"quantidade_perguntas\": 0, \"json_ld\": {\"@context\": \"https://schema.org\", \"@type\": \"FAQPage\", \"mainEntity\": []}}
   - Mínimo de 2 perguntas, máximo de 10 perguntas (se encontrar)

=== EXEMPLOS ===

Exemplo 1 - FAQ Explícito:
Conteúdo: \"... texto ... Perguntas Frequentes: 1. Como funciona? R: Funciona assim... 2. Quanto custa? R: Custa X...\"
Retorno: Extrair exatamente as perguntas e respostas da seção FAQ

Exemplo 2 - FAQ Implícito:
Conteúdo: \"... texto ... <h2>Como funciona o sistema?</h2> <p>O sistema funciona através de...</p> <h2>Quais são os benefícios?</h2> <p>Os benefícios incluem...</p>\"
Retorno: Usar os títulos H2 que terminam com '?' como perguntas e os parágrafos P abaixo como respostas

Exemplo 3 - Título não é pergunta:
Conteúdo: \"... <h2>Benefícios do produto</h2> <p>O produto oferece...</p>\"
Retorno: Desconsiderar este título pois não termina com '?' e retornar json_ld com mainEntity vazio (a menos que existam outros títulos com '?')

=== INSTRUÇÕES FINAIS ===

Analise o conteúdo fornecido abaixo seguindo TODAS as regras acima.

PASSO 1: Verifique se há seção de FAQ explícita (CONDIÇÃO A)
PASSO 2: Se NÃO houver, procure por títulos H2/H3/H4 que terminem EXATAMENTE com '?' (CONDIÇÃO B)
PASSO 3: Se NÃO encontrar títulos com '?', retorne IMEDIATAMENTE json_ld com mainEntity vazio
PASSO 4: Mantenha o idioma original do conteúdo - se for inglês, FAQ em inglês; se for português, FAQ em português
PASSO 5: Preserve o texto exato sempre que possível

IMPORTANTE: Se o conteúdo for apenas informativo sem perguntas explícitas, NÃO crie FAQ. Retorne mainEntity vazio.

Retorne APENAS o JSON válido, sem texto adicional antes ou depois.

CONTEÚDO PARA ANÁLISE:";
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
        $errors = array();
        
        foreach ($urls as $url) {
            $analysis = $this->analyze_post_by_url($url);
            
            if (is_wp_error($analysis)) {
                // Erro na análise
                $errors[] = array(
                    'url' => $url,
                    'post_id' => 0,
                    'post_title' => '',
                    'error' => $analysis->get_error_message(),
                    'error_code' => $analysis->get_error_code()
                );
            } elseif (empty($analysis['faq'])) {
                // Análise bem-sucedida mas sem FAQ encontrado
                $errors[] = array(
                    'url' => $url,
                    'post_id' => isset($analysis['post_id']) ? $analysis['post_id'] : 0,
                    'post_title' => isset($analysis['post_title']) ? $analysis['post_title'] : '',
                    'error' => __('Nenhum FAQ encontrado no conteúdo. O conteúdo pode não ter títulos em formato de pergunta ou seção de FAQ explícita.', 'ferramentas-upload'),
                    'error_code' => 'no_faq_found'
                );
            } else {
                // Sucesso - FAQ encontrado
                $results[] = array(
                    'url' => $url,
                    'post_id' => $analysis['post_id'],
                    'post_title' => $analysis['post_title'],
                    'faq' => $analysis['faq']
                );
            }
        }

        return array(
            'success' => $results,
            'errors' => $errors
        );
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
        
        if (empty($faq_data)) {
            return array(
                'post_id' => $post_id,
                'post_title' => $post_title,
                'url' => $url,
                'faq' => array()
            );
        }

        // Valida o FAQ extraído
        $validation = $this->validate_faq_data($faq_data, $full_html);
        if (is_wp_error($validation)) {
            // Se a validação falhou, retorna sem FAQ (será marcado como erro)
            return array(
                'post_id' => $post_id,
                'post_title' => $post_title,
                'url' => $url,
                'faq' => array(),
                'validation_error' => $validation->get_error_message()
            );
        }

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
     * Gera CSVs com os resultados da análise (sucessos e erros separados)
     * Retorna array com dois arquivos: success_csv e errors_csv
     */
    public function generate_results_csv($results_data) {
        // Compatibilidade: se receber array simples (formato antigo), converte
        if (isset($results_data['success']) || isset($results_data['errors'])) {
            $success_results = isset($results_data['success']) ? $results_data['success'] : array();
            $error_results = isset($results_data['errors']) ? $results_data['errors'] : array();
        } else {
            // Formato antigo - assume que são todos sucessos
            $success_results = $results_data;
            $error_results = array();
        }

        $timestamp = date('Y-m-d-H-i-s');
        $return_data = array();

        // Gera CSV de sucessos
        if (!empty($success_results)) {
            $success_file = $this->generate_success_csv($success_results, $timestamp);
            if (!is_wp_error($success_file)) {
                $return_data['success_csv'] = $success_file;
            }
        }

        // Gera CSV de erros
        if (!empty($error_results)) {
            $error_file = $this->generate_errors_csv($error_results, $timestamp);
            if (!is_wp_error($error_file)) {
                $return_data['errors_csv'] = $error_file;
            }
        }

        if (empty($return_data)) {
            return new WP_Error('no_results', __('Nenhum resultado para exportar.', 'ferramentas-upload'));
        }

        return $return_data;
    }

    /**
     * Gera CSV com resultados de sucesso
     * Formato: URL, Post ID, Post Title, Question1, Answer1, Question2, Answer2, ...
     */
    private function generate_success_csv($results, $timestamp) {
        $filename = 'faq-analysis-success-' . $timestamp . '.csv';
        $filepath = sys_get_temp_dir() . '/' . $filename;

        $handle = fopen($filepath, 'w');
        if ($handle === false) {
            return new WP_Error('file_write_error', __('Erro ao criar arquivo CSV de sucessos.', 'ferramentas-upload'));
        }

        // Adiciona BOM para melhor compatibilidade com Excel em UTF-8
        fwrite($handle, "\xEF\xBB\xBF");

        // Encontra o número máximo de perguntas para criar cabeçalho completo
        $max_faq_count = 0;
        foreach ($results as $result) {
            if (!empty($result['faq']) && is_array($result['faq'])) {
                $count = count($result['faq']);
                if ($count > $max_faq_count) {
                    $max_faq_count = $count;
                }
            }
        }

        // Monta cabeçalho
        $header = array('URL', 'Post ID', 'Post Title');
        for ($i = 1; $i <= $max_faq_count; $i++) {
            $header[] = 'Question' . $i;
            $header[] = 'Answer' . $i;
        }
        fputcsv($handle, $header);

        // Dados - agrupa todas perguntas/respostas de uma URL na mesma linha
        foreach ($results as $result) {
            $row = array(
                $result['url'],
                $result['post_id'],
                $result['post_title']
            );

            if (!empty($result['faq']) && is_array($result['faq'])) {
                foreach ($result['faq'] as $faq_item) {
                    $row[] = $faq_item['question'];
                    $row[] = $faq_item['answer'];
                }
            }

            // Preenche com valores vazios se tiver menos FAQ que o máximo
            $current_faq_count = !empty($result['faq']) ? count($result['faq']) : 0;
            $missing_pairs = $max_faq_count - $current_faq_count;
            for ($i = 0; $i < $missing_pairs; $i++) {
                $row[] = '';
                $row[] = '';
            }

            fputcsv($handle, $row);
        }

        fclose($handle);

        return array(
            'filepath' => $filepath,
            'filename' => $filename
        );
    }

    /**
     * Gera CSV com resultados de erros e justificativas
     * Formato: URL, Post ID, Post Title, Erro, Justificativa
     */
    private function generate_errors_csv($errors, $timestamp) {
        $filename = 'faq-analysis-errors-' . $timestamp . '.csv';
        $filepath = sys_get_temp_dir() . '/' . $filename;

        $handle = fopen($filepath, 'w');
        if ($handle === false) {
            return new WP_Error('file_write_error', __('Erro ao criar arquivo CSV de erros.', 'ferramentas-upload'));
        }

        // Adiciona BOM para melhor compatibilidade com Excel em UTF-8
        fwrite($handle, "\xEF\xBB\xBF");

        // Cabeçalho
        $header = array('URL', 'Post ID', 'Post Title', 'Código do Erro', 'Justificativa/Erro');
        fputcsv($handle, $header);

        // Dados
        foreach ($errors as $error) {
            $row = array(
                $error['url'],
                isset($error['post_id']) ? $error['post_id'] : '',
                isset($error['post_title']) ? $error['post_title'] : '',
                isset($error['error_code']) ? $error['error_code'] : 'unknown',
                isset($error['error']) ? $error['error'] : __('Erro desconhecido', 'ferramentas-upload')
            );
            fputcsv($handle, $row);
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
    /**
     * Processa CSV revisado e aplica nos posts
     * Formato esperado: URL, Post ID, Post Title, Question1, Answer1, Question2, Answer2, ...
     */
    public function process_reviewed_csv($csv_file_path) {
        if (!file_exists($csv_file_path)) {
            return new WP_Error('file_not_found', __('Arquivo CSV não encontrado.', 'ferramentas-upload'));
        }

        $handle = fopen($csv_file_path, 'r');
        if ($handle === false) {
            return new WP_Error('file_read_error', __('Erro ao ler arquivo CSV.', 'ferramentas-upload'));
        }

        // Adiciona BOM para melhor compatibilidade com Excel em UTF-8
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // Lê cabeçalho
        $header = fgetcsv($handle);
        
        // Verifica se o cabeçalho tem o formato esperado (mínimo: URL, Post ID, Post Title)
        if (empty($header) || count($header) < 3) {
            fclose($handle);
            return new WP_Error('invalid_format', __('Formato de CSV inválido. Esperado: URL, Post ID, Post Title, Question1, Answer1, Question2, Answer2, ...', 'ferramentas-upload'));
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
            
            if (count($row) < 3) {
                continue; // Linha incompleta, pula
            }
            
            $url = trim($row[0]);
            $post_id = intval($row[1]);
            $post_title = isset($row[2]) ? trim($row[2]) : '';
            
            // Valida dados obrigatórios básicos
            if (empty($url) || $post_id <= 0) {
                continue; // Dados inválidos, pula
            }
            
            // Verifica se o post existe
            $post = get_post($post_id);
            if (!$post) {
                continue; // Post não existe, pula
            }
            
            // Extrai todas as perguntas e respostas da linha
            // Começa do índice 3 (após URL, Post ID, Post Title)
            $faq_items = array();
            for ($i = 3; $i < count($row); $i += 2) {
                $question = isset($row[$i]) ? trim($row[$i]) : '';
                $answer = isset($row[$i + 1]) ? trim($row[$i + 1]) : '';
                
                // Se encontrou uma pergunta e resposta válidas, adiciona
                if (!empty($question) && !empty($answer)) {
                    $faq_items[] = array(
                        'question' => $question,
                        'answer' => $answer
                    );
                }
            }
            
            // Se encontrou pelo menos uma pergunta/resposta, adiciona ao array
            if (!empty($faq_items)) {
                if (!isset($posts_faq[$post_id])) {
                    $posts_faq[$post_id] = array();
                }
                // Adiciona todas as perguntas/respostas encontradas
                $posts_faq[$post_id] = array_merge($posts_faq[$post_id], $faq_items);
            }
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
     * Valida o FAQ extraído para garantir qualidade
     * Verifica: perguntas terminam com '?', idioma preservado, mínimo de perguntas
     */
    private function validate_faq_data($faq_data, $original_content) {
        if (empty($faq_data) || !is_array($faq_data)) {
            return new WP_Error('invalid_faq', __('FAQ vazio ou inválido.', 'ferramentas-upload'));
        }

        // Verifica se há pelo menos 2 perguntas
        if (count($faq_data) < 2) {
            return new WP_Error('insufficient_faq', __('FAQ deve ter no mínimo 2 perguntas. Conteúdo não elegível para FAQ.', 'ferramentas-upload'));
        }

        // Detecta idioma do conteúdo original
        $original_lang = $this->detect_language($original_content);
        
        // Valida cada item do FAQ
        $valid_questions = 0;
        foreach ($faq_data as $item) {
            if (!isset($item['question']) || !isset($item['answer'])) {
                continue;
            }

            $question = trim($item['question']);
            $answer = trim($item['answer']);

            // Verifica se a pergunta termina com '?'
            if (substr($question, -1) !== '?') {
                return new WP_Error('invalid_question_format', sprintf(
                    __('FAQ rejeitado: pergunta não termina com "?". Pergunta encontrada: "%s". Conteúdo não elegível - não há perguntas explícitas no texto.', 'ferramentas-upload'),
                    esc_html(substr($question, 0, 100))
                ));
            }

            // Verifica se o idioma da pergunta corresponde ao conteúdo original
            $question_lang = $this->detect_language($question);
            if ($original_lang !== 'unknown' && $question_lang !== 'unknown' && $original_lang !== $question_lang) {
                return new WP_Error('language_mismatch', sprintf(
                    __('FAQ rejeitado: idioma não preservado. Conteúdo original: %s, FAQ gerado: %s. Pergunta: "%s"', 'ferramentas-upload'),
                    $original_lang,
                    $question_lang,
                    esc_html(substr($question, 0, 100))
                ));
            }

            $valid_questions++;
        }

        // Verifica se há pelo menos 2 perguntas válidas
        if ($valid_questions < 2) {
            return new WP_Error('insufficient_valid_questions', __('FAQ deve ter no mínimo 2 perguntas válidas terminando com "?".', 'ferramentas-upload'));
        }

        return true;
    }

    /**
     * Detecta o idioma de um texto (simples - verifica palavras comuns)
     */
    private function detect_language($text) {
        $text = strtolower($text);
        
        // Palavras comuns em inglês
        $english_words = array('the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from', 'as', 'is', 'are', 'was', 'were', 'be', 'been', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'should', 'could', 'may', 'might', 'must', 'can');
        
        // Palavras comuns em português
        $portuguese_words = array('o', 'a', 'os', 'as', 'um', 'uma', 'uns', 'umas', 'de', 'da', 'do', 'das', 'dos', 'em', 'na', 'no', 'nas', 'nos', 'para', 'por', 'com', 'sem', 'sobre', 'entre', 'até', 'desde', 'que', 'qual', 'quais', 'como', 'quando', 'onde', 'porque', 'porquê', 'é', 'são', 'foi', 'foram', 'ser', 'estar', 'ter', 'haver', 'fazer', 'dizer', 'ir', 'vir', 'ver', 'dar', 'saber', 'poder', 'querer', 'dever');
        
        $english_count = 0;
        $portuguese_count = 0;
        
        foreach ($english_words as $word) {
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/', $text)) {
                $english_count++;
            }
        }
        
        foreach ($portuguese_words as $word) {
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/', $text)) {
                $portuguese_count++;
            }
        }
        
        if ($english_count > $portuguese_count && $english_count > 3) {
            return 'en';
        } elseif ($portuguese_count > $english_count && $portuguese_count > 3) {
            return 'pt-BR';
        }
        
        return 'unknown';
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
