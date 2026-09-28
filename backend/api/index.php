<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
date_default_timezone_set('America/Sao_Paulo');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

const COLECOES = ['empresas','gestores','colaboradores','treinamentos','registros',
                  'agendamentos','solicitacoes','alertas','avaliacoes','chamados','duvidas'];

class ErroApi extends Exception {}

function responder($dados, int $codigo = 200): void {
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function erro(string $msg, int $codigo = 400): void { responder(['erro' => $msg], $codigo); }

function pdo(): PDO {
    static $p = null;
    if ($p) return $p;
    try {
        $p = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NOME . ';charset=utf8mb4', DB_USUARIO, DB_SENHA, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable $e) {
        error_log('DB: ' . $e->getMessage());
        erro('Banco de dados indisponível.', 500);
    }
    return $p;
}

function idValido(string $id): bool { return (bool)preg_match('/^[A-Za-z0-9_-]{1,40}$/', $id); }

function corpo(): array {
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') === false) erro('Requisição inválida.', 415);
    $raw = file_get_contents('php://input', false, null, 0, MAX_BODY + 1);
    if ($raw === false || strlen($raw) > MAX_BODY) erro('Dados grandes demais.', 413);
    $d = json_decode($raw, true);
    if (!is_array($d)) erro('JSON inválido.');
    return $d;
}

function chaveEmpresa(string $id): string {
    return substr(hash_hmac('sha256', $id, PAINEL_SEGREDO), 0, 24);
}

function empresaDe(string $col, string $id): ?string {
    $st = pdo()->prepare('SELECT empresa_id FROM dados WHERE colecao=? AND id=?');
    $st->execute([$col, $id]);
    $v = $st->fetchColumn();
    return ($v === false || $v === null) ? null : (string)$v;
}

/* ---------- sessão ---------- */
function iniciarSessao(): void {
    session_name('alerta_ativo');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'httponly' => true,
        'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

function exigirAuth(): array {
    $a = $_SESSION['auth'] ?? null;
    if (!$a) erro('Não autenticado.', 401);
    if ($a['papel'] !== 'dev') {
        $col = $a['papel'] === 'gestor' ? 'gestores' : 'colaboradores';
        $st = pdo()->prepare('SELECT empresa_id FROM dados WHERE colecao=? AND id=?');
        $st->execute([$col, $a['id']]);
        $emp = $st->fetchColumn();
        if ($emp === false) { session_destroy(); erro('Sessão inválida.', 401); }
        $a['empresa'] = $emp === null ? null : (string)$emp;
        $_SESSION['auth']['empresa'] = $a['empresa'];
    }
    return $a;
}

function login(): void {
    $c = corpo();
    $papel = (string)($c['papel'] ?? '');
    $u = trim((string)($c['usuario'] ?? ''));
    $s = (string)($c['senha'] ?? '');
    $ok = false; $id = null; $emp = null;

    if ($papel === 'dev') {
        if (hash_equals(DEV_LOGIN, $u) && hash_equals(DEV_SENHA, $s)) { $ok = true; $id = 'dev'; }
    } elseif ($papel === 'gestor' || $papel === 'colaborador') {
        $col = $papel === 'gestor' ? 'gestores' : 'colaboradores';
        $st = pdo()->prepare('SELECT id, empresa_id, json FROM dados WHERE colecao=? AND chave=? LIMIT 1');
        $st->execute([$col, mb_strtolower($u)]);
        if ($l = $st->fetch()) {
            $j = json_decode($l['json'], true);
            if (is_array($j) && !empty($j['senha']) && password_verify($s, (string)$j['senha'])) {
                $ok = true; $id = $l['id']; $emp = $l['empresa_id'];
            }
        }
    }
    if (!$ok) { usleep(700000); erro('Usuário ou senha incorretos.', 401); }

    session_regenerate_id(true);
    $_SESSION['auth'] = ['papel' => $papel, 'id' => $id, 'empresa' => $emp];
    responder(sessaoResposta(exigirAuth()));
}

function sessaoResposta(array $a): array {
    $chaves = [];
    if ($a['papel'] === 'dev') {
        foreach (pdo()->query("SELECT id FROM dados WHERE colecao='empresas'") as $l) $chaves[$l['id']] = chaveEmpresa($l['id']);
    } elseif ($a['papel'] === 'gestor' && $a['empresa']) {
        $chaves[$a['empresa']] = chaveEmpresa($a['empresa']);
    }
    return ['papel' => $a['papel'], 'usuarioId' => $a['id'], 'estado' => estadoPara($a), 'chaves' => $chaves];
}

/* ---------- leitura ---------- */
function visivel(array $a, string $col, array $it): bool {
    if ($col === 'treinamentos') return in_array($a['empresa'], (array)($it['empresas'] ?? []), true);
    if ($a['papel'] === 'gestor') return true;
    $eu = $a['id'];
    switch ($col) {
        case 'empresas': case 'agendamentos': case 'colaboradores': case 'gestores': return true;
        case 'registros': return ($it['colaboradorId'] ?? null) === $eu;
        default: return (($it['colaboradorId'] ?? $it['autorId'] ?? null) === $eu);
    }
}

function estadoPara(array $a): array {
    $pdo = pdo();
    $est = array_fill_keys(COLECOES, []);
    if ($a['papel'] === 'dev') {
        $st = $pdo->query('SELECT colecao, id, json FROM dados ORDER BY ordem');
    } else {
        $st = $pdo->prepare("SELECT colecao, id, json FROM dados WHERE empresa_id=? OR colecao='treinamentos' ORDER BY ordem");
        $st->execute([$a['empresa']]);
    }
    foreach ($st as $l) {
        $col = $l['colecao'];
        if (!isset($est[$col])) continue;
        $it = json_decode($l['json'], true);
        if (!is_array($it)) continue;
        if ($a['papel'] !== 'dev' && !visivel($a, $col, $it)) continue;
        if ($col === 'gestores' || $col === 'colaboradores') $it['senha'] = '';
        if ($a['papel'] === 'colaborador') {
            if ($col === 'colaboradores' && ($it['id'] ?? '') !== $a['id']) {
                $it = array_intersect_key($it, array_flip(['id','empresaId','nome','funcao','matricula']));
            }
            if ($col === 'gestores') $it = array_intersect_key($it, array_flip(['id','empresaId','nome']));
        }
        $est[$col][] = $it;
    }
    return $est;
}

/* ---------- gravação ---------- */
function empresaDoItem(string $col, array $it): ?string {
    if ($col === 'empresas') return (string)$it['id'];
    if ($col === 'treinamentos') return null;
    $emp = (isset($it['empresaId']) && is_string($it['empresaId']) && $it['empresaId'] !== '') ? $it['empresaId'] : null;
    $ref = null;
    if (!empty($it['colaboradorId']) && is_string($it['colaboradorId'])) {
        $ref = empresaDe('colaboradores', $it['colaboradorId']);
    } elseif (!empty($it['autorId']) && is_string($it['autorId'])) {
        $ref = empresaDe('colaboradores', $it['autorId']) ?? empresaDe('gestores', $it['autorId']);
    }
    if ($emp !== null && $ref !== null && $emp !== $ref) throw new ErroApi('Dados inconsistentes.', 422);
    return $emp ?? $ref;
}

function negar(string $m = 'Sem permissão para esta operação.'): void { throw new ErroApi($m, 403); }

function apagar(array $a, string $col, $id): void {
    if (!is_string($id) || !idValido($id)) return;
    if ($a['papel'] === 'colaborador') negar();
    $st = pdo()->prepare('SELECT empresa_id FROM dados WHERE colecao=? AND id=?');
    $st->execute([$col, $id]);
    $l = $st->fetch();
    if (!$l) return;
    if ($a['papel'] === 'gestor') {
        if ($col === 'empresas' || $col === 'treinamentos' || (string)$l['empresa_id'] !== (string)$a['empresa']) negar('Sem permissão para excluir.');
    }
    pdo()->prepare('DELETE FROM dados WHERE colecao=? AND id=?')->execute([$col, $id]);
}

function gravar(array $a, string $col, $it): void {
    if (!is_array($it) || !isset($it['id']) || !is_string($it['id']) || !idValido($it['id'])) throw new ErroApi('Item inválido.');
    $pdo = pdo();
    $id = $it['id'];
    $st = $pdo->prepare('SELECT empresa_id, json FROM dados WHERE colecao=? AND id=?');
    $st->execute([$col, $id]);
    $ex = $st->fetch();
    $emp = empresaDoItem($col, $it);

    if ($a['papel'] === 'gestor') {
        if ($col === 'treinamentos') negar();
        if ($col === 'empresas') { if ($id !== $a['empresa']) negar(); }
        elseif ($emp !== $a['empresa']) negar();
        if ($ex && (string)$ex['empresa_id'] !== (string)$a['empresa']) negar();
    } elseif ($a['papel'] === 'colaborador') {
        if (!in_array($col, ['duvidas','solicitacoes','avaliacoes'], true)) negar();
        $dono = $it['autorId'] ?? $it['colaboradorId'] ?? null;
        if ($dono !== $a['id'] || $emp !== $a['empresa']) negar();
        if ($ex && (string)$ex['empresa_id'] !== (string)$a['empresa']) negar();
    }

    $chave = null;
    if ($col === 'gestores' || $col === 'colaboradores') {
        $email = mb_strtolower(trim((string)($it['email'] ?? '')));
        $chave = $email !== '' ? $email : null;
        if ($chave !== null) {
            $d = $pdo->prepare('SELECT id FROM dados WHERE colecao=? AND chave=? AND id<>? LIMIT 1');
            $d->execute([$col, $chave, $id]);
            if ($d->fetch()) throw new ErroApi("Já existe um usuário com o e-mail $email.", 409);
        }
        $senha = (string)($it['senha'] ?? '');
        if ($senha === '') {
            $antigo = $ex ? json_decode($ex['json'], true) : null;
            $it['senha'] = is_array($antigo) ? (string)($antigo['senha'] ?? '') : '';
        } elseif (strpos($senha, '$2y$') !== 0) {
            $it['senha'] = password_hash($senha, PASSWORD_DEFAULT);
        }
    }

    $pdo->prepare('INSERT INTO dados (colecao, id, empresa_id, chave, json) VALUES (?,?,?,?,?)
                   ON DUPLICATE KEY UPDATE empresa_id=VALUES(empresa_id), chave=VALUES(chave), json=VALUES(json)')
        ->execute([$col, $id, $emp, $chave, json_encode($it, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
}

function salvar(): void {
    $a = exigirAuth();
    $c = corpo();
    $up = $c['upserts'] ?? [];
    $del = $c['deletes'] ?? [];
    if (!is_array($up) || !is_array($del)) erro('Requisição inválida.');
    $pdo = pdo();
    $pdo->beginTransaction();
    try {
        foreach (COLECOES as $col) foreach ((array)($del[$col] ?? []) as $id) apagar($a, $col, $id);
        foreach (COLECOES as $col) foreach ((array)($up[$col] ?? []) as $it) gravar($a, $col, $it);
        $pdo->commit();
    } catch (ErroApi $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        erro($e->getMessage(), $e->getCode() ?: 400);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('salvar: ' . $e->getMessage());
        erro('Falha ao gravar no banco.', 500);
    }
    responder(['ok' => true]);
}

/* ---------- painel TV (público, somente leitura, sem dados sensíveis) ---------- */
function painel(): void {
    $emp = (string)($_GET['empresa'] ?? '');
    $chave = (string)($_GET['chave'] ?? '');
    if (!idValido($emp) || !hash_equals(chaveEmpresa($emp), $chave)) { usleep(300000); erro('Link do painel inválido.', 403); }
    $pdo = pdo();

    $st = $pdo->prepare("SELECT json FROM dados WHERE colecao='empresas' AND id=?");
    $st->execute([$emp]);
    $j = $st->fetchColumn();
    if ($j === false) erro('Empresa não encontrada.', 404);
    $empresa = json_decode($j, true);

    $colabs = [];
    $st = $pdo->prepare("SELECT id, json FROM dados WHERE colecao='colaboradores' AND empresa_id=?");
    $st->execute([$emp]);
    foreach ($st as $l) { $x = json_decode($l['json'], true); if (is_array($x)) $colabs[$l['id']] = $x; }

    $treinos = [];
    foreach ($pdo->query("SELECT id, json FROM dados WHERE colecao='treinamentos'") as $l) {
        $x = json_decode($l['json'], true); if (is_array($x)) $treinos[$l['id']] = $x;
    }

    $saida = [];
    $st = $pdo->prepare("SELECT json FROM dados WHERE colecao='registros' AND empresa_id=?");
    $st->execute([$emp]);
    foreach ($st as $l) {
        $r = json_decode($l['json'], true);
        if (!is_array($r)) continue;
        $c = $colabs[$r['colaboradorId'] ?? ''] ?? null;
        $t = $treinos[$r['treinamentoId'] ?? ''] ?? null;
        if (!$c || !$t) continue;
        $venc = '';
        if (!empty($r['dataFim']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$r['dataFim'])) {
            $venc = (string)$r['dataFim'];
        } elseif (!empty($r['dataRealizacao']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$r['dataRealizacao'])) {
            $m = (int)($t['validade'] ?? 0); if ($m <= 0) $m = 12;
            $d = new DateTime($r['dataRealizacao'] . ' 00:00:00');
            $d->modify("+$m months");
            $venc = $d->format('Y-m-d');
        }
        if ($venc === '') continue;
        $saida[] = [
            'nome' => (string)($c['nome'] ?? ''), 'cargo' => (string)($c['funcao'] ?? ''),
            'matricula' => (string)($c['matricula'] ?? ''), 'treino' => (string)($t['nome'] ?? ''), 'venc' => $venc,
        ];
    }
    responder(['empresa' => ['id' => $emp, 'nome' => (string)($empresa['nome'] ?? '')],
               'registros' => $saida, 'geradoEm' => date('c')]);
}

/* ---------- roteamento ---------- */
$acao = (string)($_GET['acao'] ?? '');
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($acao === 'painel' && $metodo === 'GET') painel();

iniciarSessao();
try {
    if ($acao === 'login'  && $metodo === 'POST') login();
    if ($acao === 'sessao' && $metodo === 'GET')  responder(sessaoResposta(exigirAuth()));
    if ($acao === 'salvar' && $metodo === 'POST') salvar();
    if ($acao === 'sair'   && $metodo === 'POST') { corpo(); $_SESSION = []; session_destroy(); responder(['ok' => true]); }
} catch (Throwable $e) {
    error_log('api: ' . $e->getMessage());
    erro('Erro interno.', 500);
}
erro('Ação desconhecida.', 404);
