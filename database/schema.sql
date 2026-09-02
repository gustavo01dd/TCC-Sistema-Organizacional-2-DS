CREATE DATABASE IF NOT EXISTS clima_tcc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE clima_tcc;

CREATE TABLE IF NOT EXISTS gestores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS perguntas (
    id TINYINT UNSIGNED PRIMARY KEY,
    texto VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS respostas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    comentario TEXT NULL,
    criada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS resposta_itens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resposta_id BIGINT UNSIGNED NOT NULL,
    pergunta_id TINYINT UNSIGNED NOT NULL,
    nota TINYINT UNSIGNED NOT NULL,
    CONSTRAINT fk_item_resposta FOREIGN KEY (resposta_id) REFERENCES respostas(id) ON DELETE CASCADE,
    CONSTRAINT fk_item_pergunta FOREIGN KEY (pergunta_id) REFERENCES perguntas(id),
    CONSTRAINT chk_nota CHECK (nota BETWEEN 0 AND 10),
    UNIQUE KEY uq_resposta_pergunta (resposta_id, pergunta_id)
);

INSERT INTO perguntas (id, texto) VALUES
(1, 'O ambiente de trabalho é respeitoso e colaborativo'),
(2, 'A comunicação interna é clara e eficiente'),
(3, 'Tenho os recursos necessários para realizar meu trabalho'),
(4, 'Sinto que meu trabalho é reconhecido'),
(5, 'Tenho boas oportunidades de desenvolvimento'),
(6, 'A liderança está aberta para ouvir os funcionários'),
(7, 'Existe equilíbrio entre trabalho e vida pessoal'),
(8, 'Sinto-me seguro para dar minha opinião'),
(9, 'As decisões da empresa são comunicadas de forma clara'),
(10, 'Eu recomendaria esta empresa como um bom lugar para trabalhar')
ON DUPLICATE KEY UPDATE texto = VALUES(texto);

-- Senha de teste: 123456
-- O hash abaixo é gerado para uso apenas no projeto de TCC.
INSERT INTO gestores (nome, email, senha_hash)
VALUES (
    'Gestor',
    'gestor@escola.com',
    '$2y$12$MN38/tKqe3nloC8DcRmn5OlZB8bgTE/pIpiaPKWHWGniphfxNl7Iu'
)
ON DUPLICATE KEY UPDATE nome = VALUES(nome);
