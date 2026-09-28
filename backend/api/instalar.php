<?php
// Execute UMA vez no navegador: https://SEU-DOMINIO/backend/api/instalar.php
// Para criar também as contas de demonstração: instalar.php?demo=1
// Depois de instalar, APAGUE este arquivo do servidor.
declare(strict_types=1);
require __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');

if (file_exists(__DIR__ . '/.instalado')) { http_response_code(403); exit("Já instalado. Apague este arquivo do servidor.\n"); }

$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NOME . ';charset=utf8mb4', DB_USUARIO, DB_SENHA,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$pdo->exec("CREATE TABLE IF NOT EXISTS dados (
  ordem BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  colecao VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  id VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  empresa_id VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NULL,
  chave VARCHAR(190) NULL,
  json LONGTEXT NOT NULL,
  atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (colecao, id),
  UNIQUE KEY uq_ordem (ordem),
  KEY idx_empresa (empresa_id, colecao),
  KEY idx_chave (colecao, chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "Tabela 'dados' pronta.\n";

if (($_GET['demo'] ?? '') === '1') {
    $novo = fn() => bin2hex(random_bytes(6));
    $ins = $pdo->prepare('INSERT INTO dados (colecao,id,empresa_id,chave,json) VALUES (?,?,?,?,?)');
    $grava = function (string $col, array $it, ?string $emp, ?string $chave = null) use ($ins) {
        $ins->execute([$col, $it['id'], $emp, $chave, json_encode($it, JSON_UNESCAPED_UNICODE)]);
    };
    $e = $novo();
    $grava('empresas', ['id'=>$e,'nome'=>'Empresa Exemplo LTDA','cnpj'=>'12345678000199','telefone'=>'(47) 99999-0000','email'=>'empresa@teste.com.br'], $e);
    $g = $novo();
    $grava('gestores', ['id'=>$g,'nome'=>'Maria Gestora','cpf'=>'98765432100','email'=>'gestor@teste.com.br',
        'senha'=>password_hash('gestor123', PASSWORD_DEFAULT),'telefone'=>'(47) 98888-1111','empresaId'=>$e], $e, 'gestor@teste.com.br');
    $c1 = $novo(); $c2 = $novo();
    $grava('colaboradores', ['id'=>$c1,'empresaId'=>$e,'nome'=>'João da Silva','uid'=>'A1B2C3D4','cpf'=>'11122233344','telefone'=>'(47) 97777-2222',
        'matricula'=>'EMP001','email'=>'joao@empresa.com.br','senha'=>password_hash('colab123', PASSWORD_DEFAULT),'funcao'=>'Técnico de Manutenção'], $e, 'joao@empresa.com.br');
    $grava('colaboradores', ['id'=>$c2,'empresaId'=>$e,'nome'=>'Ana Carolina','uid'=>'11223344','cpf'=>'55566677788','telefone'=>'(47) 96666-3333',
        'matricula'=>'EMP002','email'=>'ana@empresa.com.br','senha'=>password_hash('colab123', PASSWORD_DEFAULT),'funcao'=>'Auxiliar Administrativo'], $e, 'ana@empresa.com.br');
    $t = [$novo(), $novo(), $novo()];
    foreach ([['NR-10 Elétrica',0],['Segurança do Trabalho',1],['Primeiros Socorros',2]] as [$n,$i])
        $grava('treinamentos', ['id'=>$t[$i],'nome'=>$n,'validade'=>12,'empresas'=>[$e]], null);
    foreach ([[$c1,$t[0],'2025-09-10'],[$c1,$t[1],'2025-08-20'],[$c2,$t[2],'2025-10-05']] as [$c,$tr,$d])
        $grava('registros', ['id'=>$novo(),'colaboradorId'=>$c,'treinamentoId'=>$tr,'dataRealizacao'=>$d,
            'dataFim'=>date('Y-m-d', strtotime("$d +12 months"))], $e);
    echo "Contas de demonstração criadas (gestor@teste.com.br / gestor123, joao@empresa.com.br / colab123).\n";
}

file_put_contents(__DIR__ . '/.instalado', date('c'));
echo "Instalação concluída. APAGUE api/instalar.php do servidor.\n";
