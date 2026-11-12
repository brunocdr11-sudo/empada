
<?php

require __DIR__ . '/conexao.php';

/*
 * FUNÇÃO 1:
 * Busca todos os pedidos e seus respectivos itens no banco.
 * Os pedidos mais antigos aparecem primeiro.
 */
function buscarPedidos(PDO $pdo): array
{
    $sql = "
        SELECT
            p.id AS pedido_id,
            p.nome_cliente,
            p.endereco_entrega,
            p.total,
            p.criado_em,
            pi.sabor,
            COALESCE(e.nome_exibicao, pi.sabor) AS nome_sabor,
            pi.quantidade,
            pi.preco_unitario
        FROM pedidos p
        INNER JOIN pedido_itens pi
            ON pi.pedido_id = p.id
        LEFT JOIN estoque e
            ON e.sabor = pi.sabor
        ORDER BY p.criado_em ASC, p.id ASC, pi.id ASC
    ";

    $consulta = $pdo->query($sql);
    $linhas = $consulta->fetchAll(PDO::FETCH_ASSOC);

    /*
     * Agrupa os itens pertencentes ao mesmo pedido.
     */
    $pedidos = [];

    foreach ($linhas as $linha) {
        $id = (int) $linha['pedido_id'];

        if (!isset($pedidos[$id])) {
            $pedidos[$id] = [
                'id' => $id,
                'nome_cliente' => $linha['nome_cliente'],
                'endereco_entrega' => $linha['endereco_entrega'],
                'total' => $linha['total'],
                'criado_em' => $linha['criado_em'],
                'itens' => []
            ];
        }

        $pedidos[$id]['itens'][] = [
            'sabor' => $linha['nome_sabor'],
            'quantidade' => (int) $linha['quantidade'],
            'preco_unitario' => $linha['preco_unitario']
        ];
    }

    return array_values($pedidos);
}

/*
 * FUNÇÃO 2:
 * Finaliza um pedido.
 *
 * Recebe o ID do pedido por POST, exclui primeiro
 * os itens e depois exclui o pedido.
 *
 * A transação garante que as duas exclusões sejam
 * confirmadas juntas ou desfeitas em caso de erro.
 */
if (($_GET['acao'] ?? '') === 'finalizar') {

    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);

        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Método não permitido.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $dados = json_decode(
        file_get_contents('php://input'),
        true
    );

    $pedidoId = filter_var(
        $dados['pedido_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$pedidoId || $pedidoId <= 0) {
        http_response_code(400);

        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'ID do pedido inválido.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    try {

        // Inicia uma transação no banco de dados.
        $pdo->beginTransaction();

        /*
         * Verifica se o pedido existe e bloqueia
         * seu registro durante a operação.
         */
        $consulta = $pdo->prepare("
            SELECT id
            FROM pedidos
            WHERE id = :id
            FOR UPDATE
        ");

        $consulta->execute([
            ':id' => $pedidoId
        ]);

        $pedido = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!$pedido) {
            $pdo->rollBack();

            http_response_code(404);

            echo json_encode([
                'sucesso' => false,
                'mensagem' => 'Pedido não encontrado.'
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }

        /*
         * Primeiro exclui os itens vinculados ao pedido.
         */
        $excluirItens = $pdo->prepare("
            DELETE FROM pedido_itens
            WHERE pedido_id = :pedido_id
        ");

        $excluirItens->execute([
            ':pedido_id' => $pedidoId
        ]);

        /*
         * Depois exclui o pedido principal.
         */
        $excluirPedido = $pdo->prepare("
            DELETE FROM pedidos
            WHERE id = :id
        ");

        $excluirPedido->execute([
            ':id' => $pedidoId
        ]);

        // Confirma as exclusões no banco.
        $pdo->commit();

        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Pedido finalizado e excluído com sucesso.'
        ], JSON_UNESCAPED_UNICODE);

    } catch (Throwable $e) {

        // Desfaz a operação caso aconteça algum erro.
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'Erro ao finalizar pedido: ' . $e->getMessage()
        );

        http_response_code(500);

        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Não foi possível finalizar o pedido.'
        ], JSON_UNESCAPED_UNICODE);
    }

    exit;
}

/*
 * FUNÇÃO 3:
 * Quando o JavaScript solicitar atualização,
 * retorna os pedidos em formato JSON.
 */
if (($_GET['acao'] ?? '') === 'atualizar') {

    header('Content-Type: application/json; charset=utf-8');

    try {

        $pedidos = buscarPedidos($pdo);

        echo json_encode([
            'sucesso' => true,
            'total_pedidos' => count($pedidos),
            'pedidos' => $pedidos
        ], JSON_UNESCAPED_UNICODE);

    } catch (Throwable $e) {

        error_log(
            'Erro ao consultar pedidos: ' . $e->getMessage()
        );

        http_response_code(500);

        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Não foi possível carregar os pedidos.'
        ], JSON_UNESCAPED_UNICODE);
    }

    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Painel de Pedidos - Empada Camarada</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #e6cfab;
            color: #30190f;
            margin: 0;
            padding: 25px;
        }

        main {
            max-width: 1000px;
            margin: auto;
        }

        h1 {
            text-align: center;
        }

        .resumo {
            background: #30190f;
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 25px;
        }

        .numero {
            font-size: 42px;
            font-weight: bold;
        }

        .pedido {
            background: #f7f0e5;
            border-radius: 10px;
            padding: 22px;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px #30190f20;
        }

        .pedido h2 {
            margin-top: 0;
            border-bottom: 1px solid #d8c5aa;
            padding-bottom: 12px;
        }

        .itens {
            background: #fffaf2;
            padding: 15px;
            border-radius: 7px;
            margin-top: 15px;
        }

        .item {
            padding: 8px 0;
            border-bottom: 1px solid #e5d7c5;
        }

        .item:last-child {
            border-bottom: none;
        }

        .total {
            font-size: 19px;
            font-weight: bold;
            text-align: right;
            margin-top: 15px;
        }

        .vazio,
        .erro {
            background: #f7f0e5;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }

        .erro {
            color: #a40000;
        }

        .atualizacao {
            text-align: center;
            font-size: 13px;
            margin-top: 20px;
        }

        /*
         * Estilo do botão de finalizar pedido.
         */
        .botao-finalizar {
            display: block;
            width: 100%;
            margin-top: 20px;
            padding: 13px;
            border: none;
            border-radius: 7px;
            background: #347a38;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .botao-finalizar:hover {
            background: #285f2c;
        }

        .botao-finalizar:disabled {
            background: #999;
            cursor: not-allowed;
        }

    </style>

</head>

<body>

<main>

    <h1>Pedidos - Empada Camarada</h1>

    <section class="resumo">

        <div>Total de pedidos registrados</div>

        <div class="numero" id="total-pedidos">
            0
        </div>

    </section>

    <section id="lista-pedidos">

        <p class="vazio">
            Carregando pedidos...
        </p>

    </section>

    <p class="atualizacao" id="ultima-atualizacao">
        Aguardando atualização...
    </p>

</main>

<script>

/*
 * FUNÇÃO 4:
 * Cria um elemento HTML e coloca texto nele.
 *
 * textContent evita interpretar os dados do cliente
 * como código HTML.
 */
function criarElemento(tag, texto, classe) {

    const elemento = document.createElement(tag);

    elemento.textContent = texto;

    if (classe) {
        elemento.className = classe;
    }

    return elemento;
}

/*
 * FUNÇÃO 5:
 * Recebe os pedidos vindos do PHP e monta a página.
 */
function mostrarPedidos(pedidos) {

    const lista = document.getElementById('lista-pedidos');

    // Limpa a lista antiga antes de mostrar os dados atualizados.
    lista.replaceChildren();

    if (pedidos.length === 0) {

        lista.appendChild(
            criarElemento(
                'p',
                'Nenhum pedido foi registrado ainda.',
                'vazio'
            )
        );

        return;
    }

    pedidos.forEach(function(pedido) {

        const cartao = criarElemento(
            'article',
            '',
            'pedido'
        );

        cartao.appendChild(
            criarElemento('h2', 'Pedido #' + pedido.id)
        );

        cartao.appendChild(
            criarElemento(
                'p',
                'Cliente: ' + pedido.nome_cliente
            )
        );

        cartao.appendChild(
            criarElemento(
                'p',
                'Endereço de entrega: ' + pedido.endereco_entrega
            )
        );

        const data = new Date(
            pedido.criado_em.replace(' ', 'T')
        );

        cartao.appendChild(
            criarElemento(
                'p',
                'Data do pedido: ' +
                data.toLocaleString('pt-BR')
            )
        );

        cartao.appendChild(
            criarElemento(
                'h3',
                'Empadas escolhidas:'
            )
        );

        const caixaItens = criarElemento(
            'div',
            '',
            'itens'
        );

        pedido.itens.forEach(function(item) {

            const preco = Number(item.preco_unitario);

            const subtotal = preco * item.quantidade;

            const descricao =
                item.quantidade + 'x ' +
                item.sabor +
                ' — Unitário: R$ ' +
                preco.toFixed(2).replace('.', ',') +
                ' — Subtotal: R$ ' +
                subtotal.toFixed(2).replace('.', ',');

            caixaItens.appendChild(
                criarElemento(
                    'div',
                    descricao,
                    'item'
                )
            );

        });

        cartao.appendChild(caixaItens);

        const total = Number(pedido.total);

        cartao.appendChild(
            criarElemento(
                'p',
                'Total do pedido: R$ ' +
                total.toFixed(2).replace('.', ','),
                'total'
            )
        );

        /*
         * Cria o botão para o operador confirmar
         * que o pedido foi preparado e atendido.
         */
        const botao = criarElemento(
            'button',
            'Pedido preparado e atendido',
            'botao-finalizar'
        );

        botao.type = 'button';

        botao.addEventListener('click', function() {

            finalizarPedido(pedido.id, botao);

        });

        cartao.appendChild(botao);

        lista.appendChild(cartao);

    });
}

/*
 * FUNÇÃO 6:
 * Finaliza o pedido após a confirmação do operador.
 *
 * Envia o ID para o PHP, que faz a exclusão
 * dos registros no banco de dados.
 */
async function finalizarPedido(pedidoId, botao) {

    const confirmar = window.confirm(
        'Tem certeza de que deseja confirmar que o pedido #' +
        pedidoId +
        ' foi preparado e atendido? O pedido será excluído do banco de dados.'
    );

    if (!confirmar) {
        return;
    }

    try {

        botao.disabled = true;

        botao.textContent = 'Finalizando pedido...';

        const resposta = await fetch(
            'contar_pedidos.php?acao=finalizar',
            {
                method: 'POST',

                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },

                body: JSON.stringify({
                    pedido_id: pedidoId
                })
            }
        );

        const dados = await resposta.json();

        if (!resposta.ok || !dados.sucesso) {

            throw new Error(
                dados.mensagem || 'Erro ao finalizar pedido.'
            );

        }

        alert(dados.mensagem);

        // Atualiza a lista e a contagem de pedidos.
        await atualizarPedidos();

    } catch (erro) {

        console.error('Erro:', erro);

        alert(
            erro.message ||
            'Não foi possível finalizar o pedido.'
        );

        botao.disabled = false;

        botao.textContent = 'Pedido preparado e atendido';

    }

}

/*
 * FUNÇÃO 7:
 * Consulta o PHP para buscar os pedidos atualizados.
 */
async function atualizarPedidos() {

    try {

        const resposta = await fetch(
            'contar_pedidos.php?acao=atualizar',
            {
                cache: 'no-store',

                headers: {
                    'Accept': 'application/json'
                }
            }
        );

        const dados = await resposta.json();

        if (!resposta.ok || !dados.sucesso) {

            throw new Error(
                dados.mensagem || 'Erro ao carregar pedidos.'
            );

        }

        // Atualiza a quantidade total de pedidos.
        document.getElementById('total-pedidos').textContent =
            dados.total_pedidos;

        // Atualiza os cartões com os dados de cada pedido.
        mostrarPedidos(dados.pedidos);

        // Mostra o horário da última consulta.
        document.getElementById('ultima-atualizacao').textContent =
            'Última atualização: ' +
            new Date().toLocaleTimeString('pt-BR');

    } catch (erro) {

        console.error('Erro:', erro);

        document.getElementById('lista-pedidos').replaceChildren(
            criarElemento(
                'p',
                'Não foi possível carregar os pedidos. Verifique a conexão com o banco.',
                'erro'
            )
        );

    }

}

/*
 * FUNÇÃO 8:
 * Atualiza a página imediatamente quando ela abre
 * e depois repete a consulta a cada 5 segundos.
 */
document.addEventListener('DOMContentLoaded', function() {

    atualizarPedidos();

    setInterval(atualizarPedidos, 5000);

});

</script>

</body>
</html>