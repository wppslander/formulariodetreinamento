<?php
/**
 * CONFIG.PHP
 * Arquivo de configuração global.
 * Define parâmetros de segurança e listas de opções válidas.
 */

// --- 1. Segurança de Sessão (Cookie Hardening) ---
// Configura o cookie para ser acessível apenas via HTTP (não JS) e apenas no domínio correto.
$cookieParams = [
    'lifetime' => 0, // Expira ao fechar o navegador
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // Apenas HTTPS se disponível
    'httponly' => true, // Impede roubo de sessão via XSS (JavaScript)
    'samesite' => 'Strict' // Protege contra CSRF (Cross-Site Request Forgery)
];
session_set_cookie_params($cookieParams);
session_start();

// --- 2. Listas de Whitelist (Opções Permitidas) ---
// O backend só aceita valores que estejam nestas listas.
// Se um atacante tentar enviar 'filial=hacker', será bloqueado.

$filiais_permitidas = [
    'matriz'             => 'Matriz',
    'aptec'              => 'Aptec',
    'blumenau'           => 'Blumenau',
    'itapema'            => 'Itapema',
    'balneario_camboriu' => 'Balneário Camboriú', 
    'itajai'             => 'Itajaí',
    'brusque'            => 'Brusque',
    'joinville'          => 'Joinville',
    'rio_do_sul'         => 'Rio do Sul',
    'gravatai'           => 'Gravataí', 
    'lages'              => 'Lages',
    'sao_jose'           => 'São José',
    'tubarao'            => 'Tubarão'
];

$tipos_permitidos = ['presencial', 'online_vivo', 'online_gravado', 'outro'];

// --- 3. Geração de Token CSRF ---
// Cria um token único para esta sessão, se ainda não existir.
// Este token deve ser enviado em todos os POSTs forms.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}