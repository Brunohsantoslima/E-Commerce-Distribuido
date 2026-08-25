# Instalacao e configuracao

## 1. Maquina 1: PHP

Instale PHP para Windows com suporte ao PostgreSQL. Para IIS, prefira uma distribuicao Thread Safe compativel com FastCGI.

Verifique a instalacao:

```powershell
php -v
php -m | Select-String 'PDO|pgsql'
```

Os modulos esperados sao `PDO`, `pdo_pgsql` e `pgsql`.

No ambiente de demonstracao deste repositorio, o PHP portatil fica em `tools/php/php.exe` e pode ser executado assim:

```powershell
.\tools\php\php.exe -v
```

## 2. Diretorios locais

A aplicacao precisa conseguir ler e escrever nestes caminhos:

```text
cache/produtos.json
queue/pedidos_pending.json
```

Conteudo inicial do cache:

```json
{
  "timestamp": "2026-08-25 10:00:00",
  "data": [
    {
      "id": 1,
      "nome": "Notebook Teste M1",
      "descricao": "Item salvo em cache local",
      "preco": 3500.00,
      "estoque": 5
    }
  ]
}
```

Conteudo inicial da fila:

```json
[]
```

No IIS, conceda permissao de leitura e escrita ao usuario do pool, normalmente `IIS_IUSRS` ou `IUSR`, somente para essas pastas.

## 3. Maquina 2: PostgreSQL

1. Instale o PostgreSQL.
2. Crie o banco `ecommerce`.
3. Execute o [schema.sql](../schema.sql) no banco.
4. Confirme que o servidor escuta na rede, e nao somente em `localhost`.
5. Configure `pg_hba.conf` para aceitar apenas o IP da Maquina 1.
6. Libere a porta `5432` no firewall para esse mesmo IP.

Exemplo usando `psql`:

```powershell
psql -U postgres -f schema.sql
```

O script cria as tabelas e insere quatro produtos de demonstracao.

## 4. Configuracao da aplicacao

Edite `config.php` na Maquina 1:

```php
define('DB_HOST', 'IP_DA_MAQUINA_2');
define('DB_PORT', '5432');
define('DB_NAME', 'ecommerce');
define('DB_USER', 'postgres');
define('DB_PASS', 'SENHA_DO_BANCO');
```

Nao versione uma senha real no GitHub. Em uma versao de producao, mova esses valores para variaveis de ambiente.

## 5. Teste local sem IIS

Na raiz do projeto:

```powershell
.\tools\php\php.exe -S 127.0.0.1:8080 -t .
```

Abra `http://127.0.0.1:8080`.

Esse servidor e apropriado para desenvolvimento e demonstracao local. Nao deve ser usado como servidor de producao.

## 6. Hospedagem no IIS

1. Ative o IIS e o recurso CGI/FastCGI no Windows.
2. Instale PHP para Windows.
3. Crie um site apontando para a raiz deste projeto.
4. Configure o executavel `php-cgi.exe` como handler FastCGI.
5. Defina `index.php` como documento padrao.
6. Mantenha o `web.config` no diretorio publicado.
7. Dê permissao de escrita nas pastas `cache` e `queue`.
8. Teste abrindo o endereco configurado no IIS.

O `web.config` tambem impede o acesso HTTP direto as pastas de cache e fila.

## 7. Diagnostico rapido

- Banner degradado: confira IP, firewall, `pg_hba.conf`, porta 5432 e `pdo_pgsql`.
- Catalogo vazio: valide o formato e a permissao de leitura de `cache/produtos.json`.
- Pedido nao salvo: valide a permissao de escrita em `queue`.
- Erro 500 no IIS: confira o handler FastCGI, o caminho do `php-cgi.exe` e os logs do IIS.
- Timeout: `DB_TIMEOUT` esta definido como 3 segundos para detectar indisponibilidade rapidamente.
