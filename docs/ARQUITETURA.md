# Arquitetura do sistema

> Para o passo a passo da implementação realizada nos dois computadores, consulte [RELATO-IMPLEMENTACAO.md](RELATO-IMPLEMENTACAO.md).

## Visao geral

O sistema divide o processamento em dois nos fisicos:

```text
[Navegador do usuario]
          |
          v
[Maquina 1]
IIS -> PHP -> PDO/PgSQL
          |
          | Rede TCP/IP, porta 5432
          v
[Maquina 2]
PostgreSQL -> dados relacionais
```

A separacao permite observar a dependencia de rede entre a camada de aplicacao e a camada de persistencia.

## Responsabilidades

### Maquina 1: servidor de aplicacao

- Hospedar os arquivos PHP no IIS.
- Receber requisicoes HTTP.
- Executar regras de apresentacao e negocio.
- Consultar o catalogo no PostgreSQL.
- Manter o cache local do catalogo.
- Receber pedidos e encaminha-los ao banco ou a fila local.
- Solicitar a sincronizacao dos pedidos pendentes.

### Maquina 2: servidor de banco

- Executar o PostgreSQL.
- Armazenar produtos, estoque, pedidos e itens.
- Processar comandos SQL enviados pela Maquina 1.
- Retornar dados e resultados das transacoes.

### Cliente

O navegador acessa somente a Maquina 1. O cliente nao acessa diretamente o PostgreSQL.

## Fluxo online

1. O usuario acessa `index.php`.
2. O PHP tenta abrir uma conexao PDO com o PostgreSQL.
3. A consulta busca produtos e estoque.
4. O resultado e exibido e salvo no cache local.
5. No checkout, o pedido e gravado em `pedidos` e seus produtos em `itens_pedido` dentro de uma transacao.

## Fluxo degradado

1. A conexao com a Maquina 2 falha ou expira.
2. `index.php` le `cache/produtos.json`.
3. O catalogo continua disponivel com o ultimo cache valido.
4. `checkout.php` grava o pedido em `queue/pedidos_pending.json`.
5. O usuario recebe o status `PENDENTE_SYNC`.
6. Quando o banco volta, `processQueue()` grava os pedidos em transacoes individuais.
7. Pedidos processados saem da fila; pedidos que falharem permanecem para nova tentativa.

## Modelo de dados

- `produtos`: dados descritivos e preco dos produtos.
- `estoque`: quantidade por produto.
- `pedidos`: cliente, valor total, status e data.
- `itens_pedido`: produtos e quantidades de cada pedido.

`itens_pedido.produto_id` e `estoque.produto_id` referenciam `produtos.id`. A exclusao de um produto remove seu estoque relacionado, conforme o `ON DELETE CASCADE` definido no schema.

## Decisoes e limites

O projeto usa arquivos JSON como mecanismo local simples de cache e fila. Isso e adequado para demonstracao academica, mas exige evolucao para producao: bloqueio de arquivo ou armazenamento transacional, idempotencia, autenticacao, logs estruturados, monitoramento e controle de concorrencia.

A aplicacao atual nao implementa replicacao do PostgreSQL. A Maquina 2 e o armazenamento central, e a fila protege temporariamente os pedidos durante uma indisponibilidade.

## Identificacao das maquinas utilizadas

Na montagem apresentada, a Maquina 1 foi o computador da colega, responsável pelo PHP e pelo servidor web. A Maquina 2 foi o computador do Bruno, responsável pelo PostgreSQL 17. A conexão entre elas ocorreu pela rede local e pela VPN Radmin.

Na configuração distribuída, `DB_HOST` em `config.php` deve conter o IP alcançável da Máquina 2. O valor `127.0.0.1` aponta para a própria Máquina 1 e só deve ser usado quando o PostgreSQL estiver instalado no mesmo computador da aplicação.
