<?php

/**
 * Regras locais que simulam a primeira camada de uma futura análise por IA.
 * A descrição do lançamento, a categoria escolhida pelo usuário e o valor são
 * usados juntos para classificar oportunidades de economia.
 */
function normalizarTextoAnalisePoupanca(string $texto): string
{
    $texto = mb_strtolower($texto, 'UTF-8');
    $semAcentos = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);

    if ($semAcentos !== false) {
        $texto = $semAcentos;
    }

    return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $texto));
}

function textoTemTermoAnalisePoupanca(string $texto, array $termos): bool
{
    foreach ($termos as $termo) {
        if (str_contains($texto, $termo)) {
            return true;
        }
    }

    return false;
}

/**
 * Retorna a chave da oportunidade identificada ou null quando não há indício
 * suficiente. A ordem prioriza assinaturas e transporte, que têm sinais mais
 * específicos, antes de avaliar alimentação e compras por impulso.
 */
function classificarLancamentoPoupanca(array $lancamento): ?string
{
    $descricao = normalizarTextoAnalisePoupanca((string) ($lancamento['descricao'] ?? ''));
    $categoria = normalizarTextoAnalisePoupanca((string) ($lancamento['categoria_nome'] ?? ''));
    $texto = trim($descricao . ' ' . $categoria);
    $valor = (float) ($lancamento['valor'] ?? 0);

    $termosAssinatura = [
        'assinatura', 'mensalidade', 'netflix', 'spotify', 'disney', 'disney plus',
        'hbo', 'max', 'youtube premium', 'prime video', 'globoplay', 'deezer',
        'icloud', 'google one', 'canva', 'adobe', 'office 365', 'xbox game pass',
        'playstation plus', 'ps plus'
    ];
    if (textoTemTermoAnalisePoupanca($texto, $termosAssinatura)) {
        return 'subscription';
    }

    $termosTransporte = [
        'uber', '99', '99app', 'cabify', 'indrive', 'taxi', 'corrida app',
        'transporte por aplicativo'
    ];
    if (textoTemTermoAnalisePoupanca($texto, $termosTransporte)) {
        return 'transport';
    }

    $termosCafeELanche = [
        'cafe', 'cafeteria', 'lanche', 'lanch', 'fast food', 'ifood', 'rappi',
        'ubereats', 'padaria', 'salgado', 'salgadin', 'acai', 'sorvete', 'pizza',
        'hamburg', 'burger', 'mcdonald', 'bk ', 'burger king', 'subway', 'habibs',
        'comida', 'refeicao', 'almoco', 'jantar', 'restaurante', 'delivery'
    ];
    if (textoTemTermoAnalisePoupanca($texto, $termosCafeELanche)) {
        return 'coffee';
    }

    $termosCompraImpulso = [
        'boneco', 'brinqued', 'colecion', 'funko', 'impulso', 'shopping', 'shopee', 'shein',
        'aliexpress', 'temu', 'amazon', 'mercado livre', 'mercadolivre', 'magalu',
        'magazine luiza', 'americanas', 'renner', 'zara', 'nike', 'adidas', 'tenis',
        'roupa', 'vestuario', 'acessorio', 'decoracao', 'presente', 'jogo', 'game ',
        'steam', 'playstation', 'xbox', 'eletron', 'celular', 'fone', 'notebook'
    ];
    $categoriaFlexivel = [
        'lazer', 'extra', 'compras', 'compra', 'hobby', 'moda', 'vestuario',
        'eletron', 'presentes', 'decoracao'
    ];

    if (
        ($valor >= 30 && textoTemTermoAnalisePoupanca($texto, ['impulso']))
        || ($valor >= 60 && textoTemTermoAnalisePoupanca($texto, $termosCompraImpulso))
        || ($valor >= 120 && textoTemTermoAnalisePoupanca($categoria, $categoriaFlexivel))
    ) {
        return 'impulse';
    }

    return null;
}

function estimarPotencialPoupanca(string $tipo, float $totalGasto, int $quantidade): float
{
    switch ($tipo) {
        case 'coffee':
            // Mantém uma margem de R$ 12 por consumo e considera o excedente.
            return max(0, $totalGasto - ($quantidade * 12));

        case 'impulse':
            return $totalGasto * 0.50;

        case 'subscription':
            return $totalGasto * 0.50;

        case 'transport':
            return $totalGasto * 0.30;
    }

    return 0.0;
}
