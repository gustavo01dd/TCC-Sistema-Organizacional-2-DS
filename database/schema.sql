-- ============================================================
-- Pesquisa de Clima Organizacional — Banco de dados definitivo
-- Anônimo, com suporte a múltiplos formulários (pesquisas)
-- ============================================================

CREATE DATABASE IF NOT EXISTS clima_tcc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE clima_tcc;

-- ------------------------------------------------------------
-- gestores: quem acessa o painel administrativo
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS gestores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- formularios: cada edição/campanha da pesquisa de clima.
-- Só um formulário fica com status = 'ativo' por vez — é ele
-- que aparece pro público quando alguém acessa a pesquisa.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS formularios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    descricao VARCHAR(255) NULL,
    status ENUM('rascunho', 'ativo', 'encerrado') NOT NULL DEFAULT 'rascunho',
    respondentes_esperados INT UNSIGNED NULL COMMENT 'opcional, só usado para calcular taxa de participação sem identificar ninguém',
    data_abertura DATETIME NULL,
    data_fechamento DATETIME NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- perguntas: pertencem a um formulário específico
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS perguntas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    formulario_id INT UNSIGNED NOT NULL,
    texto VARCHAR(255) NOT NULL,
    ordem TINYINT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_pergunta_formulario FOREIGN KEY (formulario_id)
        REFERENCES formularios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- respostas: uma linha por envio de pesquisa. SEM vínculo com
-- funcionário nenhum — é isso que garante o anonimato.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS respostas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    formulario_id INT UNSIGNED NOT NULL,
    comentario TEXT NULL,
    criada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_resposta_formulario FOREIGN KEY (formulario_id)
        REFERENCES formularios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- resposta_itens: a nota (0-10) dada em cada pergunta, dentro
-- de uma resposta
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS resposta_itens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resposta_id BIGINT UNSIGNED NOT NULL,
    pergunta_id INT UNSIGNED NOT NULL,
    nota TINYINT UNSIGNED NOT NULL,
    CONSTRAINT fk_item_resposta FOREIGN KEY (resposta_id)
        REFERENCES respostas(id) ON DELETE CASCADE,
    CONSTRAINT fk_item_pergunta FOREIGN KEY (pergunta_id)
        REFERENCES perguntas(id) ON DELETE CASCADE,
    CONSTRAINT chk_nota CHECK (nota BETWEEN 0 AND 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;