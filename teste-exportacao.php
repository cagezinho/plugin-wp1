<?php
/**
 * Arquivo de teste para verificar se a exportação está funcionando
 * Acesse: /wp-admin/admin.php?page=ferramentas-upload&test_export=1
 */

if (!defined('ABSPATH')) {
    exit;
}

// Hook para adicionar teste de exportação
add_action('admin_init', function() {
    if (isset($_GET['test_export']) && $_GET['test_export'] == '1' && current_user_can('manage_options')) {
        // Força a recarga do arquivo
        if (file_exists(FU_PLUGIN_PATH . 'includes/class-post-exporter.php')) {
            require_once FU_PLUGIN_PATH . 'includes/class-post-exporter.php';
            
            echo '<h2>Teste de Exportação</h2>';
            echo '<p>Testando se o arquivo foi atualizado...</p>';
            
            // Testa se a classe tem os métodos corretos
            $exporter = new Ferramentas_Upload_Post_Exporter();
            
            // Verifica se o método existe
            if (method_exists($exporter, 'export_posts_categories')) {
                echo '<p style="color: green;">✅ Classe carregada corretamente</p>';
                
                // Testa o cabeçalho
                $output = fopen('php://temp', 'w');
                $reflection = new ReflectionClass($exporter);
                $method = $reflection->getMethod('write_csv_header');
                $method->setAccessible(true);
                $method->invoke($exporter, $output);
                
                rewind($output);
                $content = stream_get_contents($output);
                fclose($output);
                
                echo '<p><strong>Conteúdo do cabeçalho:</strong></p>';
                echo '<pre>' . htmlspecialchars($content) . '</pre>';
                
                // Conta as colunas
                $lines = explode("\n", trim($content));
                $columns = str_getcsv($lines[0]);
                echo '<p><strong>Número de colunas:</strong> ' . count($columns) . '</p>';
                
                if (count($columns) >= 6) {
                    echo '<p style="color: green;">✅ Cabeçalho com 6 colunas detectado!</p>';
                } else {
                    echo '<p style="color: red;">❌ Cabeçalho com apenas ' . count($columns) . ' colunas</p>';
                }
                
            } else {
                echo '<p style="color: red;">❌ Método não encontrado</p>';
            }
        } else {
            echo '<p style="color: red;">❌ Arquivo não encontrado</p>';
        }
        
        echo '<p><a href="' . admin_url('admin.php?page=ferramentas-upload') . '">← Voltar para o plugin</a></p>';
        exit;
    }
});
