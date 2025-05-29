<?php
header('Content-Type: application/json');

$tag = isset($_GET['tag']) ? $_GET['tag'] : '';
if (!$tag) {
    http_response_code(400);
    echo json_encode(["error" => "Tag não fornecida."]);
    exit;
}

// Configura a requisição POST para a API
$apiUrl = "https://portal.vemprodeck.com.br/api/v1/index.php";
$postData = json_encode([
    "method" => "getModelByTagCompras",
    "data" => [ "tag" => $tag ]
]);

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$response) {
    http_response_code(500);
    echo json_encode(["error" => "Falha ao consultar API."]);
    exit;
}

$data = json_decode($response, true);
if (!$data || !$data['success'] || !isset($data['modelo']['nome'])) {
    http_response_code(404);
    echo json_encode(["error" => "Modelo não encontrado para a tag informada."]);
    exit;
}

$modeloNome = $data['modelo']['nome'];
$tagLimpa = urlencode($data['modelo']['tag']);

$manifest = [
    "short_name" => $modeloNome,
    "name" => $modeloNome,
    "start_url" => "/compras/{$tagLimpa}",
    "display" => "standalone",
    "background_color" => "#ffffff",
    "theme_color" => "#ff9800",
    "orientation" => "portrait",
    "icons" => [
        [
            "src" => "images/icon.png",
            "sizes" => "180x180",
            "type" => "image/png"
        ],
        [
            "src" => "images/icon-192.png",
            "sizes" => "192x192",
            "type" => "image/png"
        ],
        [
            "src" => "images/icon-512.png",
            "sizes" => "512x512",
            "type" => "image/png"
        ]
    ]
];

echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
