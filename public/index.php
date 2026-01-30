<?php
/**
 * Bootstrap da Aplicação
 * Carrega dependências, configura ambiente e despacha a requisição.
 */

// Define Caminhos Base
define('BASE_PATH', __DIR__ . '/..');
define('VENDOR_PATH', BASE_PATH . '/vendor/autoload.php');
define('REPORTS_DIR', BASE_PATH . '/reports');

// 1. Carregar Autoloader
require_once VENDOR_PATH;

// 2. Carregar Configurações e Ambiente
require_once __DIR__ . '/config.php';

// 3. Carregar Funções Auxiliares
require_once __DIR__ . '/functions.php';

// 4. Carregar Controlador (Processa POST/GET)
require_once __DIR__ . '/controller.php';

// 5. Carregar Visualização (HTML)
require_once __DIR__ . '/view.php';
