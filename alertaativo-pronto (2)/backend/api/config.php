<?php
declare(strict_types=1);

/*
 * Configuração do Alerta Ativo.
 * Em produção, use variáveis de ambiente (Railway).
 * Nunca coloque senhas reais neste arquivo nem no GitHub.
 */

function envv(string $key, string $default = ''): string {
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
}

define('DB_HOST',    envv('DB_HOST', '127.0.0.1'));
define('DB_NOME',    envv('DB_NOME', envv('MYSQLDATABASE', 'alerta_ativo')));
define('DB_USUARIO', envv('DB_USUARIO', envv('MYSQLUSER', 'root')));
define('DB_SENHA',   envv('DB_SENHA', envv('MYSQLPASSWORD', '')));

define('DEV_LOGIN', envv('DEV_LOGIN', 'dev'));
define('DEV_SENHA', envv('DEV_SENHA', ''));

/* Gere um valor longo e aleatório para produção. */
define('PAINEL_SEGREDO', envv('PAINEL_SEGREDO', ''));

define('MAX_BODY', 5 * 1024 * 1024);

if (DB_HOST === '' || DB_NOME === '' || DB_USUARIO === '' ||
    DEV_SENHA === '' || PAINEL_SEGREDO === '') {
    error_log('Alerta Ativo: variáveis de ambiente obrigatórias não configuradas.');
}
