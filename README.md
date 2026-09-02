# TCC - Pesquisa de Clima Organizacional + PHP + MariaDB

Esta versão mantém o básico do protótipo do TCC (pesquisa, 10 perguntas, comentário, login de gestor e dashboard) e acrescenta persistência real em MariaDB usando PHP/PDO.

## Estrutura

- `public/index.php` — tela principal do TCC
- `public/script.js` — perguntas e comunicação com o PHP via `fetch`
- `public/style.css` — estilo básico inspirado no protótipo
- `public/api.php` — backend PHP: salvar pesquisa, login, dashboard e logout
- `database/schema.sql` — tabelas e 10 perguntas
- `docker-compose.yml` — Nginx + PHP 8.2 + MariaDB

## Como executar

1. Instale Docker Desktop.
2. Abra o terminal dentro desta pasta.
3. Execute:

```bash
docker compose up -d --build
```

4. Aguarde o MariaDB iniciar e abra:

`http://localhost:8080`

## Login de teste

- Email: `gestor@escola.com`
- Senha: `123456`

Se o login não funcionar na primeira execução, rode:

```bash
docker compose exec php php /seed.php
```

Depois entre novamente.

## Fluxo

1. Funcionário abre a pesquisa.
2. Dá uma nota de 0 a 10 nas 10 perguntas.
3. Opcionalmente informa um comentário.
4. PHP valida e salva uma linha em `respostas` e 10 linhas em `resposta_itens`.
5. Gestor entra pelo login.
6. O dashboard consulta o banco e mostra média geral, total de respostas e pontos críticos/fortes.

## Observação sobre anonimato

O banco não grava nome, email ou usuário do respondente. A resposta é armazenada somente com as notas, comentário e data/hora. O login existe apenas para o gestor acessar o dashboard.

A "taxa de participação" está como indicador simples (100% quando existe pelo menos uma resposta), porque o protótipo não possui uma tabela de funcionários. Para uma versão posterior, pode-se criar uma tabela de funcionários/convites e calcular a taxa de forma real.
