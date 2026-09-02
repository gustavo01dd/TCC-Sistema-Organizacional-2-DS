const perguntas = [
    "O ambiente de trabalho é respeitoso e colaborativo",
    "A comunicação interna é clara e eficiente",
    "Tenho os recursos necessários para realizar meu trabalho",
    "Sinto que meu trabalho é reconhecido",
    "Tenho boas oportunidades de desenvolvimento",
    "A liderança está aberta para ouvir os funcionários",
    "Existe equilíbrio entre trabalho e vida pessoal",
    "Sinto-me seguro para dar minha opinião",
    "As decisões da empresa são comunicadas de forma clara",
    "Eu recomendaria esta empresa como um bom lugar para trabalhar"
];

let notas = [];

// ===============================
// NAVEGAÇÃO
// ===============================

function esconderTudo() {
    document.getElementById("telaPesquisa").classList.add("escondido");
    document.getElementById("telaQuestionario").classList.add("escondido");
    document.getElementById("telaGestor").classList.add("escondido");
    document.getElementById("telaDashboard").classList.add("escondido");
}

function mostrarInicio() {
    esconderTudo();
    document.getElementById("telaPesquisa").classList.remove("escondido");
}

function mostrarPesquisa() {
    esconderTudo();
    document.getElementById("telaQuestionario").classList.remove("escondido");

    notas = [];

    const comentario = document.getElementById("comentario");

    if (comentario) {
        comentario.value = "";
    }

    criarPerguntas();
}

function mostrarGestor() {
    esconderTudo();
    document.getElementById("telaGestor").classList.remove("escondido");
}

// ===============================
// CRIAR AS 10 PERGUNTAS
// ===============================

function criarPerguntas() {

    const area = document.getElementById("perguntas");

    if (!area) return;

    area.innerHTML = "";

    for (let i = 0; i < perguntas.length; i++) {

        const notaInicial = notas[i] ?? 6;

        area.innerHTML += `
            <div class="pergunta-card">

                <div class="pergunta-titulo">
                    <span>
                        ${i + 1}. ${perguntas[i]}
                    </span>

                    <span class="rosto">
                        😐
                    </span>
                </div>

                <input
                    class="range"
                    type="range"
                    min="0"
                    max="10"
                    value="${notaInicial}"
                    oninput="mudarNota(${i}, this.value)"
                >

                <div class="numeros">
                    <span>0<br><small>Insatisfeito</small></span>
                    <span>1</span>
                    <span>2</span>
                    <span>3</span>
                    <span>4</span>
                    <span>5<br><small>Satisfeito</small></span>
                    <span>6</span>
                    <span>7</span>
                    <span>8</span>
                    <span>9</span>
                    <span>10<br><small>Muito satisfeito</small></span>
                </div>

                <div class="nota" id="nota${i}">
                    ${notaInicial}
                </div>

                <div class="satisfacao" id="textoNota${i}">
                    ${textoNota(notaInicial)}
                </div>

            </div>
        `;
    }

    atualizarProgresso();
}

// ===============================
// ALTERAR NOTA
// ===============================

function mudarNota(indice, valor) {

    notas[indice] = Number(valor);

    const nota = document.getElementById("nota" + indice);
    const texto = document.getElementById("textoNota" + indice);

    if (nota) {
        nota.innerText = valor;
    }

    if (texto) {
        texto.innerText = textoNota(Number(valor));
    }

    atualizarProgresso();
}

function textoNota(nota) {

    if (nota <= 3) {
        return "😟 Insatisfeito";
    }

    if (nota <= 6) {
        return "😐 Satisfeito";
    }

    if (nota <= 8) {
        return "🙂 Satisfeito";
    }

    return "😄 Muito satisfeito";
}

// ===============================
// BARRA DE PROGRESSO
// ===============================

function atualizarProgresso() {

    let respondidas = 0;

    for (let i = 0; i < perguntas.length; i++) {

        if (notas[i] != null) {
            respondidas++;
        }
    }

    if (respondidas === 0) {
        respondidas = 1;
    }

    const numeroPergunta =
        document.getElementById("numeroPergunta");

    const barra =
        document.getElementById("barraProgresso");

    if (numeroPergunta) {
        numeroPergunta.innerText =
            respondidas + " de 10";
    }

    if (barra) {
        barra.style.width =
            (respondidas * 10) + "%";
    }
}

// ===============================
// ENVIAR PESQUISA PARA O PHP
// ===============================

async function enviarPesquisa() {

    const botao =
        document.getElementById("botaoEnviar");

    const comentarioElemento =
        document.getElementById("comentario");

    // Se o usuário não mexeu em algum slider,
    // mantém o valor inicial 6.
    for (let i = 0; i < perguntas.length; i++) {

        if (notas[i] == null) {
            notas[i] = 6;
        }
    }

    // Garante que existem exatamente 10 respostas.
    if (notas.length !== 10) {

        alert(
            "Não foi possível preparar as 10 respostas."
        );

        return;
    }

    const comentario =
        comentarioElemento
            ? comentarioElemento.value.trim()
            : "";

    // Calcula a média
    const total =
        notas.reduce(
            (soma, nota) =>
                soma + Number(nota),
            0
        );

    const media =
        (total / 10).toFixed(1);

    if (botao) {

        botao.disabled = true;
        botao.innerText = "Enviando...";
    }

    try {

        const resposta = await fetch(
            "api.php?action=salvar_pesquisa",
            {
                method: "POST",

                headers: {
                    "Content-Type":
                        "application/json"
                },

                body: JSON.stringify({

                    notas: notas,

                    comentario: comentario
                })
            }
        );

        const dados =
            await resposta.json();

        if (!resposta.ok || !dados.sucesso) {

            throw new Error(
                dados.mensagem ||
                "Não foi possível salvar a pesquisa."
            );
        }

        alert(
            "Pesquisa enviada com sucesso!\n\n" +
            "Sua média foi: " +
            media
        );

        // Limpa os dados
        notas = [];

        if (comentarioElemento) {
            comentarioElemento.value = "";
        }

        mostrarInicio();

    } catch (erro) {

        console.error(
            "Erro ao enviar pesquisa:",
            erro
        );

        alert(
            erro.message ||
            "Erro ao conectar com o servidor."
        );

    } finally {

        if (botao) {

            botao.disabled = false;
            botao.innerText =
                "Enviar pesquisa";
        }
    }
}

// ===============================
// LOGIN DO GESTOR
// ===============================

async function entrarGestor() {

    const emailElemento =
        document.getElementById("emailGestor");

    const senhaElemento =
        document.getElementById("senhaGestor");

    const erro =
        document.getElementById("erroLogin");

    const email =
        emailElemento
            ? emailElemento.value.trim()
            : "";

    const senha =
        senhaElemento
            ? senhaElemento.value
            : "";

    if (erro) {
        erro.innerText = "";
    }

    if (!email || !senha) {

        if (erro) {
            erro.innerText =
                "Informe email e senha.";
        }

        return;
    }

    try {

        const resposta = await fetch(
            "api.php?action=login",
            {
                method: "POST",

                headers: {
                    "Content-Type":
                        "application/json"
                },

                body: JSON.stringify({
                    email: email,
                    senha: senha
                })
            }
        );

        const dados =
            await resposta.json();

        if (!resposta.ok || !dados.sucesso) {

            if (erro) {

                erro.innerText =
                    dados.mensagem ||
                    "Email ou senha incorretos.";
            }

            return;
        }

        esconderTudo();

        document
            .getElementById("telaDashboard")
            .classList
            .remove("escondido");

        await carregarDashboard();

    } catch (e) {

        console.error(
            "Erro no login:",
            e
        );

        if (erro) {

            erro.innerText =
                "Erro ao conectar com o servidor.";
        }
    }
}

// ===============================
// CARREGAR DASHBOARD DO BANCO
// ===============================

async function carregarDashboard() {

    try {

        const resposta = await fetch(
            "api.php?action=dashboard"
        );

        const dados =
            await resposta.json();

        if (!resposta.ok || !dados.sucesso) {

            throw new Error(
                dados.mensagem ||
                "Não foi possível carregar o dashboard."
            );
        }

        definirTexto(
            "mediaGeral",
            Number(
                dados.media_geral
            ).toFixed(1)
        );

        definirTexto(
            "totalRespostas",
            dados.total_respostas
        );

        definirTexto(
            "taxaParticipacao",
            dados.taxa_participacao + "%"
        );

        definirTexto(
            "ultimaAtualizacao",
            dados.ultima_atualizacao || "—"
        );

        atualizarDashboardOriginal(dados);

    } catch (erro) {

        console.error(
            "Erro no dashboard:",
            erro
        );

        alert(
            erro.message ||
            "Não foi possível carregar os dados."
        );
    }
}

// ===============================
// ATUALIZAR ELEMENTOS
// ===============================

function definirTexto(id, valor) {

    const elemento =
        document.getElementById(id);

    if (elemento) {
        elemento.innerText = valor;
    }
}

// ===============================
// ATUALIZAR DASHBOARD ANTIGO
// ===============================

function atualizarDashboardOriginal(dados) {

    const cards =
        document.querySelectorAll(
            "#telaDashboard .cards .card"
        );

    if (cards.length >= 4) {

        const media =
            cards[0].querySelector("strong");

        const total =
            cards[1].querySelector("strong");

        const taxa =
            cards[2].querySelector("strong");

        const ultima =
            cards[3].querySelector("strong");

        if (media) {

            media.innerText =
                Number(
                    dados.media_geral
                ).toFixed(1);
        }

        if (total) {

            total.innerText =
                dados.total_respostas;
        }

        if (taxa) {

            taxa.innerText =
                dados.taxa_participacao + "%";
        }

        if (ultima) {

            ultima.innerText =
                dados.ultima_atualizacao || "—";
        }
    }

    const blocos =
        document.querySelectorAll(
            "#telaDashboard .duas-colunas .painel"
        );

    if (blocos.length >= 2) {

        atualizarBlocoPontos(
            blocos[0],
            dados.pontos_criticos || []
        );

        atualizarBlocoPontos(
            blocos[1],
            dados.pontos_fortes || []
        );
    }
}

// ===============================
// PONTOS FORTES / CRÍTICOS
// ===============================

function atualizarBlocoPontos(
    bloco,
    pontos
) {

    if (!bloco || !pontos.length) {
        return;
    }

    const linhas =
        bloco.querySelectorAll(
            ".linha-ponto"
        );

    for (
        let i = 0;
        i < linhas.length && i < pontos.length;
        i++
    ) {

        const nome =
            linhas[i].querySelector("span");

        const valor =
            linhas[i].querySelector("b");

        if (nome) {

            nome.innerText =
                pontos[i].nome;
        }

        if (valor) {

            valor.innerText =
                Number(
                    pontos[i].media
                ).toFixed(1);
        }
    }
}

// ===============================
// SAIR
// ===============================

async function sairDashboard() {

    try {

        await fetch(
            "api.php?action=logout"
        );

    } catch (e) {

        console.error(
            "Erro ao sair:",
            e
        );
    }

    mostrarInicio();
}

// ===============================
// MENU
// ===============================

function menuDashboard(botao) {

    const botoes =
        document.getElementsByClassName(
            "menu-item"
        );

    for (
        let i = 0;
        i < botoes.length;
        i++
    ) {

        botoes[i]
            .classList
            .remove("ativo");
    }

    if (botao) {

        botao
            .classList
            .add("ativo");
    }
}