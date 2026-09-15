# Pesquisa de Clima Organizacional

Sistema de pesquisa de clima organizacional, 100% anônimo, com suporte a
múltiplos formulários (campanhas de pesquisa ao longo do tempo).

## Tecnologias

- **Front-end**: HTML, CSS e JavaScript puro (sem frameworks)
- **Back-end**: PHP 8 + PDO
- **Banco de dados**: MySQL/MariaDB
- **Infra**: Docker (nginx + php-fpm + mariadb)

## Estrutura

```
database/
  schema.sql      -> cria o banco e as tabelas
  seed.php        -> popula o gestor de teste e o primeiro formulário
docker/
  php/Dockerfile
  nginx/default.conf
  mariadb_data/   -> dados do banco (criado automaticamente, não versionar)
public/
  index.php       -> front-end (telas de pesquisa, login e dashboard)
  style.css
  script.js       -> toda a lógica de front-end, consome a api.php
  api.php         -> API (rotas por ?action=)
  config.php      -> conexão PDO com o banco
docker-compose.yml
```

## Modelo de dados

- `gestores` — quem acessa o painel administrativo
- `formularios` — cada campanha de pesquisa (só uma fica `ativo` por vez)
- `perguntas` — pertencem a um formulário específico
- `respostas` — um envio de pesquisa, **sem nenhum vínculo com funcionário**
- `resposta_itens` — a nota (0-10) de cada pergunta dentro de uma resposta

O anonimato é estrutural: não existe em nenhuma tabela uma coluna que ligue
uma resposta a uma pessoa. A "taxa de participação" é estimada a partir de
um número de `respondentes_esperados` informado manualmente pelo gestor ao
criar o formulário — nunca a partir de identificação individual.

## Como rodar

```bash
docker compose up -d --build
docker compose exec php php /var/www/database/seed.php
```

Depois acesse **http://localhost:8080**.

## Credenciais de teste (gestor)

```
Email: gestor@escola.com
Senha: 123456
```

## API (public/api.php)

| Ação                          | Método | Autenticado | Descrição                                   |
|-------------------------------|--------|-------------|----------------------------------------------|
| `formulario_ativo`             | GET    | não         | Formulário ativo + suas perguntas            |
| `enviar_resposta`              | POST   | não         | Grava uma resposta anônima                   |
| `login`                        | POST   | não         | Login do gestor                              |
| `logout`                       | GET    | não         | Encerra a sessão do gestor                   |
| `formularios`                  | GET    | sim         | Lista todos os formulários                   |
| `criar_formulario`             | POST   | sim         | Cria um novo formulário (rascunho)           |
| `alterar_status_formulario`    | POST   | sim         | Ativa / encerra / volta pra rascunho         |
| `dashboard`                    | GET    | sim         | Estatísticas agregadas de um formulário      |
| `comentarios`                  | GET    | sim         | Lista de comentários de um formulário        |

## Próximos passos possíveis

- Exportação de relatórios (a aba "Relatórios" ainda é só protótipo)
- HTTPS e variáveis de ambiente via `.env` em produção