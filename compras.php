<?php
// Verifica se o hostname não é 'localhost'
$host = $_SERVER['HTTP_HOST'];
if ($host !== 'localhost') {
    $url = "https://portal.vemprodeck.com.br/external/pedido.html?tag=";
} else {
    $url = "http://localhost/portal-deck/external/pedido.html?tag=";
}

// Verifica se a tag foi passada como parâmetro
if (isset($_GET['tag'])) {
    $tag = $_GET['tag'];
    $url .= urlencode($tag);
} else {
    // Caso a tag não tenha sido passada, exibe uma mensagem de erro
    echo '
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
        <title>Erro 403 - Acesso Negado</title>
        
        <!-- Google Fonts -->
        <link href="https://fonts.googleapis.com/css?family=Roboto:400,700&subset=latin,cyrillic-ext" rel="stylesheet" type="text/css">
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" type="text/css">

        <!-- Bootstrap Core Css -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet">

        <!-- Waves Effect Css -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/node-waves/0.7.6/waves.min.css" rel="stylesheet" />

        <!-- Animation Css -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet" />

        <!-- Custom Css -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/adminbsb-materialdesign/1.0.7/css/style.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/adminbsb-materialdesign/1.0.7/css/themes/all-themes.css" rel="stylesheet" />

        <style>
            body {
                background-color: #f3f3f3;
                font-family: "Roboto", sans-serif;
            }

            .error-container {
                text-align: center;
                margin-top: 5%;
            }

            .error-code {
                font-size: 8em;
                color: #ff6b6b;
                text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.1);
            }

            .error-message {
                font-size: 1.5em;
                color: #666;
                margin-bottom: 20px;
            }

            .logo {
                margin: 20px auto;
            }

            .button-container {
                margin-top: 30px;
            }

            .button-container a {
                text-decoration: none;
            }
        </style>
    </head>
    <body class="theme-red">

        <div class="container error-container">
            <img class="logo" src="https://portal.vemprodeck.com.br/app/templates/theme5/images/deck.png" alt="Deck Logo" width="200">

            <div class="error-code">403</div>
            <div class="error-message">Acesso Negado</div>
            <p>O modelo de requsição não foi encontrado para a tag informada.</p>

            <div class="button-container">
                <a href="https://portal.vemprodeck.com.br/" class="btn btn-primary waves-effect">Voltar ao Início</a>
            </div>
        </div>

        <!-- Jquery Core Js -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/2.2.4/jquery.min.js"></script>
        
        <!-- Bootstrap Core Js -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/js/bootstrap.min.js"></script>

        <!-- Waves Effect Plugin Js -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/node-waves/0.7.6/waves.min.js"></script>

        <!-- Custom Js -->
        <script>
            $(document).ready(function () {
                Waves.init();
            });
        </script>
    </body>
    </html>';
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<link rel="manifest" href="https://portal.vemprodeck.com.br/manifest-loader.php?tag=<?php echo urlencode($_GET['tag'] ?? ''); ?>">
<link rel="manifest" href="https://portal.vemprodeck.com.br/manifest-compras.json">
<link rel="apple-touch-icon" href="https://portal.vemprodeck.com.br/app/images/compras/icon-192.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Compras Deck">
<meta name="theme-color" content="#ff9800">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Realizar Requisição Compras</title>
    <style>
        /* O iframe ocupará toda a tela */
        html, body {
            height: 100%;
            margin: 0;
            overflow: hidden;
        }

        iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
    </style>
</head>
<body>
    <!-- Carrega a URL dentro do iframe -->
    <iframe src="<?php echo htmlspecialchars($url); ?>"></iframe>
</body>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    let deferredPrompt = null;

    // Detectar Android (via beforeinstallprompt)
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault(); // Evita o banner automático
        deferredPrompt = e;

        Swal.fire({
            title: 'Instale o app!',
            text: 'Você gostaria de adicionar este aplicativo à sua tela inicial?',
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Adicionar à Tela',
            cancelButtonText: 'Agora não'
        }).then((result) => {
            if (result.isConfirmed && deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then(choiceResult => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('Usuário aceitou instalar o PWA');
                    } else {
                        console.log('Usuário recusou o PWA');
                    }
                    deferredPrompt = null;
                });
            }
        });
    });

    // Detectar iOS
    document.addEventListener("DOMContentLoaded", function () {
        const isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent.toLowerCase());
        const isInStandaloneMode = ('standalone' in window.navigator) && window.navigator.standalone;

        if (isIOS && !isInStandaloneMode) {
            Swal.fire({
                title: 'Instale o app!',
                html: "No iPhone, toque em <b>Compartilhar</b> e depois em <b>Adicionar à Tela de Início</b>.",
                icon: 'info',
                confirmButtonText: 'Entendi'
            });
        }
    });
</script>


</html>