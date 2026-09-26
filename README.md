# Climatize: Pesquisa de Clima Organizacional (TCC)

Sistema web para aplicar pesquisas de clima organizacional com **respostas anônimas**.
Os funcionários entram com email e senha só para o sistema controlar quem já participou;
o conteúdo das respostas nunca fica ligado à pessoa. Os gestores acompanham os resultados
em um painel com dashboard, resultados por pergunta, comentários, cadastro de funcionários
e relatórios. O visual usa fundos em azul e tem **modo escuro**, ligado pelo botão ☾ no topo
das telas ou pelo item "Modo escuro" no menu do painel.

## Tecnologias

**Arquitetura:** aplicação web cliente-servidor. O front-end (HTML, CSS e JavaScript) se comunica
por requisições HTTP, com dados em JSON, com uma API em PHP que grava em um banco MariaDB.
Tudo roda em containers Docker, com o Nginx como servidor web.

### Linguagens de programação

- **PHP 8.2**: back-end completo. API (`api.php`), regras de negócio (`funcoes.php`), login, sessões e relatório em PDF.
- **JavaScript (ES6+, puro, sem frameworks)**: troca de telas, chamadas à API com `fetch` e `async/await`, gráficos e exportações.
- **SQL**: criação do banco (`schema.sql`) e todas as consultas (médias, contagens, filtros por período, controle de quem respondeu).

### Linguagens de marcação e estilo

- **HTML5**: estrutura de todas as telas (`index.php` e `relatorio_impressao.php`).
- **CSS3**: visual, responsividade e tema claro/escuro, com variáveis CSS (custom properties), Flexbox, Grid, media queries e `conic-gradient` no gráfico de rosca.

### Formatos e arquivos de configuração

- **JSON**: formato dos dados trocados entre o JavaScript e a API.
- **YAML**: `docker-compose.yml`.
- **Dockerfile**: montagem da imagem do PHP.
- **Shell script (sh)**: rotina do backup diário, dentro do `docker-compose.yml`.
- **Markdown**: este `README.md`.
- **XLSX (Office Open XML)**: planilha do Excel com as respostas, gerada direto em PHP e já formatada (um .xlsx é um arquivo ZIP com XMLs dentro).
- **TXT e PNG**: exportações dos comentários e dos gráficos.

### Banco de dados

- **MariaDB 10.11**: banco de dados relacional compatível com MySQL, com motor **InnoDB**
  (chaves estrangeiras e transações) e conjunto de caracteres **utf8mb4** (acentos e emojis).

### Servidor e infraestrutura

- **Docker** e **Docker Compose**: sobem o sistema inteiro com um comando, em 4 containers (nginx, php, mariadb e backup).
- **Docker Desktop no Windows (com WSL2)**: ambiente de desenvolvimento.
- **Nginx** (imagem `nginx:alpine`): servidor web que entrega as páginas e repassa o PHP.
- **PHP-FPM**: executa o código PHP.
- **Volume Docker `mariadb_data`**: armazenamento permanente do banco.
- **mariadb-dump**: backup diário automático (RNF10).

### Recursos nativos usados (sem bibliotecas externas)

- **PDO** (PHP): acesso ao banco com *prepared statements*, que protegem contra SQL injection.
- **password_hash com bcrypt** (PHP): senhas guardadas só como hash.
- **Sessões PHP** com cookie HttpOnly e SameSite: controle de login.
- **Fetch API** (JavaScript): comunicação com o back-end.
- **SVG**: gráfico de evolução temporal.
- **Canvas API**: exportação dos gráficos em PNG.
- **Impressão do navegador** (`window.print`): relatório em PDF.
- **ZIP e XML gerados em PHP** (`gzdeflate`, `crc32`, `pack`): montagem do arquivo .xlsx sem bibliotecas externas.
- **localStorage**: guarda no navegador a escolha de tema (claro ou escuro).
- **prefers-color-scheme**: na primeira visita, o site segue o tema do sistema (Windows ou celular).

### Ferramentas de desenvolvimento e documentação

- **Visual Studio Code**: editor de código.
- **Figma**: protótipo das telas.
- **MySQL Workbench**: diagrama físico do banco de dados.
- **Diagrama conceitual do MER**: _(complete com o nome da ferramenta usada)_.
- **Navegador e celular**: testes das telas e da versão responsiva.
- **Assistentes de IA** (Claude e o assistente do VS Code): apoio no desenvolvimento e na correção de erros.

### Frameworks

Nenhum. O React foi avaliado e descartado: não resolveria a responsividade no celular e deixaria
o projeto mais complexo sem necessidade.

## Estrutura

```
├── docker-compose.yml
├── docker/
│   ├── nginx/default.conf
│   └── php/Dockerfile
├── database/
│   ├── schema.sql                estrutura do banco
│   └── seed.php                  cria as tabelas que faltarem + dados de exemplo
├── public/
│   ├── index.php                 todas as telas
│   ├── style.css
│   ├── script.js
│   ├── api.php                   rotas da API (api.php?action=...)
│   ├── config.php                conexão com o banco, fuso horário e sessão
│   ├── funcoes.php               regras de negócio compartilhadas
│   ├── excel.php                 gera a planilha .xlsx formatada
│   └── relatorio_impressao.php   relatório para imprimir / salvar em PDF
└── backups/                      criada sozinha pelo backup diário
```

## Como rodar

Com o Docker Desktop aberto, na pasta do projeto:

```bash
docker compose up -d --build
docker compose exec php php /var/www/database/seed.php
```

Depois abra **http://localhost:8080**

O seed espera o banco ficar pronto sozinho e pode ser rodado quantas vezes quiser (não duplica nada).

### Atualizando de uma versão anterior deste projeto

A estrutura do banco mudou. Para começar do zero (apaga os dados de teste antigos):

```bash
docker compose down -v
docker compose up -d --build
docker compose exec php php /var/www/database/seed.php
```

## Usuários de teste (senha `123456` para todos)

| Email | Perfil | Vai para |
|---|---|---|
| gestor@escola.com | Gestor | Painel de gestão |
| funcionario1@escola.com | Funcionário | Pesquisa |
| funcionario2@escola.com | Funcionário | Pesquisa |
| funcionario3@escola.com | Funcionário | Pesquisa |

- A pesquisa de exemplo fica aberta por **7 dias** (RN01). Depois disso ela é encerrada sozinha
  e o relatório consolidado é gerado. Para testar de novo, crie e ative outro formulário no painel.
- Cada funcionário responde **uma vez** por pesquisa (RN02) e só pode participar de novo depois
  de **21 dias** (RN03). Para testar com os mesmos usuários, recrie o banco (`docker compose down -v`).
- As médias só aparecem a partir de **3 respostas** (RN07). Use os 3 funcionários de teste.

## Dados e backup (RNF10)

Os dados do banco ficam no volume Docker **`mariadb_data`**, que o próprio Docker gerencia:
ele não aparece como pasta dentro do projeto (a pasta antiga `docker/mariadb_data` não é mais usada).
Para ver o volume, abra a aba **Volumes** do Docker Desktop ou rode `docker volume ls`.

| Comando ou situação | O que acontece com os dados |
|---|---|
| `docker compose down` / `docker compose up -d` | Continuam salvos |
| Reiniciar o computador ou fechar o Docker Desktop | Continuam salvos |
| `docker compose down -v` | **Apaga tudo** (o `-v` remove o volume) |

O serviço `backup` salva uma cópia por dia em `backups/clima_tcc_AAAA-MM-DD.sql` e apaga
as cópias com mais de 7 dias. Para restaurar uma cópia:

```bash
docker compose exec -T mariadb mariadb -u clima_user -pclima_pass clima_tcc < backups/clima_tcc_AAAA-MM-DD.sql
```

Para consultar os dados direto no banco:

```bash
docker compose exec mariadb mariadb -u clima_user -pclima_pass clima_tcc -e "SELECT nome, email FROM funcionarios;"
```

## Como o anonimato funciona

| Tabela | O que guarda | Liga à pessoa? |
|---|---|---|
| `controle_acesso` | que o funcionário X respondeu o formulário Y, e em que data | Sim, mas não guarda nenhuma resposta |
| `respostas` / `resposta_itens` | notas e comentário de cada envio | **Não**, não existe coluna de funcionário |

Cuidados extras (RN07), porque o gestor vê quem já respondeu:

- médias, gráficos, comentários e exportações só aparecem com pelo menos 3 respostas;
- comentários e exportações mostram só a data (sem hora) e em ordem aleatória dentro do dia;
- `controle_acesso` não tem id sequencial e guarda só a data, para não dar para cruzar a ordem
  ou o horário de quem respondeu com a ordem ou o horário das respostas.

## Requisitos x implementação

| Req. | Descrição | Situação | Como foi feito |
|---|---|---|---|
| RN01 | Pesquisa disponível por 7 dias | ✅ | Ao ativar sem data de término, fecha em 7 dias; ao vencer, é encerrada sozinha |
| RN02 | Uma resposta por ciclo | ✅ | Chave (funcionário + formulário) em `controle_acesso`; vale até com envios simultâneos |
| RN03 | 21 dias entre participações | ✅ | Bloqueio com mensagem da data de liberação |
| RN04 | Sem editar ou excluir após o envio | ✅ | Não existe rota para isso; confirmação antes de enviar |
| RN05 | Respostas anônimas | ✅ | Respostas sem nenhuma ligação com o funcionário |
| RN06 | Só gestores veem os relatórios | ✅ | Toda rota de resultado exige perfil gestor |
| RN07 | Impedir identificação individual | ✅ | Mínimo de 3 respostas, data sem hora, ordem aleatória |
| RN08 | Relatório automático no encerramento | ✅ | Tabela `relatorios`, gerado ao encerrar (manual ou por prazo) |
| RF01 | Cadastro de funcionários e gestores | ✅ | Tela Funcionários: criar, editar, desativar, excluir |
| RF02 | Criação de formulários | ✅ | Tela Formulários |
| RF03 | Coleta anônima | ✅ | Ver RN05 |
| RF04 | Armazenamento seguro | ✅ | PDO com prepared statements, senhas com hash, cookie de sessão protegido |
| RF05 | Gráficos automáticos | ✅ | Dashboard (evolução, distribuição) e Resultados (barra por pergunta) |
| RF06 | Relatório consolidado ao final | ✅ | Ver RN08 |
| RF07 | Indicadores para os gestores | ✅ | Média geral, participação, pontos críticos e fortes, estatísticas por pergunta |
| RF08 | Notificações de abertura e encerramento | ⚠️ Parcial | Aviso na tela inicial. Email exigiria um servidor SMTP |
| RF09 | Impedir múltiplas respostas | ✅ | Ver RN02 |
| RF10 | Exportar relatórios em PDF | ✅ | Relatório para "Salvar como PDF" do navegador |
| RF11 | Filtro por setor ou equipe | ❌ | Não implementado: conflita com RN05/RN07 (ver abaixo) |
| RF12 | Logs de acesso dos gestores | ✅ | Entradas, saídas e tentativas recusadas (tela Relatórios) |
| RNF01 | Responsivo | ✅ | Layout para celular, tablet e computador |
| RNF02 | Interface simples | ✅ | Visual limpo em azul, com modo escuro |
| RNF03 | Usuários simultâneos | — | Depende do servidor; o envio é protegido contra concorrência |
| RNF04 | Relatórios só para gestor autenticado | ✅ | Ver RN06 |
| RNF05 | Criptografar dados sensíveis | ⚠️ Parcial | Senhas com hash bcrypt; criptografia do disco é configuração do servidor |
| RNF06 | Anonimato | ✅ | Ver "Como o anonimato funciona" |
| RNF07 | Resposta em menos de 3 s | — | Depende do servidor; páginas leves, sem bibliotecas externas |
| RNF08 | Disponibilidade de 99% | — | Depende da hospedagem; containers reiniciam sozinhos |
| RNF09 | LGPD | ⚠️ Parcial | Coleta mínima, anonimato, senhas com hash. Termo de consentimento e política de privacidade ficam fora do código |
| RNF10 | Backup diário | ✅ | Serviço `backup` no docker-compose |

## Conflitos entre requisitos e decisões tomadas

1. **Gestor ver quem já respondeu × RN05/RN07.** Com 1 ou 2 respostas, a "média" seria a resposta
   de uma pessoa que o gestor sabe quem é. Decisão: dados só aparecem a partir de 3 respostas
   (constante `MINIMO_RESPOSTAS_ANONIMATO`).
2. **RN01 (7 dias) × período personalizado.** Sem data de término, vale o prazo de 7 dias; se o
   gestor definir datas, elas prevalecem. Para seguir a RN01 à risca, basta não oferecer o campo de término.
3. **RF11 (filtro por setor) × RN05/RN07.** Em setores pequenos, filtrar identificaria as pessoas. Não implementado.
4. **Gestor "alterar/excluir respostas" × RN04.** O gestor altera e exclui **cadastros**; respostas
   não podem ser alteradas por ninguém.
5. **Diferenças do MER:** gestores e funcionários ficam na mesma tabela `funcionarios` com
   `tipo_perfil` (como no MER); `respostas` não tem `id_funcionario` (anonimato);
   `controle_acesso` não tem `id_controle` e guarda só a data (RN07).

## Configurações ajustáveis (`public/funcoes.php`)

| Constante | Padrão | Regra |
|---|---|---|
| `MINIMO_RESPOSTAS_ANONIMATO` | 3 | RN07 |
| `DIAS_PESQUISA_ABERTA` | 7 | RN01 |
| `DIAS_INTERVALO_PARTICIPACAO` | 21 | RN03 |

Se mudar um valor, ajuste também os textos que citam o número em `index.php` e `script.js`.

## Rotas da API (`api.php?action=...`)

| Ação | Quem usa | O que faz |
|---|---|---|
| status_pesquisa | público | Aviso da tela inicial (aberta / encerrada) |
| login, logout | todos | Login único; o perfil decide a tela |
| formulario_ativo, enviar_resposta | funcionário | Carregar e responder a pesquisa |
| funcionarios, criar_funcionario, editar_funcionario, alterar_status_funcionario, excluir_funcionario | gestor | Cadastro (RF01) |
| formularios, criar_formulario, alterar_status_formulario | gestor | Formulários (RF02) |
| dashboard, resultados, comentarios | gestor | Indicadores e análises |
| exportar_excel, exportar_comentarios, logs_acesso | gestor | Exportações (Excel e TXT) e logs |

## Antes de usar de verdade

- Remova o quadro "Credenciais de teste" da tela de login (`public/index.php`).
- Troque as senhas do `docker-compose.yml` e as senhas dos usuários de teste.
- Publique com HTTPS.