<?php
date_default_timezone_set('America/Rio_Branco');


ini_set('post_max_size', '100M');
ini_set('upload_max_filesize', '100M');
ini_set('max_execution_time', '600');
ini_set('max_input_time', '600');
ini_set('memory_limit', '512M');


header("Access-Control-Allow-Origin: *"); // Permitir todas as origens
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // Métodos permitidos
header("Access-Control-Allow-Headers: Content-Type, Authorization"); // Cabeçalhos permitidos
header("Access-Control-Allow-Origin: http://localhost:3000");
header('Content-Type: application/json; charset=utf-8');

require_once 'controllers/ComposicaoController.php';
require_once 'controllers/DashboardController.php';
require_once 'controllers/EstoqueController.php';
require_once 'controllers/FornecedoresController.php';
require_once 'controllers/InsumoController.php';
require_once 'controllers/NecessidadesController.php';
require_once 'controllers/ProductionController.php';
require_once 'controllers/SalesController.php';
require_once 'controllers/TransfersController.php';
require_once 'controllers/ProductController.php';
require_once 'controllers/CategoriesController.php';
require_once 'controllers/MovimentacaoController.php';
require_once 'controllers/ModeloBalancoController.php';
require_once 'controllers/BiController.php';
require_once 'controllers/ComprasController.php';
require_once 'controllers/ManipulacaoController.php';
require_once 'controllers/UserController.php';


if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}


$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (isset($data['method']) && isset($data['data'])) {
    $method = $data['method'];
    $requestData = $data['data'];
    if (isset($data['token'])){$requestToken = $data['token'];}

    // Métodos que não precisam de autenticação
    $noAuthMethods = ['validateCPF',
        'getUserDetails',
        'listarFichas',
        'listarItensFicha',
        'listarMovimentacoes',
        'listarItensMovimentacao',
        'listarAnexosMovimentacao',
        'registrarMovimentacao',
        'saveFicha',
        'toggleStatusFicha',
        'getFichasByUser',
        'saveUsuariosPorFicha',
        'listarUsuariosDaFicha',
        'registrarAnexoMovimentacao',
        'validateCNPJ',
        'getModelByTag',
        'getModelByTagCompras',
        'saveBalanceItems',
        'saveComprasItems',
        'getUnitsByGroup',
        'registerJobExecution',
        'persistSales',
        'consolidateSalesByGroup'];

    if (!in_array($method, $noAuthMethods)) {
        if (!isset($requestToken)) {
            http_response_code(400);
            echo json_encode(['error' => 'Token ausente']);
            exit;
        }
        require_once 'database/db-permission.php';
        $userInfo = verifyToken($requestToken,$pdop);
    }

    try {
        switch ($method) {
            // Métodos para ManipulacaoController
            case 'listarFichas':
                if (isset($requestData['system_unit_id'])) {
                    $response = ManipulacaoController::listarFichas($requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro system_unit_id ausente'];
                }
                break;

            case 'listarItensFicha':
                if (isset($requestData['id_ficha'], $requestData['system_unit_id'])) {
                    $response = ManipulacaoController::listarItensFicha($requestData['id_ficha'], $requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros id_ficha e/ou system_unit_id ausentes'];
                }
                break;

            case 'listarMovimentacoes':
                if (isset($requestData['id_ficha'], $requestData['system_unit_id'])) {
                    $response = ManipulacaoController::listarMovimentacoes($requestData['id_ficha'], $requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros id_ficha e/ou system_unit_id ausentes'];
                }
                break;

            case 'getFichaDetalhada':
                if (isset($requestData['id_ficha'], $requestData['system_unit_id'])) {
                    $response = ManipulacaoController::getFichaDetalhada($requestData['id_ficha'], $requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros id_ficha e/ou system_unit_id ausentes'];
                }
                break;

            case 'listarItensMovimentacao':
                if (isset($requestData['documento'], $requestData['system_unit_id'])) {
                    $response = ManipulacaoController::listarItensMovimentacao($requestData['documento'], $requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros documento e/ou system_unit_id ausentes'];
                }
                break;

            case 'listarAnexosMovimentacao':
                if (isset($requestData['documento'], $requestData['system_unit_id'])) {
                    $response = ManipulacaoController::listarAnexosMovimentacao($requestData['documento'], $requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros documento e/ou system_unit_id ausentes'];
                }
                break;

            case 'registrarMovimentacao':
                if (isset($requestData)) {
                    $response = ManipulacaoController::registrarMovimentacao($requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro data ausente'];
                }
                break;

            case 'saveFicha':
                if (isset($requestData)) {
                    $response = ManipulacaoController::saveFicha($requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro data ausente'];
                }
                break;

            case 'toggleStatusFicha':
                if (isset($requestData['id'], $requestData['system_unit_id'])) {
                    $response = ManipulacaoController::toggleStatusFicha($requestData['id'], $requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros id e/ou system_unit_id ausentes'];
                }
                break;

            case 'getFichasByUser':
                if (isset($requestData['user_id'], $requestData['system_unit_id'])) {
                    $response = ManipulacaoController::getFichasByUser($requestData['user_id'], $requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros user_id e/ou system_unit_id ausentes'];
                }
                break;

            case 'saveUsuariosPorFicha':
                if (isset($requestData['ficha_id'], $requestData['user_ids'])) {
                    $response = ManipulacaoController::saveUsuariosPorFicha($requestData['ficha_id'], $requestData['user_ids']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros ficha_id e/ou usuarios ausentes'];
                }
                break;

            case 'listarUsuariosDaFicha':
                if (isset($requestData['ficha_id'])) {
                    $response = ManipulacaoController::listarUsuariosDaFicha($requestData['ficha_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro ficha_id ausente'];
                }
                break;

                case 'getUsuariosEstoqueManipulacao':
                    $response = ManipulacaoController::getUsuariosEstoqueManipulacao();
                    break;

            case 'registrarAnexoMovimentacao':
                if (isset($requestData['documento'], $requestData['system_unit_id'], $requestData['url'])) {
                    $descricao = $requestData['descricao'] ?? null;
                    $response = ManipulacaoController::registrarAnexoMovimentacao(
                        $requestData['documento'],
                        $requestData['system_unit_id'],
                        $requestData['url'],
                        $descricao
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros documento, system_unit_id e/ou url ausentes'];
                }
                break;
            // Métodos para BiController
            case 'getUnitsByGroup':
                if (isset($requestData['group_id'])) {
                    $response = BiController::getUnitsByGroup($requestData['group_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro group_id ausente'];
                }
                break;
            case 'registerJobExecution':
                if (isset($requestData['nome_job'], $requestData['system_unit_id'], $requestData['custom_code'], $requestData['inicio'])) {
                    $response = BiController::registerJobExecution($requestData);
                    http_response_code(200);
                } else {
                    http_response_code(400);
                    $response = [
                        'status' => 'error',
                        'message' => 'Missing required fields.'
                    ];
                }
                break;
            case 'consolidateSalesByUnit':
                if (isset($requestData['system_unit_id'], $requestData['dt_inicio'], $requestData['dt_fim'])) {
                    $response = BiController::consolidateSalesByUnit($requestData['system_unit_id'], $requestData['dt_inicio'], $requestData['dt_fim']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id, dt_inicio ou dt_fim ausentes'];
                }
                break;
            case 'consolidateSalesByGroup':
                if (isset($requestData['group_id'], $requestData['dt_inicio'], $requestData['dt_fim'])) {
                    $response = BiController::consolidateSalesByGroup($requestData['group_id'], $requestData['dt_inicio'], $requestData['dt_fim']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros group_id, dt_inicio ou dt_fim ausentes'];
                }
                break;
            case 'persistSales':
                    if (isset($requestData)) {
                        $response = BiController::persistSales($requestData);
                    } else {
                        http_response_code(400);
                        $response = ['error' => 'Parâmetro sales ausente'];
                    }
                    break;
            // Métodos para InsumoController
            case 'getInsumosUsage':
                if (isset($requestData['system_unit_id'])) {
                    $response = InsumoController::getInsumosUsage($requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            case 'getInsumoConsumption':
                if (isset($requestData['system_unit_id']) && isset($requestData['dates']) && isset($requestData['productCodes'])) {
                    $response = NecessidadesController::getInsumoConsumption($requestData['system_unit_id'], $requestData['dates'], $requestData['productCodes']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id, dates ou productCodes ausentes'];
                }
                break;
            case 'getFiliaisByMatriz':
                if (isset($requestData['unit_matriz_id'])) {
                    $response = NecessidadesController::getFiliaisByMatriz($requestData['unit_matriz_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro $unit_matriz_id ausente'];
                }
                break;
            // Métodos para ComposicaoController
            case 'createComposicao':
                $response = ComposicaoController::createComposicao($requestData);
                break;
            case 'updateComposicao':
                if (isset($requestData['id'])) {
                    $response = ComposicaoController::updateComposicao($requestData['id'], $requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'getComposicaoById':
                if (isset($requestData['id'])) {
                    $response = ComposicaoController::getComposicaoById($requestData['id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'listComposicoes':
                if (isset($requestData['unit_id'])) {
                    $response = ComposicaoController::listComposicoes($requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            case 'listFichaTecnica':
                if (isset($requestData['unit_id']) && isset($requestData['product_id'])) {
                    $response = ComposicaoController::listFichaTecnica($requestData['product_id'],$requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros unit_id ou product_id ausente'];
                }
                    break;
            // Métodos para ModeloBalancoController
            case 'createModelo':
                if (isset($requestData['nome']) && isset($requestData['usuario_id']) && isset($requestData['itens'])) {
                    $response = ModeloBalancoController::createModelo($requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros obrigatórios ausentes: nome, usuario_id ou itens'];
                }
                break;
            case 'editModelo':
                if (isset($requestData['nome']) && isset($requestData['usuario_id']) && isset($requestData['itens'])) {
                    $response = ModeloBalancoController::editModelo($requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros obrigatórios ausentes: nome, usuario_id ou itens'];
                }
                break;
            case 'createModeloCompras':
                if (isset($requestData['nome']) && isset($requestData['usuario_id']) && isset($requestData['itens'])) {
                    $response = ModeloBalancoController::createModeloCompras($requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros obrigatórios ausentes: nome, usuario_id ou itens'];
                }
                break;
            case 'updateModelo':
                if (isset($requestData['id'])) {
                    $response = ModeloBalancoController::updateModelo($requestData['id'], $requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'editModeloCompras':
                if (isset($requestData['nome']) && isset($requestData['usuario_id']) && isset($requestData['itens'])) {
                    $response = ModeloBalancoController::editModeloCompras($requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros obrigatórios ausentes: nome, usuario_id ou itens'];
                }
                break;
            case 'listPurchaseRequests':
                if (isset($requestData['system_unit_id'])) {
                    $response = ModeloBalancoController::listPurchaseRequests($requestData['system_unit_id'],$requestData['data_inicial'],$requestData['data_final']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros obrigatórios ausentes'];
                }
                break;
            case 'getPurchaseRequestByDoc':
                    if (isset($requestData['system_unit_id']) && isset($requestData['doc']) ) {
                        $response = ModeloBalancoController::getPurchaseRequestByDoc($requestData['system_unit_id'],$requestData['doc']);
                    } else {
                        http_response_code(400);
                        $response = ['error' => 'Parâmetros obrigatórios ausentes'];
                    }
                    break;
            case 'deleteModelo':
                if (isset($requestData['id'])) {
                    $response = ModeloBalancoController::deleteModelo($requestData['id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'listModelosCompras':
                $response = ModeloBalancoController::listModelosCompras();
                break;
            case 'listItensByModeloCompras':
                if (isset($requestData['id'])) {
                    $response = ModeloBalancoController::listItensByModeloCompras($requestData['id'], $requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'deleteItemFromModelo':
                if (isset($requestData['modelo_id']) && isset($requestData['produto_id'])) {
                    $response = ModeloBalancoController::deleteItemFromModelo($requestData['modelo_id'], $requestData['produto_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros modelo_id ou produto_id ausente'];
                }
                break;
            case 'getModelByTag':
                if (isset($requestData['tag'])) {
                    $response = ModeloBalancoController::getModelByTag($requestData['tag']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro tag ausente'];
                }
                break;
            case 'getModelByTagCompras':
                if (isset($requestData['tag'])) {
                    $response = ModeloBalancoController::getModelByTagCompras($requestData['tag']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro tag ausente'];
                }
                break;
            case 'listarRequisicoes':
                if (isset($requestData['system_unit_id'])) {
                    $response = ComprasController::listarRequisicoes($requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro system_unit_id ausente'];
                }
                break;
            case 'listarItensDaRequisicao':
                if (isset($requestData['requisicao_id']) && isset($requestData['system_unit_id'])) {
                    $response = ComprasController::listarItensDaRequisicao($requestData['requisicao_id'], $requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros requisicao_id ou system_unit_id ausente'];
                }
                break;
            case 'listarLogDaRequisicao':
                if (isset($requestData['requisicao_id']) && isset($requestData['system_unit_id'])) {
                    $response = ComprasController::listarLogDaRequisicao($requestData['requisicao_id'], $requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros requisicao_id ou system_unit_id ausente'];
                }
                break;
            case 'changeStatusRequisicao':
                if (isset($requestData['doc'], $requestData['system_unit_id'], $requestData['status_id'], $requestData['username'])) {
                    $response = ComprasController::changeStatus(
                        $requestData['doc'],
                        $requestData['system_unit_id'],
                        $requestData['status_id'],
                        $requestData['username']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros doc, system_unit_id, status_id ou username ausentes'];
                }
                break;
            case 'realizarEntrega':
                if (
                    isset($requestData['doc'], $requestData['system_unit_id'], $requestData['username'], $requestData['itens'])
                    && is_array($requestData['itens'])
                ) {
                    $response = ComprasController::realizarEntrega(
                        $requestData['doc'],
                        $requestData['system_unit_id'],
                        $requestData['username'],
                        $requestData['itens']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros doc, system_unit_id, username ou itens ausentes ou inválidos'];
                }
                break;
            case  'saveBalanceItems':
                if (isset($requestData['system_unit_id']) && isset($requestData['itens'])) {
                    $response = MovimentacaoController::saveBalanceItems($requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id ou itens ausente'];
                }
                break;
            case  'saveComprasItems':
                if (isset($requestData['system_unit_id']) && isset($requestData['itens'])) {
                    $response = MovimentacaoController::saveComprasItems($requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id ou itens ausente'];
                }
                break;
            case 'listBalance':
                    if (isset($requestData['system_unit_id'])) {
                        // Verifica se as datas estão presentes e as atribui, caso contrário passa null
                        $data_inicial = isset($requestData['data_inicial']) ? $requestData['data_inicial'] : null;
                        $data_final = isset($requestData['data_final']) ? $requestData['data_final'] : null;
                
                        // Chama o método listBalance com os parâmetros corretos
                        $response = MovimentacaoController::listBalance($requestData['system_unit_id'], $data_inicial, $data_final);
                    } else {
                        http_response_code(400); // Código HTTP 400 para Bad Request
                        $response = ['error' => 'Parâmetro system_unit_id ausente'];
                    }
                    break;
            case 'listCompras':
                if (isset($requestData['system_unit_id'])) {
                    // Verifica se as datas estão presentes e as atribui, caso contrário passa null
                    $data_inicial = isset($requestData['data_inicial']) ? $requestData['data_inicial'] : null;
                    $data_final = isset($requestData['data_final']) ? $requestData['data_final'] : null;

                    // Chama o método listBalance com os parâmetros corretos
                    $response = MovimentacaoController::listCompras($requestData['system_unit_id'], $data_inicial, $data_final);
                } else {
                    http_response_code(400); // Código HTTP 400 para Bad Request
                    $response = ['error' => 'Parâmetro system_unit_id ausente'];
                }
                break;
            case 'getBalanceByDoc':
                    if (isset($requestData['system_unit_id']) && isset($requestData['doc'])) {
                        $response = MovimentacaoController::getBalanceByDoc($requestData['system_unit_id'], $requestData['doc']);
                    } else {
                        http_response_code(400);
                        $response = ['error' => 'Parâmetro system_unit_id ou doc ausente'];
                    }
                    break;
            case 'getComprasByDoc':
                if (isset($requestData['system_unit_id']) && isset($requestData['doc'])) {
                    $response = MovimentacaoController::getComprasByDoc($requestData['system_unit_id'], $requestData['doc']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro system_unit_id ou doc ausente'];
                }
                break;
            case 'createTransferItems':
                    $response = MovimentacaoController::createTransferItems($requestData);
                    break;
            // Métodos para DashboardController
            case 'getDashboardData':
                if (isset($requestData['unit_id'])) {
                    $response = DashboardController::getDashboardData($requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            // Métodos para EstoqueController
            case 'createEstoque':
                $response = EstoqueController::createEstoque($requestData);
                break;
            case 'updateEstoque':
                if (isset($requestData['id'])) {
                    $response = EstoqueController::updateEstoque($requestData['id'], $requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'getEstoqueById':
                if (isset($requestData['id'])) {
                    $response = EstoqueController::getEstoqueById($requestData['id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'listEstoque':
                if (isset($requestData['unit_id'])) {
                    $response = EstoqueController::listEstoque($requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            // Métodos para FornecedoresController
            case 'createFornecedor':
                $response = FornecedoresController::createFornecedor($requestData);
                break;
            case 'updateFornecedor':
                if (isset($requestData['id'])) {
                    $response = FornecedoresController::updateFornecedor($requestData['id'], $requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'getFornecedorById':
                if (isset($requestData['id'])) {
                    $response = FornecedoresController::getFornecedorById($requestData['id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'listFornecedores':
                if (isset($requestData['unit_id'])) {
                    $response = FornecedoresController::listFornecedores($requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            // Métodos para NecessidadesController
            case 'createNecessidade':
                $response = NecessidadesController::createNecessidade($requestData);
                break;
            case 'updateNecessidade':
                if (isset($requestData['id'])) {
                    $response = NecessidadesController::updateNecessidade($requestData['id'], $requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'getNecessidadeById':
                if (isset($requestData['id'])) {
                    $response = NecessidadesController::getNecessidadeById($requestData['id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'listNecessidades':
                if (isset($requestData['unit_id'])) {
                    $response = NecessidadesController::listNecessidades($requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            // Métodos para ProductionController
            case 'createProduction':
                if (isset($requestData['unit_id'])) {
                    $response = ProductionController::createProduction($requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            case 'updateProduction':
                if (isset($requestData['unit_id'])) {
                    if (isset($requestData['id'])) {
                        $response = ProductionController::updateProduction($requestData['id'], $requestData);
                    } else {
                        http_response_code(400);
                        $response = ['error' => 'Parâmetro id ausente'];
                    }
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            case 'getProductionById':
                if (isset($requestData['unit_id'])) {
                    if (isset($requestData['id'])) {
                        $response = ProductionController::getProductionById($requestData['id']);
                    } else {
                        http_response_code(400);
                        $response = ['error' => 'Parâmetro id ausente'];
                    }
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            case 'listProductions':
                if (isset($requestData['unit_id'])) {
                    $response = ProductionController::listProductions($requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            // Métodos para SalesController
            case 'createSale':
                $response = SalesController::createSale($requestData);
                break;
            case 'updateSale':
                if (isset($requestData['id'])) {
                    $response = SalesController::updateSale($requestData['id'], $requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'getSaleById':
                if (isset($requestData['id'])) {
                    $response = SalesController::getSaleById($requestData['id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'listSales':
                if (isset($requestData['unit_id'])) {
                    $response = SalesController::listSales($requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            // Métodos para TransfersController
            case 'createTransfer':
                $response = TransfersController::createTransfer($requestData);
                break;
            case 'updateTransfer':
                if (isset($requestData['id'])) {
                    $response = TransfersController::updateTransfer($requestData['id'], $requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'getTransferById':
                if (isset($requestData['id'])) {
                    $response = TransfersController::getTransferById($requestData['id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'listTransfers':
                if (isset($requestData['unit_id'])) {
                    $response = TransfersController::listTransfers($requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            // Métodos para ProductController
            case 'createProduct':
                $response = ProductController::createProduct($requestData);
                break;
            case 'updateProduct':
                if (isset($requestData['codigo']  )) {
                    $response = ProductController::updateProduct($requestData['codigo'], $requestData, $requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro codigo ausente'];
                }
                break;
            case 'getProductById':
                if (isset($requestData['codigo'])) {
                    $response = ProductController::getProductById($requestData['codigo'], $requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'listProducts':
                if (isset($requestData['unit_id'])) {
                    $response = ProductController::listProducts($requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            case 'listModelosWithProducts':
                if (isset($requestData['unit_id'])) {
                    $response = ModeloBalancoController::listModelosWithProducts($requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            case 'listModelosWithProductsCompras':
                if (isset($requestData['unit_id'])) {
                    $response = ModeloBalancoController::listModelosWithProductsCompras($requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
             case 'toggleModeloStatus':
                   
                    if (isset($requestData['unit_id'])) {
                        $response = ModeloBalancoController::toggleModeloStatus($requestData['unit_id'],$requestData['tag'],$requestData['status']);
                    } else {
                        http_response_code(400);
                        $response = ['error' => 'Parâmetro ausentes'];
                    }
                    break;
            case 'toggleModeloStatusCompras':

                if (isset($requestData['unit_id'])) {
                    $response = ModeloBalancoController::toggleModeloStatusCompras($requestData['unit_id'],$requestData['tag'],$requestData['status']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro ausentes'];
                }
                break;
            case 'getProductCards':
                if (isset ($requestData['system_unit_id'])){
                        $response = ProductController::getProductCards($requestData['system_unit_id']);
                }else{
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro system_unit_id ausente'];
                }
                break;
            case 'importarProdutosPorLoja':
                if ($requestData['system_unit_id'] && $requestData['itens'] && $requestData['usuario_id']) {
                    $response = ProductController::importarProdutosPorLoja(
                        $requestData['system_unit_id'],
                        $requestData['itens'],
                        $requestData['usuario_id']
                    );
                    http_response_code(200);
                }else{
                    http_response_code(400);
                    $response = [
                        'status' => 'error',
                        'message' => 'Missing required fields.'
                    ];
                }
                break;
            case 'listProductsByCategory':
                //print_r($requestData);
                //exit();
                if (isset($requestData['unit_id'])) {
                    $response = ProductController::listProductsByCategory($requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            // Métodos para CategoriesController
            case 'createCategoria':
                if (isset($requestData['unit_id'])) { // Verifica se o unit_id está presente
                    $response = CategoriesController::createCategoria($requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            case 'listInsumos':
                if (isset($requestData['unit_id'])) {
                    $response = ProductController::listInsumos($requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            case 'updateCategoria':
                if (isset($requestData['unit_id'])) { // Verifica se o unit_id está presente
                    if (isset($requestData['id'])) {
                        $response = CategoriesController::updateCategoria($requestData['id'], $requestData[$data], $requestData['unit_id']);
                    } else {
                        http_response_code(400);
                        $response = ['error' => 'Parâmetro id ausente'];
                    }
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            case 'getCategoriaById':
                if (isset($requestData['unit_id'])) { // Verifica se o unit_id está presente
                    if (isset($requestData['id'])) {
                        $response = CategoriesController::getCategoriaById($requestData['id'], $requestData['unit_id']);
                    } else {
                        http_response_code(400);
                        $response = ['error' => 'Parâmetro id ausente'];
                    }
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            case 'listCategorias':
                if (isset($requestData['unit_id'])) { // Verifica se o unit_id está presente
                    $response = CategoriesController::listCategorias($requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            // Métodos para ProductController
            case 'createMovimentacao':
                $response = MovimentacaoController::createMovimentacao($requestData);
                break;
            case 'createMovimentacaoMassa':
                //$response = $requestData;
                $response = MovimentacaoController::createMovimentacaoMassa($requestData);
                break;
            case 'updateMovimentacao':
                if (isset($requestData['id']) && isset($requestData['system_unit_id'])) {
                    $response = MovimentacaoController::updateMovimentacao($requestData['id'], $requestData, $requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ou system_unit_id ausente'];
                }
                break;
            case 'getMovimentacaoById':
                if (isset($requestData['id']) && isset($requestData['system_unit_id'])) {
                    $response = MovimentacaoController::getMovimentacaoById($requestData['id'], $requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ou system_unit_id ausente'];
                }
                break;
            case 'getMovimentacaoByDoc':
                if (isset($requestData['doc']) && isset($requestData['system_unit_id'])) {
                    $response = MovimentacaoController::getMovimentacaoByDoc($requestData['system_unit_id'], $requestData['doc']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro doc ou system_unit_id ausente'];
                }
                break;
            case 'deleteMovimentacao':
                if (isset($requestData['id']) && isset($requestData['system_unit_id'])) {
                    $response = MovimentacaoController::deleteMovimentacao($requestData['id'], $requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ou system_unit_id ausente'];
                }
                break;
            case 'listMovimentacoes':
                if (isset($requestData['system_unit_id'])) {
                    $response = MovimentacaoController::listMovimentacoes($requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro system_unit_id ausente'];
                }
                break;
            case 'getLastMov':
                if (isset($requestData['system_unit_id']) && isset($requestData['tipo'])) {
                    $response = MovimentacaoController::getLastMov($requestData['system_unit_id'], $requestData['tipo']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro system_unit_id ou tipo ausente'];
                }
                break;
            case 'validateTagExists':
                if (isset($requestData['tag'])) {
                    $response = ModeloBalancoController::validateTagExists($requestData['tag']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro tag ausente'];
                }
                break;
            case 'validateTagExistsCompras':
                if (isset($requestData['tag'])) {
                    $response = ModeloBalancoController::validateTagExistsCompras($requestData['tag']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro tag ausente'];
                }
                break;
                // Métodos para UserController
            case 'getUserDetails':
                if (isset($requestData['user'])) {
                    $response = UserController::getUserDetails($requestData['user']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro user ausente'];
                }
                break;
            default:
                http_response_code(405);
                $response = ['error' => 'Método não suportado'];
                break;
        }

        header('Content-Type: application/json');
        echo json_encode($response);
    } catch (Exception $e) {
        http_response_code(500);
        $response = ['error' => 'Erro interno do servidor: ' . $e->getMessage()];
        echo json_encode($response);
    }
} else {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'Parâmetros inválidos']);
}

// Função de verificação do token
function verifyToken($token, $pdop) {
    $stmt = $pdop->prepare("SELECT * FROM system_access_log WHERE sessionid = :sessionid");
    $stmt->bindParam(':sessionid', $token, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        if ($result['logout_time'] == "0000-00-00 00:00:00") {
            if ($result['impersonated'] == 'S') {
                return ['user' => $result['impersonated_by']];
            } else {
                return ['user' => $result['login']];
            }
        } else {
            return ['error' => 'Sessão expirada', 'status_code' => 401];
        }
    } else {
        return ['error' => 'Usuário não encontrado', 'status_code' => 401];
    }
}

?>
