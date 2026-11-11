<?php
declare(strict_types=1);
require __DIR__ . '/conexao.php';

$sabores = [
    'queijo' => 'Queijo',
    'frango' => 'Frango',
    'carne_seca' => 'Carne Seca',
    'camarao' => 'Camarão',
    'romeu_julieta' => 'Romeu e Julieta',
    'chocolate' => 'Chocolate',
];

function jsonResposta(array $dados, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

$acao = (string)($_GET['acao'] ?? $_POST['acao'] ?? '');

if ($acao === 'lista') {
    try {
        $linhas = $pdo->query('SELECT sabor, quantidade FROM estoque')->fetchAll();
        $resultado = array_fill_keys(array_keys($sabores), 0);
        foreach ($linhas as $linha) {
            if (array_key_exists($linha['sabor'], $resultado)) $resultado[$linha['sabor']] = (int)$linha['quantidade'];
        }
        jsonResposta(['sucesso' => true, 'estoque' => $resultado]);
    } catch (Throwable $e) {
        error_log('Erro ao consultar estoque: ' . $e->getMessage());
        jsonResposta(['sucesso' => false, 'mensagem' => 'Não foi possível consultar o estoque.'], 500);
    }
}

$mensagem = '';
$tipoMensagem = 'sucesso';
$edicao = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sabor = (string)($_POST['sabor'] ?? '');
    $quantidadeRaw = $_POST['quantidade'] ?? '';
    try {
        if (!isset($sabores[$sabor])) throw new InvalidArgumentException('Selecione um sabor válido da lista.');
        if (!is_scalar($quantidadeRaw) || !preg_match('/^\d+$/', (string)$quantidadeRaw)) {
            throw new InvalidArgumentException('A quantidade deve ser um número inteiro entre 0 e 50.');
        }
        $quantidade = (int)$quantidadeRaw;
        if ($quantidade < 0 || $quantidade > 50) {
            throw new InvalidArgumentException('A quantidade deve estar entre 0 e 50.');
        }

        if ($acao === 'criar' || $acao === 'atualizar') {
            $stmt = $pdo->prepare(
                'INSERT INTO estoque (sabor, quantidade) VALUES (:sabor, :quantidade)
                 ON DUPLICATE KEY UPDATE quantidade = VALUES(quantidade)'
            );
            $stmt->execute(['sabor' => $sabor, 'quantidade' => $quantidade]);
            $mensagem = 'Estoque de ' . $sabores[$sabor] . ' salvo com sucesso.';
        } elseif ($acao === 'excluir') {
            $stmt = $pdo->prepare('UPDATE estoque SET quantidade = 0 WHERE sabor = :sabor');
            $stmt->execute(['sabor' => $sabor]);
            $mensagem = 'Estoque de ' . $sabores[$sabor] . ' zerado. O sabor permanece cadastrado para preservar o histórico dos pedidos.';
        } else {
            throw new InvalidArgumentException('Operação não reconhecida.');
        }
    } catch (InvalidArgumentException $e) {
        $mensagem = $e->getMessage();
        $tipoMensagem = 'erro';
    } catch (Throwable $e) {
        error_log('Erro no controle de estoque: ' . $e->getMessage());
        $mensagem = 'Não foi possível alterar o estoque. Confira a conexão e a estrutura do banco.';
        $tipoMensagem = 'erro';
    }
}

try {
    $linhas = $pdo->query('SELECT sabor, quantidade FROM estoque ORDER BY sabor')->fetchAll();
    $estoqueAtual = [];
    foreach ($linhas as $linha) $estoqueAtual[$linha['sabor']] = (int)$linha['quantidade'];
} catch (Throwable $e) {
    $estoqueAtual = [];
    $mensagem = 'Não foi possível carregar o estoque.';
    $tipoMensagem = 'erro';
}

function h(string $valor): string { return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Controle de estoque — Empada Camarada</title>
<style>
*{box-sizing:border-box}body{font-family:Arial,sans-serif;background:#e6cfab;color:#30190f;margin:0;padding:24px}
main{max-width:900px;margin:20px auto;background:#f7f0e5;padding:26px;border-radius:12px;box-shadow:0 4px 15px #30190f20}
h1,h2{text-align:center}p.intro{text-align:center}
form.edicao{display:grid;grid-template-columns:1fr 1fr auto;gap:12px;align-items:end;margin:25px 0}
label{display:flex;flex-direction:column;gap:7px;font-weight:bold}select,input{padding:11px;border:1px solid #c8b49a;border-radius:5px;font-size:16px;width:100%}
button{padding:11px 15px;border:0;border-radius:5px;background:#30190f;color:white;font-weight:bold;cursor:pointer}button:hover{background:#70411f}
table{width:100%;border-collapse:collapse;margin-top:18px}th,td{padding:12px;text-align:left;border-bottom:1px solid #d8c5aa}th{background:#30190f;color:white}
.mensagem{padding:12px;border-radius:6px;margin:15px 0}.sucesso{background:#d9eedb;color:#205a2b}.erro{background:#f8dada;color:#850000}
.acoes{display:flex;gap:8px;flex-wrap:wrap}.acoes form{margin:0}.zerado{color:#a40000;font-weight:bold}
.links{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:24px}
@media(max-width:650px){form.edicao{grid-template-columns:1fr}table{font-size:14px}th,td{padding:8px}}
</style>
</head>
<body>
<main>
<h1>Controle de estoque</h1>
<p class="intro">Cadastre ou altere a quantidade disponível de cada sabor (de 0 a 50 unidades).</p>
<?php if ($mensagem !== ''): ?>
<div class="mensagem <?= $tipoMensagem === 'erro' ? 'erro' : 'sucesso' ?>"><?= h($mensagem) ?></div>
<?php endif; ?>

<h2>Adicionar / atualizar estoque</h2>
<form class="edicao" method="post" action="estoque.php">
<input type="hidden" name="acao" value="atualizar">
<label>Sabor da empada
<select name="sabor" required>
<?php foreach ($sabores as $chave => $nome): ?>
<option value="<?= h($chave) ?>"><?= h($nome) ?></option>
<?php endforeach; ?>
</select>
</label>
<label>Quantidade (0–50)
<input type="number" name="quantidade" min="0" max="50" step="1" required value="0">
</label>
<button type="submit">Salvar quantidade</button>
</form>

<h2>Estoque atual</h2>
<table>
<thead><tr><th>Sabor</th><th>Quantidade</th><th>Status</th><th>Operações</th></tr></thead>
<tbody>
<?php foreach ($sabores as $chave => $nome): $qtd = $estoqueAtual[$chave] ?? 0; ?>
<tr>
<td><?= h($nome) ?></td>
<td><?= $qtd ?></td>
<td class="<?= $qtd === 0 ? 'zerado' : '' ?>"><?= $qtd === 0 ? 'Esgotado' : 'Disponível' ?></td>
<td><div class="acoes">
<form method="post" action="estoque.php">
<input type="hidden" name="acao" value="atualizar">
<input type="hidden" name="sabor" value="<?= h($chave) ?>">
<input type="hidden" name="quantidade" value="<?= $qtd ?>">
</form>
<form method="post" action="estoque.php" onsubmit="return confirm('Deseja zerar o estoque deste sabor?');">
<input type="hidden" name="acao" value="excluir">
<input type="hidden" name="sabor" value="<?= h($chave) ?>">
<input type="hidden" name="quantidade" value="0">
<button type="submit">Zerar</button>
</form>
</div></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<div class="links"><a href="pagina_principal.html">Voltar ao site</a><a href="conexao.php">Testar conexão MySQL</a></div>
</main>
</body>
</html>
