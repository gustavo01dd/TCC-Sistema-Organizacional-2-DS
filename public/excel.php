<?php
// ============================================================
// Planilhas do Excel (.xlsx) em PHP puro, sem bibliotecas.
// Um arquivo .xlsx é um ZIP com alguns arquivos XML dentro (Office Open XML).
//
//   gerarPlanilhaRespostas()  abas "Respostas" (formatada) e "Resumo" (com fórmulas)
//   gerarModeloImportacao()   planilha-modelo para importar funcionários
//   lerPlanilhaImportacao()   lê .xlsx ou .csv enviado na importação
// ============================================================

// este arquivo só guarda funções: não abre direto pelo navegador
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(404);
    exit;
}

// número da coluna -> letra (1 = A, 27 = AA)
function excelColuna($numero) {
    $letras = '';
    while ($numero > 0) {
        $resto = ($numero - 1) % 26;
        $letras = chr(65 + $resto) . $letras;
        $numero = intdiv($numero - 1, 26);
    }
    return $letras;
}

// letra -> número da coluna (A = 1, AA = 27)
function excelNumeroColuna($letras) {
    $numero = 0;
    foreach (str_split(strtoupper($letras)) as $letra) {
        $numero = $numero * 26 + (ord($letra) - 64);
    }
    return $numero;
}

// texto seguro para XML (remove caracteres de controle que corrompem o arquivo)
function excelTexto($texto) {
    $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string)$texto);
    return htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// "2026-09-25" -> número de série de data do Excel (vira data de verdade, filtrável)
function excelDataSerial($data) {
    $dia = DateTime::createFromFormat('!Y-m-d', substr((string)$data, 0, 10), new DateTimeZone('UTC'));
    if (!$dia) {
        return null;
    }
    return intdiv($dia->getTimestamp(), 86400) + 25569;
}

// quantas linhas um texto ocupa numa coluna (para calcular a altura da linha)
function excelContarLinhas($texto, $caracteresPorLinha) {
    $linhas = 0;
    foreach (explode("\n", (string)$texto) as $paragrafo) {
        $tamanho = function_exists('mb_strlen') ? mb_strlen($paragrafo) : strlen($paragrafo);
        $linhas += max(1, (int)ceil($tamanho / $caracteresPorLinha));
    }
    return $linhas;
}

// critério de CONT.SE que compara o texto exatamente (sem curingas * ? ~)
function excelCriterioExato($texto) {
    $texto = str_replace(['~', '*', '?'], ['~~', '~*', '~?'], (string)$texto);
    return '"=' . str_replace('"', '""', $texto) . '"';
}

// ------------------------------------------------------------
// Montagem do arquivo
// ------------------------------------------------------------

// Estilos disponíveis (posição em cellXfs):
const XL_PADRAO = 0, XL_TITULO = 1, XL_SUBTITULO = 2, XL_CABECALHO = 3,
      XL_DATA = 4, XL_DATA_Z = 5, XL_CENTRO = 6, XL_CENTRO_Z = 7,
      XL_COMENTARIO = 8, XL_COMENTARIO_Z = 9, XL_TEXTO = 10, XL_TEXTO_Z = 11,
      XL_MEDIA = 12, XL_MEDIA_Z = 13, XL_PERCENTUAL = 14, XL_PERCENTUAL_Z = 15,
      XL_SECAO = 16, XL_ROTULO = 17, XL_DESTAQUE = 18, XL_LEGENDA = 19;

class PlanilhaXlsx {
    private $textos = [];
    private $indices = [];
    private $usos = 0;
    private $abas = [];

    // índice do texto em sharedStrings.xml
    public function texto($valor) {
        $valor = str_replace(["\r\n", "\r"], "\n", (string)$valor);
        $this->usos++;
        if (!isset($this->indices[$valor])) {
            $this->indices[$valor] = count($this->textos);
            $this->textos[] = $valor;
        }
        return $this->indices[$valor];
    }

    public function celTexto($ref, $estilo, $valor) {
        return '<c r="' . $ref . '" s="' . $estilo . '" t="s"><v>' . $this->texto($valor) . '</v></c>';
    }

    public static function celNumero($ref, $estilo, $valor) {
        return '<c r="' . $ref . '" s="' . $estilo . '"><v>' . $valor . '</v></c>';
    }

    public static function celVazia($ref, $estilo) {
        return '<c r="' . $ref . '" s="' . $estilo . '"/>';
    }

    // fórmula com o valor já calculado (aparece certo mesmo em leitores que não recalculam)
    public static function celFormula($ref, $estilo, $formula, $valor) {
        if ($valor === null || $valor === '') {
            return '<c r="' . $ref . '" s="' . $estilo . '" t="str"><f>' . excelTexto($formula) . '</f><v></v></c>';
        }
        if (is_string($valor)) {
            return '<c r="' . $ref . '" s="' . $estilo . '" t="str"><f>' . excelTexto($formula) . '</f><v>' . excelTexto($valor) . '</v></c>';
        }
        return '<c r="' . $ref . '" s="' . $estilo . '"><f>' . excelTexto($formula) . '</f><v>' . $valor . '</v></c>';
    }

    // $opcoes: filtro (ex.: "A3:L10"), linhaTitulos (repetida na impressão), congelar (linha a partir da qual rola)
    public function adicionarAba($nome, array $colunas, $linhasXml, $ultimaColuna, $ultimaLinha, $mesclagens = [], $opcoes = []) {
        $this->abas[] = compact('nome', 'colunas', 'linhasXml', 'ultimaColuna', 'ultimaLinha', 'mesclagens', 'opcoes');
    }

    private function xmlAba($aba, $selecionada) {
        $ns = 'xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"';
        $cols = '';
        foreach ($aba['colunas'] as $i => $largura) {
            $n = $i + 1;
            $cols .= '<col min="' . $n . '" max="' . $n . '" width="' . $largura . '" customWidth="1"/>';
        }
        $vista = '<sheetView showGridLines="0"' . ($selecionada ? ' tabSelected="1"' : '') . ' workbookViewId="0">';
        if (!empty($aba['opcoes']['congelar'])) {
            $linha = $aba['opcoes']['congelar'];
            $vista .= '<pane ySplit="' . ($linha - 1) . '" topLeftCell="A' . $linha . '" activePane="bottomLeft" state="frozen"/>'
                . '<selection pane="bottomLeft" activeCell="A' . $linha . '" sqref="A' . $linha . '"/>';
        }
        $vista .= '</sheetView>';
        $mescla = '';
        if ($aba['mesclagens']) {
            $mescla = '<mergeCells count="' . count($aba['mesclagens']) . '">';
            foreach ($aba['mesclagens'] as $faixa) {
                $mescla .= '<mergeCell ref="' . $faixa . '"/>';
            }
            $mescla .= '</mergeCells>';
        }
        $filtro = !empty($aba['opcoes']['filtro']) ? '<autoFilter ref="' . $aba['opcoes']['filtro'] . '"/>' : '';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<worksheet ' . $ns . '>'
            . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            . '<dimension ref="A1:' . $aba['ultimaColuna'] . max(1, $aba['ultimaLinha']) . '"/>'
            . '<sheetViews>' . $vista . '</sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . '<cols>' . $cols . '</cols>'
            . '<sheetData>' . $aba['linhasXml'] . '</sheetData>'
            . $filtro . $mescla
            . '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>'
            . '<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            . '</worksheet>';
    }

    public function gerar() {
        $cab = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $nsP = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $nsR = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

        $arquivos = [];
        $sheets = '';
        $nomes = '';
        $tipos = '';
        $rels = '';
        foreach ($this->abas as $i => $aba) {
            $n = $i + 1;
            $arquivos["xl/worksheets/sheet$n.xml"] = $this->xmlAba($aba, $i === 0);
            $sheets .= '<sheet name="' . excelTexto($aba['nome']) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
            $tipos .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $rels .= '<Relationship Id="rId' . $n . '" Type="' . $nsR . '/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
            if (!empty($aba['opcoes']['filtro'])) {
                [$ini, $fim] = explode(':', $aba['opcoes']['filtro']);
                preg_match('/([A-Z]+)(\d+)/', $ini, $a);
                preg_match('/([A-Z]+)(\d+)/', $fim, $b);
                $nomes .= '<definedName name="_xlnm._FilterDatabase" localSheetId="' . $i . '" hidden="1">\'' . excelTexto($aba['nome']) . "'!\$$a[1]\$$a[2]:\$$b[1]\$$b[2]</definedName>";
            }
            if (!empty($aba['opcoes']['linhaTitulos'])) {
                $l = $aba['opcoes']['linhaTitulos'];
                $nomes .= '<definedName name="_xlnm.Print_Titles" localSheetId="' . $i . '">\'' . excelTexto($aba['nome']) . "'!\$$l:\$$l</definedName>";
            }
        }
        $total = count($this->abas);

        $textosXml = '';
        foreach ($this->textos as $valor) {
            $textosXml .= '<si><t xml:space="preserve">' . excelTexto($valor) . '</t></si>';
        }

        $arquivos = [
            '[Content_Types].xml' => $cab . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . $tipos
                . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
                . '</Types>',
            '_rels/.rels' => $cab . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="' . $nsR . '/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => $cab . '<workbook xmlns="' . $nsP . '" xmlns:r="' . $nsR . '">'
                . '<bookViews><workbookView activeTab="0"/></bookViews>'
                . '<sheets>' . $sheets . '</sheets>'
                . ($nomes ? '<definedNames>' . $nomes . '</definedNames>' : '')
                . '<calcPr calcId="191029" fullCalcOnLoad="1"/>'
                . '</workbook>',
            'xl/_rels/workbook.xml.rels' => $cab . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . $rels
                . '<Relationship Id="rId' . ($total + 1) . '" Type="' . $nsR . '/styles" Target="styles.xml"/>'
                . '<Relationship Id="rId' . ($total + 2) . '" Type="' . $nsR . '/sharedStrings" Target="sharedStrings.xml"/>'
                . '</Relationships>',
            'xl/styles.xml' => $cab . self::estilos($nsP),
            'xl/sharedStrings.xml' => $cab . '<sst xmlns="' . $nsP . '" count="' . $this->usos . '" uniqueCount="' . count($this->textos) . '">' . $textosXml . '</sst>',
        ] + $arquivos;

        return excelMontarZip($arquivos);
    }

    // cores: faixa do título 1F4E78, cabeçalho 2E75B6, zebra DDEBF7
    private static function estilos($ns) {
        $alinhar = fn($h, $v = 'center', $extra = '') => '<alignment horizontal="' . $h . '" vertical="' . $v . '"' . $extra . '/>';
        $xf = function ($fmt, $fonte, $preenchimento, $borda, $alinhamento) {
            return '<xf numFmtId="' . $fmt . '" fontId="' . $fonte . '" fillId="' . $preenchimento . '" borderId="' . $borda . '" xfId="0"'
                . ($fmt ? ' applyNumberFormat="1"' : '') . ' applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">' . $alinhamento . '</xf>';
        };
        $celulas = [
            '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>',                     // 0 padrão
            $xf(0, 1, 2, 0, $alinhar('left', 'center', ' indent="1"')),                           // 1 título
            $xf(0, 2, 0, 0, $alinhar('left', 'center', ' indent="1"')),                           // 2 subtítulo
            $xf(0, 3, 3, 2, $alinhar('center', 'center', ' wrapText="1"')),                       // 3 cabeçalho
            $xf(164, 0, 0, 1, $alinhar('center')),                                                // 4 data
            $xf(164, 0, 4, 1, $alinhar('center')),                                                // 5 data (zebra)
            $xf(0, 0, 0, 1, $alinhar('center', 'center', ' wrapText="1"')),                       // 6 centro
            $xf(0, 0, 4, 1, $alinhar('center', 'center', ' wrapText="1"')),                       // 7 centro (zebra)
            $xf(0, 0, 0, 1, $alinhar('left', 'center', ' wrapText="1" indent="1"')),              // 8 comentário
            $xf(0, 0, 4, 1, $alinhar('left', 'center', ' wrapText="1" indent="1"')),              // 9 comentário (zebra)
            $xf(0, 0, 0, 1, $alinhar('left', 'center', ' wrapText="1"')),                         // 10 texto
            $xf(0, 0, 4, 1, $alinhar('left', 'center', ' wrapText="1"')),                         // 11 texto (zebra)
            $xf(165, 0, 0, 1, $alinhar('center')),                                                // 12 média 0,0
            $xf(165, 0, 4, 1, $alinhar('center')),                                                // 13 média (zebra)
            $xf(9, 0, 0, 1, $alinhar('center')),                                                  // 14 percentual
            $xf(9, 0, 4, 1, $alinhar('center')),                                                  // 15 percentual (zebra)
            $xf(0, 4, 0, 0, $alinhar('left', 'bottom')),                                          // 16 título de seção
            $xf(0, 5, 0, 1, $alinhar('left', 'center', ' indent="1"')),                           // 17 rótulo
            $xf(165, 5, 4, 1, $alinhar('center')),                                                // 18 destaque 0,0
            $xf(0, 2, 0, 0, $alinhar('left', 'top', ' wrapText="1" indent="1"')),                 // 19 legenda
        ];
        return '<styleSheet xmlns="' . $ns . '">'
            . '<numFmts count="2"><numFmt numFmtId="164" formatCode="dd/mm/yyyy"/><numFmt numFmtId="165" formatCode="0.0"/></numFmts>'
            . '<fonts count="6">'
            . '<font><sz val="10"/><color rgb="FF1F2937"/><name val="Arial"/><family val="2"/></font>'
            . '<font><b/><sz val="15"/><color rgb="FFFFFFFF"/><name val="Arial"/><family val="2"/></font>'
            . '<font><i/><sz val="9"/><color rgb="FF5B6B85"/><name val="Arial"/><family val="2"/></font>'
            . '<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Arial"/><family val="2"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FF1F4E78"/><name val="Arial"/><family val="2"/></font>'
            . '<font><b/><sz val="10"/><color rgb="FF1F2937"/><name val="Arial"/><family val="2"/></font>'
            . '</fonts>'
            . '<fills count="5">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF1F4E78"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF2E75B6"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFDDEBF7"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="3">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FFBDD7EE"/></left><right style="thin"><color rgb="FFBDD7EE"/></right><top style="thin"><color rgb="FFBDD7EE"/></top><bottom style="thin"><color rgb="FFBDD7EE"/></bottom><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FF9DC3E6"/></left><right style="thin"><color rgb="FF9DC3E6"/></right><top style="thin"><color rgb="FF9DC3E6"/></top><bottom style="medium"><color rgb="FF1F4E78"/></bottom><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="' . count($celulas) . '">' . implode('', $celulas) . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }
}

// linha de título mesclada (faixa azul) + linha de subtítulo
function excelLinhasTitulo(PlanilhaXlsx $p, $titulo, $subtitulo, $totalColunas) {
    $celulas = $p->celTexto('A1', XL_TITULO, $titulo);
    for ($c = 2; $c <= $totalColunas; $c++) {
        $celulas .= PlanilhaXlsx::celVazia(excelColuna($c) . '1', XL_TITULO);
    }
    return '<row r="1" ht="36" customHeight="1">' . $celulas . '</row>'
        . '<row r="2" ht="22" customHeight="1">' . $p->celTexto('A2', XL_SUBTITULO, $subtitulo) . '</row>';
}

// ------------------------------------------------------------
// Planilha das respostas: abas "Respostas" e "Resumo"
// ------------------------------------------------------------

// $perguntas: [['texto', 'tipo', 'categoria', 'opcoes'], ...]
// $linhas: [['data' => 'Y-m-d', 'valores' => [nota (int) | texto da alternativa | null, ...], 'comentario' => string], ...]
function gerarPlanilhaRespostas($titulo, $subtitulo, array $perguntas, array $linhas) {
    $p = new PlanilhaXlsx();
    $totalPerguntas = count($perguntas);
    $totalColunas = $totalPerguntas + 2;          // data + perguntas + comentário
    $ultimaColuna = excelColuna($totalColunas);
    $cab = 3;
    $primeira = $cab + 1;
    $ultimaLinha = $cab + count($linhas);
    $fimFaixa = max($primeira, $ultimaLinha);     // faixa das fórmulas (uma linha vazia se não houver respostas)

    $larguraPergunta = 24;
    $larguraComentario = 60;

    // ---------- aba Respostas ----------
    $xml = excelLinhasTitulo($p, $titulo, $subtitulo, $totalColunas);

    $maiorLinhas = 1;
    foreach ($perguntas as $pergunta) {
        $maiorLinhas = max($maiorLinhas, excelContarLinhas($pergunta['texto'], $larguraPergunta - 3));
    }
    $celulas = $p->celTexto("A$cab", XL_CABECALHO, 'Data de envio');
    foreach ($perguntas as $i => $pergunta) {
        $celulas .= $p->celTexto(excelColuna($i + 2) . $cab, XL_CABECALHO, $pergunta['texto']);
    }
    $celulas .= $p->celTexto($ultimaColuna . $cab, XL_CABECALHO, 'Comentário');
    $xml .= '<row r="' . $cab . '" ht="' . min(160, max(32, $maiorLinhas * 13 + 10)) . '" customHeight="1">' . $celulas . '</row>';

    foreach (array_values($linhas) as $n => $linha) {
        $r = $primeira + $n;
        $z = $n % 2 === 1;
        $serial = excelDataSerial($linha['data'] ?? '');
        $celulas = $serial === null ? PlanilhaXlsx::celVazia("A$r", $z ? XL_DATA_Z : XL_DATA) : PlanilhaXlsx::celNumero("A$r", $z ? XL_DATA_Z : XL_DATA, $serial);
        $valores = array_values($linha['valores'] ?? []);
        for ($i = 0; $i < $totalPerguntas; $i++) {
            $ref = excelColuna($i + 2) . $r;
            $valor = $valores[$i] ?? null;
            $estilo = $z ? XL_CENTRO_Z : XL_CENTRO;
            if ($valor === null || $valor === '') {
                $celulas .= PlanilhaXlsx::celVazia($ref, $estilo);
            } elseif (is_int($valor)) {
                $celulas .= PlanilhaXlsx::celNumero($ref, $estilo, $valor);
            } else {
                $celulas .= $p->celTexto($ref, $estilo, $valor);
            }
        }
        $comentario = trim(str_replace(["\r\n", "\r"], "\n", (string)($linha['comentario'] ?? '')));
        $ref = $ultimaColuna . $r;
        $estilo = $z ? XL_COMENTARIO_Z : XL_COMENTARIO;
        $celulas .= $comentario === '' ? PlanilhaXlsx::celVazia($ref, $estilo) : $p->celTexto($ref, $estilo, $comentario);
        $altura = $comentario === '' ? 20 : min(409, max(20, excelContarLinhas($comentario, $larguraComentario) * 13 + 7));
        $xml .= '<row r="' . $r . '" ht="' . $altura . '" customHeight="1">' . $celulas . '</row>';
    }

    $colunas = array_merge([14], array_fill(0, $totalPerguntas, $larguraPergunta), [$larguraComentario]);
    $p->adicionarAba('Respostas', $colunas, $xml, $ultimaColuna, max($cab, $ultimaLinha),
        ["A1:{$ultimaColuna}1", "A2:{$ultimaColuna}2"],
        ['filtro' => "A$cab:$ultimaColuna" . max($cab, $ultimaLinha), 'linhaTitulos' => $cab, 'congelar' => $primeira]);

    // ---------- aba Resumo (fórmulas sobre a aba Respostas) ----------
    $faixa = fn($i) => 'Respostas!' . excelColuna($i + 2) . $primeira . ':' . excelColuna($i + 2) . $fimFaixa;
    $valoresColuna = fn($i) => array_values(array_filter(array_map(fn($l) => array_values($l['valores'] ?? [])[$i] ?? null, $linhas), fn($v) => $v !== null && $v !== ''));
    $media = fn(array $numeros) => count($numeros) ? array_sum($numeros) / count($numeros) : null;

    $x = excelLinhasTitulo($p, 'Resumo: ' . $titulo, 'Valores calculados com fórmulas a partir da aba Respostas. Se editar as respostas, o resumo se atualiza sozinho.', 6);

    // média geral e total
    $faixasNota = [];
    $todasNotas = [];
    foreach ($perguntas as $i => $pergunta) {
        if ($pergunta['tipo'] === 'nota') {
            $faixasNota[] = $faixa($i);
            $todasNotas = array_merge($todasNotas, $valoresColuna($i));
        }
    }
    $x .= '<row r="4" ht="22" customHeight="1">' . $p->celTexto('A4', XL_ROTULO, 'Média geral (perguntas de nota)') . PlanilhaXlsx::celVazia('B4', XL_ROTULO)
        . ($faixasNota ? PlanilhaXlsx::celFormula('C4', XL_DESTAQUE, 'IFERROR(AVERAGE(' . implode(',', $faixasNota) . '),"")', $media($todasNotas)) : PlanilhaXlsx::celVazia('C4', XL_DESTAQUE))
        . '</row>';
    $x .= '<row r="5" ht="22" customHeight="1">' . $p->celTexto('A5', XL_ROTULO, 'Total de respostas') . PlanilhaXlsx::celVazia('B5', XL_ROTULO)
        . PlanilhaXlsx::celFormula('C5', XL_CENTRO, "COUNT(Respostas!A$primeira:A$fimFaixa)", count($linhas)) . '</row>';
    $mesclas = ['A1:F1', 'A2:F2', 'A4:B4', 'A5:B5'];

    // tabela das perguntas
    $r = 7;
    $x .= '<row r="' . $r . '" ht="30" customHeight="1">'
        . $p->celTexto("A$r", XL_CABECALHO, 'Nº') . $p->celTexto("B$r", XL_CABECALHO, 'Pergunta') . $p->celTexto("C$r", XL_CABECALHO, 'Categoria')
        . $p->celTexto("D$r", XL_CABECALHO, 'Tipo') . $p->celTexto("E$r", XL_CABECALHO, 'Respostas') . $p->celTexto("F$r", XL_CABECALHO, 'Resultado')
        . '</row>';
    foreach ($perguntas as $i => $pergunta) {
        $r++;
        $z = $i % 2 === 1;
        $valores = $valoresColuna($i);
        $fx = $faixa($i);
        $celulas = PlanilhaXlsx::celNumero("A$r", $z ? XL_CENTRO_Z : XL_CENTRO, $i + 1)
            . $p->celTexto("B$r", $z ? XL_TEXTO_Z : XL_TEXTO, $pergunta['texto'])
            . $p->celTexto("C$r", $z ? XL_CENTRO_Z : XL_CENTRO, $pergunta['categoria'] ?: '—')
            . $p->celTexto("D$r", $z ? XL_CENTRO_Z : XL_CENTRO, rotuloTipo($pergunta['tipo']))
            . PlanilhaXlsx::celFormula("E$r", $z ? XL_CENTRO_Z : XL_CENTRO, "COUNTA($fx)", count($valores));
        if ($pergunta['tipo'] === 'nota') {
            $celulas .= PlanilhaXlsx::celFormula("F$r", $z ? XL_MEDIA_Z : XL_MEDIA, "IFERROR(AVERAGE($fx),\"\")", $media($valores));
        } elseif ($pergunta['tipo'] === 'sim_nao') {
            $sim = count(array_filter($valores, fn($v) => $v === 'Sim'));
            $celulas .= PlanilhaXlsx::celFormula("F$r", $z ? XL_PERCENTUAL_Z : XL_PERCENTUAL, 'IFERROR(COUNTIF(' . $fx . ',' . excelCriterioExato('Sim') . ")/COUNTA($fx),\"\")", count($valores) ? $sim / count($valores) : null);
        } else {
            $celulas .= $p->celTexto("F$r", $z ? XL_CENTRO_Z : XL_CENTRO, 'ver alternativas abaixo');
        }
        $altura = max(20, excelContarLinhas($pergunta['texto'], 44) * 13 + 7);
        $x .= '<row r="' . $r . '" ht="' . $altura . '" customHeight="1">' . $celulas . '</row>';
    }

    // médias por categoria
    $categorias = [];
    foreach ($perguntas as $i => $pergunta) {
        if ($pergunta['tipo'] === 'nota') {
            $categorias[$pergunta['categoria'] ?: 'Sem categoria'][] = $i;
        }
    }
    if ($categorias) {
        ksort($categorias);
        $r += 2;
        $x .= '<row r="' . $r . '" ht="22" customHeight="1">' . $p->celTexto("A$r", XL_SECAO, 'Médias por categoria') . '</row>';
        $mesclas[] = "A$r:F$r";
        $r++;
        $x .= '<row r="' . $r . '" ht="24" customHeight="1">' . $p->celTexto("A$r", XL_CABECALHO, 'Categoria') . PlanilhaXlsx::celVazia("B$r", XL_CABECALHO)
            . $p->celTexto("C$r", XL_CABECALHO, 'Perguntas') . $p->celTexto("D$r", XL_CABECALHO, 'Média') . '</row>';
        $mesclas[] = "A$r:B$r";
        $k = 0;
        foreach ($categorias as $categoria => $indices) {
            $r++;
            $z = $k++ % 2 === 1;
            $numeros = [];
            foreach ($indices as $i) {
                $numeros = array_merge($numeros, $valoresColuna($i));
            }
            $x .= '<row r="' . $r . '" ht="20" customHeight="1">' . $p->celTexto("A$r", $z ? XL_TEXTO_Z : XL_TEXTO, $categoria) . PlanilhaXlsx::celVazia("B$r", $z ? XL_TEXTO_Z : XL_TEXTO)
                . PlanilhaXlsx::celNumero("C$r", $z ? XL_CENTRO_Z : XL_CENTRO, count($indices))
                . PlanilhaXlsx::celFormula("D$r", $z ? XL_MEDIA_Z : XL_MEDIA, 'IFERROR(AVERAGE(' . implode(',', array_map($faixa, $indices)) . '),"")', $media($numeros))
                . '</row>';
            $mesclas[] = "A$r:B$r";
        }
    }

    // alternativas das perguntas de sim/não e múltipla escolha
    $comAlternativas = array_filter($perguntas, fn($q) => $q['tipo'] !== 'nota');
    if ($comAlternativas) {
        $r += 2;
        $x .= '<row r="' . $r . '" ht="22" customHeight="1">' . $p->celTexto("A$r", XL_SECAO, 'Alternativas escolhidas') . '</row>';
        $mesclas[] = "A$r:F$r";
        $r++;
        $x .= '<row r="' . $r . '" ht="24" customHeight="1">' . $p->celTexto("A$r", XL_CABECALHO, 'Nº') . $p->celTexto("B$r", XL_CABECALHO, 'Pergunta')
            . $p->celTexto("C$r", XL_CABECALHO, 'Alternativa') . $p->celTexto("D$r", XL_CABECALHO, 'Respostas') . $p->celTexto("E$r", XL_CABECALHO, '%') . '</row>';
        $k = 0;
        foreach ($perguntas as $i => $pergunta) {
            if ($pergunta['tipo'] === 'nota') {
                continue;
            }
            $valores = $valoresColuna($i);
            $fx = $faixa($i);
            $rotulos = $pergunta['tipo'] === 'sim_nao' ? ['Sim', 'Não'] : (array)$pergunta['opcoes'];
            foreach ($rotulos as $rotulo) {
                $r++;
                $z = $k++ % 2 === 1;
                $quantos = count(array_filter($valores, fn($v) => $v === $rotulo));
                $x .= '<row r="' . $r . '" ht="20" customHeight="1">'
                    . PlanilhaXlsx::celNumero("A$r", $z ? XL_CENTRO_Z : XL_CENTRO, $i + 1)
                    . $p->celTexto("B$r", $z ? XL_TEXTO_Z : XL_TEXTO, $pergunta['texto'])
                    . $p->celTexto("C$r", $z ? XL_TEXTO_Z : XL_TEXTO, $rotulo)
                    . PlanilhaXlsx::celFormula("D$r", $z ? XL_CENTRO_Z : XL_CENTRO, 'COUNTIF(' . $fx . ',' . excelCriterioExato($rotulo) . ')', $quantos)
                    . PlanilhaXlsx::celFormula("E$r", $z ? XL_PERCENTUAL_Z : XL_PERCENTUAL, "IFERROR(D$r/COUNTA($fx),\"\")", count($valores) ? $quantos / count($valores) : null)
                    . '</row>';
            }
        }
    }

    $p->adicionarAba('Resumo', [8, 52, 22, 18, 12, 14], $x, 'F', $r, $mesclas, []);
    return $p->gerar();
}

// ------------------------------------------------------------
// Planilha-modelo para importar funcionários
// ------------------------------------------------------------

const EMAIL_EXEMPLO_IMPORTACAO = 'maria.exemplo@escola.com';

function gerarModeloImportacao() {
    $p = new PlanilhaXlsx();
    $xml = excelLinhasTitulo($p, 'Importação de funcionários', 'Preencha uma pessoa por linha a partir da linha 5. A linha 5 é só um exemplo: apague-a ou escreva por cima.', 6);
    $legenda = "Obrigatórios: nome e email.  Senha: mínimo de 6 caracteres; se ficar vazia, o sistema gera uma senha provisória e mostra na tela.  "
        . "Data de admissão: dd/mm/aaaa.  Perfil: funcionario ou gestor (vazio = funcionario).  Emails já cadastrados são ignorados.";
    $xml .= '<row r="3" ht="42" customHeight="1">' . $p->celTexto('A3', XL_LEGENDA, $legenda) . '</row>';
    $cabecalhos = ['nome', 'email', 'senha', 'cargo', 'data_admissao', 'perfil'];
    $celulas = '';
    foreach ($cabecalhos as $i => $c) {
        $celulas .= $p->celTexto(excelColuna($i + 1) . '4', XL_CABECALHO, $c);
    }
    $xml .= '<row r="4" ht="24" customHeight="1">' . $celulas . '</row>';
    $exemplo = ['Maria Exemplo', EMAIL_EXEMPLO_IMPORTACAO, 'trocar123', 'Professora', '10/02/2025', 'funcionario'];
    $celulas = '';
    foreach ($exemplo as $i => $v) {
        $celulas .= $p->celTexto(excelColuna($i + 1) . '5', XL_TEXTO_Z, $v);
    }
    $xml .= '<row r="5" ht="20" customHeight="1">' . $celulas . '</row>';
    $p->adicionarAba('Funcionarios', [30, 34, 16, 20, 16, 14], $xml, 'F', 5, ['A1:F1', 'A2:F2', 'A3:F3'], ['congelar' => 5]);
    return $p->gerar();
}

// ------------------------------------------------------------
// ZIP (escrita e leitura)
// ------------------------------------------------------------

// monta um arquivo ZIP em memória (formato usado pelo .xlsx)
function excelMontarZip(array $arquivos) {
    $zip = '';
    $diretorio = '';
    $quantidade = 0;

    $agora = getdate();
    $horaDos = ($agora['hours'] << 11) | ($agora['minutes'] << 5) | intdiv($agora['seconds'], 2);
    $dataDos = (($agora['year'] - 1980) << 9) | ($agora['mon'] << 5) | $agora['mday'];

    foreach ($arquivos as $nome => $conteudo) {
        $crc = crc32($conteudo);
        $comprimido = function_exists('gzdeflate') ? gzdeflate($conteudo, 6) : false;
        $metodo = 8; // deflate
        if ($comprimido === false) {
            $comprimido = $conteudo;
            $metodo = 0; // sem compressão
        }
        $posicao = strlen($zip);

        $zip .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0, $metodo, $horaDos, $dataDos, $crc, strlen($comprimido), strlen($conteudo), strlen($nome), 0)
            . $nome . $comprimido;

        $diretorio .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, $metodo, $horaDos, $dataDos, $crc, strlen($comprimido), strlen($conteudo), strlen($nome), 0, 0, 0, 0, 0, $posicao)
            . $nome;
        $quantidade++;
    }

    return $zip . $diretorio
        . pack('VvvvvVVv', 0x06054b50, 0, 0, $quantidade, $quantidade, strlen($diretorio), strlen($zip), 0);
}

// lê as entradas de um ZIP em memória: [nome => conteúdo]
function excelLerZip($dados, array $interessam) {
    $limite = 20 * 1024 * 1024;
    // registro de fim do diretório central: a última assinatura PK\x05\x06 do arquivo
    $pos = strrpos($dados, "PK\x05\x06");
    if ($pos === false) {
        throw new RuntimeException('O arquivo não é uma planilha .xlsx válida.');
    }
    $fim = unpack('Vassinatura/vdisco/vdiscoInicio/ventradasDisco/ventradas/Vtamanho/Vinicio', substr($dados, $pos, 22));
    $p = $fim['inicio'];
    $resultado = [];
    for ($i = 0; $i < $fim['entradas']; $i++) {
        $cd = unpack('Vassinatura/vfeito/vprecisa/vflags/vmetodo/vhora/vdata/Vcrc/Vcomprimido/Vtamanho/vnome/vextra/vcomentario/vdisco/vinterno/Vexterno/Vlocal', substr($dados, $p, 46));
        if ($cd['assinatura'] !== 0x02014b50) {
            throw new RuntimeException('Planilha .xlsx corrompida.');
        }
        $nome = substr($dados, $p + 46, $cd['nome']);
        $p += 46 + $cd['nome'] + $cd['extra'] + $cd['comentario'];
        if (!in_array($nome, $interessam, true)) {
            continue;
        }
        if ($cd['tamanho'] > $limite) {
            throw new RuntimeException('Planilha grande demais.');
        }
        $local = unpack('Vassinatura/vprecisa/vflags/vmetodo/vhora/vdata/Vcrc/Vcomprimido/Vtamanho/vnome/vextra', substr($dados, $cd['local'], 30));
        $bruto = substr($dados, $cd['local'] + 30 + $local['nome'] + $local['extra'], $cd['comprimido']);
        if ($cd['metodo'] === 8) {
            $conteudo = @gzinflate($bruto, $limite);
        } elseif ($cd['metodo'] === 0) {
            $conteudo = $bruto;
        } else {
            throw new RuntimeException('Compressão da planilha não suportada.');
        }
        if ($conteudo === false) {
            throw new RuntimeException('Não foi possível ler a planilha.');
        }
        $resultado[$nome] = $conteudo;
    }
    return $resultado;
}

// ------------------------------------------------------------
// Leitura da planilha enviada na importação (.xlsx ou .csv)
// Retorna uma lista de linhas; cada linha é uma lista de textos.
// ------------------------------------------------------------

function lerPlanilhaImportacao($caminho, $nomeOriginal) {
    $extensao = strtolower(pathinfo((string)$nomeOriginal, PATHINFO_EXTENSION));
    $dados = file_get_contents($caminho);
    if ($dados === false || $dados === '') {
        throw new RuntimeException('O arquivo está vazio.');
    }
    if ($extensao === 'csv' || $extensao === 'txt') {
        return excelLerCsv($dados);
    }
    if ($extensao === 'xlsx') {
        return excelLerXlsx($dados);
    }
    throw new RuntimeException('Formato não suportado. Envie uma planilha .xlsx ou .csv.');
}

function excelLerCsv($dados) {
    if (str_starts_with($dados, "\xEF\xBB\xBF")) {
        $dados = substr($dados, 3);
    }
    // o Excel no Brasil costuma salvar CSV em Windows-1252
    if (!mb_check_encoding($dados, 'UTF-8')) {
        $dados = mb_convert_encoding($dados, 'UTF-8', 'Windows-1252');
    }
    $primeira = strtok($dados, "\n");
    $separador = ';';
    if (substr_count($primeira, "\t") > substr_count($primeira, ';')) {
        $separador = "\t";
    } elseif (substr_count($primeira, ',') > substr_count($primeira, ';')) {
        $separador = ',';
    }
    $fluxo = fopen('php://temp', 'r+');
    fwrite($fluxo, $dados);
    rewind($fluxo);
    $linhas = [];
    while (($linha = fgetcsv($fluxo, 0, $separador, '"', '')) !== false) {
        $linhas[] = array_map(fn($v) => trim((string)$v), $linha);
    }
    fclose($fluxo);
    return $linhas;
}

function excelLerXlsx($dados) {
    $base = excelLerZip($dados, ['xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/sharedStrings.xml']);
    if (!isset($base['xl/workbook.xml'], $base['xl/_rels/workbook.xml.rels'])) {
        throw new RuntimeException('O arquivo não é uma planilha .xlsx válida.');
    }
    $nsR = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    // primeira aba da pasta de trabalho
    $pasta = simplexml_load_string($base['xl/workbook.xml'], 'SimpleXMLElement', LIBXML_NONET);
    $relacoes = simplexml_load_string($base['xl/_rels/workbook.xml.rels'], 'SimpleXMLElement', LIBXML_NONET);
    if (!$pasta || !$relacoes || !isset($pasta->sheets->sheet[0])) {
        throw new RuntimeException('Não foi possível ler as abas da planilha.');
    }
    $idAba = (string)$pasta->sheets->sheet[0]->attributes($nsR)['id'];
    $alvo = null;
    foreach ($relacoes->Relationship as $rel) {
        if ((string)$rel['Id'] === $idAba) {
            $alvo = (string)$rel['Target'];
        }
    }
    if ($alvo === null) {
        throw new RuntimeException('Não foi possível encontrar a primeira aba da planilha.');
    }
    $caminhoAba = str_starts_with($alvo, '/') ? ltrim($alvo, '/') : 'xl/' . $alvo;
    $abas = excelLerZip($dados, [$caminhoAba]);
    if (!isset($abas[$caminhoAba])) {
        throw new RuntimeException('Não foi possível abrir a primeira aba da planilha.');
    }

    $compartilhados = [];
    if (isset($base['xl/sharedStrings.xml'])) {
        $sst = simplexml_load_string($base['xl/sharedStrings.xml'], 'SimpleXMLElement', LIBXML_NONET);
        foreach ($sst->si as $si) {
            if (isset($si->t)) {
                $compartilhados[] = (string)$si->t;
            } else {
                $texto = '';
                foreach ($si->r as $trecho) {
                    $texto .= (string)$trecho->t;
                }
                $compartilhados[] = $texto;
            }
        }
    }

    $aba = simplexml_load_string($abas[$caminhoAba], 'SimpleXMLElement', LIBXML_NONET);
    $linhas = [];
    foreach ($aba->sheetData->row as $row) {
        $linha = [];
        foreach ($row->c as $c) {
            preg_match('/^([A-Z]+)/', (string)$c['r'], $m);
            $indice = isset($m[1]) ? excelNumeroColuna($m[1]) - 1 : count($linha);
            $tipo = (string)$c['t'];
            if ($tipo === 's') {
                $valor = $compartilhados[(int)$c->v] ?? '';
            } elseif ($tipo === 'inlineStr') {
                $valor = isset($c->is->t) ? (string)$c->is->t : '';
                if ($valor === '' && isset($c->is->r)) {
                    foreach ($c->is->r as $trecho) {
                        $valor .= (string)$trecho->t;
                    }
                }
            } else {
                $valor = (string)$c->v;
            }
            $linha[$indice] = trim($valor);
        }
        if ($linha) {
            $completa = array_fill(0, max(array_keys($linha)) + 1, '');
            $linhas[] = array_replace($completa, $linha);
        }
    }
    return $linhas;
}