<?php
header('Content-Type: application/json');

// Verifica se a tag foi passada na URL
$tag = isset($_GET['tag']) ? preg_replace('/[^a-zA-Z0-9\-]/', '', $_GET['tag']) : '';

// Define o start_url com a tag (ou raiz como fallback)
$startUrl = $tag ? "/compras/{$tag}" : "/";

$manifest = [
    "short_name" => "Solicitar Compras Deck",
    "name" => "Solicitar Compras Deck",
    "start_url" => $startUrl,
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
