<?php

/**
 * Cria as categorias basicas para uma conta que ainda nao as possui.
 * A verificacao por nome e tipo torna a operacao segura para chamadas repetidas.
 */
function garantirCategoriasPadrao(mysqli $conn, int $usuarioId): bool
{
    if ($usuarioId <= 0) {
        return false;
    }

    $categoriasPadrao = [
        ['Receita', 'receita', 'arrow-up-right', 'green'],
        ['Moradia', 'despesa', 'house-door', 'purple'],
        ['Alimentação', 'despesa', 'basket2', 'green'],
        ['Transporte', 'despesa', 'bus-front', 'blue'],
        ['Contas e utilidades', 'despesa', 'lightning-charge', 'orange'],
        ['Lazer e extras', 'despesa', 'bag-heart', 'red'],
    ];

    $buscar = $conn->prepare(
        'SELECT id FROM categorias WHERE usuario_id = ? AND nome = ? AND tipo = ? LIMIT 1'
    );
    $inserir = $conn->prepare(
        'INSERT INTO categorias (usuario_id, nome, tipo, icone, cor) VALUES (?, ?, ?, ?, ?)'
    );

    if (!$buscar || !$inserir) {
        if ($buscar) {
            $buscar->close();
        }
        if ($inserir) {
            $inserir->close();
        }
        error_log('Nao foi possivel preparar a criacao das categorias padrao: ' . $conn->error);
        return false;
    }

    foreach ($categoriasPadrao as [$nome, $tipo, $icone, $cor]) {
        $buscar->bind_param('iss', $usuarioId, $nome, $tipo);
        $buscar->execute();
        $jaExiste = $buscar->get_result()->fetch_assoc();

        if ($jaExiste) {
            continue;
        }

        $inserir->bind_param('issss', $usuarioId, $nome, $tipo, $icone, $cor);
        if (!$inserir->execute()) {
            $buscar->close();
            $inserir->close();
            error_log('Nao foi possivel criar categoria padrao: ' . $conn->error);
            return false;
        }
    }

    $buscar->close();
    $inserir->close();

    return true;
}
