// ============================================================
// Pesquisa de Clima Organizacional - front-end (JavaScript puro)
// Conversa com o back-end em api.php usando fetch()
// ============================================================

// ===================== Estado =====================

var perguntasAtuais = [];           // perguntas da pesquisa aberta (tela do funcionário)
var formularioAtualId = null;       // pesquisa que o funcionário está respondendo
var respostas = {};                 // respostas por id da pergunta: {nota: n} ou {opcao: n}
var usuarioAtualId = null;          // id de quem está logado (usado no rascunho)
var nomeUsuarioAtual = "";

var formularioSelecionadoId = null; // formulário que o gestor está analisando
var ultimoDashboard = null;         // últimos dados do dashboard (usados no PNG)
var ultimosResultados = [];         // últimos resultados por pergunta (filtro de categoria)
var minimoAnonimato = 3;
var ultimaListaFuncionarios = [];   // última lista da tela Funcionários
var meuId = null;                   // id do gestor logado
var funcionarioEditandoId = null;   // null = cadastrando; número = editando esse id
var formularioEditandoId = null;    // null = criando; número = editando esse formulário
var perguntasEditor = [];           // perguntas do editor de formulários

var TELAS = ["telaPesquisa", "telaLogin", "telaTermo", "telaQuestionario", "telaObrigado", "telaDashboard"];
var VIEWS = ["dashboard", "resultados", "comparar", "formularios", "comentarios", "funcionarios", "relatorios"];
var CATEGORIAS_SUGERIDAS = ["Ambiente", "Liderança", "Comunicação", "Recursos e infraestrutura", "Desenvolvimento", "Engajamento", "Qualidade de vida", "Reconhecimento"];
var HORAS_RASCUNHO = 24;

document.addEventListener("DOMContentLoaded", iniciar);

function iniciar() {
    aplicarTema(temaAtual());
    carregarAvisoPesquisa();
    limparRascunhosAntigos();

    // se a pessoa nunca escolheu um tema, acompanha o tema do sistema
    try {
        window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", function (evento) {
            if (!localStorage.getItem("tema")) {
                aplicarTema(evento.matches ? "escuro" : "claro");
            }
        });
    } catch (e) {
        // navegador antigo: fica com o tema atual
    }
}


// ===================== Tema claro / escuro =====================

function temaAtual() {
    return document.documentElement.getAttribute("data-tema") === "escuro" ? "escuro" : "claro";
}

function aplicarTema(tema) {
    var escuro = tema === "escuro";
    document.documentElement.setAttribute("data-tema", escuro ? "escuro" : "claro");

    var dica = escuro ? "Mudar para o modo claro" : "Mudar para o modo escuro";
    var botao = document.getElementById("botaoTema");
    botao.innerText = escuro ? "☀" : "☾";
    botao.title = dica;
    botao.setAttribute("aria-label", dica);

    document.getElementById("botaoTemaMenu").innerText = escuro ? "☀   Modo claro" : "☾   Modo escuro";
    document.querySelector('meta[name="theme-color"]').setAttribute("content", escuro ? "#0a1733" : "#0b56c9");
}

function alternarTema() {
    var novoTema = temaAtual() === "escuro" ? "claro" : "escuro";
    aplicarTema(novoTema);
    try {
        localStorage.setItem("tema", novoTema);
    } catch (e) {
        // sem permissão para salvar: o tema vale só até fechar a página
    }
}


// ===================== Comunicação com a API =====================

// GET quando não há corpo; POST com JSON quando há
async function chamarApi(acao, corpo, parametros) {
    var url = "api.php?action=" + acao + (parametros || "");
    var opcoes = {};
    if (corpo !== undefined) {
        opcoes = {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(corpo)
        };
    }
    var resposta = await fetch(url, opcoes);
    var dados = {};
    try {
        dados = await resposta.json();
    } catch (e) {
        dados = {};
    }
    return { ok: resposta.ok, status: resposta.status, dados: dados };
}

function sessaoExpirada(retorno) {
    if (retorno.status === 401) {
        alert("Sua sessão expirou. Entre novamente.");
        mostrarLogin();
        return true;
    }
    return false;
}

function parametroFormulario() {
    return formularioSelecionadoId ? "&formulario_id=" + formularioSelecionadoId : "";
}

// acessibilidade: mensagem lida pelos leitores de tela
function anunciar(texto) {
    var regiao = document.getElementById("avisoLeitor");
    regiao.textContent = "";
    setTimeout(function () { regiao.textContent = texto; }, 50);
}

function focarTitulo(container) {
    var titulo = container.querySelector('[tabindex="-1"]');
    if (titulo) {
        titulo.focus({ preventScroll: true });
    }
}


// ===================== Tela inicial: aviso da pesquisa (RF08) =====================

async function carregarAvisoPesquisa() {
    var aviso = document.getElementById("avisoPesquisa");
    try {
        var r = await chamarApi("status_pesquisa");
        var d = r.dados;
        if (d.situacao === "aberta") {
            aviso.className = "aviso-pesquisa aberta";
            aviso.innerText = "📢 Pesquisa aberta: " + d.titulo +
                (d.data_fechamento ? ". Responda até " + formatarDataHora(d.data_fechamento) + "." : ".");
        } else if (d.situacao === "encerrada") {
            aviso.className = "aviso-pesquisa encerrada";
            aviso.innerText = "🔔 A pesquisa “" + d.titulo + "” foi encerrada em " +
                formatarData(d.data_fechamento) + ". Obrigado a todos que participaram!";
        } else {
            aviso.className = "aviso-pesquisa escondido";
        }
    } catch (e) {
        aviso.className = "aviso-pesquisa escondido";
    }
}


// ===================== Navegação entre telas =====================

function mostrarTela(id) {
    TELAS.forEach(function (tela) {
        document.getElementById(tela).classList.add("escondido");
    });
    var tela = document.getElementById(id);
    tela.classList.remove("escondido");

    // no painel do gestor o botão de tema fica dentro do menu
    document.getElementById("botaoTema").classList.toggle("escondido", id === "telaDashboard");
    window.scrollTo(0, 0);
    if (id !== "telaDashboard") {
        focarTitulo(tela);
    }
}

function mostrarInicio() {
    mostrarTela("telaPesquisa");
    carregarAvisoPesquisa();
}

function mostrarLogin() {
    mostrarTela("telaLogin");
    document.getElementById("erroLogin").innerText = "";
    document.getElementById("emailLogin").focus();
}

// Login único: a API responde o perfil e ele decide a próxima tela
async function entrar() {
    var email = document.getElementById("emailLogin").value.trim();
    var senha = document.getElementById("senhaLogin").value;
    var erro = document.getElementById("erroLogin");
    var botao = document.getElementById("botaoEntrar");

    erro.innerText = "";
    if (!email || !senha) {
        erro.innerText = "Informe o email e a senha.";
        return;
    }

    botao.disabled = true;
    try {
        var r = await chamarApi("login", { email: email, senha: senha });
        if (!r.ok || !r.dados.sucesso) {
            erro.innerText = r.dados.erro || "Email ou senha incorretos.";
            return;
        }
        document.getElementById("senhaLogin").value = "";
        usuarioAtualId = Number(r.dados.id);
        nomeUsuarioAtual = r.dados.nome || "";
        if (r.dados.perfil === "gestor") {
            abrirPainelGestor(r.dados.nome);
        } else if (r.dados.precisa_termo) {
            mostrarTermo();
        } else {
            mostrarPesquisa();
        }
    } catch (e) {
        erro.innerText = "Erro de conexão. Tente novamente.";
    } finally {
        botao.disabled = false;
    }
}

// encerra a sessão no servidor sem mudar de tela
async function encerrarSessao() {
    try {
        await chamarApi("logout");
    } catch (e) {
        // segue mesmo sem conexão
    }
    formularioSelecionadoId = null;
    ultimoDashboard = null;
    meuId = null;
    usuarioAtualId = null;
    nomeUsuarioAtual = "";
    cancelarEdicaoFuncionario();
    cancelarEdicaoFormulario();
}

async function sair() {
    apagarRascunho();
    await encerrarSessao();
    mostrarInicio();
}


// ===================== Termo de consentimento (RNF09) =====================

function mostrarTermo() {
    mostrarTela("telaTermo");
    document.getElementById("aceiteTermo").checked = false;
    document.getElementById("botaoAceitarTermo").disabled = true;
    document.getElementById("erroTermo").innerText = "";
}

async function aceitarTermo() {
    var erro = document.getElementById("erroTermo");
    if (!document.getElementById("aceiteTermo").checked) {
        erro.innerText = "Marque a caixa para confirmar que leu e concorda com o termo.";
        return;
    }
    try {
        var r = await chamarApi("aceitar_termo", {});
        if (sessaoExpirada(r)) return;
        if (!r.ok) {
            erro.innerText = r.dados.erro || "Não foi possível registrar o aceite.";
            return;
        }
        mostrarPesquisa();
    } catch (e) {
        erro.innerText = "Erro de conexão. Tente novamente.";
    }
}


// ===================== Rascunho no navegador =====================
// Fica só neste navegador, expira em 24 horas e é apagado ao sair ou enviar.

function chaveRascunho() {
    if (!usuarioAtualId || !formularioAtualId) return null;
    return "rascunho:" + usuarioAtualId + ":" + formularioAtualId;
}

function salvarRascunho() {
    var chave = chaveRascunho();
    if (!chave) return;
    try {
        localStorage.setItem(chave, JSON.stringify({
            salvo: Date.now(),
            respostas: respostas,
            comentario: document.getElementById("comentario").value
        }));
        document.getElementById("avisoRascunho").classList.remove("escondido");
    } catch (e) {
        // navegador sem armazenamento: segue sem rascunho
    }
}

function carregarRascunho() {
    var chave = chaveRascunho();
    if (!chave) return null;
    try {
        var dados = JSON.parse(localStorage.getItem(chave) || "null");
        if (!dados) return null;
        if (Date.now() - dados.salvo > HORAS_RASCUNHO * 3600 * 1000) {
            localStorage.removeItem(chave);
            return null;
        }
        return dados;
    } catch (e) {
        return null;
    }
}

function apagarRascunho() {
    var chave = chaveRascunho();
    if (!chave) return;
    try {
        localStorage.removeItem(chave);
    } catch (e) {
        // nada a fazer
    }
}

function limparRascunhosAntigos() {
    try {
        Object.keys(localStorage).forEach(function (chave) {
            if (chave.indexOf("rascunho:") !== 0) return;
            var dados = JSON.parse(localStorage.getItem(chave) || "null");
            if (!dados || Date.now() - dados.salvo > HORAS_RASCUNHO * 3600 * 1000) {
                localStorage.removeItem(chave);
            }
        });
    } catch (e) {
        // nada a fazer
    }
}


// ===================== Questionário (funcionário) =====================

async function mostrarPesquisa() {
    mostrarTela("telaQuestionario");
    document.getElementById("saudacaoFuncionario").innerText = nomeUsuarioAtual ? "Olá, " + nomeUsuarioAtual + "!" : "Pesquisa";
    document.getElementById("formularioTitulo").innerText = "";
    document.getElementById("blocoEnvio").classList.add("escondido");
    document.getElementById("avisoRascunho").classList.add("escondido");
    document.getElementById("comentario").value = "";

    var area = document.getElementById("perguntas");
    area.innerHTML = '<div class="mensagem-pesquisa">Carregando pesquisa...</div>';

    perguntasAtuais = [];
    formularioAtualId = null;
    respostas = {};
    atualizarProgresso();

    try {
        var r = await chamarApi("formulario_ativo");
        if (sessaoExpirada(r)) return;

        if (r.status === 403 && r.dados.precisa_termo) {
            mostrarTermo();
            return;
        }
        if (!r.ok) {
            area.innerHTML = '<div class="mensagem-pesquisa" role="status">' +
                escaparHtml(r.dados.erro || "Nenhuma pesquisa disponível no momento.") + '</div>';
            return;
        }

        formularioAtualId = Number(r.dados.id);
        perguntasAtuais = r.dados.perguntas || [];
        document.getElementById("formularioTitulo").innerText = r.dados.titulo || "";

        var rascunho = carregarRascunho();
        if (rascunho) {
            respostas = rascunho.respostas || {};
            document.getElementById("comentario").value = rascunho.comentario || "";
            document.getElementById("avisoRascunho").classList.remove("escondido");
        }

        criarPerguntas();

        if (perguntasAtuais.length > 0) {
            document.getElementById("blocoEnvio").classList.remove("escondido");
        }
        if (rascunho) {
            anunciar("Suas respostas anteriores foram recuperadas do rascunho.");
        }
    } catch (e) {
        area.innerHTML = '<div class="mensagem-pesquisa">Não foi possível carregar a pesquisa. Verifique sua conexão e tente novamente.</div>';
    }
}

function criarPerguntas() {
    var area = document.getElementById("perguntas");

    if (perguntasAtuais.length === 0) {
        area.innerHTML = '<div class="mensagem-pesquisa">Esta pesquisa ainda não tem perguntas cadastradas.</div>';
        atualizarProgresso();
        return;
    }

    area.innerHTML = perguntasAtuais.map(function (pergunta, i) {
        var id = Number(pergunta.id);
        var cabecalho = '<p class="pergunta-numero">Pergunta ' + (i + 1) + ' de ' + perguntasAtuais.length +
            (pergunta.categoria ? ' <span class="categoria-chip">' + escaparHtml(pergunta.categoria) + '</span>' : '') + '</p>';

        if (pergunta.tipo === "sim_nao" || pergunta.tipo === "multipla") {
            var rotulos = pergunta.tipo === "sim_nao" ? [["Sim", 1], ["Não", 0]] :
                (pergunta.opcoes || []).map(function (texto, k) { return [texto, k]; });
            var escolhida = respostas[id] ? respostas[id].opcao : null;

            return '<div class="pergunta-card" id="card-' + id + '">' +
                cabecalho +
                '<fieldset class="grupo-opcoes">' +
                '<legend class="pergunta-titulo">' + escaparHtml(pergunta.texto) + '</legend>' +
                '<div class="opcoes-resposta' + (pergunta.tipo === "sim_nao" ? ' sim-nao' : '') + '">' +
                rotulos.map(function (r) {
                    var marcada = escolhida === r[1];
                    return '<label class="opcao-resposta' + (marcada ? ' selecionada' : '') + '">' +
                        '<input type="radio" name="pergunta-' + id + '" value="' + r[1] + '"' + (marcada ? ' checked' : '') +
                        ' onchange="escolherOpcao(' + id + ', ' + r[1] + ', this)">' +
                        '<span>' + escaparHtml(r[0]) + '</span></label>';
                }).join("") +
                '</div></fieldset></div>';
        }

        // nota de 0 a 10; no celular a régua mostra só 0, 5 e 10
        var nota = respostas[id] && respostas[id].nota != null ? respostas[id].nota : 6;
        var regua = '<div class="numeros" aria-hidden="true">' +
            '<span>0<br><small>Insatisfeito</small></span>' +
            '<span class="num-meio">1</span><span class="num-meio">2</span>' +
            '<span class="num-meio">3</span><span class="num-meio">4</span>' +
            '<span>5<br><small>Neutro</small></span>' +
            '<span class="num-meio">6</span><span class="num-meio">7</span>' +
            '<span class="num-meio">8</span><span class="num-meio">9</span>' +
            '<span>10<br><small>Muito satisfeito</small></span>' +
            '</div>';

        return '<div class="pergunta-card" id="card-' + id + '">' +
            cabecalho +
            '<h2 class="pergunta-titulo" id="titulo-' + id + '">' + escaparHtml(pergunta.texto) + '</h2>' +
            '<input type="range" min="0" max="10" step="1" value="' + nota + '" class="range" ' +
            'aria-labelledby="titulo-' + id + '" aria-valuetext="' + nota + ', ' + textoNota(nota).replace(/^\S+\s/, "") + '" ' +
            'style="' + corPreenchimento(nota) + '" ' +
            'oninput="mudarNota(' + id + ', this.value, this)">' +
            regua +
            '<div class="nota" aria-hidden="true">' +
            '<div class="nota-grande" id="nota-' + id + '">' + nota + '</div>' +
            '<div class="satisfacao" id="texto-' + id + '">' + textoNota(nota) + '</div>' +
            '</div>' +
            '</div>';
    }).join("");

    atualizarProgresso();
}

function corPreenchimento(valor) {
    var porcentagem = (Number(valor) / 10) * 100;
    return "background: linear-gradient(to right, var(--primaria) 0%, var(--primaria) " + porcentagem +
        "%, var(--trilho) " + porcentagem + "%, var(--trilho) 100%);";
}

function mudarNota(perguntaId, valor, elemento) {
    var nota = Number(valor);
    respostas[perguntaId] = { nota: nota };

    document.getElementById("nota-" + perguntaId).innerText = nota;
    document.getElementById("texto-" + perguntaId).innerText = textoNota(nota);
    elemento.style.cssText = corPreenchimento(nota);
    elemento.setAttribute("aria-valuetext", nota + ", " + textoNota(nota).replace(/^\S+\s/, ""));

    salvarRascunho();
    atualizarProgresso();
}

function escolherOpcao(perguntaId, indice, elemento) {
    respostas[perguntaId] = { opcao: Number(indice) };
    var card = document.getElementById("card-" + perguntaId);
    card.classList.remove("pergunta-pendente");
    card.querySelectorAll(".opcao-resposta").forEach(function (rotulo) {
        rotulo.classList.toggle("selecionada", rotulo.contains(elemento));
    });
    salvarRascunho();
    atualizarProgresso();
}

// mesmas faixas da legenda do dashboard
function textoNota(nota) {
    if (nota <= 3) return "😞 Insatisfeito";
    if (nota <= 6) return "😐 Neutro";
    if (nota <= 8) return "🙂 Satisfeito";
    return "😄 Muito satisfeito";
}

function atualizarProgresso() {
    var total = perguntasAtuais.length;
    var texto = document.getElementById("numeroPergunta");
    var barra = document.getElementById("barraProgresso");
    var container = document.getElementById("barraProgressoContainer");

    if (total === 0) {
        texto.innerText = "";
        barra.style.width = "0%";
        container.setAttribute("aria-valuenow", "0");
        return;
    }

    var respondidas = perguntasAtuais.filter(function (p) {
        return respostas[Number(p.id)] != null;
    }).length;
    var porcentagem = Math.round((respondidas / total) * 100);

    texto.innerText = respondidas + " de " + total + " respondidas";
    barra.style.width = porcentagem + "%";
    container.setAttribute("aria-valuenow", String(porcentagem));
    container.setAttribute("aria-valuetext", respondidas + " de " + total + " perguntas respondidas");
}

async function enviarPesquisa() {
    if (!formularioAtualId || perguntasAtuais.length === 0) {
        alert("Não há pesquisa carregada para enviar.");
        return;
    }

    // perguntas de escolha não têm valor padrão: precisam ser respondidas
    var pendentes = perguntasAtuais.filter(function (p) {
        return p.tipo !== "nota" && respostas[Number(p.id)] == null;
    });
    if (pendentes.length > 0) {
        pendentes.forEach(function (p) {
            document.getElementById("card-" + p.id).classList.add("pergunta-pendente");
        });
        var primeira = document.getElementById("card-" + pendentes[0].id);
        primeira.scrollIntoView({ behavior: "smooth", block: "center" });
        var radio = primeira.querySelector("input");
        if (radio) radio.focus({ preventScroll: true });
        alert(pendentes.length === 1
            ? "Falta responder 1 pergunta de escolha (destacada em vermelho)."
            : "Faltam responder " + pendentes.length + " perguntas de escolha (destacadas em vermelho).");
        return;
    }

    // RN04: depois de enviada não dá para alterar, então confirma antes
    var naoMexidas = perguntasAtuais.filter(function (p) {
        return p.tipo === "nota" && respostas[Number(p.id)] == null;
    }).length;

    var aviso = "Depois de enviadas, as respostas não podem ser alteradas.";
    if (naoMexidas > 0) {
        aviso = "Você não mexeu em " + naoMexidas +
            (naoMexidas === 1 ? " pergunta de nota, que será enviada" : " perguntas de nota, que serão enviadas") +
            " com a nota 6.\n\n" + aviso;
    }
    if (!confirm(aviso + "\n\nDeseja enviar agora?")) return;

    var notas = [];
    var itens = perguntasAtuais.map(function (p) {
        var id = Number(p.id);
        if (p.tipo === "nota") {
            var nota = respostas[id] && respostas[id].nota != null ? respostas[id].nota : 6;
            notas.push(nota);
            return { pergunta_id: id, nota: nota };
        }
        return { pergunta_id: id, opcao: respostas[id].opcao };
    });
    var media = notas.length ? (notas.reduce(function (a, b) { return a + b; }, 0) / notas.length).toFixed(1) : null;

    var botao = document.getElementById("botaoEnviar");
    botao.disabled = true;
    botao.innerText = "Enviando...";

    try {
        var r = await chamarApi("enviar_resposta", {
            formulario_id: formularioAtualId,
            respostas: itens,
            comentario: document.getElementById("comentario").value
        });
        if (sessaoExpirada(r)) return;

        if (r.ok && r.dados.sucesso) {
            apagarRascunho();
            await encerrarSessao();
            mostrarObrigado(media, r.dados.data);
        } else if (r.status === 403 && r.dados.precisa_termo) {
            mostrarTermo();
        } else {
            alert(r.dados.erro || "Não foi possível enviar sua resposta. Tente novamente.");
        }
    } catch (e) {
        alert("Erro de conexão. Verifique sua internet e tente novamente. Suas respostas continuam salvas neste navegador.");
    } finally {
        botao.disabled = false;
        botao.innerText = "Enviar respostas";
    }
}

function mostrarObrigado(media, data) {
    var texto = "Sua participação foi registrada em " + formatarData(data || new Date().toISOString().slice(0, 10)) + ".";
    if (media !== null) {
        texto += " A média das suas notas foi " + media + ".";
    }
    document.getElementById("textoObrigado").innerText = texto;
    mostrarTela("telaObrigado");
}


// ===================== Painel do gestor: navegação =====================

function abrirPainelGestor(nome) {
    mostrarTela("telaDashboard");
    document.getElementById("nomeGestor").innerText = nome ? "Olá, " + nome : "";
    formularioSelecionadoId = null;
    ultimoDashboard = null;
    mostrarView("dashboard", document.querySelector('.menu-item[data-view="dashboard"]'));
}

function mostrarView(nome, botao) {
    document.querySelectorAll(".menu-item").forEach(function (b) {
        b.classList.remove("ativo");
        b.removeAttribute("aria-current");
    });
    if (botao) {
        botao.classList.add("ativo");
        botao.setAttribute("aria-current", "page");
    }

    VIEWS.forEach(function (view) {
        document.getElementById("view" + capitalizar(view)).classList.add("escondido");
    });
    var secao = document.getElementById("view" + capitalizar(nome));
    secao.classList.remove("escondido");
    window.scrollTo(0, 0);
    focarTitulo(secao);

    if (nome === "dashboard") carregarDashboard();
    if (nome === "resultados") carregarResultados();
    if (nome === "comparar") carregarOpcoesComparacao();
    if (nome === "formularios") { carregarListaFormularios(); if (!formularioEditandoId && perguntasEditor.length === 0) cancelarEdicaoFormulario(); }
    if (nome === "comentarios") carregarComentarios();
    if (nome === "funcionarios") carregarListaFuncionarios();
    if (nome === "relatorios") carregarLogsAcesso();
}

function irParaView(nome) {
    mostrarView(nome, document.querySelector('.menu-item[data-view="' + nome + '"]'));
}

function capitalizar(texto) {
    return texto.charAt(0).toUpperCase() + texto.slice(1);
}

function rotuloStatus(status) {
    if (status === "ativo") return "Ativo";
    if (status === "encerrado") return "Encerrado";
    return "Rascunho";
}

function rotuloTipo(tipo) {
    if (tipo === "sim_nao") return "Sim ou não";
    if (tipo === "multipla") return "Múltipla escolha";
    return "Nota de 0 a 10";
}

function classeMedia(media) {
    if (media === null || media === undefined) return "";
    return media >= 7 ? "media-boa" : media >= 4 ? "media-neutra" : "media-ruim";
}


// ===================== Dashboard =====================

async function carregarDashboard() {
    try {
        var r = await chamarApi("dashboard", undefined, parametroFormulario());
        if (sessaoExpirada(r)) return;

        if (!r.ok || r.dados.erro) {
            limparDashboard(r.dados.erro || "Nenhum formulário cadastrado ainda");
            return;
        }

        var d = r.dados;
        formularioSelecionadoId = Number(d.formulario.id);
        ultimoDashboard = d;

        document.getElementById("dashboardTitulo").innerText =
            "Dashboard — " + d.formulario.titulo + " (" + rotuloStatus(d.formulario.status) + ")";

        document.getElementById("statMediaGeral").innerText =
            d.media_geral !== null ? Number(d.media_geral).toFixed(1) : "—";

        document.getElementById("statTotalRespostas").innerText = d.total_respostas;
        document.getElementById("statRespostasDesde").innerText =
            d.formulario.data_abertura ? "desde " + formatarData(d.formulario.data_abertura) : " ";

        if (d.taxa_participacao !== null) {
            document.getElementById("statTaxaParticipacao").innerText = d.taxa_participacao + "%";
            document.getElementById("statTaxaDetalhe").innerText = d.total_respostas + " de " + d.respondentes_esperados +
                (d.formulario.respondentes_esperados ? " esperadas" : " funcionários");
        } else {
            document.getElementById("statTaxaParticipacao").innerText = "—";
            document.getElementById("statTaxaDetalhe").innerText = "nenhum funcionário cadastrado";
        }

        var agora = new Date();
        document.getElementById("statUltimaAtualizacao").innerText = "Hoje";
        document.getElementById("statUltimaAtualizacaoData").innerText =
            agora.toLocaleDateString("pt-BR") + ", " +
            agora.toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" });

        var aviso = document.getElementById("avisoAnonimatoDashboard");
        if (d.dados_ocultos) {
            aviso.innerText = "🔒 Este formulário tem " + d.total_respostas + " resposta(s). Para proteger o anonimato, " +
                "médias, gráficos e comentários só aparecem a partir de " + d.minimo_anonimato + " respostas.";
            aviso.classList.remove("escondido");
        } else {
            aviso.classList.add("escondido");
        }

        var comMedia = (d.por_pergunta || []).filter(function (p) { return p.tipo === "nota" && p.media !== null; });
        renderizarPontos("pontosCriticos", comMedia.slice(0, 2), "↘");
        // com até 2 perguntas, elas já aparecem como pontos críticos
        renderizarPontos("pontosFortes", comMedia.length > 2 ? comMedia.slice(-2).reverse() : [], "↗");
        renderizarCategorias(d.categorias || []);
        renderizarDistribuicao(d.distribuicao);
        renderizarEvolucao(d.evolucao || []);
    } catch (e) {
        console.error("Erro ao carregar dashboard:", e);
        limparDashboard("Erro ao carregar o dashboard");
    }
}

function limparDashboard(mensagem) {
    ultimoDashboard = null;
    document.getElementById("dashboardTitulo").innerText = mensagem;
    ["statMediaGeral", "statTotalRespostas", "statTaxaParticipacao", "statUltimaAtualizacao"].forEach(function (id) {
        document.getElementById(id).innerText = "—";
    });
    ["statRespostasDesde", "statTaxaDetalhe", "statUltimaAtualizacaoData"].forEach(function (id) {
        document.getElementById(id).innerText = " ";
    });
    document.getElementById("avisoAnonimatoDashboard").classList.add("escondido");
    renderizarPontos("pontosCriticos", [], "");
    renderizarPontos("pontosFortes", [], "");
    renderizarCategorias([]);
    renderizarDistribuicao({ insatisfeito: 0, neutro: 0, satisfeito: 0 });
    renderizarEvolucao([]);
}

function renderizarPontos(containerId, lista, seta) {
    var container = document.getElementById(containerId);
    if (lista.length === 0) {
        container.innerHTML = '<p class="aviso-vazio">Ainda sem dados suficientes.</p>';
        return;
    }
    container.innerHTML = lista.map(function (p) {
        return '<div class="linha-ponto"><span>' + escaparHtml(p.texto) + '</span><b>' +
            Number(p.media).toFixed(1) + ' <span aria-hidden="true">' + seta + '</span></b></div>';
    }).join("");
}

// barras horizontais com a média de cada categoria
function renderizarCategorias(categorias) {
    var container = document.getElementById("graficoCategorias");
    if (categorias.length === 0) {
        container.innerHTML = '<p class="aviso-vazio">Ainda sem dados suficientes.</p>';
        return;
    }
    var ordenadas = categorias.slice().sort(function (a, b) { return Number(b.media) - Number(a.media); });
    container.innerHTML = '<ul class="lista-categorias">' + ordenadas.map(function (c) {
        var media = Number(c.media);
        return '<li class="linha-categoria">' +
            '<span class="nome-categoria">' + escaparHtml(c.categoria) +
            ' <small>(' + c.perguntas + (Number(c.perguntas) === 1 ? ' pergunta' : ' perguntas') + ')</small></span>' +
            '<span class="barra-categoria" aria-hidden="true"><span class="barra-categoria-preenchida ' + classeMedia(media) + '" style="width:' + (media * 10) + '%"></span></span>' +
            '<b class="valor-categoria">' + media.toFixed(1) + '</b>' +
            '</li>';
    }).join("") + '</ul>';
}

function renderizarDistribuicao(dist) {
    var insatisfeito = Number(dist.insatisfeito) || 0;
    var neutro = Number(dist.neutro) || 0;
    var satisfeito = Number(dist.satisfeito) || 0;
    var total = insatisfeito + neutro + satisfeito;
    var pizza = document.getElementById("graficoPizza");

    if (total === 0) {
        pizza.style.background = "var(--trilho)";
        pizza.setAttribute("aria-label", "Distribuição de satisfação: ainda sem dados");
        return;
    }

    var pSatisfeito = (satisfeito / total) * 100;
    var pNeutro = (neutro / total) * 100;

    pizza.style.background = "conic-gradient(" +
        "#15b573 0% " + pSatisfeito + "%, " +
        "#f4b400 " + pSatisfeito + "% " + (pSatisfeito + pNeutro) + "%, " +
        "#ef4444 " + (pSatisfeito + pNeutro) + "% 100%)";
    pizza.setAttribute("aria-label", "Distribuição de satisfação: " + satisfeito + " satisfeitos, " +
        neutro + " neutros e " + insatisfeito + " insatisfeitos");
}

function renderizarEvolucao(evolucao) {
    var grafico = document.getElementById("graficoEvolucao");
    var legenda = document.getElementById("legendaEvolucao");
    var tendencia = document.getElementById("textoTendencia");

    if (evolucao.length === 0) {
        grafico.innerHTML = '<p class="aviso-vazio">Sem respostas ainda.</p>';
        legenda.innerHTML = "";
        tendencia.innerText = "";
        return;
    }

    var largura = 400;
    var altura = 130;
    var maximo = Math.max.apply(null, evolucao.map(function (e) { return Number(e.total); }));
    if (maximo === 0) maximo = 1;

    var pontos = evolucao.map(function (e, i) {
        var x = evolucao.length === 1 ? largura / 2 : (i / (evolucao.length - 1)) * largura;
        var y = altura - (Number(e.total) / maximo) * (altura - 20) - 10;
        return x.toFixed(1) + "," + y.toFixed(1);
    });

    var circulos = pontos.map(function (p) {
        var xy = p.split(",");
        return '<circle cx="' + xy[0] + '" cy="' + xy[1] + '" r="4" fill="#075fd3"/>';
    }).join("");

    grafico.innerHTML =
        '<svg viewBox="0 0 ' + largura + ' ' + altura + '" preserveAspectRatio="none" style="width:100%;height:100%" aria-hidden="true">' +
        '<polyline points="' + pontos.join(" ") + '" fill="none" stroke="#075fd3" stroke-width="3"/>' +
        circulos +
        '</svg>';

    legenda.innerHTML = evolucao.map(function (e) {
        return "<span>" + formatarData(e.dia).slice(0, 5) + "</span>";
    }).join("");

    var totalGeral = evolucao.reduce(function (acc, e) { return acc + Number(e.total); }, 0);
    tendencia.innerText = totalGeral + " resposta(s) em " + evolucao.length + " dia(s) com envios";
    grafico.setAttribute("aria-label", "Respostas por dia: " + evolucao.map(function (e) {
        return formatarData(e.dia) + ", " + e.total;
    }).join("; "));
}


// ===================== Resultados =====================

async function carregarResultados() {
    var container = document.getElementById("listaResultados");
    var titulo = document.getElementById("resultadosFormulario");
    var periodo = document.getElementById("filtroPeriodo").value;

    container.innerHTML = '<p class="aviso-vazio">Carregando...</p>';

    try {
        var r = await chamarApi("resultados", undefined, "&periodo=" + periodo + parametroFormulario());
        if (sessaoExpirada(r)) return;

        if (!r.ok || r.dados.erro) {
            titulo.innerText = "";
            container.innerHTML = '<div class="painel"><p class="aviso-vazio">' +
                escaparHtml(r.dados.erro || "Não foi possível carregar os resultados.") + '</p></div>';
            return;
        }

        formularioSelecionadoId = Number(r.dados.formulario.id);
        titulo.innerText = "Formulário: " + r.dados.formulario.titulo;
        ultimosResultados = r.dados.perguntas || [];
        minimoAnonimato = r.dados.minimo_anonimato;
        preencherFiltroCategorias();
        renderizarResultadosFiltrados();
    } catch (e) {
        container.innerHTML = '<div class="painel"><p class="aviso-vazio">Erro de conexão ao carregar os resultados.</p></div>';
    }
}

function preencherFiltroCategorias() {
    var select = document.getElementById("filtroCategoria");
    var atual = select.value;
    var categorias = [];
    ultimosResultados.forEach(function (p) {
        if (p.categoria && categorias.indexOf(p.categoria) === -1) categorias.push(p.categoria);
    });
    categorias.sort();
    select.innerHTML = '<option value="">Todas as categorias</option>' + categorias.map(function (c) {
        return '<option value="' + escaparHtml(c) + '">' + escaparHtml(c) + '</option>';
    }).join("");
    select.value = categorias.indexOf(atual) !== -1 ? atual : "";
    select.classList.toggle("escondido", categorias.length === 0);
}

function renderizarResultadosFiltrados() {
    var categoria = document.getElementById("filtroCategoria").value;
    var lista = ultimosResultados.map(function (p, i) { return Object.assign({ numero: i + 1 }, p); });
    if (categoria) {
        lista = lista.filter(function (p) { return p.categoria === categoria; });
    }
    renderizarResultados(lista, minimoAnonimato);
}

function renderizarResultados(perguntas, minimo) {
    var container = document.getElementById("listaResultados");

    if (perguntas.length === 0) {
        container.innerHTML = '<div class="painel"><p class="aviso-vazio">Nenhuma pergunta para mostrar.</p></div>';
        return;
    }

    container.innerHTML = perguntas.map(function (p) {
        var total = Number(p.total);
        var corpo;
        var badge;

        if (p.tipo === "nota") {
            var temMedia = p.media !== null;
            var media = temMedia ? Number(p.media) : 0;
            badge = '<div class="badge-media ' + classeMedia(temMedia ? media : null) + '">Média: ' + (temMedia ? media.toFixed(1) : "—") + '</div>';
            if (total > 0 && !p.oculto) {
                corpo =
                    '<div class="stats-resultado">' +
                    '<div><span>Respostas</span><b>' + total + '</b></div>' +
                    '<div><span>Desvio Padrão</span><b>' + Number(p.desvio_padrao).toFixed(1) + '</b></div>' +
                    '<div><span>Mediana</span><b>' + Number(p.mediana).toFixed(1) + '</b></div>' +
                    '</div>' +
                    '<div class="barra-resultado-topo"><span>Satisfação média</span><span>' + Math.round(media * 10) + '%</span></div>' +
                    '<div class="barra-resultado" aria-hidden="true"><div class="barra-resultado-preenchida ' + classeMedia(media) +
                    '" style="width:' + (media * 10) + '%"></div></div>';
            }
        } else {
            badge = p.tipo === "sim_nao" && p.percentual_sim !== null
                ? '<div class="badge-media ' + (p.percentual_sim >= 50 ? "media-boa" : "media-ruim") + '">Sim: ' + p.percentual_sim + '%</div>'
                : '<div class="badge-media">' + total + (total === 1 ? ' resposta' : ' respostas') + '</div>';
            if (total > 0 && !p.oculto) {
                var maior = Math.max.apply(null, p.opcoes.map(function (o) { return o.total; }));
                var opcoes = p.tipo === "sim_nao" ? p.opcoes.slice().reverse() : p.opcoes;
                corpo = '<ul class="lista-opcoes">' + opcoes.map(function (o) {
                    var destaque = o.total === maior && maior > 0 ? " destaque" : "";
                    return '<li class="linha-opcao">' +
                        '<span class="nome-opcao">' + escaparHtml(o.texto) + '</span>' +
                        '<span class="barra-opcao" aria-hidden="true"><span class="barra-opcao-preenchida' + destaque + '" style="width:' + o.percentual + '%"></span></span>' +
                        '<b class="valor-opcao">' + o.percentual + '% <small>(' + o.total + ')</small></b>' +
                        '</li>';
                }).join("") + '</ul>';
            }
        }

        if (total === 0) {
            corpo = '<div class="resultado-oculto">Nenhuma resposta neste período.</div>';
        } else if (p.oculto) {
            corpo = '<div class="resultado-oculto">🔒 ' + total + ' resposta(s) neste período. Para proteger o anonimato, ' +
                'os números só aparecem a partir de ' + minimo + ' respostas.</div>';
        }

        return '<div class="card-resultado">' +
            '<div class="resultado-topo">' +
            '<div class="numero-resultado">' + p.numero + '</div>' +
            '<div class="texto-resultado">' + escaparHtml(p.texto) +
            '<div class="resultado-etiquetas">' +
            (p.categoria ? '<span class="categoria-chip">' + escaparHtml(p.categoria) + '</span>' : '') +
            (p.tipo !== "nota" ? '<span class="tipo-chip">' + rotuloTipo(p.tipo) + '</span>' : '') +
            '</div></div>' +
            badge +
            '</div>' +
            corpo +
            '</div>';
    }).join("");
}


// ===================== Comparar pesquisas =====================

async function carregarOpcoesComparacao() {
    var selectA = document.getElementById("compararA");
    var selectB = document.getElementById("compararB");
    var erro = document.getElementById("erroComparar");
    erro.innerText = "";

    try {
        var r = await chamarApi("formularios");
        if (sessaoExpirada(r)) return;
        var lista = (r.dados.formularios || []).filter(function (f) { return f.status !== "rascunho"; });

        var opcoes = lista.map(function (f) {
            var periodo = f.data_abertura ? " (" + formatarData(f.data_abertura) + ")" : "";
            return '<option value="' + f.id + '">' + escaparHtml(f.titulo) + periodo + ' · ' + f.total_respostas + ' resp.</option>';
        }).join("");
        selectA.innerHTML = opcoes;
        selectB.innerHTML = opcoes;

        if (lista.length < 2) {
            document.getElementById("resultadoComparacao").innerHTML =
                '<div class="painel"><p class="aviso-vazio">É preciso ter pelo menos duas pesquisas ativadas para comparar. ' +
                'Dica: use "Duplicar" na tela Formulários para criar o próximo ciclo com as mesmas perguntas.</p></div>';
            return;
        }

        var ids = lista.map(function (f) { return Number(f.id); });
        var a = formularioSelecionadoId && ids.indexOf(formularioSelecionadoId) !== -1 ? formularioSelecionadoId : ids[0];
        var b = ids.filter(function (id) { return id !== a; })[0];
        selectA.value = String(a);
        selectB.value = String(b);
        carregarComparacao();
    } catch (e) {
        erro.innerText = "Erro de conexão ao carregar as pesquisas.";
    }
}

function formatarDiferenca(valor, sufixo) {
    if (valor === null || valor === undefined) return '<span class="delta">—</span>';
    var n = Number(valor);
    var texto = (n > 0 ? "+" : "") + (sufixo === "%" ? Math.round(n) : n.toFixed(1)) + (sufixo || "");
    if (n > 0) return '<span class="delta delta-positivo"><span aria-hidden="true">▲ </span>' + texto + '<span class="sr-only"> (subiu)</span></span>';
    if (n < 0) return '<span class="delta delta-negativo"><span aria-hidden="true">▼ </span>' + texto + '<span class="sr-only"> (caiu)</span></span>';
    return '<span class="delta">= 0</span>';
}

function formatarValor(valor, tipo) {
    if (valor === null || valor === undefined) return "—";
    return tipo === "sim_nao" ? Math.round(Number(valor)) + "% sim" : Number(valor).toFixed(1);
}

async function carregarComparacao() {
    var a = Number(document.getElementById("compararA").value);
    var b = Number(document.getElementById("compararB").value);
    var erro = document.getElementById("erroComparar");
    var container = document.getElementById("resultadoComparacao");
    erro.innerText = "";

    if (!a || !b || a === b) {
        erro.innerText = "Escolha duas pesquisas diferentes.";
        return;
    }

    try {
        var r = await chamarApi("comparar", undefined, "&a=" + a + "&b=" + b);
        if (sessaoExpirada(r)) return;
        if (!r.ok) {
            erro.innerText = r.dados.erro || "Não foi possível comparar.";
            return;
        }
        var d = r.dados;

        var cartao = function (dados, rotulo) {
            var f = dados.formulario;
            return '<div class="card-comparacao">' +
                '<span class="rotulo-comparacao">' + rotulo + '</span>' +
                '<h2>' + escaparHtml(f.titulo) + '</h2>' +
                '<p>' + (f.data_abertura ? formatarData(f.data_abertura) + " a " + formatarData(f.data_fechamento) : "sem período") + '</p>' +
                '<div class="numeros-comparacao">' +
                '<div><span>Média geral</span><b>' + (dados.media_geral !== null ? Number(dados.media_geral).toFixed(1) : "—") + '</b></div>' +
                '<div><span>Respostas</span><b>' + dados.total_respostas + '</b></div>' +
                '<div><span>Participação</span><b>' + (dados.taxa_participacao !== null ? dados.taxa_participacao + "%" : "—") + '</b></div>' +
                '</div>' +
                (dados.oculto ? '<p class="resultado-oculto">🔒 Menos de ' + d.minimo_anonimato + ' respostas: números ocultos para proteger o anonimato.</p>' : '') +
                '</div>';
        };

        var html = '<div class="grade-comparacao">' + cartao(d.a, "Pesquisa principal") + cartao(d.b, "Comparada com") + '</div>';
        html += '<div class="painel resumo-diferenca"><span>Diferença na média geral</span>' + formatarDiferenca(d.diferenca_media) + '</div>';

        if (d.categorias.length > 0) {
            html += '<div class="painel"><h2>Por categoria</h2><div class="tabela-rolagem"><table class="tabela-comparacao">' +
                '<thead><tr><th scope="col">Categoria</th><th scope="col">Principal</th><th scope="col">Comparada</th><th scope="col">Diferença</th></tr></thead><tbody>' +
                d.categorias.map(function (c) {
                    return '<tr><th scope="row">' + escaparHtml(c.categoria) + '</th><td data-rotulo="Principal">' + formatarValor(c.media_a) + '</td><td data-rotulo="Comparada">' +
                        formatarValor(c.media_b) + '</td><td data-rotulo="Diferença">' + formatarDiferenca(c.diferenca) + '</td></tr>';
                }).join("") + '</tbody></table></div></div>';
        }

        html += '<div class="painel"><h2>Por pergunta</h2>';
        if (d.perguntas.length === 0) {
            html += '<p class="aviso-vazio">Nenhuma pergunta com o mesmo texto nas duas pesquisas.</p>';
        } else {
            html += '<div class="tabela-rolagem"><table class="tabela-comparacao">' +
                '<thead><tr><th scope="col">Pergunta</th><th scope="col">Principal</th><th scope="col">Comparada</th><th scope="col">Diferença</th></tr></thead><tbody>' +
                d.perguntas.map(function (p) {
                    return '<tr><th scope="row">' + escaparHtml(p.texto) +
                        (p.categoria ? ' <span class="categoria-chip">' + escaparHtml(p.categoria) + '</span>' : '') + '</th>' +
                        '<td data-rotulo="Principal">' + formatarValor(p.valor_a, p.tipo) + '</td><td data-rotulo="Comparada">' + formatarValor(p.valor_b, p.tipo) + '</td>' +
                        '<td data-rotulo="Diferença">' + formatarDiferenca(p.diferenca, p.tipo === "sim_nao" ? " p.p." : "") + '</td></tr>';
                }).join("") + '</tbody></table></div>';
        }
        if (d.perguntas_sem_correspondencia > 0) {
            html += '<p class="dica-campo">' + d.perguntas_sem_correspondencia +
                ' pergunta(s) da pesquisa principal não têm correspondente na outra (ou são de múltipla escolha) e ficaram de fora.</p>';
        }
        html += '</div>';
        container.innerHTML = html;
    } catch (e) {
        erro.innerText = "Erro de conexão ao comparar.";
    }
}


// ===================== Formulários =====================

async function carregarListaFormularios() {
    var container = document.getElementById("listaFormularios");
    try {
        var r = await chamarApi("formularios");
        if (sessaoExpirada(r)) return;
        if (!r.ok) {
            container.innerHTML = '<p class="aviso-vazio">' + escaparHtml(r.dados.erro || "Erro ao carregar formulários.") + '</p>';
            return;
        }
        document.getElementById("avisoEmail").innerText = r.dados.email_ativo
            ? "✉ Os avisos por email de abertura, encerramento e lembrete estão ativos."
            : "O envio de emails não está configurado no servidor: os avisos aparecem só na tela inicial.";
        renderizarListaFormularios(r.dados.formularios || [], !!r.dados.email_ativo);
    } catch (e) {
        container.innerHTML = '<p class="aviso-vazio">Erro de conexão ao carregar formulários.</p>';
    }
}

function renderizarListaFormularios(lista, emailAtivo) {
    var container = document.getElementById("listaFormularios");

    if (lista.length === 0) {
        container.innerHTML = '<p class="aviso-vazio">Nenhum formulário cadastrado ainda.</p>';
        return;
    }

    container.innerHTML = lista.map(function (f) {
        var id = Number(f.id);
        var total = Number(f.total_respostas);
        var editavel = f.status === "rascunho" && total === 0;
        var titulo = escaparHtml(f.titulo);

        var acoes = "";
        if (f.status !== "ativo") {
            acoes += '<button class="botao-pequeno" onclick="mudarStatusFormulario(' + id + ', \'ativo\')">Ativar</button>';
        } else {
            acoes += '<button class="botao-pequeno botao-encerrar" onclick="mudarStatusFormulario(' + id + ', \'encerrado\')">Encerrar</button>';
        }
        if (f.status !== "rascunho") {
            acoes += '<button class="botao-pequeno" onclick="verDashboardFormulario(' + id + ')">Ver dashboard</button>';
        }
        if (f.relatorio_gerado_em) {
            acoes += '<button class="botao-pequeno" onclick="verRelatorioFormulario(' + id + ')">Ver relatório</button>';
        }
        if (Number(f.aberta_agora) === 1 && emailAtivo) {
            acoes += '<button class="botao-pequeno" onclick="enviarLembrete(' + id + ')">✉ Lembrete</button>';
        }
        if (editavel) {
            acoes += '<button class="botao-pequeno" onclick="iniciarEdicaoFormulario(' + id + ')" aria-label="Editar ' + titulo + '">Editar</button>';
        }
        acoes += '<button class="botao-pequeno" onclick="duplicarFormulario(' + id + ')" aria-label="Duplicar ' + titulo + '">Duplicar</button>';
        if (editavel) {
            acoes += '<button class="botao-pequeno botao-encerrar" onclick="excluirFormulario(' + id + ')" aria-label="Excluir ' + titulo + '">Excluir</button>';
        }

        var detalhe = Number(f.total_perguntas) + (Number(f.total_perguntas) === 1 ? " pergunta · " : " perguntas · ") +
            total + (total === 1 ? " resposta" : " respostas");
        if (f.respondentes_esperados) detalhe += " de " + f.respondentes_esperados + " esperadas";

        var periodo = "";
        if (f.data_abertura || f.data_fechamento) {
            periodo = "<br><small>Período: " +
                (f.data_abertura ? formatarDataHora(f.data_abertura) : "sem início definido") + " até " +
                (f.data_fechamento ? formatarDataHora(f.data_fechamento) : "7 dias após ativar") + "</small>";
        }

        var extras = "";
        if (f.relatorio_gerado_em) {
            extras += '<small class="linha-relatorio">📄 Relatório consolidado gerado automaticamente em ' + formatarDataHora(f.relatorio_gerado_em) + '</small>';
        }
        if (f.email_abertura_em) extras += '<small class="linha-email">✉ Aviso de abertura enviado em ' + formatarDataHora(f.email_abertura_em) + '</small>';
        if (f.email_encerramento_em) extras += '<small class="linha-email">✉ Aviso de encerramento enviado em ' + formatarDataHora(f.email_encerramento_em) + '</small>';
        if (f.lembrete_em) extras += '<small class="linha-email">✉ Último lembrete em ' + formatarDataHora(f.lembrete_em) + '</small>';

        return '<div class="item-formulario">' +
            '<div>' +
            '<b>' + titulo + '</b>' +
            '<span class="status-' + f.status + '">' + rotuloStatus(f.status) + '</span>' +
            '<br><small>' + detalhe + '</small>' +
            periodo + extras +
            '</div>' +
            '<div class="acoes-formulario">' + acoes + '</div>' +
            '</div>';
    }).join("");
}

async function mudarStatusFormulario(id, novoStatus) {
    var mensagem = novoStatus === "ativo"
        ? "Ativar este formulário? Se outro estiver ativo, ele será encerrado. Sem data de término, a pesquisa fica aberta por 7 dias."
        : "Encerrar este formulário agora? Os funcionários não poderão mais responder e o relatório consolidado será gerado automaticamente.";
    if (!confirm(mensagem)) return;

    try {
        var r = await chamarApi("alterar_status_formulario", { formulario_id: id, status: novoStatus });
        if (sessaoExpirada(r)) return;
        if (!r.ok) alert(r.dados.erro || "Não foi possível alterar o status.");
    } catch (e) {
        alert("Erro de conexão.");
    }
    carregarListaFormularios();
}

function verDashboardFormulario(id) {
    formularioSelecionadoId = id;
    irParaView("dashboard");
}

function verRelatorioFormulario(id) {
    window.open("relatorio_impressao.php?formulario_id=" + id, "_blank");
}

async function enviarLembrete(id) {
    if (!confirm("Enviar um lembrete por email para os funcionários que ainda não responderam esta pesquisa?")) return;
    try {
        var r = await chamarApi("enviar_lembrete", { formulario_id: id });
        if (sessaoExpirada(r)) return;
        if (!r.ok) {
            alert(r.dados.erro || "Não foi possível enviar o lembrete.");
        } else {
            var texto = "Lembrete enviado para " + r.dados.enviados + (Number(r.dados.enviados) === 1 ? " pessoa." : " pessoas.");
            if (Number(r.dados.falhas) > 0) texto += "\n" + r.dados.falhas + " email(s) não puderam ser enviados.";
            alert(texto);
        }
    } catch (e) {
        alert("Erro de conexão.");
    }
    carregarListaFormularios();
}

async function duplicarFormulario(id) {
    try {
        var r = await chamarApi("duplicar_formulario", { formulario_id: id });
        if (sessaoExpirada(r)) return;
        if (!r.ok) {
            alert(r.dados.erro || "Não foi possível duplicar.");
            return;
        }
        await carregarListaFormularios();
        await iniciarEdicaoFormulario(Number(r.dados.formulario_id));
        anunciar("Cópia criada como rascunho e aberta para edição.");
    } catch (e) {
        alert("Erro de conexão.");
    }
}

async function excluirFormulario(id) {
    if (!confirm("Excluir este rascunho? Esta ação não pode ser desfeita.")) return;
    try {
        var r = await chamarApi("excluir_formulario", { formulario_id: id });
        if (sessaoExpirada(r)) return;
        if (!r.ok) alert(r.dados.erro || "Não foi possível excluir.");
        else if (formularioEditandoId === id) cancelarEdicaoFormulario();
    } catch (e) {
        alert("Erro de conexão.");
    }
    carregarListaFormularios();
}

// ---------- editor de perguntas ----------

function perguntaVazia() {
    return { texto: "", tipo: "nota", categoria: "", opcoes: "" };
}

function preencherSugestoesCategorias() {
    var todas = CATEGORIAS_SUGERIDAS.slice();
    perguntasEditor.forEach(function (p) {
        if (p.categoria && todas.indexOf(p.categoria) === -1) todas.push(p.categoria);
    });
    document.getElementById("listaCategorias").innerHTML = todas.map(function (c) {
        return '<option value="' + escaparHtml(c) + '">';
    }).join("");
}

function renderizarEditorPerguntas() {
    var container = document.getElementById("editorPerguntas");
    var total = perguntasEditor.length;
    container.innerHTML = perguntasEditor.map(function (p, i) {
        var n = i + 1;
        var opcoesTipo = [["nota", "Nota de 0 a 10"], ["sim_nao", "Sim ou não"], ["multipla", "Múltipla escolha"]].map(function (t) {
            return '<option value="' + t[0] + '"' + (p.tipo === t[0] ? " selected" : "") + '>' + t[1] + '</option>';
        }).join("");
        return '<div class="pergunta-editor">' +
            '<div class="pergunta-editor-topo">' +
            '<span class="numero-resultado" aria-hidden="true">' + n + '</span>' +
            '<label class="sr-only" for="pe-texto-' + i + '">Texto da pergunta ' + n + '</label>' +
            '<input id="pe-texto-' + i + '" type="text" maxlength="255" placeholder="Texto da pergunta ' + n + '" value="' + escaparHtml(p.texto) +
            '" oninput="atualizarPerguntaEditor(' + i + ', \'texto\', this.value)">' +
            '</div>' +
            '<div class="pergunta-editor-campos">' +
            '<div><label for="pe-tipo-' + i + '">Tipo</label>' +
            '<select id="pe-tipo-' + i + '" onchange="mudarTipoPerguntaEditor(' + i + ', this.value)">' + opcoesTipo + '</select></div>' +
            '<div><label for="pe-cat-' + i + '">Categoria (opcional)</label>' +
            '<input id="pe-cat-' + i + '" type="text" list="listaCategorias" maxlength="60" placeholder="Ex.: Liderança" value="' + escaparHtml(p.categoria) +
            '" oninput="atualizarPerguntaEditor(' + i + ', \'categoria\', this.value)"></div>' +
            '</div>' +
            (p.tipo === "multipla"
                ? '<div class="pergunta-editor-opcoes"><label for="pe-op-' + i + '">Alternativas (uma por linha, de 2 a 10)</label>' +
                  '<textarea id="pe-op-' + i + '" rows="4" placeholder="Ex.:&#10;E-mail&#10;WhatsApp&#10;Reuniões presenciais" oninput="atualizarPerguntaEditor(' + i + ', \'opcoes\', this.value)">' +
                  escaparHtml(p.opcoes) + '</textarea></div>'
                : '') +
            '<div class="pergunta-editor-acoes">' +
            '<button class="botao-icone" onclick="moverPerguntaEditor(' + i + ', -1)"' + (i === 0 ? " disabled" : "") + ' aria-label="Mover pergunta ' + n + ' para cima">↑</button>' +
            '<button class="botao-icone" onclick="moverPerguntaEditor(' + i + ', 1)"' + (i === total - 1 ? " disabled" : "") + ' aria-label="Mover pergunta ' + n + ' para baixo">↓</button>' +
            '<button class="botao-icone botao-encerrar" onclick="removerPerguntaEditor(' + i + ')"' + (total === 1 ? " disabled" : "") + ' aria-label="Remover pergunta ' + n + '">✕</button>' +
            '</div>' +
            '</div>';
    }).join("");
    preencherSugestoesCategorias();
}

function atualizarPerguntaEditor(i, campo, valor) {
    perguntasEditor[i][campo] = valor;
}

function mudarTipoPerguntaEditor(i, tipo) {
    perguntasEditor[i].tipo = tipo;
    renderizarEditorPerguntas();
    var foco = document.getElementById(tipo === "multipla" ? "pe-op-" + i : "pe-tipo-" + i);
    if (foco) foco.focus();
}

function adicionarPerguntaEditor() {
    perguntasEditor.push(perguntaVazia());
    renderizarEditorPerguntas();
    document.getElementById("pe-texto-" + (perguntasEditor.length - 1)).focus();
}

function moverPerguntaEditor(i, direcao) {
    var j = i + direcao;
    if (j < 0 || j >= perguntasEditor.length) return;
    var temp = perguntasEditor[i];
    perguntasEditor[i] = perguntasEditor[j];
    perguntasEditor[j] = temp;
    renderizarEditorPerguntas();
    var botoes = document.querySelectorAll(".pergunta-editor")[j].querySelectorAll(".botao-icone");
    (direcao < 0 ? botoes[0] : botoes[1]).focus();
    anunciar("Pergunta movida para a posição " + (j + 1) + ".");
}

function removerPerguntaEditor(i) {
    if (perguntasEditor.length === 1) return;
    perguntasEditor.splice(i, 1);
    renderizarEditorPerguntas();
    anunciar("Pergunta removida.");
}

function cancelarEdicaoFormulario() {
    formularioEditandoId = null;
    ["novoFormTitulo", "novoFormEsperados", "novoFormInicio", "novoFormFim"].forEach(function (id) {
        document.getElementById(id).value = "";
    });
    perguntasEditor = [perguntaVazia()];
    renderizarEditorPerguntas();
    document.getElementById("tituloEditorFormulario").innerText = "Novo formulário";
    document.getElementById("botaoSalvarFormulario").innerText = "Criar formulário";
    document.getElementById("botaoCancelarEdicaoForm").classList.add("escondido");
    document.getElementById("erroNovoForm").innerText = "";
}

async function iniciarEdicaoFormulario(id) {
    try {
        var r = await chamarApi("detalhes_formulario", undefined, "&formulario_id=" + id);
        if (sessaoExpirada(r)) return;
        if (!r.ok) {
            alert(r.dados.erro || "Não foi possível abrir o formulário.");
            return;
        }
        var f = r.dados;
        formularioEditandoId = Number(f.id);
        document.getElementById("novoFormTitulo").value = f.titulo;
        document.getElementById("novoFormEsperados").value = f.respondentes_esperados || "";
        document.getElementById("novoFormInicio").value = paraDatetimeLocal(f.data_abertura);
        document.getElementById("novoFormFim").value = paraDatetimeLocal(f.data_fechamento);
        perguntasEditor = (f.perguntas || []).map(function (p) {
            return { texto: p.texto, tipo: p.tipo, categoria: p.categoria || "", opcoes: (p.opcoes || []).join("\n") };
        });
        if (perguntasEditor.length === 0) perguntasEditor = [perguntaVazia()];
        renderizarEditorPerguntas();

        document.getElementById("tituloEditorFormulario").innerText = "Editando: " + f.titulo;
        document.getElementById("botaoSalvarFormulario").innerText = "Salvar alterações";
        document.getElementById("botaoCancelarEdicaoForm").classList.remove("escondido");
        document.getElementById("erroNovoForm").innerText = "";
        document.getElementById("painelEditorFormulario").scrollIntoView({ behavior: "smooth", block: "start" });
        document.getElementById("novoFormTitulo").focus({ preventScroll: true });
    } catch (e) {
        alert("Erro de conexão.");
    }
}

async function salvarFormulario() {
    var titulo = document.getElementById("novoFormTitulo").value.trim();
    var esperados = document.getElementById("novoFormEsperados").value;
    var inicio = document.getElementById("novoFormInicio").value;
    var fim = document.getElementById("novoFormFim").value;
    var erro = document.getElementById("erroNovoForm");
    var editando = formularioEditandoId !== null;

    erro.innerText = "";

    var perguntas = [];
    for (var i = 0; i < perguntasEditor.length; i++) {
        var p = perguntasEditor[i];
        var texto = p.texto.trim();
        if (!texto) continue;
        var pergunta = { texto: texto, tipo: p.tipo, categoria: p.categoria.trim() };
        if (p.tipo === "multipla") {
            pergunta.opcoes = p.opcoes.split("\n").map(function (o) { return o.trim(); }).filter(function (o) { return o !== ""; });
            if (pergunta.opcoes.length < 2) {
                erro.innerText = "A pergunta " + (i + 1) + " é de múltipla escolha: informe pelo menos 2 alternativas.";
                document.getElementById("pe-op-" + i).focus();
                return;
            }
        }
        perguntas.push(pergunta);
    }

    if (!titulo || perguntas.length === 0) {
        erro.innerText = "Informe um título e ao menos uma pergunta.";
        return;
    }
    if (inicio && fim && new Date(fim) <= new Date(inicio)) {
        erro.innerText = "A data de término precisa ser depois da data de início.";
        return;
    }
    if (fim && new Date(fim) <= new Date()) {
        erro.innerText = "A data de término já passou.";
        return;
    }

    var corpo = {
        titulo: titulo,
        respondentes_esperados: esperados ? Number(esperados) : null,
        data_abertura: paraDatetimeMysql(inicio),
        data_fechamento: paraDatetimeMysql(fim),
        perguntas: perguntas
    };
    if (editando) corpo.formulario_id = formularioEditandoId;

    try {
        var r = await chamarApi(editando ? "editar_formulario" : "criar_formulario", corpo);
        if (sessaoExpirada(r)) return;

        if (!r.ok) {
            erro.innerText = r.dados.erro || "Erro ao salvar o formulário.";
            return;
        }

        cancelarEdicaoFormulario();
        carregarListaFormularios();
        alert(editando ? "Formulário atualizado." : "Formulário criado como rascunho. Clique em \"Ativar\" para liberar para os funcionários.");
    } catch (e) {
        erro.innerText = "Erro de conexão.";
    }
}


// ===================== Comentários =====================

async function carregarComentarios() {
    var container = document.getElementById("listaComentarios");
    var titulo = document.getElementById("comentariosFormulario");

    container.innerHTML = '<p class="aviso-vazio">Carregando...</p>';

    try {
        var r = await chamarApi("comentarios", undefined, parametroFormulario());
        if (sessaoExpirada(r)) return;

        if (!r.ok || r.dados.erro) {
            titulo.innerText = "";
            container.innerHTML = '<p class="aviso-vazio">' +
                escaparHtml(r.dados.erro || "Não foi possível carregar os comentários.") + '</p>';
            return;
        }

        var d = r.dados;
        formularioSelecionadoId = Number(d.formulario.id);
        titulo.innerText = "Formulário: " + d.formulario.titulo;

        if (d.oculto) {
            container.innerHTML = '<div class="aviso-anonimato">🔒 Este formulário tem ' + d.total_respostas +
                ' resposta(s). Para proteger o anonimato, os comentários só aparecem a partir de ' +
                d.minimo_anonimato + ' respostas.</div>';
            return;
        }

        if (d.comentarios.length === 0) {
            container.innerHTML = '<p class="aviso-vazio">Nenhum comentário enviado ainda.</p>';
            return;
        }

        container.innerHTML = d.comentarios.map(function (c) {
            return '<div class="comentario-item">' +
                '<small>' + formatarData(c.data_envio) + '</small>' +
                '<p>' + escaparHtml(c.comentario) + '</p>' +
                '</div>';
        }).join("");
    } catch (e) {
        container.innerHTML = '<p class="aviso-vazio">Erro de conexão ao carregar os comentários.</p>';
    }
}


// ===================== Funcionários (cadastro - RF01) =====================

async function carregarListaFuncionarios() {
    var container = document.getElementById("listaFuncionarios");
    var info = document.getElementById("infoPesquisaAtual");

    try {
        var r = await chamarApi("funcionarios");
        if (sessaoExpirada(r)) return;

        if (!r.ok) {
            container.innerHTML = '<p class="aviso-vazio">' + escaparHtml(r.dados.erro || "Erro ao carregar os cadastros.") + '</p>';
            return;
        }

        ultimaListaFuncionarios = r.dados.funcionarios || [];
        meuId = Number(r.dados.meu_id);

        info.innerText = r.dados.formulario_atual
            ? "Situação de resposta em relação à pesquisa ativa: “" + r.dados.formulario_atual.titulo + "”"
            : "Nenhuma pesquisa ativa no momento.";

        renderizarListaFuncionarios(ultimaListaFuncionarios, !!r.dados.formulario_atual);
    } catch (e) {
        container.innerHTML = '<p class="aviso-vazio">Erro de conexão ao carregar os cadastros.</p>';
    }
}

function renderizarListaFuncionarios(lista, temPesquisaAtiva) {
    var container = document.getElementById("listaFuncionarios");

    if (lista.length === 0) {
        container.innerHTML = '<p class="aviso-vazio">Nenhum cadastro ainda.</p>';
        return;
    }

    container.innerHTML = lista.map(function (f) {
        var id = Number(f.id);
        var ativo = Number(f.ativo) === 1;
        var gestor = f.tipo_perfil === "gestor";
        var souEu = id === meuId;
        var nome = escaparHtml(f.nome);

        var selos = '<span class="' + (gestor ? "perfil-gestor" : "perfil-funcionario") + '">' +
            (gestor ? "Gestor" : "Funcionário") + '</span>' +
            '<span class="' + (ativo ? "status-ativo" : "status-encerrado") + '">' +
            (ativo ? "Ativo" : "Inativo") + '</span>';

        if (!gestor) {
            // RNF09: comprovante do aceite do termo de consentimento
            selos += Number(f.termo_em_dia) === 1
                ? '<span class="status-ativo" title="Aceito em ' + formatarDataHora(f.termo_aceito_em) + '">Termo aceito</span>'
                : '<span class="status-rascunho">Termo pendente</span>';
        }

        // o gestor vê apenas SE a pessoa já respondeu, nunca O QUE respondeu
        if (!gestor && temPesquisaAtiva) {
            var respondeu = Number(f.respondeu_atual) === 1;
            selos += '<span class="' + (respondeu ? "status-ativo" : "status-rascunho") + '">' +
                (respondeu ? "✓ Já respondeu" : "Ainda não respondeu") + '</span>';
        }

        var detalhes = [escaparHtml(f.email)];
        if (f.cargo) detalhes.push(escaparHtml(f.cargo));
        if (f.data_admissao) detalhes.push("admissão em " + formatarData(f.data_admissao));

        var acoes = '<button class="botao-pequeno" onclick="iniciarEdicaoFuncionario(' + id + ')" aria-label="Editar ' + nome + '">Editar</button>';
        if (souEu) {
            acoes += '<span class="voce">(você)</span>';
        } else {
            acoes += '<button class="botao-pequeno" onclick="alternarStatusFuncionario(' + id + ', ' + (ativo ? 0 : 1) + ')" aria-label="' +
                (ativo ? "Desativar " : "Reativar ") + nome + '">' + (ativo ? "Desativar" : "Reativar") + '</button>';
            acoes += '<button class="botao-pequeno botao-encerrar" onclick="excluirFuncionario(' + id + ')" aria-label="Excluir ' + nome + '">Excluir</button>';
        }

        return '<div class="item-formulario">' +
            '<div><b>' + nome + '</b>' + selos +
            '<br><small>' + detalhes.join(" · ") + '</small></div>' +
            '<div class="acoes-formulario">' + acoes + '</div>' +
            '</div>';
    }).join("");
}

function iniciarEdicaoFuncionario(id) {
    var f = ultimaListaFuncionarios.find(function (x) { return Number(x.id) === Number(id); });
    if (!f) return;

    funcionarioEditandoId = Number(f.id);
    document.getElementById("novoFuncNome").value = f.nome;
    document.getElementById("novoFuncEmail").value = f.email;
    document.getElementById("novoFuncSenha").value = "";
    document.getElementById("novoFuncSenha").placeholder = "Deixe em branco para manter a senha atual";
    document.getElementById("novoFuncCargo").value = f.cargo || "";
    document.getElementById("novoFuncAdmissao").value = f.data_admissao || "";
    document.getElementById("novoFuncPerfil").value = f.tipo_perfil;

    document.getElementById("tituloFormFunc").innerText = "Editando: " + f.nome;
    document.getElementById("botaoSalvarFunc").innerText = "Salvar alterações";
    document.getElementById("botaoCancelarEdicaoFunc").classList.remove("escondido");
    document.getElementById("erroNovoFunc").innerText = "";
    document.getElementById("formFuncionario").scrollIntoView({ behavior: "smooth", block: "start" });
    document.getElementById("novoFuncNome").focus({ preventScroll: true });
}

function cancelarEdicaoFuncionario() {
    funcionarioEditandoId = null;
    ["novoFuncNome", "novoFuncEmail", "novoFuncSenha", "novoFuncCargo", "novoFuncAdmissao"].forEach(function (id) {
        document.getElementById(id).value = "";
    });
    document.getElementById("novoFuncPerfil").value = "funcionario";
    document.getElementById("novoFuncSenha").placeholder = "Mínimo de 6 caracteres";
    document.getElementById("tituloFormFunc").innerText = "Novo cadastro";
    document.getElementById("botaoSalvarFunc").innerText = "Cadastrar";
    document.getElementById("botaoCancelarEdicaoFunc").classList.add("escondido");
    document.getElementById("erroNovoFunc").innerText = "";
}

async function salvarFuncionario() {
    var nome = document.getElementById("novoFuncNome").value.trim();
    var email = document.getElementById("novoFuncEmail").value.trim();
    var senha = document.getElementById("novoFuncSenha").value;
    var cargo = document.getElementById("novoFuncCargo").value.trim();
    var admissao = document.getElementById("novoFuncAdmissao").value;
    var perfil = document.getElementById("novoFuncPerfil").value;
    var erro = document.getElementById("erroNovoFunc");
    var editando = funcionarioEditandoId !== null;

    erro.innerText = "";

    if (!nome || !email) {
        erro.innerText = "Informe o nome e o email.";
        return;
    }
    if (!editando && senha.length < 6) {
        erro.innerText = "A senha precisa ter pelo menos 6 caracteres.";
        return;
    }
    if (editando && senha !== "" && senha.length < 6) {
        erro.innerText = "A nova senha precisa ter pelo menos 6 caracteres.";
        return;
    }

    var corpo = {
        nome: nome,
        email: email,
        senha: senha,
        cargo: cargo,
        data_admissao: admissao,
        tipo_perfil: perfil
    };
    if (editando) corpo.funcionario_id = funcionarioEditandoId;

    try {
        var r = await chamarApi(editando ? "editar_funcionario" : "criar_funcionario", corpo);
        if (sessaoExpirada(r)) return;

        if (!r.ok) {
            erro.innerText = r.dados.erro || "Não foi possível salvar.";
            return;
        }

        alert(editando ? "Cadastro atualizado!" : "Cadastro criado! A pessoa já pode entrar com esse email e senha.");
        cancelarEdicaoFuncionario();
        carregarListaFuncionarios();
    } catch (e) {
        erro.innerText = "Erro de conexão.";
    }
}

async function alternarStatusFuncionario(id, novoAtivo) {
    var mensagem = novoAtivo
        ? "Reativar este cadastro? A pessoa volta a conseguir entrar."
        : "Desativar este cadastro? A pessoa não conseguirá mais entrar (as respostas já enviadas continuam anônimas e guardadas).";
    if (!confirm(mensagem)) return;

    try {
        var r = await chamarApi("alterar_status_funcionario", { funcionario_id: id, ativo: novoAtivo });
        if (sessaoExpirada(r)) return;
        if (!r.ok) alert(r.dados.erro || "Não foi possível alterar o status.");
    } catch (e) {
        alert("Erro de conexão.");
    }
    carregarListaFuncionarios();
}

async function excluirFuncionario(id) {
    var f = ultimaListaFuncionarios.find(function (x) { return Number(x.id) === Number(id); });
    var nome = f ? f.nome : "este cadastro";
    if (!confirm("Excluir o cadastro de " + nome + "? Esta ação não pode ser desfeita.\nAs respostas já enviadas continuam guardadas, de forma anônima.")) return;

    try {
        var r = await chamarApi("excluir_funcionario", { funcionario_id: id });
        if (sessaoExpirada(r)) return;
        if (!r.ok) {
            alert(r.dados.erro || "Não foi possível excluir.");
        } else if (funcionarioEditandoId === Number(id)) {
            cancelarEdicaoFuncionario();
        }
    } catch (e) {
        alert("Erro de conexão.");
    }
    carregarListaFuncionarios();
}

// ---------- importação por planilha ----------

function baixarModeloImportacao() {
    baixarArquivo("modelo_importacao", "modelo_importacao_funcionarios.xlsx", "");
}

async function importarPlanilha() {
    var campo = document.getElementById("arquivoImportacao");
    var erro = document.getElementById("erroImportacao");
    var resultado = document.getElementById("resultadoImportacao");
    var botao = document.getElementById("botaoImportar");
    erro.innerText = "";
    resultado.innerHTML = "";

    if (!campo.files || campo.files.length === 0) {
        erro.innerText = "Escolha uma planilha .xlsx ou .csv.";
        campo.focus();
        return;
    }

    var dados = new FormData();
    dados.append("arquivo", campo.files[0]);
    botao.disabled = true;
    botao.innerText = "Importando...";

    try {
        var resposta = await fetch("api.php?action=importar_funcionarios", { method: "POST", body: dados });
        var r = {};
        try { r = await resposta.json(); } catch (e) { r = {}; }
        if (resposta.status === 401) {
            alert("Sua sessão expirou. Entre novamente.");
            mostrarLogin();
            return;
        }
        if (!resposta.ok) {
            erro.innerText = r.erro || "Não foi possível importar a planilha.";
            return;
        }

        var html = '<div class="resultado-importacao"><p><b>' + r.criados + (Number(r.criados) === 1 ? " cadastro criado." : " cadastros criados.") + '</b></p>';
        if (r.senhas_geradas && r.senhas_geradas.length > 0) {
            html += '<div class="aviso-anonimato">Estas pessoas vieram sem senha e receberam uma senha provisória. ' +
                'Anote e entregue a cada uma: as senhas não serão mostradas de novo.</div>' +
                '<div class="tabela-rolagem"><table class="tabela-comparacao tabela-senhas"><thead><tr><th scope="col">Nome</th><th scope="col">Email</th><th scope="col">Senha provisória</th></tr></thead><tbody>' +
                r.senhas_geradas.map(function (s) {
                    return '<tr><td data-rotulo="Nome">' + escaparHtml(s.nome) + '</td><td data-rotulo="Email">' + escaparHtml(s.email) +
                        '</td><td data-rotulo="Senha provisória"><code>' + escaparHtml(s.senha) + '</code></td></tr>';
                }).join("") + '</tbody></table></div>';
        }
        if (r.ignorados && r.ignorados.length > 0) {
            html += '<p>' + r.ignorados.length + (r.ignorados.length === 1 ? " linha ignorada:" : " linhas ignoradas:") + '</p><ul class="lista-ignorados">' +
                r.ignorados.map(function (i) {
                    return '<li>Linha ' + i.linha + (i.email ? " (" + escaparHtml(i.email) + ")" : "") + ': ' + escaparHtml(i.motivo) + '</li>';
                }).join("") + '</ul>';
        }
        html += '</div>';
        resultado.innerHTML = html;
        campo.value = "";
        carregarListaFuncionarios();
    } catch (e) {
        erro.innerText = "Erro de conexão ao enviar a planilha.";
    } finally {
        botao.disabled = false;
        botao.innerText = "Importar";
    }
}


// ===================== Relatórios e exportações =====================

function exigirFormularioSelecionado() {
    if (!formularioSelecionadoId) {
        alert("Nenhum formulário selecionado. Abra o Dashboard primeiro.");
        return false;
    }
    return true;
}

// baixa pelo fetch para conseguir mostrar a mensagem de erro sem sair do painel
async function baixarArquivo(acao, nomeArquivo, parametros) {
    try {
        var resposta = await fetch("api.php?action=" + acao + (parametros !== undefined ? parametros : parametroFormulario()));
        if (!resposta.ok) {
            var dados = {};
            try {
                dados = await resposta.json();
            } catch (e) {
                dados = {};
            }
            if (resposta.status === 401) {
                alert("Sua sessão expirou. Entre novamente.");
                mostrarLogin();
                return;
            }
            alert(dados.erro || "Não foi possível gerar o arquivo.");
            return;
        }

        var blob = await resposta.blob();
        var link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = nomeArquivo;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(function () { URL.revokeObjectURL(link.href); }, 2000);
    } catch (e) {
        alert("Erro de conexão ao gerar o arquivo.");
    }
}

function exportarPdf() {
    if (!exigirFormularioSelecionado()) return;
    window.open("relatorio_impressao.php?formulario_id=" + formularioSelecionadoId, "_blank");
}

function exportarExcel() {
    if (!exigirFormularioSelecionado()) return;
    baixarArquivo("exportar_excel", "respostas_pesquisa_" + formularioSelecionadoId + ".xlsx");
}

function exportarComentariosArquivo() {
    if (!exigirFormularioSelecionado()) return;
    baixarArquivo("exportar_comentarios", "comentarios_pesquisa_" + formularioSelecionadoId + ".txt");
}

function exportarGraficoPng() {
    if (!ultimoDashboard) {
        alert("Abra o Dashboard antes de exportar os gráficos.");
        return;
    }
    if (ultimoDashboard.dados_ocultos || Number(ultimoDashboard.total_respostas) === 0) {
        alert("Ainda não há respostas suficientes para gerar os gráficos (mínimo de " +
            ultimoDashboard.minimo_anonimato + ", para proteger o anonimato).");
        return;
    }

    var canvas = document.createElement("canvas");
    canvas.width = 1000;
    canvas.height = 480;
    var ctx = canvas.getContext("2d");

    ctx.fillStyle = "#ffffff";
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    ctx.fillStyle = "#111827";
    ctx.font = "bold 22px Arial";
    ctx.fillText(ultimoDashboard.formulario.titulo, 40, 45);
    ctx.font = "15px Arial";
    ctx.fillStyle = "#64748b";
    ctx.fillText("Média geral: " + (ultimoDashboard.media_geral !== null ? Number(ultimoDashboard.media_geral).toFixed(1) : "—") +
        "   |   Respostas: " + ultimoDashboard.total_respostas, 40, 72);

    // pizza de distribuição
    var dist = ultimoDashboard.distribuicao;
    var fatias = [
        { valor: Number(dist.satisfeito), cor: "#15b573", rotulo: "Satisfeito (7-10)" },
        { valor: Number(dist.neutro), cor: "#f4b400", rotulo: "Neutro (4-6)" },
        { valor: Number(dist.insatisfeito), cor: "#ef4444", rotulo: "Insatisfeito (0-3)" }
    ];
    var totalDist = fatias.reduce(function (acc, f) { return acc + f.valor; }, 0);

    ctx.fillStyle = "#111827";
    ctx.font = "bold 18px Arial";
    ctx.fillText("Distribuição de Satisfação", 600, 120);

    var cx = 700, cy = 280, raio = 110, angulo = -Math.PI / 2;
    fatias.forEach(function (f, i) {
        if (totalDist > 0 && f.valor > 0) {
            var fim = angulo + (f.valor / totalDist) * Math.PI * 2;
            ctx.beginPath();
            ctx.moveTo(cx, cy);
            ctx.arc(cx, cy, raio, angulo, fim);
            ctx.closePath();
            ctx.fillStyle = f.cor;
            ctx.fill();
            angulo = fim;
        }
        ctx.fillStyle = f.cor;
        ctx.fillRect(840, 220 + i * 30, 14, 14);
        ctx.fillStyle = "#111827";
        ctx.font = "14px Arial";
        ctx.fillText(f.rotulo + ": " + f.valor, 862, 232 + i * 30);
    });

    // gráfico de evolução (desenhado a partir do SVG da tela)
    ctx.fillStyle = "#111827";
    ctx.font = "bold 18px Arial";
    ctx.fillText("Evolução Temporal", 40, 120);

    var svg = document.querySelector("#graficoEvolucao svg");
    var finalizar = function () {
        canvas.toBlob(function (blob) {
            var link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = "graficos_pesquisa_" + formularioSelecionadoId + ".png";
            document.body.appendChild(link);
            link.click();
            link.remove();
        });
    };

    if (!svg) {
        finalizar();
        return;
    }

    var imagem = new Image();
    imagem.onload = function () {
        ctx.drawImage(imagem, 40, 140, 500, 280);
        finalizar();
    };
    imagem.onerror = finalizar;
    imagem.src = "data:image/svg+xml;charset=utf-8," + encodeURIComponent(new XMLSerializer().serializeToString(svg));
}

// RF12: log de acesso dos gestores
async function carregarLogsAcesso() {
    var container = document.getElementById("listaLogs");
    try {
        var r = await chamarApi("logs_acesso");
        if (sessaoExpirada(r)) return;

        var logs = Array.isArray(r.dados) ? r.dados : [];
        if (!r.ok || logs.length === 0) {
            container.innerHTML = '<p class="aviso-vazio">' + (r.ok ? "Nenhum acesso registrado ainda." : escaparHtml(r.dados.erro || "Erro ao carregar os acessos.")) + '</p>';
            return;
        }

        var rotulos = {
            login: ["acao-login", "Entrou no painel"],
            logout: ["acao-logout", "Saiu do painel"],
            falha_login: ["acao-falha", "Tentativa de login recusada"]
        };

        container.innerHTML = logs.map(function (log) {
            var rotulo = rotulos[log.acao] || ["acao-logout", log.acao];
            return '<div class="linha-log">' +
                '<span><span class="' + rotulo[0] + '">' + rotulo[1] + '</span> · ' + escaparHtml(log.email) + '</span>' +
                '<small>' + formatarDataHora(log.data_hora) + '</small>' +
                '</div>';
        }).join("");
    } catch (e) {
        container.innerHTML = '<p class="aviso-vazio">Erro de conexão ao carregar os acessos.</p>';
    }
}


// ===================== Utilidades =====================

// "2026-09-16 19:45:00" ou "2026-09-16" -> Date no horário local
// (datas sem hora são lidas como meia-noite local, senão o navegador
// mostraria o dia anterior)
function paraData(valor) {
    if (!valor) return null;
    var texto = String(valor).replace(" ", "T");
    if (texto.length === 10) texto += "T00:00:00";
    var data = new Date(texto);
    return isNaN(data.getTime()) ? null : data;
}

function formatarData(valor) {
    var data = paraData(valor);
    return data ? data.toLocaleDateString("pt-BR") : (valor || "");
}

function formatarDataHora(valor) {
    var data = paraData(valor);
    if (!data) return valor || "";
    return data.toLocaleDateString("pt-BR") + " às " +
        data.toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" });
}

// "2026-09-16T19:45" (campo datetime-local) -> "2026-09-16 19:45:00"
function paraDatetimeMysql(valor) {
    if (!valor) return null;
    return valor.replace("T", " ") + (valor.length === 16 ? ":00" : "");
}

// "2026-09-16 19:45:00" -> "2026-09-16T19:45" (para preencher o campo datetime-local)
function paraDatetimeLocal(valor) {
    if (!valor) return "";
    return String(valor).replace(" ", "T").slice(0, 16);
}

// escapa texto para usar dentro de HTML e de atributos (mantém as quebras de linha)
function escaparHtml(texto) {
    return (texto == null ? "" : String(texto))
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
}