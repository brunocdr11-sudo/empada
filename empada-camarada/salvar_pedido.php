<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');

function resposta(string $titulo, string $mensagem, bool $sucesso = false): void {
    $cor = $sucesso ? '#236b35' : '#a40000';
    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</title>';
    echo '<body style="font-family:Arial,sans-serif;background:#e6cfab;color:#30190f;padding:30px">';
    echo '<main style="max-width:650px;margin:40px auto;background:#f7f0e5;padding:28px;border-radius:10px">';
    echo '<h1 style="color:' . $cor . '">' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</h1>';
    echo '<p>' . htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p><a href="pagina_principal.html">Voltar à página principal</a></p></main></body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    resposta('Método inválido', 'Envie o pedido pelo formulário do site.');
}

$nome = trim((string)($_POST['nome'] ?? ''));
$endereco = trim((string)($_POST['endereco'] ?? ''));
$quantidades = $_POST['quantidade'] ?? null;

if ($nome === '' || mb_strlen($nome) > 120 || !is_string($_POST['nome'] ?? null)) {
    resposta('Dados inválidos', 'Informe um nome válido com até 120 caracteres.');
}
if ($endereco === '' || mb_strlen($endereco) > 500 || !is_string($_POST['endereco'] ?? null)) {
    resposta('Dados inválidos', 'Informe um endereço válido com até 500 caracteres.');
}
if (!is_array($quantidades)) {
    resposta('Pedido inválido', 'As quantidades enviadas não são válidas.');
}

require __DIR__ . '/conexao.php';

$saboresPermitidos = [
    'queijo' => 'Queijo',
    'frango' => 'Frango',
    'carne_seca' => 'Carne Seca',
    'camarao' => 'Camarão',
    'romeu_julieta' => 'Romeu e Julieta',
    'chocolate' => 'Chocolate',
];
$precos = [
    'queijo' => 6.50,
    'frango' => 7.50,
    'carne_seca' => 8.50,
    'camarao' => 10.50,
    'romeu_julieta' => 7.00,
    'chocolate' => 7.00,
];

try {
    $pdo->beginTransaction();

    $itens = [];
    $total = 0.0;

    /* Bloqueia as linhas durante a compra para impedir venda acima do estoque. */
    $consultaEstoque = $pdo->prepare('SELECT quantidade FROM estoque WHERE sabor = :sabor FOR UPDATE');
    foreach ($saboresPermitidos as $chave => $nomeSabor) {
        $valor = $quantidades[$chave] ?? '0';
        if (is_array($valor) || !preg_match('/^\d+$/', (string)$valor)) {
            throw new InvalidArgumentException('Informe uma quantidade inteira válida para ' . $nomeSabor . '.');
        }
        $qtd = (int)$valor;
        if ($qtd < 0 || $qtd > 50) {
            throw new InvalidArgumentException('A quantidade de ' . $nomeSabor . ' deve ficar entre 0 e 50.');
        }
        if ($qtd === 0) continue;

        $consultaEstoque->execute(['sabor' => $chave]);
        $linha = $consultaEstoque->fetch();
        if (!$linha) {
            throw new RuntimeException('O estoque do sabor ' . $nomeSabor . ' não está cadastrado.');
        }
        if ($qtd > (int)$linha['quantidade']) {
            throw new RuntimeException('Estoque insuficiente para ' . $nomeSabor . '. Disponível: ' . (int)$linha['quantidade'] . '.');
        }
        $itens[] = ['sabor' => $chave, 'nome' => $nomeSabor, 'quantidade' => $qtd, 'preco' => $precos[$chave]];
        $total += $qtd * $precos[$chave];
    }

    if (!$itens) {
        throw new InvalidArgumentException('Selecione pelo menos uma empada para fazer o pedido.');
    }

    $inserirPedido = $pdo->prepare(
        'INSERT INTO pedidos (nome_cliente, endereco_entrega, total, criado_em) VALUES (:nome, :endereco, :total, NOW())'
    );
    $inserirPedido->execute(['nome' => $nome, 'endereco' => $endereco, 'total' => number_format($total, 2, '.', '')]);
    $pedidoId = (int)$pdo->lastInsertId();

    $inserirItem = $pdo->prepare(
        'INSERT INTO pedido_itens (pedido_id, sabor, quantidade, preco_unitario) VALUES (:pedido_id, :sabor, :quantidade, :preco)'
    );
    $baixarEstoque = $pdo->prepare(
        'UPDATE estoque SET quantidade = quantidade - :quantidade WHERE sabor = :sabor AND quantidade >= :quantidade_cond'
    );

    foreach ($itens as $item) {
        $inserirItem->execute([
            'pedido_id' => $pedidoId,
            'sabor' => $item['sabor'],
            'quantidade' => $item['quantidade'],
            'preco' => number_format($item['preco'], 2, '.', ''),
        ]);
        $baixarEstoque->execute([
            'quantidade' => $item['quantidade'],
            'sabor' => $item['sabor'],
            'quantidade_cond' => $item['quantidade'],
        ]);
        if ($baixarEstoque->rowCount() !== 1) {
            throw new RuntimeException('O estoque mudou durante o pedido. Atualize a página e tente novamente.');
        }
    }

    $pdo->commit();
    resposta('Pedido realizado com sucesso!', 'Número do pedido: ' . $pedidoId . '. Total: R$ ' . number_format($total, 2, ',', '.') . '. O estoque foi atualizado.', true);
} catch (InvalidArgumentException | RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    resposta('Não foi possível concluir o pedido', $e->getMessage());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Erro ao salvar pedido: ' . $e->getMessage());
    resposta('Erro ao salvar o pedido', 'Ocorreu um erro inesperado. Confira a configuração do banco e tente novamente.');
}
?>
