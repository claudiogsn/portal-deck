<?php
header('Content-Type: application/json; charset=utf-8');

$configp = parse_ini_file(__DIR__ . '/../../../app/config/permission.ini'); // Caminho ajustado

if ($configp === false) {
    http_response_code(500);
    die("Erro ao ler o arquivo de configuração.");
}

$hostp = $configp['host'];
$portp = $configp['port'];
$dbnamep = $configp['name'];
$usernamep = $configp['user'];
$passwordp = $configp['pass'];

$dsn = "{$configp['type']}:host={$hostp};port={$portp};dbname={$dbnamep};charset=utf8mb4";

try {
    $pdop = new PDO($dsn, $usernamep, $passwordp);
    $pdop->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $ep) {
    http_response_code(500);
    die("Erro ao conectar ao banco de dados: " . $ep->getMessage());
}
?>
