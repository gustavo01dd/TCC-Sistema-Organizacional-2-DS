// ===================== Estado global =====================

var perguntasAtuais = [];   // perguntas do formulário ativo (pesquisa pública)
var formularioAtualId = null;
var notas = {};             // notas indexadas por id da pergunta

var formularioSelecionadoId = null; // qual formulário está sendo visto no dashboard do gestor


// ===================== Navegação entre telas =====================

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

async function mostrarPesquisa() {
    esconderTudo();
    document.getElementById("telaQuestionario").classList.remove("escondido");

    var area = document.getElementById("perguntas");
    area.innerHTML = "<p>Carregando pesquisa...</p>";
    document.getElementById("botaoEnviar").style.display = "";

    try {
        var resposta = await fetch("api.php?action=formulario_ativo");
        var dados = await resposta.json();

        if (!resposta.ok) {
            area.innerHTML = "<p>" + (dados.erro || "Nenhuma pesquisa disponível no momento.") + "</p>";
            document.getElementById("botaoEnviar").style.display = "none";
            document.getElementById("formularioTitulo").innerText = "";
            return;
        }

        formularioAtualId = dados.id;
        perguntasAtuais = dados.perguntas || [];
        notas = {};

        document.getElementById("formularioTitulo").innerText = dados.titulo || "";
        criarPerguntas();
    } catch (erro) {
        area.innerHTML = "<p>Não foi possível carregar a pesquisa. Verifique sua conexão e tente novamente.</p>";
        document.getElementById("botaoEnviar").style.display = "none";
    }
}

function mostrarGestor() {
    esconderTudo();
    document.getElementById("telaGestor").classList.remove("escondido");
}


// ===================== Questionário =====================

function criarPerguntas() {
    var area = document.getElementById("perguntas");

    if (perguntasAtuais.length === 0) {
        area.innerHTML = "<p>Este formulário ainda não tem perguntas cadastradas.</p>";
        document.getElementById("botaoEnviar").style.display = "none";
        return;
    }

    area.innerHTML = "";

    perguntasAtuais.forEach(function (pergunta, i) {
        var notaInicial = notas[pergunta.id] != null ? notas[pergunta.id] : 6;

        area.innerHTML +=
            '<div class="pergunta-card">' +
                '<div class="pergunta-titulo">' +
                    '<span>' + (i + 1) + '. ' + escaparHtml(pergunta.texto) + '</span>' +
                    '<span class="rosto">😐</span>' +
                '</div>' +
                '<input class="range" type="range" min="0" max="10" value="' + notaInicial + '" oninput="mudarNota(' + pergunta.id + ', this.value)">' +
                '<div class="numeros"><span>0<br><small>Insatisfeito</small></span><span>1</span><span>2</span><span>3</span><span>4</span><span>5<br><small>Neutro</small></span><span>6</span><span>7</span><span>8</span><span>9</span><span>10<br><small>Muito satisfeito</small></span></div>' +
                '<div class="nota" id="nota' + pergunta.id + '">' + notaInicial + '</div>' +
                '<div class="satisfacao" id="textoNota' + pergunta.id + '">' + textoNota(notaInicial) + '</div>' +
            '</div>';
    });

    atualizarProgresso();
}

function mudarNota(perguntaId, valor) {
    notas[perguntaId] = Number(valor);
    document.getElementById("nota" + perguntaId).innerText = valor;
    document.getElementById("textoNota" + perguntaId).innerText = textoNota(Number(valor));
    atualizarProgresso();
}

function textoNota(nota) {
    if (nota <= 3) return "😟 Insatisfeito";
    if (nota <= 6) return "😐 Satisfeito";
    if (nota <= 8) return "🙂 Satisfeito";
    return "😄 Muito satisfeito";
}

function atualizarProgresso() {
    var total = perguntasAtuais.length || 1;
    var respondidas = 0;

    perguntasAtuais.forEach(function (p) {
        if (notas[p.id] != null) respondidas++;
    });

    if (respondidas === 0) respondidas = 1;

    document.getElementById("numeroPergunta").innerText = respondidas + " de " + total;
    document.getElementById("barraProgresso").style.width = ((respondidas / total) * 100) + "%";
}

async function enviarPesquisa() {
    if (!formularioAtualId || perguntasAtuais.length === 0) {
        alert("Não há pesquisa carregada para enviar.");
        return;
    }

    var itens = perguntasAtuais.map(function (p) {
        var nota = notas[p.id] != null ? notas[p.id] : 6;
        return { pergunta_id: p.id, nota: nota };
    });

    var comentario = document.getElementById("comentario").value;
    var botao = document.getElementById("botaoEnviar");
    botao.disabled = true;
    botao.innerText = "Enviando...";

    try {
        var resposta = await fetch("api.php?action=enviar_resposta", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                formulario_id: formularioAtualId,
                respostas: itens,
                comentario: comentario
            })
        });

        var dados = await resposta.json();

        if (resposta.ok && dados.sucesso) {
            var total = itens.reduce(function (soma, item) { return soma + item.nota; }, 0);
            var media = (total / itens.length).toFixed(1);
            alert("Pesquisa enviada com sucesso!\nSua média registrada foi: " + media);
            document.getElementById("comentario").value = "";
            mostrarInicio();
        } else {
            alert(dados.erro || "Não foi possível enviar sua resposta. Tente novamente.");
        }
    } catch (erro) {
        alert("Erro de conexão. Verifique sua internet e tente novamente.");
    } finally {
        botao.disabled = false;
        botao.innerText = "Enviar pesquisa";
    }
}


// ===================== Login do gestor =====================

async function entrarGestor() {
    var email = document.getElementById("emailGestor").value;
    var senha = document.getElementById("senhaGestor").value;
    var erro = document.getElementById("erroLogin");

    try {
        var resposta = await fetch("api.php?action=login", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ email: email, senha: senha })
        });

        var dados = await resposta.json();

        if (resposta.ok && dados.sucesso) {
            erro.innerText = "";
            document.getElementById("senhaGestor").value = "";
            esconderTudo();
            document.getElementById("telaDashboard").classList.remove("escondido");
            mostrarView("dashboard", document.querySelector(".menu-item"));
        } else {
            erro.innerText = dados.erro || "Email ou senha incorretos.";
        }
    } catch (e) {
        erro.innerText = "Erro de conexão. Tente novamente.";
    }
}

async function sairDashboard() {
    try {
        await fetch("api.php?action=logout");
    } catch (e) {
        // mesmo se der erro, volta pro início
    }
    formularioSelecionadoId = null;
    mostrarInicio();
}


// ===================== Navegação dentro do dashboard =====================

function mostrarView(nome, botao) {
    var botoes = document.getElementsByClassName("menu-item");
    for (var i = 0; i < botoes.length; i++) botoes[i].classList.remove("ativo");
    if (botao) botao.classList.add("ativo");

    ["viewDashboard", "viewFormularios", "viewComentarios"].forEach(function (id) {
        document.getElementById(id).classList.add("escondido");
    });

    var nomeCapitalizado = nome.charAt(0).toUpperCase() + nome.slice(1);
    document.getElementById("view" + nomeCapitalizado).classList.remove("escondido");

    if (nome === "dashboard") carregarDashboard(formularioSelecionadoId);
    if (nome === "formularios") carregarListaFormularios();
    if (nome === "comentarios") carregarComentarios(formularioSelecionadoId);
}

function mostrarAviso(botao) {
    var botoes = document.getElementsByClassName("menu-item");
    for (var i = 0; i < botoes.length; i++) botoes[i].classList.remove("ativo");
    botao.classList.add("ativo");
    alert("Esta tela está representada no protótipo. A funcionalidade completa será desenvolvida posteriormente.");
}


// ===================== Dashboard =====================

async function carregarDashboard(formularioId) {
    var url = "api.php?action=dashboard";
    if (formularioId) url += "&formulario_id=" + formularioId;

    try {
        var resposta = await fetch(url);
        var dados = await resposta.json();

        if (!resposta.ok || dados.erro) {
            document.getElementById("dashboardTitulo").innerText = dados.erro || "Nenhum formulário encontrado ainda";
            return;
        }

        formularioSelecionadoId = dados.formulario.id;

        document.getElementById("dashboardTitulo").innerText = "Dashboard — " + dados.formulario.titulo;
        document.getElementById("statMediaGeral").innerText = dados.media_geral;
        document.getElementById("statTotalRespostas").innerText = dados.total_respostas;
        document.getElementById("statRespostasDesde").innerText = dados.formulario.data_abertura
            ? "desde " + formatarData(dados.formulario.data_abertura)
            : "\u00A0";

        if (dados.taxa_participacao !== null && dados.taxa_participacao !== undefined) {
            document.getElementById("statTaxaParticipacao").innerText = dados.taxa_participacao + "%";
            document.getElementById("statTaxaDetalhe").innerText =
                dados.total_respostas + " de " + dados.formulario.respondentes_esperados + " esperadas";
        } else {
            document.getElementById("statTaxaParticipacao").innerText = "—";
            document.getElementById("statTaxaDetalhe").innerText = "Nº esperado não informado";
        }

        var agora = new Date();
        document.getElementById("statUltimaAtualizacao").innerText = "Agora";
        document.getElementById("statUltimaAtualizacaoData").innerText = agora.toLocaleString("pt-BR");

        renderizarPontos("pontosCriticos", dados.por_pergunta.slice(0, 2), "↘");
        renderizarPontos("pontosFortes", dados.por_pergunta.slice(-2).reverse(), "↗");

        renderizarDistribuicao(dados.distribuicao);
        renderizarEvolucao(dados.evolucao);
    } catch (erro) {
        console.error("Erro ao carregar dashboard:", erro);
    }
}

function renderizarPontos(containerId, lista, seta) {
    var container = document.getElementById(containerId);
    var comDados = lista.filter(function (p) { return p.media !== null; });

    if (comDados.length === 0) {
        container.innerHTML = '<p class="aviso-vazio">Ainda sem dados suficientes</p>';
        return;
    }

    container.innerHTML = comDados.map(function (p) {
        return '<div class="linha-ponto"><span>' + escaparHtml(p.texto) + '</span><b>' + p.media.toFixed(1) + ' ' + seta + '</b></div>';
    }).join("");
}

function renderizarDistribuicao(dist) {
    var total = dist.insatisfeito + dist.neutro + dist.satisfeito;
    var pizza = document.getElementById("graficoPizza");

    if (total === 0) {
        pizza.style.background = "#e7eaf0";
        return;
    }

    var pSat = Math.round((dist.satisfeito / total) * 100);
    var pNeu = Math.round((dist.neutro / total) * 100);

    pizza.style.background =
        "conic-gradient(#199b62 0 " + pSat + "%, #c7a000 " + pSat + "% " + (pSat + pNeu) + "%, #c63e3e " + (pSat + pNeu) + "% 100%)";
}

function renderizarEvolucao(evolucao) {
    var svgContainer = document.getElementById("graficoEvolucao");
    var legenda = document.getElementById("legendaEvolucao");
    var textoTendencia = document.getElementById("textoTendencia");

    if (!evolucao || evolucao.length === 0) {
        svgContainer.innerHTML = '<p class="aviso-vazio" style="text-align:center;padding-top:70px;">Sem respostas suficientes ainda</p>';
        legenda.innerHTML = "";
        textoTendencia.innerText = "";
        return;
    }

    var xMin = 45, xMax = 480, yTopo = 20, yBase = 150;
    var totais = evolucao.map(function (e) { return Number(e.total); });
    var maior = Math.max.apply(null, totais.concat([1]));

    var pontos = evolucao.map(function (e, i) {
        var x = evolucao.length === 1 ? xMin : xMin + (i / (evolucao.length - 1)) * (xMax - xMin);
        var y = yBase - (Number(e.total) / maior) * (yBase - yTopo);
        return { x: x, y: y };
    });

    var polylinePontos = pontos.map(function (p) { return p.x.toFixed(1) + "," + p.y.toFixed(1); }).join(" ");
    var circulos = pontos.map(function (p) {
        return '<circle cx="' + p.x.toFixed(1) + '" cy="' + p.y.toFixed(1) + '" r="5" fill="#1261d6"/>';
    }).join("");

    svgContainer.innerHTML =
        '<svg viewBox="0 0 500 180" preserveAspectRatio="none">' +
            '<line x1="45" y1="150" x2="480" y2="150" stroke="#ddd"/>' +
            '<line x1="45" y1="20" x2="45" y2="150" stroke="#ddd"/>' +
            '<polyline points="' + polylinePontos + '" fill="none" stroke="#1261d6" stroke-width="4"/>' +
            circulos +
        '</svg>';

    legenda.innerHTML = evolucao.map(function (e) {
        var data = new Date(e.dia + "T00:00:00");
        return "<span>" + data.toLocaleDateString("pt-BR", { day: "2-digit", month: "2-digit" }) + "</span>";
    }).join("");

    if (totais.length > 1) {
        textoTendencia.innerText = (totais[totais.length - 1] >= totais[0] ? "↑ Tendência de alta" : "↓ Tendência de queda") + " no período";
    } else {
        textoTendencia.innerText = "";
    }
}


// ===================== Formulários (gestão) =====================

async function carregarListaFormularios() {
    var container = document.getElementById("listaFormularios");
    try {
        var resposta = await fetch("api.php?action=formularios");
        var dados = await resposta.json();

        if (!resposta.ok) {
            container.innerHTML = '<p class="aviso-vazio">Não foi possível carregar os formulários</p>';
            return;
        }

        renderizarListaFormularios(dados);
    } catch (e) {
        container.innerHTML = '<p class="aviso-vazio">Erro de conexão</p>';
    }
}

function renderizarListaFormularios(lista) {
    var container = document.getElementById("listaFormularios");

    if (lista.length === 0) {
        container.innerHTML = '<p class="aviso-vazio">Nenhum formulário criado ainda</p>';
        return;
    }

    container.innerHTML = lista.map(function (f) {
        var statusInfo = {
            ativo: ["status-ativo", "Ativo"],
            encerrado: ["status-encerrado", "Encerrado"],
            rascunho: ["status-rascunho", "Rascunho"]
        }[f.status] || ["status-rascunho", f.status];

        var acoes = "";
        if (f.status !== "ativo") {
            acoes += '<button class="botao-pequeno" onclick="mudarStatusFormulario(' + f.id + ', \'ativo\')">Ativar</button>';
        }
        if (f.status === "ativo") {
            acoes += '<button class="botao-pequeno botao-encerrar" onclick="mudarStatusFormulario(' + f.id + ', \'encerrado\')">Encerrar</button>';
        }
        acoes += '<button class="botao-pequeno" onclick="verDashboardFormulario(' + f.id + ')">Ver dashboard</button>';

        var detalhe = f.total_respostas + " resposta" + (f.total_respostas === 1 ? "" : "s");
        if (f.respondentes_esperados) detalhe += " de " + f.respondentes_esperados + " esperadas";

        return (
            '<div class="item-formulario">' +
                "<div>" +
                    "<b>" + escaparHtml(f.titulo) + "</b>" +
                    '<span class="' + statusInfo[0] + '">' + statusInfo[1] + "</span>" +
                    "<br><small>" + detalhe + "</small>" +
                "</div>" +
                '<div class="acoes-formulario">' + acoes + "</div>" +
            "</div>"
        );
    }).join("");
}

function paraDatetimeMysql(valor) {
    if (!valor) return null;
    return valor.replace("T", " ") + ":00";
}
async function criarFormulario() {
    var titulo = document.getElementById("novoFormTitulo").value.trim();
    var esperados = document.getElementById("novoFormEsperados").value;
    var perguntasTexto = document.getElementById("novoFormPerguntas").value
        .split("\n")
        .map(function (l) { return l.trim(); })
        .filter(function (l) { return l.length > 0; });

    var erroEl = document.getElementById("erroNovoForm");

    if (!titulo || perguntasTexto.length === 0) {
        erroEl.innerText = "Preencha o título e ao menos uma pergunta.";
        return;
    }

    try {
        var resposta = await fetch("api.php?action=criar_formulario", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                titulo: titulo,
                respondentes_esperados: esperados ? Number(esperados) : null,
                perguntas: perguntasTexto,
                data_inicio: paraDatetimeMysql(document.getElementById("novoFormInicio").value),
                data_termino: paraDatetimeMysql(document.getElementById("novoFormFim").value)   
            })
        });

        var dados = await resposta.json();

        if (resposta.ok && dados.sucesso) {
            erroEl.innerText = "";
            document.getElementById("novoFormTitulo").value = "";
            document.getElementById("novoFormEsperados").value = "";
            document.getElementById("novoFormInicio").value = "";
            document.getElementById("novoFormFim").value = "";
            document.getElementById("novoFormPerguntas").value = "";
            carregarListaFormularios();
        } else {
            erroEl.innerText = dados.erro || "Não foi possível criar o formulário.";
        }
    } catch (e) {
        erroEl.innerText = "Erro de conexão.";
    }
}

async function mudarStatusFormulario(id, novoStatus) {
    if (novoStatus === "ativo" && !confirm("Ativar este formulário vai encerrar o formulário ativo atual (se houver). Continuar?")) {
        return;
    }

    try {
        await fetch("api.php?action=alterar_status_formulario", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ formulario_id: id, status: novoStatus })
        });
        carregarListaFormularios();
    } catch (e) {
        alert("Não foi possível atualizar o status. Tente novamente.");
    }
}

function verDashboardFormulario(id) {
    formularioSelecionadoId = id;
    mostrarView("dashboard", document.querySelectorAll(".menu-item")[0]);
}


// ===================== Comentários =====================

async function carregarComentarios(formularioId) {
    var container = document.getElementById("listaComentarios");
    var url = "api.php?action=comentarios";
    if (formularioId) url += "&formulario_id=" + formularioId;

    try {
        var resposta = await fetch(url);
        var dados = await resposta.json();

        if (!resposta.ok || dados.length === 0) {
            container.innerHTML = '<p class="aviso-vazio">Nenhum comentário recebido ainda</p>';
            return;
        }

        container.innerHTML = dados.map(function (c) {
            var data = formatarData(c.criada_em);
            return (
                '<div class="comentario-item">' +
                    "<p>\u201C" + escaparHtml(c.comentario) + "\u201D</p>" +
                    "<small>" + data + "</small>" +
                "</div>"
            );
        }).join("");
    } catch (e) {
        container.innerHTML = '<p class="aviso-vazio">Erro de conexão</p>';
    }
}


// ===================== Utilidades =====================

function formatarData(dataStr) {
    if (!dataStr) return "";
    var d = new Date(dataStr.replace(" ", "T"));
    if (isNaN(d.getTime())) return dataStr;
    return d.toLocaleDateString("pt-BR");
}

function escaparHtml(texto) {
    var div = document.createElement("div");
    div.innerText = texto == null ? "" : String(texto);
    return div.innerHTML;
}