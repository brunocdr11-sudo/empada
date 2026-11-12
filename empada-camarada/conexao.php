<?php

/*
 * conexao.php — Login e conexão PDO com MySQL
 *
 * A pessoa informa as credenciais de um usuário MySQL existente.
 * Depois do login, as credenciais ficam na sessão PHP para que os
 * demais arquivos do projeto possam criar sua conexão PDO.
 *
 * Arquivos como estoque.php e salvar_pedido.php devem continuar usando:
 * require __DIR__ . '/conexao.php';
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* Configurações fixas do servidor e do banco de dados. */
$host = 'localhost';
$porta = 3306;
$banco = 'empada_camarada';

/* Identifica se conexao.php foi aberto diretamente no navegador. */
$acessoDireto = isset($_SERVER['SCRIPT_FILENAME'])
    && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__);

/* Escapa textos antes de exibi-los no HTML. */
if (!function_exists('h')) {
    function h(string $valor): string
    {
        return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
    }
}

/*
 * Cria a conexão usando as credenciais informadas no login
 * ou armazenadas na sessão após um login bem-sucedido.
 */
function criarConexao(string $host, int $porta, string $banco, string $usuario, string $senha): PDO
{
    $dsn = "mysql:host={$host};port={$porta};dbname={$banco};charset=utf8mb4";

    return new PDO($dsn, $usuario, $senha, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

$mensagem = '';
$tipoMensagem = '';
$pdo = null;

/* Opção para encerrar a sessão de login. */
if ($acessoDireto && isset($_GET['sair'])) {
    unset($_SESSION['mysql_usuario'], $_SESSION['mysql_senha']);
    session_regenerate_id(true);
    $mensagem = 'Você saiu do sistema.';
    $tipoMensagem = 'sucesso';
}

/*
 * LOGIN: testa as credenciais digitadas diretamente no MySQL.
 * Se a conexão funcionar, guarda as credenciais na sessão.
 */
if ($acessoDireto && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuarioDigitado = trim((string)($_POST['usuario'] ?? ''));
    $senhaDigitada = (string)($_POST['senha'] ?? '');

    if ($usuarioDigitado === '' || $senhaDigitada === '') {
        $mensagem = 'Informe o usuário e a senha.';
        $tipoMensagem = 'erro';
    } else {
        try {
            $pdo = criarConexao(
                $host,
                $porta,
                $banco,
                $usuarioDigitado,
                $senhaDigitada
            );

            session_regenerate_id(true);

            $_SESSION['mysql_usuario'] = $usuarioDigitado;
            $_SESSION['mysql_senha'] = $senhaDigitada;

            $mensagem = 'Login realizado com sucesso! Conexão com o banco de dados estabelecida.';
            $tipoMensagem = 'sucesso';
        } catch (PDOException $e) {
            error_log('Falha no login/conexão MySQL: ' . $e->getMessage());

            unset($_SESSION['mysql_usuario'], $_SESSION['mysql_senha']);

            $mensagem = 'Falha no login ou na conexão. Confira o usuário, a senha e se o banco MySQL está ativo.';
            $tipoMensagem = 'erro';
            $pdo = null;
        }
    }
}

/*
 * Se já existe uma sessão autenticada, cria a conexão para esta
 * requisição. Isso permite que os outros arquivos continuem usando $pdo.
 */
if ($pdo === null
    && isset($_SESSION['mysql_usuario'], $_SESSION['mysql_senha'])) {
    try {
        $pdo = criarConexao(
            $host,
            $porta,
            $banco,
            (string)$_SESSION['mysql_usuario'],
            (string)$_SESSION['mysql_senha']
        );

        if ($acessoDireto && $mensagem === '') {
            $mensagem = 'Conexão com o banco de dados realizada com sucesso!';
            $tipoMensagem = 'sucesso';
        }
    } catch (PDOException $e) {
        error_log('Falha ao reconectar ao MySQL: ' . $e->getMessage());

        unset($_SESSION['mysql_usuario'], $_SESSION['mysql_senha']);
        $pdo = null;

        if ($acessoDireto) {
            $mensagem = 'Falha na conexão. Faça login novamente e confira as credenciais.';
            $tipoMensagem = 'erro';
        }
    }
}

/*
 * Quando outro PHP inclui este arquivo sem uma sessão autenticada,
 * envia a pessoa para a tela de login.
 *
 * Para chamadas JavaScript que esperam JSON, responde com HTTP 401
 * em JSON em vez de redirecionar para uma página HTML.
 */
if ($pdo === null && !$acessoDireto) {
    $aceitaJson = str_contains(
        (string)($_SERVER['HTTP_ACCEPT'] ?? ''),
        'application/json'
    );

    if ($aceitaJson) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Faça login para acessar o sistema.'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        header('Location: conexao.php');
    }

    exit;
}

/*
 * Quando conexao.php é incluído por outro arquivo PHP, não exibe HTML.
 * O objeto $pdo fica disponível para o arquivo que fez o require.
 */
if (!$acessoDireto) {
    return;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Empada Camarada</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #e6cfab;
            color: #30190f;
        }

        .login {
            width: 100%;
            max-width: 420px;
            padding: 30px;
            background: #f7f0e5;
            border-radius: 12px;
            box-shadow: 0 4px 18px #30190f25;
        }

        h1 {
            margin-top: 0;
            text-align: center;
        }

        .introducao {
            text-align: center;
            line-height: 1.5;
        }

        label {
            display: block;
            margin-top: 18px;
            font-weight: bold;
        }

        input {
            width: 100%;
            margin-top: 7px;
            padding: 12px;
            border: 1px solid #c8b49a;
            border-radius: 6px;
            font-size: 16px;
        }

        button {
            width: 100%;
            margin-top: 22px;
            padding: 13px;
            border: 0;
            border-radius: 6px;
            background: #30190f;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #70411f;
        }

        .mensagem {
            margin-top: 18px;
            padding: 12px;
            border-radius: 6px;
            line-height: 1.45;
        }

        .sucesso {
            color: #205a2b;
            background: #d9eedb;
        }

        .erro {
            color: #850000;
            background: #f8dada;
        }

        .links {
            margin-top: 20px;
            text-align: center;
            line-height: 1.8;
        }

        a {
            color: #70411f;
        }
    </style>
</head>

<body>
<main class="login">
    <h1>Empada Camarada</h1>

    <p class="introducao">
        Digite o usuário e a senha do MySQL para estabelecer
        a conexão com o banco de dados.
    </p>

    <?php if ($mensagem !== ''): ?>
        <div class="mensagem <?= h($tipoMensagem) ?>" role="status">
            <?= h($mensagem) ?>
        </div>
    <?php endif; ?>

    <?php if ($pdo === null): ?>
        <form method="post" action="conexao.php">
            <label for="usuario">Usuário</label>
            <input
                type="text"
                id="usuario"
                name="usuario"
                autocomplete="username"
                required
            >

            <label for="senha">Senha</label>
            <input
                type="password"
                id="senha"
                name="senha"
                autocomplete="current-password"
                required
            >

            <button type="submit">Entrar</button>
        </form>
    <?php else: ?>
        <div class="links">
            <p><a href="pagina_principal.html">Ir para a página principal</a></p>
            <p><a href="estoque.php">Acessar o controle de estoque</a></p>
            <p><a href="contar_pedidos.php">Visualizar os pedidos</a></p>
            <p><a href="conexao.php?sair=1">Sair do sistema</a></p>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
