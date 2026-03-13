<?php
/**
 * Teste de Envio de Relatório RH (Validar Mock)
 */

define('BASE_PATH', __DIR__);
define('REPORTS_DIR', BASE_PATH . '/reports');
require_once BASE_PATH . '/vendor/autoload.php';

// Carrega .env
$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->load();
$_ENV['APP_ENV'] = 'local';
$_ENV['ADMIN_TOKEN'] = 'test_token';

// Simula GET
$_GET['action'] = 'enviar_relatorio';
$_GET['token'] = 'test_token';

// Mock de Sessão
session_start();

require_once BASE_PATH . '/public/config.php';
require_once BASE_PATH . '/public/functions.php';

// O controller processa o GET e chama enviar_relatorio_rh
ob_start();
require_once BASE_PATH . '/public/controller.php';
$output = ob_get_clean();

echo "Saída do script: " . $output . "
";

$mock_file = BASE_PATH . '/public/email_mock_relatorio.html';
if (file_exists($mock_file)) {
    echo "✅ SUCESSO: Arquivo de mock de relatório criado!
";
    echo "Conteúdo do mock:
";
    echo strip_tags(file_get_contents($mock_file)) . "
";
} else {
    echo "❌ ERRO: Arquivo de mock não foi criado.
";
}
