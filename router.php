<?php
// Router para o servidor embutido do PHP.
// Uso: php -S 0.0.0.0:80 -t /var/www/html router.php

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = rawurldecode($path === null || $path === false ? '/' : $path);

// GET / -> página de login (servida como HTML estático, sem executar PHP).
if ($path === '/' || $path === '') {
    $index = __DIR__ . '/site-controle/index.html';
    if (is_file($index)) {
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Length: ' . filesize($index));
        readfile($index);
        return true;
    }
    http_response_code(404);
    echo 'Not found';
    return true;
}

// /backend/api, /backend/api/ e /backend/api/index.php -> API.
// $_GET (ex.: ?acao=...) e $_SERVER são preservados.
$normalized = rtrim($path, '/');
if ($normalized === '/backend/api' || $normalized === '/backend/api/index.php') {
    $api = __DIR__ . '/backend/api/index.php';
    $_SERVER['SCRIPT_NAME'] = '/backend/api/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $api;
    chdir(dirname($api));
    require $api;
    return true;
}

// Demais requisições: arquivos reais (imagens, CSS, JS, PHP) são servidos
// normalmente pelo servidor embutido.
$file = realpath(__DIR__ . $path);
if ($file !== false && strpos($file, __DIR__) === 0 && (is_file($file) || is_dir($file))) {
    return false;
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Not found';
return true;
