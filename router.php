<?php
// Roteador para o servidor embutido do PHP (php -S ... router.php).

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) ? rawurldecode($path) : '/';

// Bloqueia tentativas de path traversal.
if (strpos($path, "\0") !== false || strpos($path, '..') !== false) {
    http_response_code(400);
    exit;
}

// Raiz: serve a página de login do site de controle.
if ($path === '/' || $path === '/index.html') {
    header('Content-Type: text/html; charset=UTF-8');
    readfile(__DIR__ . '/site-controle/index.html');
    return true;
}

// API: /backend/api e /backend/api/...
if ($path === '/backend/api' || strpos($path, '/backend/api/') === 0) {
    // Todas as rotas da API passam pelo index.php (config.php nunca é executado diretamente).
    chdir(__DIR__ . '/backend/api');
    $_SERVER['SCRIPT_NAME'] = '/backend/api/index.php';
    $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/backend/api/index.php';
    require __DIR__ . '/backend/api/index.php';
    return true;
}

// Demais caminhos: arquivos estáticos existentes são servidos normalmente.
return false;
