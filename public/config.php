<?php
// Segurança de Sessão (Cookie Hardening)
$cookieParams = [
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Strict'
];
session_set_cookie_params($cookieParams);
session_start();

// Configurações de Whitelist
$filiais_permitidas = [
    'matriz', 'aptec', 'blumenau', 'itapema', 'balneario_camboriu', 
    'itajai', 'brusque', 'joinville', 'rio_do_sul', 'gravatai', 
    'lages', 'sao_jose', 'tubarao'
];

$tipos_permitidos = ['presencial', 'online_vivo', 'online_gravado', 'outro'];

// Gerar Token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
