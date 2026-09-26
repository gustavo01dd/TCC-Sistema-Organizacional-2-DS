<?php
// ============================================================
// Gera a planilha do Excel (.xlsx) com as respostas, já formatada:
// título em faixa azul, cabeçalho azul com filtros, linhas zebradas,
// colunas com largura ajustada e cabeçalho congelado.
//
// Feito em PHP puro, sem bibliotecas: um arquivo .xlsx é um ZIP
// com alguns arquivos XML dentro (padrão Office Open XML).
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

// $perguntas: textos das perguntas, na ordem
// $linhas: cada item ['data' => 'Y-m-d', 'notas' => [int|null, ...], 'comentario' => string]
function gerarPlanilhaRespostas($titulo, $subtitulo, array $perguntas, array $linhas) {
    $totalColunas = count($perguntas) + 2;          // data + perguntas + comentário
    $ultimaColuna = excelColuna($totalColunas);
    $linhaCabecalho = 3;
    $ultimaLinha = $linhaCabecalho + count($linhas);

    $larguraData = 14;
    $larguraPergunta = 24;
    $larguraComentario = 60;

    // textos ficam em sharedStrings.xml; cada célula guarda só o índice
    $textos = [];
    $indices = [];
    $usos = 0;
    $indiceTexto = function ($valor) use (&$textos, &$indices, &$usos) {
        $valor = str_replace(["\r\n", "\r"], "\n", (string)$valor);
        $usos++;
        if (!isset($indices[$valor])) {
            $indices[$valor] = count($textos);
            $textos[] = $valor;
        }
        return $indices[$valor];
    };

    // estilos (posição em cellXfs, no styles.xml mais abaixo)
    $E_TITULO = 1;
    $E_SUBTITULO = 2;
    $E_CABECALHO = 3;

    $linhasXml = '';

    // linha 1: título em faixa azul-escura
    $celulas = '<c r="A1" s="' . $E_TITULO . '" t="s"><v>' . $indiceTexto($titulo) . '</v></c>';
    for ($c = 2; $c <= $totalColunas; $c++) {
        $celulas .= '<c r="' . excelColuna($c) . '1" s="' . $E_TITULO . '"/>';
    }
    $linhasXml .= '<row r="1" ht="36" customHeight="1">' . $celulas . '</row>';

    // linha 2: informações da exportação
    $linhasXml .= '<row r="2" ht="22" customHeight="1"><c r="A2" s="' . $E_SUBTITULO . '" t="s"><v>' . $indiceTexto($subtitulo) . '</v></c></row>';

    // linha 3: cabeçalho azul, com o texto completo das perguntas quebrando linha
    $cabecalhos = array_merge(['Data de envio'], $perguntas, ['Comentário']);
    $maiorQuantidadeLinhas = 1;
    foreach ($perguntas as $pergunta) {
        $maiorQuantidadeLinhas = max($maiorQuantidadeLinhas, excelContarLinhas($pergunta, $larguraPergunta - 3));
    }
    $alturaCabecalho = min(160, max(32, $maiorQuantidadeLinhas * 13 + 10));
    $celulas = '';
    foreach ($cabecalhos as $i => $cabecalho) {
        $celulas .= '<c r="' . excelColuna($i + 1) . $linhaCabecalho . '" s="' . $E_CABECALHO . '" t="s"><v>' . $indiceTexto($cabecalho) . '</v></c>';
    }
    $linhasXml .= '<row r="' . $linhaCabecalho . '" ht="' . $alturaCabecalho . '" customHeight="1">' . $celulas . '</row>';

    // respostas, com linhas zebradas (uma branca, uma azul-clara)
    foreach (array_values($linhas) as $n => $linha) {
        $r = $linhaCabecalho + 1 + $n;
        $zebra = $n % 2 === 1;
        $eData = $zebra ? 5 : 4;
        $eNota = $zebra ? 7 : 6;
        $eComentario = $zebra ? 9 : 8;

        $serial = excelDataSerial($linha['data'] ?? '');
        $celulas = $serial === null
            ? '<c r="A' . $r . '" s="' . $eData . '"/>'
            : '<c r="A' . $r . '" s="' . $eData . '"><v>' . $serial . '</v></c>';

        $notas = array_values($linha['notas'] ?? []);
        for ($i = 0; $i < count($perguntas); $i++) {
            $referencia = excelColuna($i + 2) . $r;
            $nota = $notas[$i] ?? null;
            $celulas .= $nota === null
                ? '<c r="' . $referencia . '" s="' . $eNota . '"/>'
                : '<c r="' . $referencia . '" s="' . $eNota . '"><v>' . (int)$nota . '</v></c>';
        }

        $comentario = trim(str_replace(["\r\n", "\r"], "\n", (string)($linha['comentario'] ?? '')));
        $referencia = $ultimaColuna . $r;
        $celulas .= $comentario === ''
            ? '<c r="' . $referencia . '" s="' . $eComentario . '"/>'
            : '<c r="' . $referencia . '" s="' . $eComentario . '" t="s"><v>' . $indiceTexto($comentario) . '</v></c>';

        $altura = $comentario === '' ? 20 : min(409, max(20, excelContarLinhas($comentario, $larguraComentario) * 13 + 7));
        $linhasXml .= '<row r="' . $r . '" ht="' . $altura . '" customHeight="1">' . $celulas . '</row>';
    }

    // largura das colunas
    $colunas = '<col min="1" max="1" width="' . $larguraData . '" customWidth="1"/>';
    if (count($perguntas) > 0) {
        $colunas .= '<col min="2" max="' . ($totalColunas - 1) . '" width="' . $larguraPergunta . '" customWidth="1"/>';
    }
    $colunas .= '<col min="' . $totalColunas . '" max="' . $totalColunas . '" width="' . $larguraComentario . '" customWidth="1"/>';

    $faixaFiltro = 'A' . $linhaCabecalho . ':' . $ultimaColuna . $ultimaLinha;
    $cabecalhoXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $nsPrincipal = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $nsRelacoes = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    $planilha = $cabecalhoXml
        . '<worksheet xmlns="' . $nsPrincipal . '" xmlns:r="' . $nsRelacoes . '">'
        . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
        . '<dimension ref="A1:' . $ultimaColuna . $ultimaLinha . '"/>'
        . '<sheetViews><sheetView showGridLines="0" tabSelected="1" workbookViewId="0">'
        . '<pane ySplit="' . $linhaCabecalho . '" topLeftCell="A' . ($linhaCabecalho + 1) . '" activePane="bottomLeft" state="frozen"/>'
        . '<selection pane="bottomLeft" activeCell="A' . ($linhaCabecalho + 1) . '" sqref="A' . ($linhaCabecalho + 1) . '"/>'
        . '</sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="15"/>'
        . '<cols>' . $colunas . '</cols>'
        . '<sheetData>' . $linhasXml . '</sheetData>'
        . '<autoFilter ref="' . $faixaFiltro . '"/>'
        . '<mergeCells count="2"><mergeCell ref="A1:' . $ultimaColuna . '1"/><mergeCell ref="A2:' . $ultimaColuna . '2"/></mergeCells>'
        . '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>'
        . '<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
        . '</worksheet>';

    $textosXml = '';
    foreach ($textos as $valor) {
        $textosXml .= '<si><t xml:space="preserve">' . excelTexto($valor) . '</t></si>';
    }
    $sharedStrings = $cabecalhoXml
        . '<sst xmlns="' . $nsPrincipal . '" count="' . $usos . '" uniqueCount="' . count($textos) . '">' . $textosXml . '</sst>';

    // cores: faixa do título 1F4E78, cabeçalho 2E75B6, zebra DDEBF7
    $estilos = $cabecalhoXml
        . '<styleSheet xmlns="' . $nsPrincipal . '">'
        . '<numFmts count="1"><numFmt numFmtId="164" formatCode="dd/mm/yyyy"/></numFmts>'
        . '<fonts count="4">'
        . '<font><sz val="10"/><color rgb="FF1F2937"/><name val="Arial"/><family val="2"/></font>'
        . '<font><b/><sz val="15"/><color rgb="FFFFFFFF"/><name val="Arial"/><family val="2"/></font>'
        . '<font><i/><sz val="9"/><color rgb="FF5B6B85"/><name val="Arial"/><family val="2"/></font>'
        . '<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Arial"/><family val="2"/></font>'
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
        . '<cellXfs count="10">'
        // 0 padrão
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        // 1 título
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="left" vertical="center" indent="1"/></xf>'
        // 2 subtítulo
        . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center" indent="1"/></xf>'
        // 3 cabeçalho
        . '<xf numFmtId="0" fontId="3" fillId="3" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        // 4 e 5 data (branca / zebra)
        . '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '<xf numFmtId="164" fontId="0" fillId="4" borderId="1" xfId="0" applyNumberFormat="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        // 6 e 7 nota (branca / zebra)
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="4" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        // 8 e 9 comentário (branca / zebra)
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1" indent="1"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="4" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1" indent="1"/></xf>'
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';

    $pasta = $cabecalhoXml
        . '<workbook xmlns="' . $nsPrincipal . '" xmlns:r="' . $nsRelacoes . '">'
        . '<bookViews><workbookView/></bookViews>'
        . '<sheets><sheet name="Respostas" sheetId="1" r:id="rId1"/></sheets>'
        . '<definedNames>'
        . '<definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">Respostas!$A$' . $linhaCabecalho . ':$' . $ultimaColuna . '$' . $ultimaLinha . '</definedName>'
        . '<definedName name="_xlnm.Print_Titles" localSheetId="0">Respostas!$' . $linhaCabecalho . ':$' . $linhaCabecalho . '</definedName>'
        . '</definedNames>'
        . '</workbook>';

    $tiposConteudo = $cabecalhoXml
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
        . '</Types>';

    $relacoesPacote = $cabecalhoXml
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="' . $nsRelacoes . '/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $relacoesPasta = $cabecalhoXml
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="' . $nsRelacoes . '/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="' . $nsRelacoes . '/styles" Target="styles.xml"/>'
        . '<Relationship Id="rId3" Type="' . $nsRelacoes . '/sharedStrings" Target="sharedStrings.xml"/>'
        . '</Relationships>';

    return excelMontarZip([
        '[Content_Types].xml' => $tiposConteudo,
        '_rels/.rels' => $relacoesPacote,
        'xl/workbook.xml' => $pasta,
        'xl/_rels/workbook.xml.rels' => $relacoesPasta,
        'xl/styles.xml' => $estilos,
        'xl/sharedStrings.xml' => $sharedStrings,
        'xl/worksheets/sheet1.xml' => $planilha,
    ]);
}

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
