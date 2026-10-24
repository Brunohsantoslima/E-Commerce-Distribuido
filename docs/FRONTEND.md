# Front-end e integracao visual

## Objetivo

O front-end foi atualizado para aproximar a aplicacao do prototipo criado no Figma, sem substituir a infraestrutura PHP. O resultado preserva o comportamento distribuido e melhora a experiencia de compra em telas grandes e pequenas.

## O que foi implementado

### Navegacao

- Titulo e texto de contexto sobre a operacao distribuida.
- Contagem de produtos e horario do cache quando aplicavel.
- Filtros dinâmicos por categoria, combináveis com a busca, com contagens calculadas a partir do catálogo carregado.
- Categorias visuais para orientar a navegacao.
- Busca local por nome e descricao.
- Cards com nome, descricao, preco e estoque.
- Adicao, remocao e alteracao de quantidade.
- Subtotal, frete e total atualizados no cliente.
- Formulario com os nomes esperados pelo backend: `cliente_nome`, `cliente_email` e `cart_data`.
- Bloqueio do checkout quando nao existem itens.
- Feedback visual ao adicionar um produto.

### Estados distribuido e resiliente

- Banco online: o usuario ve que os dados estao sincronizados.
- Banco indisponivel: o catalogo continua usando `cache/produtos.json`.
- Pedidos pendentes: a interface informa a quantidade na fila e oferece `sync.php`.
- Checkout online: apresenta status `PAGO`.
- Checkout offline: apresenta status `PENDENTE_SYNC`.

## Organizacao tecnica

```text
assets/
├── css/
│   └── style.css
└── js/
    └── app.js
```

O PHP continua responsavel por dados e fluxo de negocio. O CSS controla apresentacao, enquanto o JavaScript controla apenas interacoes do carrinho e da busca.

## Responsividade

O layout possui tres comportamentos principais:

- Desktop: menu completo, filtros ao lado do catalogo e resumo ao lado do carrinho.
- Tablet: grid de produtos com duas colunas.
- Mobile: menu reorganizado, uma coluna de produtos e secoes empilhadas.

Foi feita uma verificacao em viewport de 390px com largura de conteudo igual a largura da viewport, sem overflow horizontal.

## Decisoes de implementacao

- O Bootstrap foi removido da pagina inicial para evitar conflito entre o prototipo e estilos prontos.
- O backend nao foi reescrito.
- Como o cache atual nao possui imagens, produtos sem `imagem_url` recebem uma ilustracao CSS discreta.
- Os textos sobre fila e banco descrevem somente o que a aplicacao implementa: cache local, fila JSON e sincronizacao transacional.
- Nao foram implementados recursos que aparecem apenas como conceito no prototipo, como replicacao multi-master, assinatura digital ou Two-Phase Commit.

## Validacao

```powershell
Get-ChildItem -Filter *.php | ForEach-Object { .\tools\php\php.exe -l $_.FullName }
node --check .\assets\js\app.js
```

Tambem foram validados no servidor local:

- HTTP 200 na pagina inicial.
- Carregamento do produto vindo do cache.
- Exibicao do modo degradado.
- Adicao de produto e atualizacao do total.
- Habilitacao do checkout apos adicionar item.
- Ausencia de overflow horizontal em 390px.