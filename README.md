# Sistema Distribuido PHP, IIS e PostgreSQL

Aplicacao web academica para demonstrar uma arquitetura distribuida com separacao entre servidor de aplicacao e servidor de banco de dados.

> **Status do projeto:** a demonstracao local da Maquina 1 esta funcional. O catalogo pode operar a partir do cache local e os pedidos sao armazenados em uma fila quando a Maquina 2 esta indisponivel.

## Equipe

- Carolina Aprigio Brotto - RA G744AB3 - CC8Q12
- Bruno Henrique dos Santos Lima - RA G77GDF0 - CC8Q12
- Daniel Augusto de Amorim Borges - RA N051574 - CC8Q12
- Coordenador: Daniel Augusto de Amorim Borges - daniel0107borges@gmail.com

## Objetivo

Demonstrar, em duas maquinas fisicas, a comunicacao entre uma aplicacao web e um banco de dados remoto:

```text
Usuario/navegador
        |
        v
Maquina 1: IIS + PHP + regras da aplicacao
        |
        | TCP/IP - PostgreSQL:5432
        v
Maquina 2: PostgreSQL + persistencia dos dados
```

A Maquina 1 recebe as requisicoes, apresenta o catalogo, processa o checkout e tenta acessar o banco. A Maquina 2 concentra os dados relacionais e executa as operacoes SQL.

## Funcionalidades atuais

- Exibicao do catalogo de produtos.
- Cache local em `cache/produtos.json`.
- Indicacao visual de banco online ou modo degradado.
- Carrinho armazenado no `localStorage` do navegador.
- Interface responsiva inspirada no prototipo visual do Figma.
- Busca local de produtos e feedback visual ao adicionar itens.
- Checkout direto no PostgreSQL quando a conexao esta disponivel.
- Fila local em `queue/pedidos_pending.json` quando a Maquina 2 esta offline.
- Sincronizacao automatica ao abrir o catalogo com o banco online.
- Sincronizacao manual por `sync.php`.
- Modelo relacional para produtos, estoque, pedidos e itens de pedido.

O schema foi preparado para o dominio de CRUD. Nesta versao, a interface entregue concentra as operacoes de leitura do catalogo e criacao de pedidos; telas de atualizacao e exclusao podem ser adicionadas como evolucao do trabalho.

## Estrutura do repositorio

| Arquivo/pasta | Funcao |
| --- | --- |
| `index.php` | Catalogo, status da conexao, carrinho e entrada para checkout |
| `checkout.php` | Valida e grava o pedido no banco ou na fila local |
| `cache.php` | Leitura e gravacao do cache de produtos |
| `queue.php` | Persistencia e processamento da fila de pedidos |
| `sync.php` | Tentativa manual de sincronizacao |
| `config.php` | Host, porta, banco e caminhos locais |
| `schema.sql` | Criacao das tabelas e dados iniciais |
| `web.config` | Documento padrao e bloqueio HTTP das pastas internas |
| `cache/` | Dados temporarios do catalogo |
| `queue/` | Pedidos pendentes de sincronizacao |
| `tools/php/` | PHP portatil usado no teste local desta maquina |
| `docs/` | Documentacao detalhada do projeto |

## Front-end implementado

O front-end foi reconstruido a partir da referencia visual exportada do Figma, sem depender de uma conta Figma Pro ou de codigo exportado. A implementacao usa HTML semantico, CSS proprio e JavaScript simples, mantendo o backend PHP existente.

- `assets/css/style.css`: identidade visual, grid responsivo, cards, alertas, carrinho e telas de resultado.
- `assets/js/app.js`: carrinho no `localStorage`, quantidades, totais, busca, escape de texto e toast de confirmacao.
- `index.php`: catalogo, status da infraestrutura, fila pendente, busca, carrinho e formulario de checkout.
- `checkout.php`: telas de pedido confirmado e pedido pendente, sem alterar o fluxo de persistencia.

O design usa azul escuro para navegacao e acoes principais, verde para operacao normal, amarelo para contingencia e fundo claro para leitura. O layout foi validado em desktop e em viewport mobile de 390px, sem overflow horizontal.

Os arquivos `Untitled.png` e `Untitled@2x.png` sao apenas referencias locais do prototipo e foram excluidos do versionamento.

## Execucao rapida local

### Requisitos

- Windows 10 ou superior.
- PHP 8.2 ou superior.
- Extensao `pdo_pgsql` habilitada para usar a Maquina 2.
- Navegador web.
- PostgreSQL somente para o modo online.

### Teste sem a Maquina 2

1. Confirme que existem as pastas `cache/` e `queue/`.
2. Confirme que `cache/produtos.json` possui o formato documentado em [docs/INSTALACAO.md](docs/INSTALACAO.md).
3. Inicie o servidor PHP:

```powershell
.\tools\php\php.exe -S 127.0.0.1:8080 -t .
```

4. Abra [http://127.0.0.1:8080](http://127.0.0.1:8080).
5. Adicione um produto, preencha os dados do cliente e finalize a compra.
6. Verifique o pedido em `queue/pedidos_pending.json`.

O banner deve indicar `Modo Degradado (Cache Local M1)` e o checkout deve exibir `PENDENTE_SYNC`.

### Execucao com PostgreSQL

1. Execute [schema.sql](schema.sql) na Maquina 2.
2. Libere a porta TCP `5432` somente para o IP da Maquina 1.
3. Ajuste `DB_HOST`, `DB_USER` e `DB_PASS` em `config.php`.
4. Garanta que `pdo_pgsql` e `pgsql` estejam habilitadas no `php.ini`.
5. Abra a aplicacao e confirme o indicador `DB Online`.
6. Acesse `sync.php` para transferir pedidos pendentes.

Consulte [docs/INSTALACAO.md](docs/INSTALACAO.md) para o procedimento completo e [docs/TESTES.md](docs/TESTES.md) para o roteiro de validacao.

## Tolerancia a falhas

Quando a conexao com o PostgreSQL falha, a aplicacao nao deixa de exibir o catalogo: ela usa o cache local. No checkout, o pedido e convertido em um registro de fila com `local_id` e horario de inclusao. Quando o banco retorna, a fila e processada transacionalmente e somente os pedidos aceitos sao removidos.

Esse comportamento e um padrao simples de **store-and-forward**. Ele reduz a indisponibilidade percebida pelo usuario, mas nao substitui replicacao, observabilidade, autenticacao ou politicas de retry de um ambiente de producao.

## Seguranca e versionamento

- Nao publique senhas reais no GitHub.
- Use variaveis de ambiente ou um arquivo de configuracao fora do repositorio para credenciais.
- Restrinja o PostgreSQL por firewall e `pg_hba.conf`.
- Mantenha `cache/` e `queue/` sem acesso direto via HTTP.
- Em um repositorio publico, avalie ignorar arquivos de dados gerados e pedidos de teste.

## Trabalho academico

Este repositorio materializa a proposta **Desenvolvimento de um Sistema Distribuido**, com PHP e IIS na Maquina 1, PostgreSQL na Maquina 2 e comunicacao via rede TCP/IP.

Detalhes de arquitetura, fluxo de comunicacao e responsabilidades estao em [docs/ARQUITETURA.md](docs/ARQUITETURA.md). O relato completo da montagem realizada entre os dois computadores, incluindo rede, firewall, PostgreSQL, recuperacao de credenciais e teste de falha, esta em [docs/RELATO-IMPLEMENTACAO.md](docs/RELATO-IMPLEMENTACAO.md).

## Referencias

- [Documentacao do PHP](https://www.php.net/docs.php)
- [Documentacao do PostgreSQL](https://www.postgresql.org/docs/)
- [Documentacao do IIS](https://learn.microsoft.com/iis/)
- Coulouris, George et al. *Sistemas Distribuidos: Conceitos e Projeto*. 5. ed. Bookman, 2013.
- Tanenbaum, Andrew S.; Van Steen, Maarten. *Sistemas Distribuidos: Principios e Paradigmas*. 2. ed. Pearson Prentice Hall, 2007.
