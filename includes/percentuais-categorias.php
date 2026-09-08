<?php

if (!function_exists('aplicarPercentuaisInteiros')) {
    /**
     * Distribui percentuais inteiros que somam 100 sem alterar a ordem original.
     * Os pontos restantes são entregues aos maiores restos decimais.
     */
    function aplicarPercentuaisInteiros(array &$itens, string $campoValor, string $campoPercentual): void
    {
        $total = 0.0;
        foreach ($itens as $item) {
            $total += (float) ($item[$campoValor] ?? 0);
        }

        if (!$itens || $total <= 0) {
            foreach ($itens as &$item) {
                $item[$campoPercentual] = 0;
            }
            unset($item);
            return;
        }

        $ordemDosRestos = [];
        $somaInteira = 0;
        foreach ($itens as $indice => &$item) {
            $percentual = ((float) ($item[$campoValor] ?? 0) / $total) * 100;
            $inteiro = (int) floor($percentual);
            $item[$campoPercentual] = $inteiro;
            $somaInteira += $inteiro;
            $ordemDosRestos[] = [
                'indice' => $indice,
                'resto' => $percentual - $inteiro,
            ];
        }
        unset($item);

        usort($ordemDosRestos, static function (array $a, array $b): int {
            $comparacao = $b['resto'] <=> $a['resto'];
            return $comparacao !== 0 ? $comparacao : $a['indice'] <=> $b['indice'];
        });

        $pontosRestantes = max(0, 100 - $somaInteira);
        for ($i = 0; $i < $pontosRestantes && $i < count($ordemDosRestos); $i++) {
            $indice = $ordemDosRestos[$i]['indice'];
            $itens[$indice][$campoPercentual]++;
        }
    }
}
