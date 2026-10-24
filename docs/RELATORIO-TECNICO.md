# Relatório Técnico: TechStore Distribuída

## 1. Objetivo e arquitetura

A TechStore demonstra a separação entre a camada de aplicação e a camada de persistência em uma arquitetura distribuída com duas máquinas.

- **Servidor de aplicação (Máquina 1):** executa PHP/IIS ou o servidor embutido do PHP, recebe requisições HTTP, renderiza o catálogo, processa o carrinho e o checkout e mantém os arquivos locais de cache e fila.
- **Servidor de banco de dados (Máquina 2):** executa PostgreSQL e armazena o catálogo, o estoque, os pedidos e os itens dos pedidos.
- **Navegador:** comunica-se somente com o servidor de aplicação. As credenciais e conexões PostgreSQL permanecem no backend PHP.

No fluxo normal, `index.php` abre uma conexão PDO, consulta produtos e estoque, envia os dados ao navegador e atualiza `cache/produtos.json`. No checkout, o PHP grava pedido e itens no PostgreSQL dentro de uma transação. A conexão entre as máquinas usa TCP/IP na porta 5432 e deve ser permitida apenas para o endereço do servidor de aplicação.

```text
Navegador -> HTTP -> Máquina 1 (PHP/IIS) -> PDO/TCP 5432 -> Máquina 2 (PostgreSQL)
                           |                                      |
                           +-- cache/produtos.json               +-- produtos/estoque
                           +-- queue/pedidos_pending.json        +-- pedidos/itens_pedido
```

## 2. Modelo de dados e catálogo

O schema define as tabelas `produtos`, `estoque`, `pedidos` e `itens_pedido`. A tabela `produtos` contém agora a coluna `categoria`, usada pelos filtros do catálogo. Os dados iniciais incluem 12 produtos variados, distribuídos de forma equilibrada: quatro em **Componentes**, quatro em **Notebooks** e quatro em **Periféricos**.

O `schema.sql` usa criação condicional de tabelas e coluna, e insere produtos por nome somente quando ainda não existem. Assim, pode ser executado novamente sem duplicar o catálogo. Os produtos existentes são mantidos e os quatro itens do seed anterior recebem categoria compatível. O estoque inicial também é inserido apenas quando ainda não há registro para o produto.

A aplicação consulta `p.*` com a quantidade de estoque e grava o resultado completo, incluindo a categoria, no cache JSON. Caches antigos sem categoria continuam legíveis: a camada de cache atribui uma categoria compatível antes de entregá-los à interface.

## 3. Filtros e experiência de uso

A barra lateral oferece **Todos os produtos**, **Componentes**, **Notebooks** e **Periféricos**. Os totais são calculados a partir dos produtos efetivamente carregados pelo banco ou cache. A seleção filtra os cards sem recarregar a página; o campo de busca pode ser combinado com a categoria. A interface atualiza o título, mantém o estado selecionado acessível por `aria-pressed` e apresenta uma mensagem quando nenhum produto corresponde à seleção.

Os cards exibem categoria, descrição, preço e estoque. Os estados de hover, foco e clique tornam as interações mais perceptíveis, inclusive uma confirmação breve no botão de adicionar ao carrinho. O banner de estado do banco (online ou modo degradado) permanece no cabeçalho e no conteúdo informativo.

## 4. Tolerância a falhas e modo degradado

A aplicação tenta conectar ao PostgreSQL por PDO. Se a conexão ou a consulta do catálogo falhar, `index.php` usa `cache/produtos.json`, mantendo o catálogo disponível com os últimos dados salvos. No checkout sem banco, os pedidos são persistidos em `queue/pedidos_pending.json` com estado pendente. Quando o PostgreSQL volta a ficar acessível, a rotina de sincronização reprocessa a fila e remove os pedidos aceitos.

O cache e a fila são mecanismos locais de contingência para fins didáticos; não substituem armazenamento transacional distribuído, replicação, bloqueio concorrente, idempotência ou monitoramento de produção.

## 5. Execução rápida

### Requisitos

- Windows com PHP 8.2 ou superior e extensão `pdo_pgsql` habilitada.
- PostgreSQL com o banco `ecommerce` e acesso ao schema.
- Node.js apenas para validar a sintaxe de `assets/js/app.js`.

### Banco e aplicação

1. No banco `ecommerce`, execute `schema.sql`:

   ```powershell
   psql -U postgres -d ecommerce -f .\schema.sql
   ```

2. Em `config.php`, configure host, porta, banco, usuário e senha. Use `127.0.0.1` somente se PostgreSQL estiver no mesmo computador; em duas máquinas, informe o endereço da Máquina 2 alcançável pela Máquina 1.
3. Garanta que o usuário do processo PHP possa escrever em `cache/` e `queue/`.
4. Na raiz do projeto, inicie o servidor local:

   ```powershell
   .\tools\php\php.exe -S 127.0.0.1:8080 -t .
   ```

5. Acesse `http://127.0.0.1:8080/`. O cabeçalho deve informar **Banco online** e a página deve mostrar 12 produtos.

### Apresentação do modo degradado

Com o cache já preenchido, interrompa temporariamente o acesso ao PostgreSQL (por exemplo, pare o serviço ou bloqueie a porta em ambiente de teste) e recarregue a página. O catálogo deve ser servido pelo JSON e a interface deve indicar **Modo degradado**. Não faça esse teste em uma base compartilhada ou durante operações reais. O checkout de demonstração pode ser validado com um pedido de teste e inspeção da fila local.

## 6. Roteiro de testes

Na raiz do repositório, valide a sintaxe dos arquivos PHP e JavaScript:

```powershell
Get-ChildItem -Filter *.php | ForEach-Object { .\tools\php\php.exe -l $_.FullName }
node --check .\assets\js\app.js
```

Verificações funcionais para apresentação:

1. Confirmar 12 produtos no catálogo e quatro itens em cada categoria.
2. Selecionar cada categoria e conferir que somente os produtos correspondentes permanecem visíveis; selecionar **Todos os produtos** restaura a lista completa.
3. Digitar um termo de busca com uma categoria selecionada e confirmar que ambos os critérios são aplicados.
4. Conferir que os contadores da barra lateral correspondem ao total de cada categoria.
5. Adicionar um item ao carrinho e conferir o feedback do botão, o contador do carrinho e o subtotal.
6. Com o banco acessível, confirmar o estado online; sem acesso ao banco e com cache preenchido, confirmar o modo degradado.

## 7. Segurança e limitações

Não publique credenciais reais nem exponha a porta PostgreSQL à internet. Restrinja `pg_hba.conf` e o firewall ao endereço da Máquina 1. Para produção, mova as credenciais para variáveis de ambiente ou configuração externa ao repositório, proteja os diretórios de cache e fila e adote mecanismos transacionais e de observabilidade apropriados.
