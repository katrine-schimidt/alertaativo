<?php
/*
 * Router para o servidor embutido do PHP:
 *   php -S 0.0.0.0:80 -t /var/www/html router.php
 *
 * - GET /                -> site-controle/index.html
 * - /backend/api[/...]   -> backend/api/index.php (GET e POST)
 * - demais arquivos existentes (CSS, JS, imagens, /site-tv/...) sao servidos
 *   normalmente pelo proprio servidor embutido.
 */

$raiz = __DIR__;
$caminho = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

// Pagina de login na raiz
if ($caminho === '/' || $caminho === '') {
    header('Content-Type: text/html; charset=UTF-8');
    readfile($raiz . '/site-controle/index.html');
    return true;
}

// API backend (nao expoe config.php nem outros arquivos da pasta)
if ($caminho === '/backend/api' || strpos($caminho, '/backend/api/') === 0) {
    $_SERVER['SCRIPT_NAME'] = '/backend/api/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $raiz . '/backend/api/index.php';
    chdir($raiz . '/backend/api');
    require $raiz . '/backend/api/index.php';
    return true;
}

// Diretorios com index.html (ex.: /site-tv/ e /site-controle/)
$arquivo = realpath($raiz . $caminho);
if ($arquivo !== false && strpos($arquivo, $raiz) === 0 && is_dir($arquivo)) {
    $index = $arquivo . '/index.html';
    if (is_file($index)) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($index);
        return true;
    }
}

// Arquivos estaticos existentes: deixa o servidor embutido servir
if ($arquivo !== false && strpos($arquivo, $raiz) === 0 && is_file($arquivo)) {
    return false;
}

http_response_code(404);
header('Content-Type: text/plain; charset=UTF-8');
echo 'Not found';
return true;
