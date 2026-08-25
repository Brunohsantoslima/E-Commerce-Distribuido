-- ========================================================
-- SCRIPT DE INICIALIZAÇÃO DO BANCO DE DADOS (MÁQUINA 2)
-- Executar no PostgreSQL da Máquina 2
-- ========================================================

CREATE DATABASE ecommerce;
\c ecommerce;

CREATE TABLE produtos (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    descricao TEXT,
    preco NUMERIC(10, 2) NOT NULL,
    imagem_url TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE estoque (
    id SERIAL PRIMARY KEY,
    produto_id INT NOT NULL REFERENCES produtos(id) ON DELETE CASCADE,
    quantidade INT NOT NULL CHECK (quantidade >= 0),
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE pedidos (
    id SERIAL PRIMARY KEY,
    cliente_nome VARCHAR(100) NOT NULL,
    cliente_email VARCHAR(100) NOT NULL,
    valor_total NUMERIC(10, 2) NOT NULL,
    status VARCHAR(50) DEFAULT 'PENDENTE',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE itens_pedido (
    id SERIAL PRIMARY KEY,
    pedido_id INT NOT NULL REFERENCES pedidos(id) ON DELETE CASCADE,
    produto_id INT NOT NULL REFERENCES produtos(id),
    quantidade INT NOT NULL CHECK (quantidade > 0),
    preco_unitario NUMERIC(10, 2) NOT NULL
);

-- Dados Iniciais para Demonstração
INSERT INTO produtos (nome, descricao, preco) VALUES
('Notebook Dell Inspiron', 'Intel i7, 16GB RAM, SSD 512GB', 4500.00),
('Smartphone Samsung S23', '128GB, Tela 6.1, Câmera Tripla', 3200.00),
('Teclado Mecânico RGB', 'Switch Blue, Layout ABNT2', 280.00),
('Monitor Gamer 27 144Hz', 'Painel IPS, 1ms resposta', 1250.00);

INSERT INTO estoque (produto_id, quantidade) VALUES
(1, 10),
(2, 15),
(3, 50),
(4, 8);
