# Climatize: Pesquisa de Clima Organizacional (TCC)

Sistema web para aplicar pesquisas de clima organizacional com **respostas anônimas**.
Os funcionários entram com email e senha só para o sistema controlar quem já participou;
o conteúdo das respostas nunca fica ligado à pessoa. Os gestores acompanham os resultados
em um painel com dashboard, resultados por pergunta e por categoria, comparação entre ciclos,
comentários, cadastro de funcionários e relatórios. O visual usa fundos em azul e tem **modo escuro**,
ligado pelo botão ☾ no topo das telas ou pelo item "Modo escuro" no menu do painel.
O **logo do Climatize** aparece no topo das telas, no painel, no relatório impresso, no ícone da aba
do navegador e nos e-mails enviados aos funcionários.

## Funcionalidades

**Para quem responde**

- Termo de consentimento (LGPD) no primeiro acesso, com link para a política de privacidade.
- Três tipos de pergunta: **nota de 0 a 10**, **sim ou não** e **múltipla escolha**.
- **Rascunho automático** no navegador: se a página fechar ou a internet cair, as respostas voltam
  ao entrar de novo (vale por 24 horas e é apagado ao enviar ou ao clicar em Sair).
- Tela de agradecimento depois do envio.
- **Acessibilidade**: dá para responder tudo só com o teclado (Tab, setas, espaço), os controles têm
  descrição para leitores de tela, o foco fica sempre visível e as animações são desligadas para
  quem pediu "reduzir movimento" no sistema.
- E-mails de aviso quando uma pesquisa abre, quando encerra, lembretes enviados pela gestão e um
  **lembrete automático** nos últimos dias para quem ainda não respondeu.
- **Primeiro acesso**: a senha cadastrada pela gestão é provisória. Ao entrar pela primeira vez, a pessoa
  é levada para a tela "Crie a sua senha" e só continua depois de criar uma senha própria.
- **Alterar senha** (botão no topo da pesquisa), com e-mail de confirmação depois da troca.
- **Esqueci minha senha**: recebe por e-mail um link para criar uma senha nova, sem precisar pedir ao gestor.

**Para os gestores**

- Dashboard com média geral, participação, evolução, distribuição das notas e **média por categoria**.
- Resultados por pergunta com filtro por categoria: nota (média, mediana, desvio padrão),
  sim/não (% de sim) e múltipla escolha (% de cada alternativa).
- **Comparação entre ciclos**: escolha duas pesquisas e veja a diferença da média geral, de cada
  categoria e de cada pergunta que as duas têm em comum.
- Formulários: criar com editor de perguntas (tipo, categoria, alternativas, mudar a ordem),
  **editar rascunhos**, **duplicar** uma pesquisa para o próximo ciclo e excluir rascunhos.
- Botão de **lembrete por e-mail** para quem ainda não respondeu.
- Cadastro de funcionários na tela ou **importado por planilha** (.xlsx ou .csv), com modelo pronto para baixar.
  O e-mail pode ser de qualquer provedor (Gmail, Hotmail, da empresa). A senha do cadastro é provisória:
  a pessoa troca depois e a gestão não fica sabendo a senha nova.
- Exportação para Excel com a aba **Respostas** e a aba **Resumo**, que tem fórmulas
  (médias, contagens e percentuais que se recalculam no Excel).
- Comentários, relatório consolidado em PDF e logs de acesso.
- **Alterar senha** pelo menu do painel.
- **Redefinir senha** de funcionários e de outros gestores, na tela Funcionários: a pessoa recebe por e-mail
  um link para criar a senha nova. A gestão não vê o link nem a senha, e a senha atual continua valendo até
  a pessoa usar o link (no máximo 3 links por hora para a mesma pessoa).
- Selo **"Senha provisória"** na lista de funcionários para quem ainda não fez o primeiro acesso.
- **Lembrete automático** programado sozinho para 48 horas antes do fim da pesquisa, com a data mostrada
  na tela Formulários (e, depois, para quantas pessoas foi enviado).
- **Dados de demonstração** prontos para a apresentação, com um comando (veja "Na hora da apresentação").

**Segurança do login**

- Depois de **5 senhas erradas** no mesmo e-mail, o login dele fica bloqueado por **15 minutos**
  (o "Esqueci minha senha" libera antes).
- Trocar a senha derruba as sessões abertas com a senha antiga em outros aparelhos.

## Tecnologias

**Arquitetura:** aplicação web cliente-servidor. O front-end (HTML, CSS e JavaScript) se comunica
por requisições HTTP, com dados em JSON, com uma API em PHP que grava em um banco MariaDB.
Tudo roda em containers Docker, com o Nginx como servidor web.

### Linguagens de programação

- **PHP 8.2**: back-end completo. API (`api.php`), regras de negócio (`funcoes.php`), login, sessões, envio de e-mail, criptografia, leitura e geração de planilhas e relatório em PDF.
- **JavaScript (ES6+, puro, sem frameworks)**: troca de telas, chamadas à API com `fetch` e `async/await`, gráficos e exportações.
- **SQL**: criação do banco (`schema.sql`) e todas as consultas (médias, contagens, filtros por período, controle de quem respondeu).

### Linguagens de marcação e estilo

- **HTML5**: estrutura de todas as telas (`index.php`, `privacidade.php` e `relatorio_impressao.php`), com atributos **ARIA** para acessibilidade.
- **CSS3**: visual, responsividade e tema claro/escuro, com variáveis CSS (custom properties), Flexbox, Grid, media queries e `conic-gradient` no gráfico de rosca.

### Formatos e arquivos de configuração

- **JSON**: formato dos dados trocados entre o JavaScript e a API.
- **YAML**: `docker-compose.yml`.
- **Dockerfile**: montagem da imagem do PHP.
- **Shell script (sh)**: rotina do backup diário, dentro do `docker-compose.yml`.
- **Markdown**: este `README.md`.
- **XLSX (Office Open XML)**: planilha do Excel com as respostas e o resumo com fórmulas, gerada direto em PHP e já formatada (um .xlsx é um arquivo ZIP com XMLs dentro). O sistema também **lê** .xlsx e .csv para importar funcionários.
- **TXT e PNG**: exportações dos comentários e dos gráficos.

### Banco de dados

- **MariaDB 10.11**: banco de dados relacional compatível com MySQL, com motor **InnoDB**
  (chaves estrangeiras e transações) e conjunto de caracteres **utf8mb4** (acentos e emojis).

### Servidor e infraestrutura

- **Docker** e **Docker Compose**: sobem o sistema inteiro com um comando, em 5 containers (nginx, php, mariadb, mailpit e backup).
- **Docker Desktop no Windows (com WSL2)**: ambiente de desenvolvimento.
- **Nginx** (imagem `nginx:alpine`): servidor web que entrega as páginas e repassa o PHP.
- **PHP-FPM**: executa o código PHP.
- **Volume Docker `mariadb_data`**: armazenamento permanente do banco.
- **mariadb-dump**: backup diário automático (RNF10).
- **Mailpit** (imagem `axllent/mailpit`): servidor de e-mail de teste. Recebe os e-mails do sistema e mostra
  numa caixa de entrada em http://localhost:8025, sem enviar nada para fora (RF08).

### Recursos nativos usados (sem bibliotecas externas)

- **PDO** (PHP): acesso ao banco com *prepared statements*, que protegem contra SQL injection.
- **password_hash com bcrypt** (PHP): senhas guardadas só como hash.
- **random_bytes e SHA-256** (PHP): códigos dos links de "Esqueci minha senha" e chaves do limite de tentativas, guardados só como hash.
- **OpenSSL com AES-256-GCM** (PHP): comentários e relatórios guardados criptografados no banco (RNF05).
- **SMTP** (protocolo de e-mail), implementado em PHP com `fsockopen`: envio dos e-mails de abertura,
  encerramento e lembrete, com suporte a TLS e login para usar o servidor de e-mail da empresa.
- **Sessões PHP** com cookie HttpOnly e SameSite: controle de login.
- **Fetch API** (JavaScript): comunicação com o back-end.
- **SVG**: gráfico de evolução temporal.
- **Canvas API**: exportação dos gráficos em PNG.
- **Impressão do navegador** (`window.print`): relatório em PDF.
- **ZIP e XML gerados em PHP** (`gzdeflate`, `crc32`, `pack`): montagem do arquivo .xlsx sem bibliotecas externas.
- **ZIP e XML lidos em PHP** (`gzinflate`, `SimpleXML`): leitura das planilhas de importação.
- **localStorage**: guarda no navegador a escolha de tema (claro ou escuro) e o rascunho das respostas.
- **FormData** (JavaScript): envio do arquivo da planilha de importação.
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

### Identidade visual (logo)

O símbolo é uma **prancheta de pesquisa** com **três pessoas** (a equipe), **barras crescentes**
(resultados melhorando) e um **selo de check** (pesquisa respondida). Slogan: **"Pessoas unidas, clima melhor."**
O logo foi redesenhado em vetor (SVG) a partir da arte original em PDF, então fica nítido em qualquer tamanho.
No nome, "Clima" e "tize" têm cores diferentes.

| Versão | Onde é usada | Prancheta | Nome |
|---|---|---|---|
| Fundo claro | Relatório impresso | Azul-marinho | "Clima" azul-marinho + "tize" azul |
| Fundo azul (degradê) | Topo das telas, painel e e-mails no tema claro | Branca | "Clima" branco + "tize" âmbar |
| Modo escuro | Topo das telas e painel no modo escuro | Azul | "Clima" branco + "tize" âmbar |
| Ícone de app | Ícone da aba, ícone do celular e foto de perfil do Gmail do RH | Branca em quadrado azul | — |

| Cor | Código |
|---|---|
| Azul principal | `#1565E0` |
| Azul-marinho | `#0B2A6F` |
| Âmbar | `#FFB703` |
| Verde-água | `#14B8A6` |

Os e-mails usam o logo em PNG (`logo-email.png`), porque Gmail e Outlook não mostram SVG.

## Estrutura

```
├── docker-compose.yml
├── docker/
│   ├── nginx/default.conf
│   └── php/Dockerfile
├── database/
│   ├── schema.sql                estrutura do banco
│   ├── seed.php                  cria/atualiza as tabelas + dados de exemplo
│   ├── demo.php                  dados de demonstração para a apresentação (rodar só na hora)
│   ├── tarefas.php               tarefas agendadas (encerramentos e avisos), rodado pelo agendador
│   └── chave.key                 chave da criptografia (criada sozinha pelo seed, fora do git)
├── .env.exemplo                  modelo para configurar um e-mail de verdade (Gmail)
├── public/
│   ├── index.php                 todas as telas
│   ├── style.css
│   ├── script.js
│   ├── api.php                   rotas da API (api.php?action=...)
│   ├── config.php                conexão com o banco, fuso horário e sessão
│   ├── funcoes.php               regras de negócio compartilhadas
│   ├── excel.php                 gera as planilhas .xlsx e lê as planilhas de importação
│   ├── privacidade.php           política de privacidade (modelo, LGPD)
│   ├── relatorio_impressao.php   relatório para imprimir / salvar em PDF
│   └── img/                      logo do Climatize
│       ├── logo-climatize.svg          símbolo + nome, para fundo claro
│       ├── logo-climatize-branco.svg   símbolo + nome, para fundo azul (tema claro)
│       ├── logo-climatize-escuro.svg   símbolo + nome, para o modo escuro
│       ├── logo-simbolo*.svg           só o símbolo (claro, branco e escuro)
│       ├── icone-app.svg               ícone de app (prancheta branca em quadrado azul)
│       ├── favicon.svg / favicon-32.png / apple-touch-icon.png   ícone da aba e do celular
│       └── logo-email.png              logo usado no topo dos e-mails
└── backups/                      criada sozinha pelo backup diário
```

## Como rodar

Com o Docker Desktop aberto, na pasta do projeto:

```bash
docker compose up -d --build
docker compose exec php php /var/www/database/seed.php
```

Depois abra:

- **http://localhost:8080**: o sistema;
- **http://localhost:8025**: a caixa de entrada de teste com os e-mails enviados pelo sistema (Mailpit).

O seed espera o banco ficar pronto sozinho e pode ser rodado quantas vezes quiser (não duplica nada).

Além do site, sobe o container **agendador**, que a cada 5 minutos encerra as pesquisas vencidas (gerando
o relatório) e manda os avisos por e-mail, inclusive o lembrete automático. Assim os avisos saem na hora
certa mesmo sem ninguém usando o sistema. O que ele envia aparece em `docker compose logs agendador`.

## Na hora da apresentação (dados de demonstração)

Para o painel aparecer completo na frente da banca, rode **um comando só, na hora da apresentação**:

```bash
docker compose exec php php /var/www/database/demo.php
```

Ele cria, em segundos:

- **24 funcionários fictícios** (e-mails `@demo.climatize`, que **nunca recebem e-mail**);
- uma pesquisa **encerrada há uns 5 meses** (19 respostas, com relatório consolidado);
- uma pesquisa **aberta agora** (começou há 5 dias e fecha daqui a 7, com 22 respostas);
- comentários criptografados, como num uso real.

A comparação entre os dois ciclos conta uma história: **comunicação e liderança melhoraram**, enquanto
**recursos e qualidade de vida pioraram** (bons pontos para comentar na apresentação).

Os usuários de teste (Matheus, Felipe e Funcionário 3) ficam **sem responder** a pesquisa aberta. Durante a
apresentação, dá para responder pelo celular e ver a resposta aparecer no painel na hora, ou clicar em
**Lembrete** e mostrar o e-mail chegando no Gmail (as contas fictícias não recebem nada).

Observações:

- A pesquisa que estiver aberta é encerrada (sem mandar e-mail) para dar lugar à demonstração.
- Os sorteios são sempre iguais: os números saem idênticos toda vez que o comando é rodado.
- Rodar de novo recria tudo do zero. Para apagar os dados de demonstração:
  `docker compose exec php php /var/www/database/demo.php --limpar`

### Atualizando de uma versão anterior deste projeto

Não precisa apagar o banco. Rode os mesmos dois comandos (ou o comando único abaixo): o `--build` sobe
os containers novos (Mailpit e agendador) e o seed acrescenta as colunas e tabelas novas, criptografa os
comentários e relatórios que já existiam, completa a pesquisa de exemplo e converte os usuários de teste
antigos (`@escola.com` e depois `gestor@empresa.com` e `funcionario1/2@empresa.com`) para as contas atuais (Gmail), mantendo o histórico
de quem já respondeu.

```bash
docker compose up -d --build && docker compose exec php php /var/www/database/seed.php
```

Se preferir começar do zero (apaga os dados de teste antigos), use `docker compose down -v` antes.

### Chave de criptografia: faça backup

Na primeira execução, o seed cria o arquivo **`database/chave.key`**. É com ele que os comentários e
relatórios são criptografados. **Sem esse arquivo, os comentários já gravados não podem mais ser lidos.**
Guarde uma cópia junto com os backups do banco e não envie para o GitHub (ele já está no `.gitignore`).
Em produção, a chave também pode ser passada pela variável de ambiente `APP_KEY` (32 bytes em base64).

## Usuários de teste (senha `123456` para todos)

| Email | Perfil | Vai para |
|---|---|---|
| gestorclimatize@gmail.com (Gestor Climatize) | Gestor | Painel de gestão (Gmail de verdade: recebe o "Relatório disponível" e as respostas aos lembretes) |
| funcionario01.empresa@gmail.com (Matheus Cunha) | Funcionário | Pesquisa (Gmail de verdade, para testar os e-mails) |
| funcionario02.empresa@gmail.com (Felipe Alves) | Funcionário | Pesquisa (Gmail de verdade, para testar os e-mails) |
| funcionario3@empresa.com | Funcionário | Pesquisa |

- A pesquisa de exemplo fica aberta por **7 dias** (RN01). Depois disso ela é encerrada sozinha
  e o relatório consolidado é gerado. Para testar de novo, crie e ative outro formulário no painel.
- Cada funcionário responde **uma vez** por pesquisa (RN02) e só pode participar de novo depois
  de **21 dias** (RN03). Para testar com os mesmos usuários, recrie o banco (`docker compose down -v`).
- As médias só aparecem a partir de **3 respostas** (RN07). Use os 3 funcionários de teste.
- No primeiro acesso, cada funcionário precisa **aceitar o termo de consentimento** antes de responder.
- A pesquisa de exemplo tem 10 perguntas de nota, 1 de sim/não e 1 de múltipla escolha, divididas em categorias.
- Para testar a importação, baixe o modelo na tela Funcionários, preencha e envie. A linha de exemplo
  (`maria.exemplo@empresa.com`) é ignorada.
- Rodar o seed de novo volta a senha dos 4 usuários de teste para `123456` (útil se você trocar e esquecer).
  Os usuários de teste não passam pela troca obrigatória de senha.
- Para ver a **troca obrigatória de senha**: cadastre alguém na tela Funcionários e entre com a senha que você
  definiu. O sistema pede para criar uma senha nova antes de continuar.
- Para testar o "Esqueci minha senha": na tela de login, clique no link, informe o e-mail e abra o
  link do e-mail que chega no Mailpit (http://localhost:8025).

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
| `respostas` / `resposta_itens` | notas, alternativas e comentário de cada envio | **Não**, não existe coluna de funcionário |

Cuidados extras (RN07), porque o gestor vê quem já respondeu:

- médias, gráficos, comentários e exportações só aparecem com pelo menos 3 respostas;
- comentários e exportações mostram só a data (sem hora) e em ordem aleatória dentro do dia;
- `controle_acesso` não tem id sequencial e guarda só a data, para não dar para cruzar a ordem
  ou o horário de quem respondeu com a ordem ou o horário das respostas;
- comentários e relatórios ficam criptografados no banco (AES-256-GCM): quem abrir o banco direto
  ou um arquivo de backup não consegue lê-los sem a chave;
- na comparação entre ciclos, os números de uma pesquisa com menos de 3 respostas ficam ocultos;
- o rascunho fica só no navegador de quem está respondendo, nunca no servidor.

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
| RF02 | Criação de formulários | ✅ | Tela Formulários: editor com 3 tipos de pergunta e categorias, editar rascunho, duplicar |
| RF03 | Coleta anônima | ✅ | Ver RN05 |
| RF04 | Armazenamento seguro | ✅ | PDO com prepared statements, senhas com hash, cookie de sessão protegido, limite de tentativas de login, sessões derrubadas quando a senha muda |
| RF05 | Gráficos automáticos | ✅ | Dashboard (evolução, distribuição, categorias), Resultados (por pergunta e alternativa) e Comparar |
| RF06 | Relatório consolidado ao final | ✅ | Ver RN08 |
| RF07 | Indicadores para os gestores | ✅ | Média geral, participação, pontos críticos e fortes, média por categoria, estatísticas por pergunta, comparação entre ciclos |
| RF08 | Notificações de abertura e encerramento | ✅ | Aviso na tela inicial + e-mails de abertura, encerramento e lembrete (SMTP; Mailpit no ambiente de teste) |
| RF09 | Impedir múltiplas respostas | ✅ | Ver RN02 |
| RF10 | Exportar relatórios em PDF | ✅ | Relatório para "Salvar como PDF" do navegador |
| RF11 | Filtro por setor ou equipe | ❌ | Não implementado: conflita com RN05/RN07 (ver abaixo) |
| RF12 | Logs de acesso dos gestores | ✅ | Entradas, saídas e tentativas recusadas (tela Relatórios) |
| RNF01 | Responsivo | ✅ | Layout para celular, tablet e computador |
| RNF02 | Interface simples | ✅ | Visual limpo em azul, modo escuro, navegação por teclado, textos para leitores de tela e tela de agradecimento |
| RNF03 | Usuários simultâneos | — | Depende do servidor; o envio é protegido contra concorrência |
| RNF04 | Relatórios só para gestor autenticado | ✅ | Ver RN06 |
| RNF05 | Criptografar dados sensíveis | ✅ | Senhas com hash bcrypt; comentários e relatórios com AES-256-GCM. Em produção, somar HTTPS |
| RNF06 | Anonimato | ✅ | Ver "Como o anonimato funciona" |
| RNF07 | Resposta em menos de 3 s | — | Depende do servidor; páginas leves, sem bibliotecas externas |
| RNF08 | Disponibilidade de 99% | — | Depende da hospedagem; containers reiniciam sozinhos |
| RNF09 | LGPD | ✅ | Coleta mínima, anonimato, termo de consentimento (aceite gravado com data e versão) e política de privacidade. A empresa precisa revisar a política e preencher os campos entre colchetes |
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
| `VERSAO_TERMO` | 1 | RNF09: aumente quando mudar a política, para todos aceitarem o termo de novo |
| `MAXIMO_OPCOES` | 10 | Alternativas por pergunta de múltipla escolha |
| `TAMANHO_MINIMO_SENHA` | 6 | Tamanho mínimo das senhas |
| `MAX_TENTATIVAS_LOGIN` / `MINUTOS_BLOQUEIO_LOGIN` | 5 / 15 | Senhas erradas antes do bloqueio e duração dele |
| `MAX_TENTATIVAS_POR_IP` | 100 | Senhas erradas por IP em 15 minutos (contra robôs; alto porque a empresa toda pode sair pelo mesmo IP) |
| `MINUTOS_VALIDADE_LINK_SENHA` | 60 | Validade do link de "Esqueci minha senha" |
| `MAX_PEDIDOS_REDEFINICAO_HORA` | 3 | Links de redefinição por conta a cada hora |
| `HORAS_ANTES_LEMBRETE_AUTOMATICO` | 48 | Quantas horas antes do fim sai o lembrete automático |
| `HORAS_MINIMAS_ANTES_DO_LEMBRETE` | 24 | A pesquisa precisa estar aberta há pelo menos esse tempo para o lembrete sair |
| `DOMINIO_DEMONSTRACAO` | demo.climatize | Domínio das contas fictícias, que nunca recebem e-mail |

Se mudar um valor, ajuste também os textos que citam o número em `index.php` e `script.js`.

## Rotas da API (`api.php?action=...`)

| Ação | Quem usa | O que faz |
|---|---|---|
| status_pesquisa | público | Aviso da tela inicial (aberta / encerrada) |
| login, logout | todos | Login único; o perfil decide a tela |
| alterar_senha | logado | Troca a própria senha (pede a atual) e manda e-mail de confirmação |
| definir_senha_inicial | logado com senha provisória | Primeiro acesso: troca a senha provisória por uma senha própria |
| solicitar_redefinicao, verificar_redefinicao, redefinir_senha | público | "Esqueci minha senha": link por e-mail, conferência do link e nova senha |
| aceitar_termo | funcionário | Grava o aceite do termo de consentimento (RNF09) |
| formulario_ativo, enviar_resposta | funcionário | Carregar e responder a pesquisa |
| funcionarios, criar_funcionario, editar_funcionario, alterar_status_funcionario, excluir_funcionario | gestor | Cadastro (RF01) |
| enviar_link_senha | gestor | Envia para um funcionário ou gestor o e-mail com o link de "Criar nova senha" |
| importar_funcionarios, modelo_importacao | gestor | Importação por planilha e modelo para preencher |
| formularios, detalhes_formulario, criar_formulario, editar_formulario, duplicar_formulario, excluir_formulario, alterar_status_formulario | gestor | Formulários (RF02) |
| enviar_lembrete | gestor | Lembrete por e-mail para quem ainda não respondeu (RF08) |
| dashboard, resultados, comparar, comentarios | gestor | Indicadores, análises e comparação entre ciclos |
| exportar_excel, exportar_comentarios, logs_acesso | gestor | Exportações (Excel e TXT) e logs |

## E-mails (RF08)

| Quando | Para quem |
|---|---|
| Uma pesquisa abre | Funcionários ativos que podem responder (não respondem os que estão nos 21 dias da RN03) |
| Uma pesquisa encerra | Todos os usuários ativos, avisando que o relatório foi gerado |
| O gestor clica em "Enviar lembrete" | Quem ainda não respondeu (no máximo um lembrete a cada 10 minutos) |
| Faltam 48 horas para a pesquisa fechar (lembrete automático, uma vez por pesquisa) | Quem ainda não respondeu |
| Alguém pede "Esqueci minha senha" | A própria pessoa, com o link para criar a senha nova |
| O gestor clica em "Redefinir senha" (tela Funcionários) | A pessoa escolhida, com o link para criar a senha nova |
| A senha muda (pela pessoa, pelo link ou pelo gestor) | A própria pessoa, confirmando a troca. **Nunca leva a senha** |

Cada aviso é enviado uma vez só (tabela `notificacoes`). O e-mail nunca diz nada sobre as respostas.
No ambiente de teste, tudo cai no Mailpit (http://localhost:8025).

**Visual:** os e-mails saem em HTML com o **logo do Climatize** numa faixa azul no topo, a saudação em
destaque e um **botão** para o link principal ("Responder a pesquisa", "Criar nova senha" ou
"Abrir o painel do gestor"), com o endereço escrito embaixo caso o botão não funcione. A mensagem leva
junto uma versão só em texto, para programas de e-mail que não mostram HTML. O logo vai anexado à
própria mensagem (imagem embutida com `cid:`), então aparece mesmo com o sistema rodando em `localhost`.

**Remetentes:** todos os e-mails saem de um remetente institucional (padrão "Climatize RH").
Nos lembretes, o campo **"Responder para"** leva o e-mail do gestor que clicou no botão (ou o de
`EMAIL_GESTOR_LEMBRETES`, se preenchido): quem tiver dúvida responde e fala direto com a gestão.
Assim é preciso configurar uma conta de e-mail só, e o lembrete não chega com cara de cobrança do chefe,
o que poderia inibir respostas sinceras.

### Trocando o Mailpit por um e-mail de verdade (Gmail)

Não precisa mexer no código nem no `docker-compose.yml`:

1. Use uma conta Gmail só para o sistema (ex.: `rh.climatize@gmail.com`) e ative a
   **verificação em duas etapas** nela.
2. Crie uma **senha de app** em https://myaccount.google.com/apppasswords (16 letras).
   A senha normal da conta não funciona.
3. Na pasta do projeto, copie `.env.exemplo` para um arquivo chamado `.env` e preencha
   o e-mail, a senha de app e o `APP_URL`.
4. Rode `docker compose up -d` para o PHP pegar as novas configurações.
5. Cadastre os funcionários com os e-mails reais deles.

| Variável | Gmail |
|---|---|
| `SMTP_HOST` / `SMTP_PORT` | `smtp.gmail.com` / `587` |
| `SMTP_SEGURANCA` | `tls` (porta 587) ou `ssl` (porta 465) |
| `SMTP_USUARIO` / `SMTP_SENHA` | o Gmail da conta e a senha de app |
| `SMTP_REMETENTE` / `SMTP_NOME` | o mesmo Gmail / nome que aparece como remetente (ex.: `Climatize RH`) |
| `EMAIL_GESTOR_LEMBRETES` | "Responder para" dos lembretes (vazio = e-mail do gestor que enviou) |
| `APP_URL` | endereço que vai no link dos e-mails |

Cuidados:

- **O link do e-mail** (lembretes e "Esqueci minha senha"). `http://localhost:8080` só abre no computador onde o Docker está rodando.
  Para quem recebe o e-mail conseguir entrar, o `APP_URL` precisa ser o IP do computador na rede
  da empresa (ex.: `http://192.168.0.10:8080`, com o celular ou PC na mesma rede) ou o endereço do
  servidor onde o sistema estiver publicado.
- O `.env` já está no `.gitignore`: a senha de app não vai para o GitHub.
- Uma conta Gmail comum envia até 500 e-mails por dia.
- Os primeiros e-mails de uma conta nova costumam cair no **spam**. Em cada caixa que recebeu, clique em
  "Não é spam" e adicione o e-mail do RH aos contatos; se quiser garantir, crie um filtro
  (`from:` + e-mail do RH) com a opção "Nunca enviar para spam".
- Contas corporativas (Google Workspace) podem ter a senha de app bloqueada pelo administrador.
- **Logo na foto de perfil:** a bolinha com a foto do remetente, no Gmail de quem recebe, vem da **foto da
  conta Google do RH**, não do código. Para aparecer o logo, entre na conta do RH, abra
  https://myaccount.google.com → **Informações pessoais** → **Foto** e envie o arquivo `foto-perfil-gmail.png`
  (entregue junto com o projeto). Pode levar algumas horas para aparecer para todo mundo.
- Se o e-mail não chegar, `docker compose logs php` mostra o motivo (por exemplo, login recusado).
  Para voltar ao Mailpit, apague o `.env` e rode `docker compose up -d`.

Sem `SMTP_HOST`, o sistema funciona normalmente e só não envia e-mails.

## Antes de usar de verdade

- Remova o quadro "Credenciais de teste" da tela de login (`public/index.php`).
- Se rodou a demonstração, apague os dados fictícios (`demo.php --limpar`).
- Troque as senhas do `docker-compose.yml` e as senhas dos usuários de teste.
- Publique com HTTPS.
- Revise a política de privacidade (`public/privacidade.php`) e preencha os campos entre colchetes.
- Configure o e-mail de verdade (seção "Trocando o Mailpit por um e-mail de verdade") e guarde uma cópia de `database/chave.key`.