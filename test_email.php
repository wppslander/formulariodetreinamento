<?php
/**
 * Teste de Envio de E-mail (Validar Mock e Lógica)
 */

// Simula ambiente
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

// Define caminhos
define('BASE_PATH', __DIR__);
define('REPORTS_DIR', BASE_PATH . '/reports');
require_once BASE_PATH . '/vendor/autoload.php';

// Carrega .env para testar (mas vamos forçar APP_ENV=local para o mock)
$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->load();
$_ENV['APP_ENV'] = 'local'; // Força local para teste

// Mock de Sessão
session_start();
$_SESSION['csrf_token'] = 'test_token';

// Dados do POST
$_POST = [
    'csrf_token' => 'test_token',
    'email' => 'teste@exemplo.com',
    'nome' => 'Desenvolvedor de Teste',
    'filial' => 'matriz',
    'departamento' => 'Engenharia',
    'curso' => 'PHP Avançado',
    'tipo_treinamento' => 'online_vivo',
    'duracao_horas' => 2,
    'duracao_minutos' => 30
];

// Mock de $_FILES (vazio)
$_FILES = [];

// Inclui os arquivos do app (eles vão processar o POST)
require_once BASE_PATH . '/public/config.php';
require_once BASE_PATH . '/public/functions.php';

// Captura a saída do controller (se houver)
ob_start();
require_once BASE_PATH . '/public/controller.php';
$output = ob_get_clean();

echo "Mensagem do Controller: " . strip_tags($message) . "
";

// Valida se o arquivo mock foi criado
$mock_file = BASE_PATH . '/public/email_mock.html';
if (file_exists($mock_file)) {
    echo "✅ SUCESSO: Arquivo de mock de e-mail criado!
";
    echo "Conteúdo do mock:
";
    echo strip_tags(file_get_contents($mock_file)) . "
";
} else {
    echo "❌ ERRO: Arquivo de mock não foi criado.
";
}
