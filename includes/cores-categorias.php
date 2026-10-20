<?php

function finmapCorVisualCategoria(int $id, string $nome, ?string $corSalva = null): string
{
    $nomeNormalizado = strtolower(strtr($nome, [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a',
        'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ü' => 'u', 'ç' => 'c',
    ]));

    $coresPorCategoria = [
        'aliment' => 'orange',
        'mercado' => 'orange',
        'transp' => 'blue',
        'moradia' => 'purple',
        'casa' => 'purple',
        'saude' => 'red',
        'farmac' => 'red',
        'lazer' => 'purple',
        'entreten' => 'purple',
        'educa' => 'blue',
        'compr' => 'orange',
        'assin' => 'blue',
        'conta' => 'red',
        'servic' => 'red',
        'pet' => 'orange',
        'salario' => 'green',
        'receita' => 'green',
    ];

    $coresValidas = ['green', 'blue', 'orange', 'purple', 'red'];
    if ($corSalva !== null && $corSalva !== 'green' && in_array($corSalva, $coresValidas, true)) {
        return $corSalva;
    }

    foreach ($coresPorCategoria as $trecho => $cor) {
        if (str_contains($nomeNormalizado, $trecho)) {
            return $cor;
        }
    }

    // Mantém cores estáveis para categorias personalizadas sem cor própria.
    $paleta = ['blue', 'purple', 'orange', 'red'];
    return $paleta[abs($id) % count($paleta)];
}
