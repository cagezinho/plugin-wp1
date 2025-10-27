<?php
/**
 * Arquivo de debug temporário para identificar problemas
 * Remova este arquivo após resolver os problemas
 */

if (!defined('ABSPATH')) {
    exit;
}

// Função para log de debug
function fu_debug_log($message) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Ferramentas Upload Debug] ' . $message);
    }
}

// Hook para capturar erros
add_action('admin_notices', function() {
    if (isset($_POST[FU_PREFIX . 'action']) && $_POST[FU_PREFIX . 'action'] === 'update_alt_text') {
        fu_debug_log('Tentativa de upload de alt text detectada');
        
        // Verifica se as constantes estão definidas
        fu_debug_log('FU_TEXT_DOMAIN definida: ' . (defined('FU_TEXT_DOMAIN') ? 'sim' : 'não'));
        fu_debug_log('FU_PREFIX definida: ' . (defined('FU_PREFIX') ? 'sim' : 'não'));
        
        // Verifica se as funções estão disponíveis
        fu_debug_log('attachment_url_to_postid disponível: ' . (function_exists('attachment_url_to_postid') ? 'sim' : 'não'));
        fu_debug_log('mb_check_encoding disponível: ' . (function_exists('mb_check_encoding') ? 'sim' : 'não'));
        fu_debug_log('finfo_open disponível: ' . (function_exists('finfo_open') ? 'sim' : 'não'));
        
        // Verifica se o arquivo foi enviado
        if (isset($_FILES['fu_alt_csv_file'])) {
            fu_debug_log('Arquivo enviado: ' . $_FILES['fu_alt_csv_file']['name']);
            fu_debug_log('Tamanho do arquivo: ' . $_FILES['fu_alt_csv_file']['size']);
            fu_debug_log('Erro do upload: ' . $_FILES['fu_alt_csv_file']['error']);
        } else {
            fu_debug_log('Nenhum arquivo enviado');
        }
    }
});

// Hook para capturar erros fatais
add_action('shutdown', function() {
    $error = error_get_last();
    if ($error && $error['type'] === E_ERROR) {
        fu_debug_log('Erro fatal detectado: ' . $error['message']);
        fu_debug_log('Arquivo: ' . $error['file']);
        fu_debug_log('Linha: ' . $error['line']);
    }
}); 