# Alerta Ativo

Sistema de gestão de treinamentos com:
- site de controle para gestores/TI;
- painel TV público por empresa;
- API PHP;
- MySQL/MariaDB;
- preparação para integração com ponte da catraca.

## Estrutura

- `site-controle/index.html` — gestão.
- `site-tv/index.html` — painel da TV.
- `backend/api/index.php` — API.
- `backend/api/config.php` — lê variáveis de ambiente.
- `backend/api/instalar.php` — cria a tabela inicial.

## Variáveis de ambiente

Configure no servidor:

`DB_HOST`
`DB_NOME`
`DB_USUARIO`
`DB_SENHA`
`DEV_LOGIN`
`DEV_SENHA`
`PAINEL_SEGREDO`

Nunca publique senhas reais no GitHub.

## Instalação

1. Crie o MySQL.
2. Configure as variáveis acima.
3. Abra `/backend/api/instalar.php` uma única vez.
4. Depois remova `backend/api/instalar.php` do servidor.
5. Entre no site de controle.
6. Cadastre empresa, gestor, colaboradores e treinamentos.
7. Gere o link do Painel TV pelo próprio controle.

## Importante sobre a TV

O painel TV não usa mais `localStorage` para os dados dos treinamentos. Ele consulta a API do servidor usando `empresa` + `chave`, então pode ser aberto em outro computador/TV.

## Ponte da catraca

A interface de controle mantém suporte opcional a uma ponte HTTP em `/api/sync`.
Quando `CATRACA_BRIDGE_URL` e `CATRACA_BRIDGE_TOKEN` estiverem configurados na página, o controle poderá enviar colaboradores, registros e treinamentos para a ponte.
