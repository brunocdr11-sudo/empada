-- Execute este arquivo no MySQL (por exemplo, pelo phpMyAdmin ou MySQL Workbench).
-- Cria o banco, as tabelas e o usuário solicitado para o trabalho acadêmico.
CREATE DATABASE IF NOT EXISTS empada_camarada
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'admin'@'localhost' IDENTIFIED BY '1234abcd';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX
  ON empada_camarada.* TO 'admin'@'localhost';
FLUSH PRIVILEGES;

USE empada_camarada;

CREATE TABLE IF NOT EXISTS estoque (
  sabor VARCHAR(40) PRIMARY KEY,
  nome_exibicao VARCHAR(80) NOT NULL,
  quantidade TINYINT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT chk_estoque_quantidade CHECK (quantidade BETWEEN 0 AND 50)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pedidos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome_cliente VARCHAR(120) NOT NULL,
  endereco_entrega VARCHAR(500) NOT NULL,
  total DECIMAL(10,2) NOT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pedido_itens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pedido_id BIGINT UNSIGNED NOT NULL,
  sabor VARCHAR(40) NOT NULL,
  quantidade TINYINT UNSIGNED NOT NULL,
  preco_unitario DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_item_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id),
  CONSTRAINT fk_item_sabor FOREIGN KEY (sabor) REFERENCES estoque(sabor),
  CONSTRAINT chk_item_quantidade CHECK (quantidade BETWEEN 1 AND 50)
) ENGINE=InnoDB;

INSERT INTO estoque (sabor, nome_exibicao, quantidade) VALUES
('queijo', 'Queijo', 0),
('frango', 'Frango', 0),
('carne_seca', 'Carne Seca', 0),
('camarao', 'Camarão', 0),
('romeu_julieta', 'Romeu e Julieta', 0),
('chocolate', 'Chocolate', 0)
ON DUPLICATE KEY UPDATE nome_exibicao = VALUES(nome_exibicao);
