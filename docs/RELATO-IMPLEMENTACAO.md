# Relato detalhado da implementação distribuída

## 1. Contexto do trabalho

Este projeto implementa uma plataforma de comércio eletrónico distribuído. A aplicação foi dividida entre dois computadores físicos ligados pela rede local e pela VPN Radmin. A divisão foi feita para demonstrar a separação entre a camada de aplicação e a camada de persistência de dados.

A organização final foi:

```text
Computador da colega - Máquina 1
Servidor de aplicação
PHP + servidor web embutido/IIS
Código do e-commerce, cache e fila local
            |
            | Rede local ou VPN Radmin
            | TCP/IP - porta 5432
            v
Computador do Bruno - Máquina 2
Servidor de banco de dados
PostgreSQL 17
Banco ecommerce e dados relacionais
```

O navegador comunica com a Máquina 1. A Máquina 1 é a única responsável por receber requisições HTTP e, quando necessário, comunica-se com a Máquina 2 através do PostgreSQL. O navegador não acessa diretamente o banco de dados.

## 2. Responsabilidade de cada máquina

### 2.1 Máquina 1: computador da colega

A Máquina 1 hospeda a aplicação PHP e executa as regras do sistema. As suas responsabilidades são:

- disponibilizar o site para o navegador;
- executar `index.php`, `checkout.php` e os demais scripts PHP;
- abrir a conexão remota usando PDO e PostgreSQL;
- consultar o catálogo de produtos;
- atualizar o cache local em `cache/produtos.json`;
- processar o carrinho e o checkout;
- guardar pedidos localmente quando o banco estiver indisponível;
- reenviar os pedidos pendentes quando a conexão retornar.

Os arquivos `cache/` e `queue/` ficam na Máquina 1 porque são mecanismos locais de contingência da aplicação.

### 2.2 Máquina 2: computador do Bruno

A Máquina 2 hospeda o PostgreSQL 17 e funciona como repositório central dos dados. As suas responsabilidades são:

- executar o serviço PostgreSQL;
- aceitar conexões autorizadas da Máquina 1;
- armazenar produtos e estoque;
- armazenar pedidos e itens de pedidos;
- executar consultas SQL e transações;
- preservar os dados mesmo quando a interface web não está aberta.

### 2.3 Navegador do utilizador

O navegador acessa somente o endereço web da Máquina 1. Por motivos de segurança e separação de responsabilidades, o navegador não recebe as credenciais do PostgreSQL e não abre conexões na porta 5432.

## 3. Preparação do banco de dados

Na Máquina 2 foi instalado e utilizado o PostgreSQL 17. Em seguida, foi criado o banco de dados utilizado pelo projeto:

```sql
CREATE DATABASE ecommerce;
```

Depois, o arquivo `schema.sql` foi executado nesse banco. O script cria as tabelas:

- `produtos`: nome, descrição, preço e imagem dos produtos;
- `estoque`: quantidade disponível por produto;
- `pedidos`: cliente, valor total, status e data do pedido;
- `itens_pedido`: produtos e quantidades pertencentes a cada pedido.

O relacionamento entre `produtos` e `estoque` permite consultar o catálogo junto com a quantidade disponível. Os itens de cada pedido referenciam tanto o pedido quanto o produto. O script também insere quatro produtos iniciais para a demonstração.

Exemplo de execução no computador que hospeda o PostgreSQL:

```powershell
psql -U postgres -d ecommerce -f schema.sql
```

A execução foi feita no banco `ecommerce`, e não apenas no banco padrão `postgres`, para que o nome usado pela aplicação coincidisse com o banco criado.

## 4. Configuração da rede

Para que a Máquina 1 alcançasse a Máquina 2, foi necessário identificar o endereço IP que seria usado na comunicação. Como os computadores estavam ligados por rede local e por uma VPN Radmin, o endereço utilizado deve ser o IP da Máquina 2 que seja alcançável pela Máquina 1 dentro dessa rede.

Na Máquina 2, o endereço pode ser consultado com:

```powershell
ipconfig
```

A validação básica a partir da Máquina 1 foi feita com:

```powershell
ping IP_DA_MAQUINA_2
```

O `ping` pode ser bloqueado pelo firewall e, nesse caso, não é uma prova definitiva de que o PostgreSQL está inacessível. O teste mais importante é verificar a porta do banco:

```powershell
Test-NetConnection IP_DA_MAQUINA_2 -Port 5432
```

O resultado esperado é `TcpTestSucceeded : True`. Caso o resultado seja falso, a causa normalmente está em uma destas etapas: endereço IP incorreto, serviço PostgreSQL parado, PostgreSQL escutando somente em localhost ou regra de firewall ausente.

## 5. Configuração do PostgreSQL para aceitar conexões remotas

Por padrão, o PostgreSQL pode aceitar somente conexões locais. Foi necessário alterar a configuração do servidor na Máquina 2 para que ele escutasse a interface de rede utilizada pela VPN ou pela rede local.

### 5.1 `postgresql.conf`

No arquivo `postgresql.conf`, foi ajustada a diretiva:

```conf
listen_addresses = '*'
```

Essa configuração faz o serviço escutar em todas as interfaces disponíveis. Para um ambiente real, é preferível limitar o valor às interfaces necessárias ou ao endereço específico do servidor.

Depois da alteração, o serviço PostgreSQL foi reiniciado para carregar a nova configuração. No Windows, isso pode ser feito em `services.msc`, localizando o serviço `postgresql-x64-17`, ou pelo terminal com privilégios administrativos:

```powershell
Restart-Service postgresql-x64-17
```

### 5.2 `pg_hba.conf`

O arquivo `pg_hba.conf` controla quais clientes podem autenticar-se. Durante a configuração, foi utilizada uma regra para permitir conexões TCP com autenticação SCRAM:

```conf
host    all    all    0.0.0.0/0    scram-sha-256
```

Essa regra foi útil para validar a comunicação, mas `0.0.0.0/0` aceita tentativas vindas de qualquer endereço IPv4 alcançável. A configuração recomendada para a entrega final é restringir a origem ao IP da Máquina 1:

```conf
host    all    all    IP_DA_MAQUINA_1/32    scram-sha-256
```

Se a comunicação ocorrer pelo endereço da VPN, deve ser usado o IP da VPN da Máquina 1, e não necessariamente o IP físico da rede Wi-Fi.

Após modificar o arquivo, o serviço deve ser recarregado ou reiniciado:

```powershell
Restart-Service postgresql-x64-17
```

## 6. Recuperação controlada da credencial do PostgreSQL

Durante a integração ocorreu o erro `password authentication failed`. Isso significa que a rede podia estar funcionando, mas a credencial apresentada pela aplicação não correspondia à credencial do utilizador no PostgreSQL.

A recuperação foi realizada de forma temporária e controlada:

1. O serviço PostgreSQL foi localizado na Máquina 2.
2. O `pg_hba.conf` foi alterado temporariamente para usar `trust` somente numa regra local de emergência.
3. O serviço foi recarregado para aplicar a alteração.
4. Foi aberto o terminal na Máquina 2 e executado o `psql` para entrar no PostgreSQL sem a palavra-passe nessa conexão local.
5. A palavra-passe do utilizador `postgres` foi redefinida com uma instrução `ALTER USER`.
6. O método temporário `trust` foi removido imediatamente.
7. A autenticação voltou a ser `scram-sha-256`.
8. O serviço foi reiniciado ou recarregado novamente.
9. A conexão foi testada a partir da Máquina 1 usando a palavra-passe configurada no PHP.

O comando utilizado para a redefinição segue este formato, sem registrar a credencial real na documentação:

```sql
ALTER USER postgres WITH PASSWORD 'NOVA_SENHA_FORTE';
```

O método `trust` não deve permanecer habilitado. Enquanto ele está ativo, uma conexão que corresponda à regra pode autenticar-se sem apresentar palavra-passe, o que é especialmente perigoso para o utilizador administrador `postgres`.

## 7. Liberação da porta no firewall do Windows

Mesmo com o PostgreSQL configurado, o Windows pode bloquear conexões externas. Na Máquina 2 foi criada uma Regra de Entrada no Windows Defender Firewall para permitir tráfego TCP na porta `5432`.

A regra deve ser limitada ao IP da Máquina 1 sempre que possível. Uma alternativa pelo PowerShell, executada com privilégios administrativos, é:

```powershell
New-NetFirewallRule -DisplayName "PostgreSQL 17 - Máquina 1" -Direction Inbound -Protocol TCP -LocalPort 5432 -RemoteAddress IP_DA_MAQUINA_1 -Action Allow
```

Depois da regra, a Máquina 1 deve repetir:

```powershell
Test-NetConnection IP_DA_MAQUINA_2 -Port 5432
```

Essa validação separa o problema de rede do problema de autenticação. Se a porta estiver acessível, mas o PHP informar falha de autenticação, o próximo ponto a conferir é utilizador, senha, banco e regra no `pg_hba.conf`.

## 8. Configuração do PHP na Máquina 1

Na Máquina 1, o arquivo `config.php` concentra os dados da conexão. O valor `DB_HOST` deve ser alterado de `127.0.0.1` para o endereço remoto da Máquina 2 usado na rede ou VPN:

```php
define('DB_HOST', 'IP_DA_MAQUINA_2');
define('DB_PORT', '5432');
define('DB_NAME', 'ecommerce');
define('DB_USER', 'postgres');
define('DB_PASS', 'SENHA_DO_BANCO');
define('DB_TIMEOUT', 3);
```

`127.0.0.1` representa a própria Máquina 1. Portanto, ele só é correto quando o PostgreSQL está instalado no mesmo computador que executa o PHP. Na configuração distribuída, o endereço precisa apontar para a Máquina 2.

A aplicação usa PDO com o driver `pdo_pgsql`. O timeout de três segundos permite detectar rapidamente a indisponibilidade do servidor remoto, evitando que a página fique aguardando indefinidamente.

Os módulos necessários podem ser verificados com:

```powershell
php -m | Select-String 'PDO|pgsql'
```

A saída deve incluir `PDO`, `pdo_pgsql` e `pgsql`.

A senha real não deve ser publicada no repositório. Para uma versão mais segura, ela deve ser obtida por variável de ambiente ou por arquivo de configuração fora do controle de versão.

## 9. Inicialização da aplicação

Na Máquina 1, o projeto pode ser executado com o servidor embutido do PHP durante a demonstração:

```powershell
.\tools\php\php.exe -S 0.0.0.0:8080 -t .
```

A aplicação deve ser acessada pelo navegador usando o IP da Máquina 1:

```text
http://IP_DA_MAQUINA_1:8080
```

Quando o projeto for hospedado no IIS, o site deve apontar para a pasta do projeto e o PHP deve ser configurado por FastCGI. Em ambos os casos, a Máquina 1 é o servidor web e a Máquina 2 permanece somente como servidor do banco.

## 10. Fluxo normal com o banco online

Quando a Máquina 2 está disponível, o funcionamento é o seguinte:

1. O navegador solicita `index.php` à Máquina 1.
2. `config.php` monta um DSN PostgreSQL usando o endereço remoto da Máquina 2.
3. `getDbConnection()` abre a conexão PDO.
4. `index.php` consulta `produtos` e `estoque` no PostgreSQL.
5. O catálogo é enviado para o navegador.
6. O resultado também é salvo em `cache/produtos.json` na Máquina 1.
7. No checkout, `checkout.php` inicia uma transação no PostgreSQL.
8. O pedido é inserido em `pedidos` e os itens em `itens_pedido`.
9. Se todos os comandos funcionarem, a transação é confirmada com `commit`.
10. O utilizador recebe o status `PAGO`.

O uso de uma transação garante que o pedido e os seus itens sejam gravados juntos. Se uma etapa falhar, o `rollback` desfaz a operação parcial.

## 11. Teste de tolerância a falhas e modo degradado

Para testar a resiliência, o serviço `postgresql-x64-17` foi interrompido manualmente na Máquina 2. Esse procedimento simulou uma falha completa do servidor de banco.

O resultado observado foi:

1. A Máquina 1 tentou abrir a conexão PDO.
2. A conexão falhou ou expirou dentro do timeout configurado.
3. A aplicação capturou a exceção e não apresentou erro HTTP 500 ao utilizador.
4. `index.php` leu o último catálogo válido em `cache/produtos.json`.
5. A interface exibiu o aviso de banco remoto indisponível e o indicador de modo degradado.
6. O utilizador continuou conseguindo visualizar os produtos.
7. Um novo checkout foi salvo em `queue/pedidos_pending.json`.
8. O pedido recebeu o estado `PENDENTE_SYNC`.

Esse comportamento é chamado de degradação graciosa: a aplicação perde temporariamente a persistência central, mas preserva a consulta do catálogo e recebe pedidos através de armazenamento local.

## 12. Sincronização após o retorno do banco

Depois que o serviço PostgreSQL foi iniciado novamente na Máquina 2, a Máquina 1 pôde restabelecer a conexão.

A sincronização ocorre quando:

- o catálogo é aberto e existe conexão com o banco; ou
- o endereço `sync.php` é acessado manualmente.

O processo lê `queue/pedidos_pending.json`, tenta gravar cada pedido em uma transação e remove da fila somente os pedidos processados com sucesso. Caso um pedido falhe, ele permanece no arquivo para uma nova tentativa.

A validação foi feita conferindo:

- o status online na interface;
- o conteúdo atualizado de `cache/produtos.json`;
- as tabelas `pedidos` e `itens_pedido` no PostgreSQL;
- a remoção dos pedidos já sincronizados do arquivo local.

Esse mecanismo é um padrão simples de *store-and-forward*. Ele não é replicação de banco e não substitui mecanismos de produção como filas dedicadas, idempotência, locks, monitoramento e backups.

## 13. Evidências recomendadas para a apresentação

Para demonstrar a implementação, devem ser reunidas as seguintes evidências:

1. Diagrama mostrando a Máquina 1, a Máquina 2 e a porta TCP `5432`.
2. Saída do `Test-NetConnection` com a porta acessível.
3. Configuração do PostgreSQL escutando conexões remotas.
4. Tabelas e produtos criados pelo `schema.sql`.
5. Tela da aplicação com o banco online.
6. Tela da aplicação com o modo degradado ativo.
7. Conteúdo de `cache/produtos.json` durante a indisponibilidade.
8. Conteúdo de `queue/pedidos_pending.json` após um checkout offline.
9. Pedido sincronizado nas tabelas `pedidos` e `itens_pedido`.
10. Resultado dos testes de sintaxe dos arquivos PHP.

## 14. Conclusão

A implementação comprovou a comunicação entre duas máquinas físicas com responsabilidades distintas. A Máquina 1 concentrou a camada web e as regras de negócio, enquanto a Máquina 2 concentrou o PostgreSQL e a persistência relacional.

A configuração de `listen_addresses`, `pg_hba.conf` e do firewall permitiu a comunicação remota. A correção da credencial resolveu o bloqueio de autenticação. Por fim, o cache e a fila local permitiram que o sistema continuasse oferecendo o catálogo e recebendo pedidos durante a queda do banco, com sincronização posterior quando a infraestrutura voltou a ficar disponível.

O resultado demonstra conceitos de arquitetura cliente-servidor, comunicação TCP/IP, separação de responsabilidades, autenticação, tolerância a falhas e recuperação de dados em uma aplicação distribuída.
