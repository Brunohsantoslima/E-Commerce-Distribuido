# Plano de testes

## Preparacao

- Maquina 1 com PHP funcionando.
- Projeto publicado ou servidor PHP local iniciado.
- Cache preenchido com pelo menos um produto.
- Fila iniciada com `[]`.

## Teste 1: catalogo com banco offline

1. Pare o PostgreSQL ou use um IP temporariamente indisponivel em `config.php`.
2. Abra a pagina inicial.
3. Confirme o banner `Modo Degradado (Cache Local M1)`.
4. Confirme que os produtos de `cache/produtos.json` aparecem.

**Resultado esperado:** a pagina carrega mesmo sem a Maquina 2.

## Teste 2: carrinho

1. Adicione um ou mais produtos.
2. Aumente a quantidade de um item.
3. Remova um item.
4. Confira os subtotais e o total.

**Resultado esperado:** o carrinho e calculado no navegador e o botao de checkout fica habilitado somente quando ha itens.

## Teste 3: checkout offline

1. Com o banco indisponivel, adicione um produto.
2. Preencha nome e e-mail.
3. Clique em `Finalizar Compra`.
4. Abra `queue/pedidos_pending.json`.

**Resultado esperado:** a tela mostra `PENDENTE_SYNC` e o pedido aparece na fila com `local_id`, `queued_at`, itens e valor total.

## Teste 4: catalogo online e atualizacao do cache

1. Ligue o PostgreSQL na Maquina 2.
2. Confira o IP e as credenciais em `config.php`.
3. Abra a pagina inicial.
4. Confirme o indicador de banco online.
5. Confira se `cache/produtos.json` foi atualizado.

**Resultado esperado:** os produtos sao lidos do banco e o cache recebe uma nova lista.

## Teste 5: sincronizacao da fila

1. Mantenha pelo menos um pedido pendente.
2. Com o banco online, abra `sync.php` ou use o botao na pagina inicial.
3. Consulte `pedidos` e `itens_pedido` no PostgreSQL.
4. Verifique novamente `queue/pedidos_pending.json`.

**Resultado esperado:** os pedidos validos sao gravados no banco e removidos da fila.

## Teste 6: falha durante a sincronizacao

1. Coloque na fila um pedido com dados que violem uma restricao do banco.
2. Execute `sync.php`.
3. Verifique a fila.

**Resultado esperado:** o pedido que falhar permanece na fila para nova tentativa e os demais pedidos validos continuam sendo processados.

## Validacao tecnica local

Use os comandos abaixo na raiz do projeto:

```powershell
.\tools\php\php.exe -m | Select-String 'PDO|pgsql'
Get-ChildItem -Filter *.php | ForEach-Object { .\tools\php\php.exe -l $_.FullName }
```

A resposta esperada para cada PHP e `No syntax errors detected`.

## Evidencias para a apresentacao

Registre capturas ou anote os resultados de:

- Catalogo com status online.
- Catalogo com status degradado.
- Checkout com `PENDENTE_SYNC`.
- Conteudo da fila local.
- Pedido sincronizado nas tabelas `pedidos` e `itens_pedido`.
- Diagrama das duas maquinas e do fluxo de comunicacao.
