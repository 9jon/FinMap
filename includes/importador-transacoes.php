<?php

/**
 * Utilitários para importar extratos. O arquivo original é lido no diretório
 * temporário do PHP e nunca fica armazenado no projeto.
 */

const IMPORTACAO_LIMITE_LINHAS = 5000;

function importacaoParaUtf8($valor): string
{
    $texto = is_string($valor) ? $valor : (string) $valor;
    $texto = preg_replace('/^\xEF\xBB\xBF/', '', $texto);

    if ($texto !== '' && !mb_check_encoding($texto, 'UTF-8')) {
        $convertido = @mb_convert_encoding($texto, 'UTF-8', 'Windows-1252');
        if ($convertido !== false) {
            $texto = $convertido;
        }
    }

    return trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $texto));
}

function importacaoChave($texto): string
{
    $texto = mb_strtolower(importacaoParaUtf8($texto), 'UTF-8');
    // iconv no Windows pode gerar "?" para alguns acentos portugueses.
    // A conversão explícita mantém as chaves de categorias estáveis.
    $texto = strtr($texto, [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
        'é' => 'e', 'ê' => 'e', 'è' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n',
    ]);
    $semAcentos = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
    if ($semAcentos !== false) {
        $texto = $semAcentos;
    }

    return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $texto));
}

function importacaoTextoCurto($texto, int $limite): string
{
    $texto = trim(importacaoParaUtf8($texto));
    return mb_substr($texto, 0, $limite, 'UTF-8');
}

function importacaoValor($valor): ?float
{
    if (is_int($valor) || is_float($valor)) {
        return is_finite((float) $valor) ? (float) $valor : null;
    }

    $texto = trim(importacaoParaUtf8($valor));
    if ($texto === '') {
        return null;
    }

    $negativo = str_contains($texto, '-')
        || (str_starts_with($texto, '(') && str_ends_with($texto, ')'));
    $texto = preg_replace('/[^0-9,\.]/', '', $texto);
    if ($texto === '') {
        return null;
    }

    $ultimaVirgula = strrpos($texto, ',');
    $ultimoPonto = strrpos($texto, '.');

    if ($ultimaVirgula !== false && $ultimoPonto !== false) {
        if ($ultimaVirgula > $ultimoPonto) {
            $texto = str_replace('.', '', $texto);
            $texto = str_replace(',', '.', $texto);
        } else {
            $texto = str_replace(',', '', $texto);
        }
    } elseif ($ultimaVirgula !== false) {
        $texto = str_replace('.', '', $texto);
        $texto = str_replace(',', '.', $texto);
    } elseif (substr_count($texto, '.') > 1) {
        $partes = explode('.', $texto);
        $decimal = array_pop($partes);
        $texto = implode('', $partes) . '.' . $decimal;
    }

    if (!is_numeric($texto)) {
        return null;
    }

    $numero = (float) $texto;
    return $negativo ? -$numero : $numero;
}

function importacaoData($valor): ?string
{
    if (is_int($valor) || is_float($valor)) {
        $numero = (float) $valor;
        if ($numero > 20000 && $numero < 90000) {
            return gmdate('Y-m-d', (int) round(($numero - 25569) * 86400));
        }
    }

    $texto = trim(importacaoParaUtf8($valor));
    if ($texto === '') {
        return null;
    }

    if (preg_match('/^(\d{4})(\d{2})(\d{2})/', $texto, $partes)) {
        $data = DateTime::createFromFormat('!Y-m-d', $partes[1] . '-' . $partes[2] . '-' . $partes[3]);
        return $data ? $data->format('Y-m-d') : null;
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $texto, $partes)) {
        $data = DateTime::createFromFormat('!Y-m-d', $partes[1] . '-' . $partes[2] . '-' . $partes[3]);
        return $data ? $data->format('Y-m-d') : null;
    }

    $texto = preg_replace('/\s+.*/', '', $texto);
    foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd.m.Y', 'd/m/y'] as $formato) {
        $data = DateTime::createFromFormat('!' . $formato, $texto);
        $erros = DateTime::getLastErrors();
        if ($data && ($erros === false || ($erros['warning_count'] === 0 && $erros['error_count'] === 0))) {
            return $data->format('Y-m-d');
        }
    }

    return null;
}

function importacaoTipoPorTexto(string $valor): ?string
{
    $texto = importacaoChave($valor);
    if ($texto === '') {
        return null;
    }

    if (preg_match('/\b(debito|despesa|pagamento|compra|sa[íi]da|withdrawal|payment|pos|fee)\b/u', $texto)) {
        return 'despesa';
    }
    if (preg_match('/\b(credito|receita|deposito|dep|entrada|salario|renda|income|credit)\b/u', $texto)) {
        return 'receita';
    }

    return null;
}

function importacaoMapearCabecalhos(array $cabecalho): array
{
    $mapa = [];

    foreach ($cabecalho as $indice => $titulo) {
        $chave = importacaoChave($titulo);
        if ($chave === '') {
            continue;
        }

        if (preg_match('/\b(debito|despesa|saida|pagamento|withdrawal)\b/', $chave)) {
            $mapa['debito'] ??= $indice;
        } elseif (preg_match('/\b(credito|receita|entrada|deposito|income)\b/', $chave)) {
            $mapa['credito'] ??= $indice;
        } elseif (preg_match('/\b(categoria|category)\b/', $chave)) {
            $mapa['categoria'] ??= $indice;
        } elseif (preg_match('/\b(tipo|natureza|nature)\b/', $chave)) {
            $mapa['tipo'] ??= $indice;
        } elseif (preg_match('/\b(data|date)\b/', $chave)) {
            $mapa['data'] ??= $indice;
        } elseif (preg_match('/\b(descricao|historico|estabelecimento|merchant|memo|detalhe|narrative|lancamento)\b/', $chave)) {
            $mapa['descricao'] ??= $indice;
        } elseif (preg_match('/\b(valor|amount|value|montante)\b/', $chave)) {
            $mapa['valor'] ??= $indice;
        }
    }

    return $mapa;
}

function importacaoLinhaTabular(array $linha, array $mapa, int $numeroLinha, string $origem): array
{
    $campo = static function (string $nome) use ($linha, $mapa): string {
        if (!isset($mapa[$nome]) || !array_key_exists($mapa[$nome], $linha)) {
            return '';
        }
        return importacaoParaUtf8($linha[$mapa[$nome]]);
    };

    return [
        'linha' => $numeroLinha,
        'origem' => $origem,
        'descricao' => $campo('descricao'),
        'data' => $campo('data'),
        'valor' => $campo('valor'),
        'debito' => $campo('debito'),
        'credito' => $campo('credito'),
        'tipo' => $campo('tipo'),
        'categoria' => $campo('categoria'),
        'identificador' => '',
    ];
}

function importacaoLinhasTabulares(array $linhas, string $origem): array
{
    if (empty($linhas)) {
        throw new RuntimeException('A planilha não possui linhas para importar.');
    }

    $cabecalho = array_shift($linhas);
    $mapa = importacaoMapearCabecalhos($cabecalho);
    $temCabecalho = isset($mapa['descricao']) && (isset($mapa['valor']) || isset($mapa['debito']) || isset($mapa['credito']));
    $resultado = [];
    $numeroLinha = 1;

    if (!$temCabecalho) {
        // Formato simples, sem cabeçalho: data; descrição; valor; tipo; categoria.
        $mapa = ['data' => 0, 'descricao' => 1, 'valor' => 2, 'tipo' => 3, 'categoria' => 4];
        $resultado[] = importacaoLinhaTabular($cabecalho, $mapa, $numeroLinha, $origem);
    }

    foreach ($linhas as $linha) {
        $numeroLinha++;
        if (count($resultado) >= IMPORTACAO_LIMITE_LINHAS) {
            throw new RuntimeException('O arquivo tem mais de ' . IMPORTACAO_LIMITE_LINHAS . ' lançamentos. Divida-o antes de importar.');
        }

        $temConteudo = false;
        foreach ($linha as $celula) {
            if (importacaoParaUtf8($celula) !== '') {
                $temConteudo = true;
                break;
            }
        }
        if ($temConteudo) {
            $resultado[] = importacaoLinhaTabular($linha, $mapa, $numeroLinha, $origem);
        }
    }

    return $resultado;
}

function importacaoDescobrirSeparador(string $primeiraLinha): string
{
    $candidatos = [';' => 0, ',' => 0, "\t" => 0];
    foreach ($candidatos as $separador => $total) {
        $candidatos[$separador] = count(str_getcsv($primeiraLinha, $separador));
    }
    arsort($candidatos);
    return (string) array_key_first($candidatos);
}

function importacaoLerCsv(string $arquivo): array
{
    $handle = fopen($arquivo, 'rb');
    if (!$handle) {
        throw new RuntimeException('Não foi possível abrir o CSV enviado.');
    }

    $primeiraLinha = fgets($handle);
    if ($primeiraLinha === false) {
        fclose($handle);
        throw new RuntimeException('O CSV está vazio.');
    }

    $separador = importacaoDescobrirSeparador($primeiraLinha);
    rewind($handle);
    $linhas = [];
    while (($linha = fgetcsv($handle, 0, $separador)) !== false) {
        $linhas[] = $linha;
        if (count($linhas) > IMPORTACAO_LIMITE_LINHAS + 1) {
            fclose($handle);
            throw new RuntimeException('O arquivo tem mais de ' . IMPORTACAO_LIMITE_LINHAS . ' lançamentos. Divida-o antes de importar.');
        }
    }
    fclose($handle);

    return importacaoLinhasTabulares($linhas, 'CSV');
}

function importacaoTagOfx(string $bloco, string $tag): string
{
    if (preg_match('/<' . preg_quote($tag, '/') . '\b[^>]*>\s*([^<\r\n]+)/i', $bloco, $resultado)) {
        return importacaoParaUtf8(html_entity_decode(trim($resultado[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    return '';
}

function importacaoLerOfx(string $arquivo): array
{
    $conteudo = file_get_contents($arquivo);
    if ($conteudo === false) {
        throw new RuntimeException('Não foi possível abrir o OFX enviado.');
    }
    $conteudo = importacaoParaUtf8($conteudo);

    preg_match_all('/<STMTTRN\b[^>]*>(.*?)(?=<STMTTRN\b|<\/BANKTRANLIST\b|$)/is', $conteudo, $blocos);
    if (empty($blocos[1])) {
        throw new RuntimeException('Nenhuma transação foi encontrada no OFX.');
    }
    if (count($blocos[1]) > IMPORTACAO_LIMITE_LINHAS) {
        throw new RuntimeException('O arquivo tem mais de ' . IMPORTACAO_LIMITE_LINHAS . ' lançamentos. Divida-o antes de importar.');
    }

    $linhas = [];
    foreach ($blocos[1] as $indice => $bloco) {
        $linhas[] = [
            'linha' => $indice + 1,
            'origem' => 'OFX',
            'descricao' => importacaoTagOfx($bloco, 'NAME') ?: importacaoTagOfx($bloco, 'MEMO'),
            'data' => importacaoTagOfx($bloco, 'DTPOSTED'),
            'valor' => importacaoTagOfx($bloco, 'TRNAMT'),
            'debito' => '',
            'credito' => '',
            'tipo' => importacaoTagOfx($bloco, 'TRNTYPE'),
            'categoria' => '',
            'identificador' => importacaoTagOfx($bloco, 'FITID'),
        ];
    }

    return $linhas;
}

function importacaoZip16(string $dados, int $offset): int
{
    $valor = unpack('vvalor', substr($dados, $offset, 2));
    return (int) ($valor['valor'] ?? 0);
}

function importacaoZip32(string $dados, int $offset): int
{
    $valor = unpack('Vvalor', substr($dados, $offset, 4));
    return (int) ($valor['valor'] ?? 0);
}

/** Leitor mínimo de ZIP para arquivos XLSX em instalações PHP sem ext-zip. */
function importacaoLerZipXlsx(string $arquivo): array
{
    $dados = file_get_contents($arquivo);
    if ($dados === false || substr($dados, 0, 2) !== "PK") {
        throw new RuntimeException('O arquivo Excel não é um XLSX válido.');
    }

    $inicioBusca = max(0, strlen($dados) - 66000);
    $posicaoFinal = strrpos(substr($dados, $inicioBusca), "PK\x05\x06");
    if ($posicaoFinal === false) {
        throw new RuntimeException('Não foi possível ler a estrutura do XLSX.');
    }
    $posicaoFinal += $inicioBusca;
    $posicao = importacaoZip32($dados, $posicaoFinal + 16);
    $arquivos = [];

    while (substr($dados, $posicao, 4) === "PK\x01\x02") {
        $metodo = importacaoZip16($dados, $posicao + 10);
        $tamanhoCompactado = importacaoZip32($dados, $posicao + 20);
        $tamanhoNome = importacaoZip16($dados, $posicao + 28);
        $tamanhoExtra = importacaoZip16($dados, $posicao + 30);
        $tamanhoComentario = importacaoZip16($dados, $posicao + 32);
        $posicaoLocal = importacaoZip32($dados, $posicao + 42);
        $nome = substr($dados, $posicao + 46, $tamanhoNome);

        if (substr($dados, $posicaoLocal, 4) !== "PK\x03\x04") {
            throw new RuntimeException('O XLSX contém uma entrada inválida.');
        }
        $nomeLocal = importacaoZip16($dados, $posicaoLocal + 26);
        $extraLocal = importacaoZip16($dados, $posicaoLocal + 28);
        $inicioDados = $posicaoLocal + 30 + $nomeLocal + $extraLocal;
        $compactado = substr($dados, $inicioDados, $tamanhoCompactado);

        if ($metodo === 0) {
            $conteudo = $compactado;
        } elseif ($metodo === 8) {
            $conteudo = @gzinflate($compactado);
        } else {
            throw new RuntimeException('O XLSX usa uma compactação não suportada.');
        }

        if ($conteudo === false) {
            throw new RuntimeException('Não foi possível descompactar o XLSX.');
        }
        $arquivos[$nome] = $conteudo;
        $posicao += 46 + $tamanhoNome + $tamanhoExtra + $tamanhoComentario;
    }

    return $arquivos;
}

function importacaoTextoNo($no): string
{
    $partes = $no->xpath('.//*[local-name()="t"]');
    if (!$partes) {
        return trim((string) $no);
    }
    return implode('', array_map(static fn($parte) => (string) $parte, $partes));
}

function importacaoColunaXlsx(string $referencia): int
{
    if (!preg_match('/([A-Z]+)/i', $referencia, $resultado)) {
        return 0;
    }
    $numero = 0;
    foreach (str_split(strtoupper($resultado[1])) as $letra) {
        $numero = ($numero * 26) + (ord($letra) - 64);
    }
    return max(0, $numero - 1);
}

function importacaoLerXlsx(string $arquivo): array
{
    $arquivos = importacaoLerZipXlsx($arquivo);
    $planilhas = array_values(array_filter(array_keys($arquivos), static fn($nome) => preg_match('#^xl/worksheets/sheet\d+\.xml$#', $nome)));
    natsort($planilhas);
    $planilha = reset($planilhas);
    if (!$planilha) {
        throw new RuntimeException('Nenhuma planilha foi encontrada no XLSX.');
    }

    $strings = [];
    if (isset($arquivos['xl/sharedStrings.xml'])) {
        $xmlStrings = @simplexml_load_string($arquivos['xl/sharedStrings.xml'], 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
        if ($xmlStrings === false) {
            throw new RuntimeException('Não foi possível ler os textos da planilha XLSX.');
        }
        $namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        foreach ($xmlStrings->children($namespace)->si as $item) {
            $strings[] = importacaoTextoNo($item);
        }
    }

    $xml = @simplexml_load_string($arquivos[$planilha], 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
    if ($xml === false) {
        throw new RuntimeException('Não foi possível ler a primeira planilha do XLSX.');
    }

    $namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $dadosPlanilha = $xml->children($namespace)->sheetData;
    $linhas = [];
    foreach ($dadosPlanilha->children($namespace)->row as $linhaXml) {
        $linha = [];
        foreach ($linhaXml->children($namespace)->c as $celula) {
            $atributos = $celula->attributes();
            $coluna = importacaoColunaXlsx((string) ($atributos['r'] ?? 'A1'));
            $tipo = (string) ($atributos['t'] ?? '');
            $filhos = $celula->children($namespace);
            $valor = '';

            if ($tipo === 's') {
                $indice = (int) $filhos->v;
                $valor = $strings[$indice] ?? '';
            } elseif ($tipo === 'inlineStr') {
                $valor = importacaoTextoNo($filhos->is);
            } else {
                $valor = (string) $filhos->v;
            }
            $linha[$coluna] = $valor;
        }

        if ($linha) {
            ksort($linha);
            $linhas[] = $linha;
        }
        if (count($linhas) > IMPORTACAO_LIMITE_LINHAS + 1) {
            throw new RuntimeException('O arquivo tem mais de ' . IMPORTACAO_LIMITE_LINHAS . ' lançamentos. Divida-o antes de importar.');
        }
    }

    return importacaoLinhasTabulares($linhas, 'Excel');
}

function importacaoLerArquivo(string $arquivo, string $extensao): array
{
    return match ($extensao) {
        'csv' => importacaoLerCsv($arquivo),
        'ofx', 'qfx' => importacaoLerOfx($arquivo),
        'xlsx' => importacaoLerXlsx($arquivo),
        'xls' => throw new RuntimeException('Arquivos .xls não são compatíveis. Abra-o no Excel e salve como .xlsx para importar.'),
        default => throw new RuntimeException('Formato de arquivo não suportado.'),
    };
}

function importacaoCategoriasPorTipo(array $categorias): array
{
    $resultado = ['despesa' => [], 'receita' => []];
    foreach ($categorias as $categoria) {
        if (isset($resultado[$categoria['tipo']])) {
            $resultado[$categoria['tipo']][] = $categoria;
        }
    }
    return $resultado;
}

function importacaoMapaAprendizado(mysqli $conn, int $usuarioId): array
{
    $stmt = $conn->prepare(
        "SELECT descricao, categoria_id, COUNT(*) AS ocorrencias\n"
        . "FROM transacoes\n"
        . "WHERE usuario_id = ? AND status = 'aprovado' AND categoria_id IS NOT NULL\n"
        . "GROUP BY descricao, categoria_id\n"
        . "ORDER BY ocorrencias DESC, MAX(atualizado_em) DESC\n"
        . "LIMIT 500"
    );
    $stmt->bind_param('i', $usuarioId);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $mapa = [];
    while ($linha = $resultado->fetch_assoc()) {
        $chave = importacaoChave($linha['descricao']);
        if ($chave !== '' && !isset($mapa[$chave])) {
            $mapa[$chave] = (int) $linha['categoria_id'];
        }
    }
    $stmt->close();
    return $mapa;
}

function importacaoCategoriaComNome(array $categorias, string $nome): ?array
{
    $chave = importacaoChave($nome);
    if ($chave === '') {
        return null;
    }
    foreach ($categorias as $categoria) {
        if (importacaoChave($categoria['nome']) === $chave) {
            return $categoria;
        }
    }
    return null;
}

function importacaoCategoriaPorMarcadores(array $categorias, array $marcadores): ?array
{
    foreach ($categorias as $categoria) {
        $nome = importacaoChave($categoria['nome']);
        foreach ($marcadores as $marcador) {
            if (str_contains($nome, $marcador)) {
                return $categoria;
            }
        }
    }
    return null;
}

function importacaoSugerirCategoria(array $linha, array $categoriasTipo, array $aprendizado): array
{
    $categorias = $categoriasTipo[$linha['tipo']] ?? [];
    if (!$categorias) {
        return ['id' => null, 'pontuacao' => 0, 'motivo' => 'nenhuma categoria desse tipo cadastrada'];
    }

    $categoriaArquivo = importacaoCategoriaComNome($categorias, $linha['categoria']);
    if ($categoriaArquivo) {
        return ['id' => (int) $categoriaArquivo['id'], 'pontuacao' => 25, 'motivo' => 'categoria informada no arquivo'];
    }

    $descricao = importacaoChave($linha['descricao']);
    if (isset($aprendizado[$descricao])) {
        foreach ($categorias as $categoria) {
            if ((int) $categoria['id'] === $aprendizado[$descricao]) {
                return ['id' => (int) $categoria['id'], 'pontuacao' => 25, 'motivo' => 'lançamento semelhante já aprovado'];
            }
        }
    }

    $regras = [
        [['ifood', 'rappi', 'ubereats', 'restaurante', 'lanchonete', 'padaria', 'mercado', 'supermercado', 'pizza', 'cafe'], ['alimentacao', 'mercado', 'refeicao']],
        [['uber', '99', 'taxi', 'posto', 'combustivel', 'estacionamento', 'metro', 'onibus'], ['transporte', 'combustivel', 'veiculo']],
        [['netflix', 'spotify', 'cinema', 'steam', 'playstation', 'xbox', 'bar ', 'show'], ['lazer', 'extra', 'assinatura']],
        [['aluguel', 'condominio', 'imovel', 'imobiliaria'], ['moradia', 'casa']],
        [['energia', 'enel', 'cemig', 'sabesp', 'internet', 'telefone', 'vivo', 'claro', 'tim'], ['contas', 'utilidades', 'internet', 'telefone']],
        [['farmacia', 'drogaria', 'hospital', 'clinica', 'medico'], ['saude', 'farmacia']],
        [['salario', 'pagamento', 'pro labore', 'freelance'], ['receita', 'salario', 'renda']],
    ];

    foreach ($regras as [$termos, $marcadores]) {
        foreach ($termos as $termo) {
            if (str_contains($descricao, $termo)) {
                $categoria = importacaoCategoriaPorMarcadores($categorias, $marcadores);
                if ($categoria) {
                    return ['id' => (int) $categoria['id'], 'pontuacao' => 20, 'motivo' => 'palavras-chave da descrição'];
                }
            }
        }
    }

    foreach ($categorias as $categoria) {
        $nome = importacaoChave($categoria['nome']);
        if (mb_strlen($nome, 'UTF-8') >= 4 && str_contains($descricao, $nome)) {
            return ['id' => (int) $categoria['id'], 'pontuacao' => 15, 'motivo' => 'nome da categoria na descrição'];
        }
    }

    return ['id' => null, 'pontuacao' => 0, 'motivo' => 'categoria não identificada'];
}

function importacaoNormalizarLinha(array $bruta, array $categoriasTipo, array $aprendizado): array
{
    $descricao = importacaoTextoCurto($bruta['descricao'] ?? '', 150);
    if ($descricao === '') {
        throw new RuntimeException('Descrição ausente.');
    }

    $debito = importacaoValor($bruta['debito'] ?? '');
    $credito = importacaoValor($bruta['credito'] ?? '');
    $valorLido = importacaoValor($bruta['valor'] ?? '');
    $tipoTexto = importacaoTipoPorTexto((string) ($bruta['tipo'] ?? ''));
    $tipoConhecido = false;

    if ($debito !== null && abs($debito) > 0) {
        $valor = abs($debito);
        $tipo = 'despesa';
        $tipoConhecido = true;
    } elseif ($credito !== null && abs($credito) > 0) {
        $valor = abs($credito);
        $tipo = 'receita';
        $tipoConhecido = true;
    } elseif ($valorLido !== null) {
        $valor = abs($valorLido);
        if ($tipoTexto !== null) {
            $tipo = $tipoTexto;
            $tipoConhecido = true;
        } elseif ($valorLido < 0) {
            $tipo = 'despesa';
            $tipoConhecido = true;
        } else {
            $tipo = 'despesa';
        }
    } else {
        throw new RuntimeException('Valor inválido ou ausente.');
    }

    if ($valor <= 0) {
        throw new RuntimeException('O valor deve ser maior que zero.');
    }

    $dataArquivo = importacaoData($bruta['data'] ?? '');
    $data = $dataArquivo ?? date('Y-m-d');
    $linha = [
        'descricao' => $descricao,
        'valor' => round($valor, 2),
        'tipo' => $tipo,
        'categoria' => $bruta['categoria'] ?? '',
    ];
    $categoria = importacaoSugerirCategoria($linha, $categoriasTipo, $aprendizado);
    $temIdentificador = trim((string) ($bruta['identificador'] ?? '')) !== '';

    $confianca = 45
        + ($dataArquivo ? 10 : 0)
        + ($tipoConhecido ? 10 : 0)
        + ($temIdentificador ? 10 : 0)
        + $categoria['pontuacao'];
    $confianca = min(100, $confianca);
    if ($categoria['id'] === null) {
        $confianca = min($confianca, 75);
    }
    if (!$dataArquivo) {
        $confianca = min($confianca, 65);
    }

    $observacoes = [
        (string) ($bruta['origem'] ?? 'Arquivo'),
        'Categoria: ' . $categoria['motivo'],
    ];
    if (!$dataArquivo) {
        $observacoes[] = 'data ausente; confirme na revisão';
    }

    $identificador = trim((string) ($bruta['identificador'] ?? ''));
    $chaveDeduplicacao = $identificador !== ''
        ? 'id:' . $identificador
        : implode('|', [
            importacaoChave($descricao),
            $data,
            number_format($valor, 2, '.', ''),
            $tipo,
        ]);

    return [
        'descricao' => $descricao,
        'valor' => $valor,
        'tipo' => $tipo,
        'categoria_id' => $categoria['id'],
        'data' => $data,
        'confianca' => (int) $confianca,
        'observacao' => importacaoTextoCurto(implode(' • ', $observacoes), 255),
        'hash' => hash('sha256', $chaveDeduplicacao),
    ];
}
