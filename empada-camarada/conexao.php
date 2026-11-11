<?php
declare(strict_types=1);

/*
 * Conexão PDO com MySQL.
 * Para este trabalho acadêmico, as credenciais solicitadas ficam explícitas.
 * Em produção, use variáveis de ambiente e uma conta com privilégios mínimos.
 */
$host = 'localhost';
$porta = 3306;
$banco = 'empada_camarada';
$usuario = 'admin';
$senha = '1234abcd';

$dsn = "mysql:host={$host};port={$porta};dbname={$banco};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $usuario, $senha, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log('Falha na conexão PDO: ' . $e->getMessage());
    if (isset($_SERVER['SCRIPT_FILENAME']) &&
        realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
        http_response_code(500);
        echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Conexão MySQL</title><body>';
        echo '<h1>Falha ao conectar ao servidor MySQL.</h1>';
        echo '<p>Verifique se o MySQL está ativo, se o banco existe e se usuário/senha estão corretos.</p>';
        echo '</body></html>';
    } else {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['sucesso' => false, 'mensagem' => 'Não foi possível conectar ao banco de dados.']);
    }
    exit;
}

/* Exibe o teste de conexão somente quando conexao.php é aberto diretamente. */
if (isset($_SERVER['SCRIPT_FILENAME']) &&
    realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Conexão MySQL</title><body>';
    echo '<h1>Conexão com o servidor MySQL realizada com sucesso!</h1>';
    echo '<p>Servidor: localhost:' . (int)$porta . ' — Banco: ' . htmlspecialchars($banco, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '</body></html>';
}
?>
