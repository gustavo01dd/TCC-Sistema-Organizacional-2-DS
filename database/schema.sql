-- ============================================================
-- Pesquisa de Clima Organizacional - banco de dados
-- ------------------------------------------------------------
-- Regra central de anonimato (RN05, RNF06, RN07):
-- o sistema sabe QUEM já respondeu (tabela controle_acesso),
-- mas nunca O QUE cada pessoa respondeu. As tabelas respostas e
-- resposta_itens não têm nenhuma coluna que aponte para o funcionário.
-- ============================================================

CREATE DATABASE IF NOT EXISTS clima_tcc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE clima_tcc;

-- ------------------------------------------------------------
-- funcionarios: todos os usuários do sistema (RF01)
-- tipo_perfil decide para onde o login leva:
--   funcionario = tela da pesquisa
--   gestor      = painel de gestão
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS funcionarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    cargo VARCHAR(50) NULL,
    data_admissao DATE NULL,
    tipo_perfil ENUM('funcionario', 'gestor') NOT NULL DEFAULT 'funcionario',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    termo_versao INT UNSIGNED NULL,
    termo_aceito_em DATETIME NULL,
    senha_alterada_em DATETIME NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- formularios: cada edição da pesquisa de clima (RF02)
-- Só um formulário fica com status ativo por vez.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS formularios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    descricao VARCHAR(255) NULL,
    status ENUM('rascunho', 'ativo', 'encerrado') NOT NULL DEFAULT 'rascunho',
    respondentes_esperados INT UNSIGNED NULL,
    data_abertura DATETIME NULL,
    data_fechamento DATETIME NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- controle_acesso: registra QUE um funcionário respondeu um
-- formulário (RN02, RN03, RF09). Não guarda nada do conteúdo.
-- Diferente do MER, não tem id sequencial e guarda só a DATA
-- (sem hora): assim não dá para cruzar a ordem ou o horário de
-- quem respondeu com a ordem ou o horário das respostas anônimas.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS controle_acesso (
    funcionario_id INT UNSIGNED NOT NULL,
    formulario_id INT UNSIGNED NOT NULL,
    respondeu TINYINT(1) NOT NULL DEFAULT 1,
    data_resposta DATE NOT NULL,
    PRIMARY KEY (funcionario_id, formulario_id),
    CONSTRAINT fk_acesso_funcionario FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_acesso_formulario FOREIGN KEY (formulario_id) REFERENCES formularios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- perguntas: pertencem a um formulário
-- tipo: nota (0 a 10), sim_nao ou multipla (uma opção entre várias)
-- opcoes: lista das alternativas em JSON (só para multipla)
-- categoria: agrupa perguntas (ex.: Liderança, Comunicação)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS perguntas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    formulario_id INT UNSIGNED NOT NULL,
    texto VARCHAR(255) NOT NULL,
    tipo ENUM('nota', 'sim_nao', 'multipla') NOT NULL DEFAULT 'nota',
    opcoes TEXT NULL,
    categoria VARCHAR(60) NULL,
    ordem TINYINT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_pergunta_formulario FOREIGN KEY (formulario_id) REFERENCES formularios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- respostas: um envio de pesquisa. SEM nenhum vínculo com o
-- funcionário: o conteúdo é anônimo mesmo com login (RN05).
-- O comentário fica criptografado (AES-256-GCM, RNF05).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS respostas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    formulario_id INT UNSIGNED NOT NULL,
    comentario TEXT NULL,
    criada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_resposta_formulario FOREIGN KEY (formulario_id) REFERENCES formularios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- resposta_itens: a resposta de cada pergunta em um envio
--   nota: perguntas do tipo nota (0 a 10)
--   opcao: sim_nao (0 = não, 1 = sim) ou multipla (posição da alternativa)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS resposta_itens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resposta_id BIGINT UNSIGNED NOT NULL,
    pergunta_id INT UNSIGNED NOT NULL,
    nota TINYINT UNSIGNED NULL,
    opcao TINYINT UNSIGNED NULL,
    CONSTRAINT fk_item_resposta FOREIGN KEY (resposta_id) REFERENCES respostas(id) ON DELETE CASCADE,
    CONSTRAINT fk_item_pergunta FOREIGN KEY (pergunta_id) REFERENCES perguntas(id) ON DELETE CASCADE,
    CONSTRAINT chk_nota CHECK (nota BETWEEN 0 AND 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- relatorios: relatório consolidado gerado automaticamente
-- quando a pesquisa é encerrada (RN08, RF06). Guardado criptografado.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS relatorios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    formulario_id INT UNSIGNED NOT NULL UNIQUE,
    data_geracao DATETIME NOT NULL,
    dados_consolidados LONGTEXT NOT NULL,
    CONSTRAINT fk_relatorio_formulario FOREIGN KEY (formulario_id) REFERENCES formularios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- logs_acesso: entradas, saídas e tentativas recusadas de
-- login dos gestores (RF12)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS logs_acesso (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NULL,
    email VARCHAR(100) NOT NULL,
    acao VARCHAR(20) NOT NULL,
    data_hora DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- notificacoes: e-mails de abertura, encerramento e lembrete
-- já enviados (RF08). A chave única impede envio duplicado.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notificacoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    formulario_id INT UNSIGNED NOT NULL,
    tipo VARCHAR(20) NOT NULL,
    chave VARCHAR(100) NOT NULL UNIQUE,
    enviada_em DATETIME NOT NULL,
    destinatarios INT UNSIGNED NOT NULL DEFAULT 0,
    falhas INT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_notificacao_formulario FOREIGN KEY (formulario_id) REFERENCES formularios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- redefinicoes_senha: links de "Esqueci minha senha".
-- Guarda só o hash do código do link: quem ler o banco não
-- consegue usar o link. Cada link vale por pouco tempo e uma vez só.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS redefinicoes_senha (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    funcionario_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    criado_em DATETIME NOT NULL,
    expira_em DATETIME NOT NULL,
    usado_em DATETIME NULL,
    INDEX idx_redefinicao_funcionario (funcionario_id, criado_em),
    CONSTRAINT fk_redefinicao_funcionario FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- tentativas: logins com senha errada e pedidos de redefinição,
-- usados para bloquear quem tenta adivinhar senhas.
-- A chave é um hash (não guarda o email nem o IP legíveis) e
-- os registros são apagados depois de 1 dia.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tentativas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chave CHAR(64) NOT NULL,
    criada_em DATETIME NOT NULL,
    INDEX idx_tentativa_chave (chave, criada_em),
    INDEX idx_tentativa_data (criada_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;