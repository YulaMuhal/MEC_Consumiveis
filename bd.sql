-- SIGEC — Sistema Integrado de Gestão de Estoque e Consumíveis
-- Ministério da Educação e Cultura — Moçambique
-- Schema v1.0 MVP

DROP DATABASE IF EXISTS sigec;
CREATE DATABASE IF NOT EXISTS sigec
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE sigec;

-- Papéis / Roles
CREATE TABLE roles (
  id   INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(50) NOT NULL  -- admin, gestor, requisitante
);

INSERT INTO roles (nome) VALUES ('admin'), ('gestor'), ('requisitante');

-- Utilizadores
CREATE TABLE utilizadores (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  nome       VARCHAR(100) NOT NULL,
  email      VARCHAR(100) NOT NULL UNIQUE,
  senha      VARCHAR(255) NOT NULL,          -- bcrypt (não MD5)
  role_id    INT NOT NULL DEFAULT 3,
  unidade    VARCHAR(150),
  estado     ENUM('ativo','inativo') DEFAULT 'ativo',
  criado_em  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id)
);

-- Admin padrão (senha: admin@sigec2026  — bcrypt hash, cost 12)
INSERT INTO utilizadores (nome, email, senha, role_id, unidade) VALUES
('Administrador SIGEC', 'admin@mec.gov.mz',
 '$2y$12$/aRMP7f4/H70fHNMh./2WOLQ5OIiq.N0DhQcieYEygvg5/ot4TN0G',
 1, 'Direcção de TIC');

-- Consumíveis (catálogo)
CREATE TABLE consumiveis (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nome        VARCHAR(100) NOT NULL,
  descricao   TEXT,
  unidade     VARCHAR(20) NOT NULL,   -- unidade, caixa, resma, litro
  codigo      VARCHAR(30) UNIQUE,
  criado_em   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO consumiveis (nome, descricao, unidade, codigo) VALUES
('Papel A4 80g',        'Resma de papel A4 80g/m²',        'Resma',   'PAP-A4-80'),
('Toner HP 26X',        'Cartucho toner HP LaserJet 26X',  'Unidade', 'TON-HP26X'),
('Caneta Esferográfica Azul', 'Caneta BIC azul',           'Caixa',   'CAN-BIC-AZ'),
('Marcador Permanente Preto', 'Marcador PILOT preto',      'Unidade', 'MAR-PIL-PR'),
('Pasta Suspensa A4',   'Pasta suspensa arquivo A4',       'Unidade', 'PAS-SUS-A4'),
('Envelope C4',         'Envelope branco C4 229x324mm',    'Caixa',   'ENV-C4-BR'),
('Fita Adesiva',        'Fita adesiva transparente 18mm',  'Unidade', 'FIT-ADH-18'),
('Grampeador',          'Grampeador de mesa 26/6',         'Unidade', 'GRA-MES-26');

-- Estoque
CREATE TABLE estoque (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  consumivel_id  INT NOT NULL UNIQUE,
  quantidade     INT DEFAULT 0,
  quantidade_minima INT DEFAULT 10,
  atualizado_em  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (consumivel_id) REFERENCES consumiveis(id)
);

INSERT INTO estoque (consumivel_id, quantidade, quantidade_minima) VALUES
(1, 250, 50),(2, 18, 5),(3, 120, 20),(4, 8, 10),
(5, 45, 15),(6, 30, 10),(7, 60, 10),(8, 12, 5);

-- Requisições
CREATE TABLE requisicoes (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  utilizador_id  INT NOT NULL,
  numero         VARCHAR(30) UNIQUE,
  setor          VARCHAR(100),
  justificativa  TEXT,
  estado         ENUM('pendente','realizada','perda') DEFAULT 'pendente',
  observacao     TEXT,
  criado_em      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id)
);

-- Itens de Requisição
CREATE TABLE requisicao_itens (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  requisicao_id  INT NOT NULL,
  consumivel_id  INT NOT NULL,
  quantidade     INT NOT NULL,
  FOREIGN KEY (requisicao_id) REFERENCES requisicoes(id),
  FOREIGN KEY (consumivel_id) REFERENCES consumiveis(id)
);

-- Movimentações de Estoque
CREATE TABLE movimentacoes (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  consumivel_id  INT NOT NULL,
  tipo           ENUM('entrada','saida') NOT NULL,
  quantidade     INT NOT NULL,
  referencia     VARCHAR(150),
  utilizador_id  INT,
  criado_em      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (consumivel_id) REFERENCES consumiveis(id),
  FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id)
);

-- Itens Danificados / Perdas
CREATE TABLE itens_danificados (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  consumivel_id  INT NOT NULL,
  quantidade     INT NOT NULL,
  motivo         TEXT,
  requisicao_id  INT,
  utilizador_id  INT,
  criado_em      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (consumivel_id) REFERENCES consumiveis(id),
  FOREIGN KEY (requisicao_id) REFERENCES requisicoes(id),
  FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id)
);

-- Tokens de reset de palavra-passe
CREATE TABLE password_resets (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  email        VARCHAR(100) NOT NULL,
  token        VARCHAR(64)  NOT NULL UNIQUE,
  expira_em    DATETIME     NOT NULL,
  usado        TINYINT(1)   DEFAULT 0,
  criado_em    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- Logs de auditoria
CREATE TABLE logs (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  utilizador_id  INT,
  acao           TEXT NOT NULL,
  ip             VARCHAR(50),
  criado_em      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id)
);


INSERT INTO utilizadores (nome, email, senha, role_id, unidade)
VALUES (
  'Yula Muhal',
  'yula@mec.gov.mz',
  '$2y$10$WUmjBbJj50AIXQHInVczVuwTpwNZ7BnMBZmKgLHZIXHfK29GqoYG2',
  1,
  'DTIC'
);

