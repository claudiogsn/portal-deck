<?php
header('Content-Type: application/json; charset=utf-8');

$config = parse_ini_file(__DIR__ . '/../../../app/config/dispatch.ini'); // Caminho ajustado

if ($config === false) {
    http_response_code(500);
    die("Erro ao ler o arquivo de configuração.");
}

$hostd = $config['host'];
$portd = $config['port'];
$dbnamed = $config['name'];
$usernamed = $config['user'];
$passwordd = $config['pass'];

$dsnd = "{$config['type']}:host={$hostd};port={$portd};dbname={$dbnamed};charset=utf8mb4";

try {
    $pdo = new PDO($dsnd, $usernamed, $passwordd);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    http_response_code(500);
    die("Erro ao conectar ao banco de dados: " . $e->getMessage());
}
?>
