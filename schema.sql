-- ========================================================
-- SCRIPT DE INICIALIZAÇÃO DO BANCO DE DADOS (MÁQUINA 2)
-- Executar no PostgreSQL da Máquina 2
-- ========================================================



BEGIN;

CREATE TABLE IF NOT EXISTS produtos (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    descricao TEXT,
    preco NUMERIC(10, 2) NOT NULL,
    imagem_url TEXT,
    categoria VARCHAR(40) NOT NULL DEFAULT 'Periféricos',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE produtos
    ADD COLUMN IF NOT EXISTS categoria VARCHAR(40) NOT NULL DEFAULT 'Periféricos';

CREATE TABLE IF NOT EXISTS estoque (
    id SERIAL PRIMARY KEY,
    produto_id INT NOT NULL REFERENCES produtos(id) ON DELETE CASCADE,
    quantidade INT NOT NULL CHECK (quantidade >= 0),
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS pedidos (
    id SERIAL PRIMARY KEY,
    cliente_nome VARCHAR(100) NOT NULL,
    cliente_email VARCHAR(100) NOT NULL,
    valor_total NUMERIC(10, 2) NOT NULL,
    status VARCHAR(50) DEFAULT 'PENDENTE',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS itens_pedido (
    id SERIAL PRIMARY KEY,
    pedido_id INT NOT NULL REFERENCES pedidos(id) ON DELETE CASCADE,
    produto_id INT NOT NULL REFERENCES produtos(id),
    quantidade INT NOT NULL CHECK (quantidade > 0),
    preco_unitario NUMERIC(10, 2) NOT NULL
);

-- Atualiza a categoria dos quatro produtos legados sem depender dos IDs.
UPDATE produtos AS produto
SET categoria = categorias.categoria
FROM (VALUES
    ('Notebook Dell Inspiron', 'Notebooks'),
    ('Smartphone Samsung S23', 'Periféricos'),
    ('Teclado Mecânico RGB', 'Periféricos'),
    ('Monitor Gamer 27 144Hz', 'Periféricos')
) AS categorias(nome, categoria)
WHERE produto.nome = categorias.nome;

-- Catálogo idempotente: executar novamente não duplica produtos já cadastrados.
INSERT INTO produtos (nome, descricao, preco, categoria)
SELECT catalogo.nome, catalogo.descricao, catalogo.preco, catalogo.categoria
FROM (VALUES
    ('Notebook Dell Inspiron', 'Intel i7, 16GB RAM, SSD 512GB', 4500.00, 'Notebooks', 10),
    ('Smartphone Samsung S23', '128GB, tela 6.1 pol., câmera tripla', 3200.00, 'Periféricos', 15),
    ('Teclado Mecânico RGB', 'Switch blue, layout ABNT2', 280.00, 'Periféricos', 50),
    ('Monitor Gamer 27 144Hz', 'Painel IPS, resposta de 1 ms', 1250.00, 'Periféricos', 8),
    ('Processador AMD Ryzen 7 7800X3D', '8 núcleos, arquitetura Zen 4, soquete AM5', 2499.90, 'Componentes', 12),
    ('Placa de vídeo NVIDIA GeForce RTX 4070', '12 GB GDDR6X, ray tracing e DLSS', 4299.90, 'Componentes', 7),
    ('Placa-mãe ASUS TUF Gaming B650', 'DDR5, PCIe 5.0, soquete AM5', 1599.90, 'Componentes', 9),
    ('Memória Corsair Vengeance DDR5 32 GB', 'Kit 2 x 16 GB, 6000 MHz', 899.90, 'Componentes', 18),
    ('Notebook Lenovo LOQ 15', 'Intel Core i5, 16 GB RAM, SSD 512 GB, RTX 4050', 5799.90, 'Notebooks', 6),
    ('Notebook Acer Aspire 5', 'Intel Core i5, 8 GB RAM, SSD 512 GB', 3499.90, 'Notebooks', 11),
    ('Notebook ASUS Vivobook 15', 'AMD Ryzen 7, 16 GB RAM, SSD 512 GB', 4299.90, 'Notebooks', 5),
    ('Mouse Gamer Logitech G502 HERO', 'Sensor HERO 25K, 11 botões programáveis', 349.90, 'Periféricos', 24)
) AS catalogo(nome, descricao, preco, categoria, estoque)
WHERE NOT EXISTS (
    SELECT 1 FROM produtos AS existente WHERE existente.nome = catalogo.nome
);

INSERT INTO estoque (produto_id, quantidade)
SELECT produto.id, catalogo.estoque
FROM (VALUES
    ('Notebook Dell Inspiron', 10),
    ('Smartphone Samsung S23', 15),
    ('Teclado Mecânico RGB', 50),
    ('Monitor Gamer 27 144Hz', 8),
    ('Processador AMD Ryzen 7 7800X3D', 12),
    ('Placa de vídeo NVIDIA GeForce RTX 4070', 7),
    ('Placa-mãe ASUS TUF Gaming B650', 9),
    ('Memória Corsair Vengeance DDR5 32 GB', 18),
    ('Notebook Lenovo LOQ 15', 6),
    ('Notebook Acer Aspire 5', 11),
    ('Notebook ASUS Vivobook 15', 5),
    ('Mouse Gamer Logitech G502 HERO', 24)
) AS catalogo(nome, estoque)
JOIN produtos AS produto ON produto.nome = catalogo.nome
WHERE NOT EXISTS (
    SELECT 1 FROM estoque AS atual WHERE atual.produto_id = produto.id
);

COMMIT;
