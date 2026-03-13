<?php
/**
 * Bootstrap da Aplicação
 * Carrega dependências, configura ambiente e despacha a requisição.
 */

// Define Caminhos Base
define('BASE_PATH', dirname(__DIR__));
define('VENDOR_PATH', BASE_PATH . '/vendor/autoload.php');
define('REPORTS_DIR', BASE_PATH . '/reports');

// Detecta a URL base para links relativos (importante se estiver em subpasta)
$base_url = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
define('BASE_URL', $base_url);

// 1. Carregar Autoloader
require_once VENDOR_PATH;

// 1.1 Carregar Variáveis de Ambiente (.env)
if (file_exists(BASE_PATH . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
    $dotenv->safeLoad();
}

// 2. Carregar Configurações e Ambiente
require_once __DIR__ . '/config.php';

// 3. Carregar Funções Auxiliares
require_once __DIR__ . '/functions.php';

// 4. Carregar Controlador (Processa POST/GET)
require_once __DIR__ . '/controller.php';

// 5. Carregar Visualização (HTML)
require_once __DIR__ . '/view.php';
