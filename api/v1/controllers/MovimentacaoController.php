<?php
date_default_timezone_set('America/Rio_Branco');
require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php
require_once __DIR__ . '/../database/db-permission.php'; // Ajustando o caminho para o arquivo db.php

class MovimentacaoController {

    // Criação de nova movimentação
    public static function createMovimentacao($data) {
        global $pdo;

        // Campos obrigatórios para a movimentação
        $requiredFields = ['system_unit_id', 'doc', 'tipo', 'produto', 'seq', 'data', 'quantidade', 'usuario_id'];

        // Verifica se todos os campos obrigatórios estão presentes
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return array('success' => false, 'message' => "O campo '$field' é obrigatório.");
            }
        }

        // Extraindo os dados
        $system_unit_id = $data['system_unit_id'];
        $system_unit_id_destino = isset($data['system_unit_id_destino']) ? $data['system_unit_id_destino'] : null;
        $doc = $data['doc'];
        $tipo = $data['tipo'];
        $produto = $data['produto'];
        $seq = $data['seq'];
        $data_movimentacao = $data['data'];
        $valor = isset($data['valor']) ? $data['valor'] : null; // A quantidade que deve ser adicionada ou subtraída
        $quantidade = $data['quantidade'];
        $usuario_id = $data['usuario_id'];

        // Inserção no banco de dados
        $stmt = $pdo->prepare("INSERT INTO movimentacao (system_unit_id, system_unit_id_destino, doc, tipo, produto, seq, data, valor, quantidade, usuario_id) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$system_unit_id, $system_unit_id_destino, $doc, $tipo, $produto, $seq, $data_movimentacao, $valor, $quantidade, $usuario_id]);

        if ($stmt->rowCount() > 0) {
            // Atualiza o saldo do estoque após a movimentação
            $productResponse = ProductController::updateStockBalance($system_unit_id, $produto, $quantidade, $doc);
            if (!$productResponse['success']) {
                return array('success' => false, 'message' => 'Movimentação criada, mas falha ao atualizar saldo: ' . $productResponse['message']);
            }

            return array('success' => true, 'message' => 'Movimentação criada com sucesso', 'movimentacao_id' => $pdo->lastInsertId());
        } else {
            return array('success' => false, 'message' => 'Falha ao criar movimentação');
        }
    }

    // Criação de múltiplas movimentações
    public static function createMovimentacaoMassa($dataArray) {
        $result = array('success' => true, 'messages' => array());

        foreach ($dataArray as $data) {
            $response = self::createMovimentacao($data);
            if (!$response['success']) {
                $result['success'] = false;
                $result['messages'][] = $response['message'];
            } else {
                $result['messages'][] = $response['message'];
            }
        }

        return $result;
    }

    // Atualização de movimentação
    public static function updateMovimentacao($id, $data, $system_unit_id) {
        global $pdo;

        $sql = "UPDATE movimentacao SET ";
        $values = [];
        foreach ($data as $key => $value) {
            $sql .= "$key = :$key, ";
            $values[":$key"] = $value;
        }
        $sql = rtrim($sql, ", ");
        $sql .= " WHERE id = :id AND system_unit_id = :system_unit_id";
        $values[':id'] = $id;
        $values[':system_unit_id'] = $system_unit_id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Movimentação atualizada com sucesso');
        } else {
            return array('error' => 'Falha ao atualizar movimentação');
        }
    }

    // Obter movimentação por ID
    public static function getMovimentacaoById($id, $system_unit_id) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM movimentacao WHERE id = :id AND system_unit_id = :system_unit_id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Obter movimentação por doc
    public static function getMovimentacaoByDoc($system_unit_id, $doc) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM movimentacao WHERE system_unit_id = :system_unit_id AND doc = :doc");
        $stmt->bindParam(':doc', $doc, PDO::PARAM_STR);
        $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Deletar movimentação
    public static function deleteMovimentacao($id, $system_unit_id) {
        global $pdo;

        $stmt = $pdo->prepare("DELETE FROM movimentacao WHERE id = :id AND system_unit_id = :system_unit_id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Movimentação excluída com sucesso');
        } else {
            return array('success' => false, 'message' => 'Falha ao excluir movimentação');
        }
    }

    // Listar movimentações por unidade do sistema
    public static function listMovimentacoes($system_unit_id) {
        try {
            global $pdo;

            $sql = "SELECT * FROM movimentacao WHERE system_unit_id = :system_unit_id ORDER BY created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt->execute();
            $movimentacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return ['success' => true, 'movimentacoes' => $movimentacoes];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar movimentações: ' . $e->getMessage()];
        }
    }

    // Obter última movimentação de determinado tipo
    public static function getLastMov($system_unit_id, $tipo) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM movimentacao WHERE system_unit_id = :system_unit_id AND tipo = :tipo ORDER BY created_at DESC LIMIT 1");
        $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
        $stmt->bindParam(':tipo', $tipo, PDO::PARAM_STR);
        $stmt->execute();
        $movimentacao = $stmt->fetch(PDO::FETCH_ASSOC);

        return $movimentacao ? $movimentacao['doc'] : $tipo . '-000000';
    }
    public static function getLastMovCompras($system_unit_id,$tipo) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM requisicao_compras WHERE system_unit_id = :system_unit_id ORDER BY created_at DESC LIMIT 1");
        $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
        $stmt->execute();
        $movimentacao = $stmt->fetch(PDO::FETCH_ASSOC);

        return $movimentacao ? $movimentacao['doc'] : $tipo . '-000000';
    }

    // Função para incrementar o documento (doc)
    private static function incrementDoc($ultimoDoc, $prefixo) {
        // Supondo que o formato do doc seja algo como "t-000001" ou "b-000001"
        if (preg_match('/^' . $prefixo . '-(\d+)$/', $ultimoDoc, $matches)) {
            $numero = (int)$matches[1] + 1;
            return $prefixo . '-' . str_pad($numero, 6, '0', STR_PAD_LEFT);
        }
        return $prefixo . '-000001';
    }

    // Métodos Específicos para Balanço

    // Listar balanços agrupados por 'doc' com mais informações e filtro de data
public static function listBalance($system_unit_id, $data_inicial = null, $data_final = null) {
    global $pdo;

    try {
        // Validação das datas
        if (!empty($data_inicial) && !empty($data_final) && $data_inicial > $data_final) {
            http_response_code(400); // Código HTTP 400 para Bad Request
            return ['success' => false, 'message' => 'A data inicial não pode ser maior que a data final.'];
        }

        // Constrói a base da consulta
        $query = "
            SELECT 
                m.doc,
                COALESCE(m.tipo_mov, 'Não Informado') AS tipo_mov,
                JSON_ARRAYAGG(
                    JSON_OBJECT(
                        'produto', m.produto, 
                        'quantidade', m.quantidade, 
                        'nome_produto', p.nome, 
                        'categoria_id', c.codigo,
                        'nome_categoria', c.nome
                    )
                ) AS itens, 
                MAX(m.created_at) AS created_at
            FROM movimentacao m
            INNER JOIN products p ON p.codigo = m.produto AND p.system_unit_id = m.system_unit_id
            INNER JOIN categorias c ON c.codigo = p.categoria AND c.system_unit_id = p.system_unit_id
            WHERE m.system_unit_id = :system_unit_id 
            AND m.tipo = 'b'";

        // Adiciona as condições de data, se fornecidas
        if (!empty($data_inicial) && !empty($data_final)) {
            $query .= " AND m.created_at BETWEEN :data_inicial AND :data_final";
        } elseif (!empty($data_inicial)) {
            $query .= " AND m.created_at >= :data_inicial";
        } elseif (!empty($data_final)) {
            $query .= " AND m.created_at <= :data_final";
        }

        $query .= " GROUP BY m.doc ORDER BY MAX(m.created_at) DESC";

        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);

        // Bind das datas se fornecidas
        if (!empty($data_inicial)) {
            $stmt->bindParam(':data_inicial', $data_inicial);
        }
        if (!empty($data_final)) {
            $stmt->bindParam(':data_final', $data_final);
        }

        $stmt->execute();
        $balances = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decodifica os itens de JSON para array/objeto
        foreach ($balances as &$balance) {
            $balance['itens'] = json_decode($balance['itens'], true); // Converte o JSON em array associativo
        }

        return ['success' => true, 'balances' => $balances];
    } catch (Exception $e) {
        http_response_code(500); // Código HTTP 500 para erro interno
        return ['success' => false, 'message' => 'Erro ao listar balanços: ' . $e->getMessage()];
    }
}



    public static function getBalanceByDoc($system_unit_id, $doc) {
        global $pdo;   // Banco de dados principal
        global $pdop;  // Segundo banco de dados (contendo system_users)

        // Validação de parâmetros obrigatórios
        if (!$system_unit_id || !$doc) {
            return ['success' => false, 'message' => 'Parâmetros obrigatórios ausentes.'];
        }

        try {
            // Consulta os detalhes do balanço, incluindo nome do produto e categoria (banco principal)
            $stmt = $pdo->prepare("
            SELECT 
                m.doc,
                coalesce(m.tipo_mov, 'Não Informado') AS tipo_mov,
                p.codigo as produto_codigo,
                p.nome AS produto_nome,
                m.quantidade,
                c.nome AS categoria_nome,
                m.usuario_id,
                m.created_at
            FROM movimentacao m
            LEFT JOIN products p ON m.produto = p.codigo AND m.system_unit_id = p.system_unit_id
            LEFT JOIN categorias c ON p.categoria = c.codigo AND m.system_unit_id = c.system_unit_id
            WHERE m.system_unit_id = :system_unit_id AND m.doc = :doc
        ");
            $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt->bindParam(':doc', $doc, PDO::PARAM_STR);
            $stmt->execute();

            $movimentacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$movimentacoes) {
                return ['success' => false, 'message' => 'Balanço não encontrado.'];
            }

            // Consulta o nome do usuário com base no usuario_id (usando $pdop para acessar o segundo banco)
            $usuario_id = $movimentacoes[0]['usuario_id'];
            $stmtUser = $pdop->prepare("SELECT login AS usuario_nome FROM system_users WHERE id = :usuario_id");
            $stmtUser->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $stmtUser->execute();
            $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);

            // Consulta o nome da unidade
            $stmtUnit = $pdop->prepare("SELECT name FROM system_unit WHERE id = :id LIMIT 1");
            $stmtUnit->bindParam(':id', $system_unit_id, PDO::PARAM_INT);
            $stmtUnit->execute();
            $unit_name = $stmtUnit->fetch(PDO::FETCH_ASSOC);

            $response = [
                'success' => true,
                'balance' => [
                    'unit_name' => $unit_name['name'],
                    'doc' => $movimentacoes[0]['doc'],
                    'tipo_mov' => $movimentacoes[0]['tipo_mov'],
                    'usuario_nome' => $usuario ? $usuario['usuario_nome'] : 'Balanco Externo',
                    'created_at' => $movimentacoes[0]['created_at'],
                    'itens' => []
                ]
            ];

            // Adiciona os itens ao array de itens
            foreach ($movimentacoes as $movimentacao) {
                $response['balance']['itens'][] = [
                    'codigo' => $movimentacao['produto_codigo'],
                    'produto' => $movimentacao['produto_nome'],
                    'quantidade' => $movimentacao['quantidade'],
                    'categoria' => $movimentacao['categoria_nome']
                ];
            }

            return $response;

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao buscar balanço: ' . $e->getMessage()];
        }
    }


    // Criação de movimentação de balanço (tipo 'b')
    public static function saveBalanceItems($data) {
        global $pdo;

        // Campos obrigatórios para a movimentação
        $requiredFields = ['system_unit_id', 'itens'];

        // Verifica se todos os campos obrigatórios estão presentes
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return array('success' => false, 'message' => "O campo '$field' é obrigatório.");
            }
        }

        // Verifica se 'itens' é um array e possui ao menos um item
        if (!is_array($data['itens']) || count($data['itens']) == 0) {
            return array('success' => false, 'message' => "É necessário incluir ao menos um item.");
        }

        // Extraindo os dados
        $system_unit_id = $data['system_unit_id'];
        $system_unit_id_destino = isset($data['system_unit_id_destino']) ? $data['system_unit_id_destino'] : null;
        $itens = $data['itens'];

        // Gera o valor de 'doc' chamando o método getLastMov e incrementa para obter um novo valor
        $ultimoDoc = self::getLastMov($system_unit_id, 'b');
        $doc = self::incrementDoc($ultimoDoc, 'b');

        // Definindo valores fixos
        $tipo = 'b';
        $usuario_id = 5;

        try {
            // Inicia a transação
            $pdo->beginTransaction();

            foreach ($itens as $item) {
                // Verifica se cada item possui os campos obrigatórios
                $itemRequiredFields = ['codigo', 'seq', 'quantidade'];
                foreach ($itemRequiredFields as $field) {
                    if (!isset($item[$field])) {
                        return array('success' => false, 'message' => "O campo '$field' é obrigatório para cada item.");
                    }
                }

                // Extraindo os dados do item
                $produto = $item['codigo'];
                $seq = $item['seq'];
                $quantidade = $item['quantidade'];
                $tipo_mov = $data['tipo_mov'];


                // Inserção no banco de dados
                $stmt = $pdo->prepare("INSERT INTO movimentacao (system_unit_id, system_unit_id_destino, doc, tipo,tipo_mov, produto, seq, data, quantidade, usuario_id) 
                                       VALUES (?, ?, ?, ?, ?,?, ?, NOW(), ?, ?)");
                $stmt->execute([$system_unit_id, $system_unit_id_destino, $doc, $tipo, $tipo_mov, $produto, $seq, $quantidade, $usuario_id]);
            }

            // Commit da transação
            $pdo->commit();
            return array('success' => true, 'message' => 'Movimentação criada com sucesso', 'balanco' => $doc);

        } catch (Exception $e) {
            // Rollback em caso de erro
            $pdo->rollBack();
            return array('success' => false, 'message' => 'Erro ao criar movimentação: ' . $e->getMessage());
        }
    }

    public static function saveComprasItems($data) {
        global $pdo;

        // Validação de campos obrigatórios no corpo principal
        $requiredFields = ['system_unit_id', 'data', 'itens', 'solicitante_nome'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return ['success' => false, 'message' => "O campo '$field' é obrigatório."];
            }
        }

        if (!is_array($data['itens']) || count($data['itens']) === 0) {
            return ['success' => false, 'message' => 'É necessário incluir ao menos um item.'];
        }

        // Extrai dados principais
        $system_unit_id = $data['system_unit_id'];
        $usuario_id = isset($data['usuario_id']) ? $data['usuario_id'] : 0;
        $solicitante_nome = $data['solicitante_nome'];
        $data_solicitacao = $data['data'];
        $itens = $data['itens'];

        // Gera número de documento
        $ultimoDoc = self::getLastMovCompras($system_unit_id, 'rc');
        $doc = self::incrementDoc($ultimoDoc, 'rc');

        try {
            $pdo->beginTransaction();

            // === Insere o cabeçalho da requisição ===
            $stmt = $pdo->prepare("
            INSERT INTO requisicao_compras (
                status, system_unit_id, doc, data, usuario_id, solicitante_nome, created_at, updated_at
            ) VALUES (
                1, ?, ?, ?, ?, ?, NOW(), NOW()
            )
        ");
            $stmt->execute([
                $system_unit_id,
                $doc,
                $data_solicitacao,
                $usuario_id,
                $solicitante_nome
            ]);
            $requisicao_id = $pdo->lastInsertId();

            // === Insere os itens da requisição ===
            foreach ($itens as $item) {
                $itemRequired = ['id_produto', 'produto', 'seq', 'quantidade'];
                foreach ($itemRequired as $field) {
                    if (!isset($item[$field]) || $item[$field] === '') {
                        throw new Exception("O campo '$field' é obrigatório para cada item.");
                    }
                }

                $stmt = $pdo->prepare("
                INSERT INTO requisicao_compras_itens (
                   system_unit_id,requisicao_id, id_produto, produto, seq, preco, quantidade, quantidade_comprada, created_at, updated_at
                ) VALUES (
                    ?,?, ?, ?, ?, ?, ?, NULL, NOW(), NOW()
                )
            ");
                $stmt->execute([
                    $system_unit_id,
                    $requisicao_id,
                    $item['id_produto'],
                    $item['produto'],
                    $item['seq'],
                    isset($item['preco']) ? $item['preco'] : null,
                    $item['quantidade']
                ]);
            }

            // === Cria log da solicitação ===
            $stmt = $pdo->prepare("
            INSERT INTO requisicao_compras_log (
                system_unit_id,requisicao_id, status, observacao, usuario_id, created_at
            ) VALUES (
                ?,?, 1, 'Requisição criada pela loja.', ?, NOW()
            )
        ");
            $stmt->execute([
                $system_unit_id,
                $requisicao_id,
                $usuario_id
            ]);

            $pdo->commit();

            return [
                'success' => true,
                'message' => 'Requisição de compras criada com sucesso.',
                'doc' => $doc,
                'requisicao_id' => $requisicao_id
            ];

        } catch (Exception $e) {
            $pdo->rollBack();
            return [
                'success' => false,
                'message' => 'Erro ao salvar requisição: ' . $e->getMessage()
            ];
        }
    }

    // Métodos Específicos para Transferências

    // Criação de transferência (tipo 't')
    public static function createTransferItems($data) {
        global $pdo;

        // Verifica se todos os campos obrigatórios estão presentes
        $requiredFields = ['system_unit_id', 'system_unit_id_destino', 'itens', 'usuario_id'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return array('success' => false, 'message' => "O campo '$field' é obrigatório.");
            }
        }

        // Verifica se 'itens' é um array e possui ao menos um item
        if (!is_array($data['itens']) || count($data['itens']) == 0) {
            return array('success' => false, 'message' => "É necessário incluir ao menos um item.");
        }

        // Extraindo os dados
        $system_unit_id = $data['system_unit_id'];
        $system_unit_id_destino = $data['system_unit_id_destino'];
        $itens = $data['itens'];
        $usuario_id = $data['usuario_id'];

        // Gera o valor de 'doc' chamando o método getLastMov e incrementa para obter um novo valor
        $ultimoDoc = self::getLastMov($system_unit_id, 't');
        $doc = self::incrementDoc($ultimoDoc, 't');

        // Definindo valores fixos
        $tipo_saida = 't';
        $tipo_entrada = 'entrega';

        try {
            // Inicia a transação
            $pdo->beginTransaction();

            // Criação dos movimentos de saída
            foreach ($itens as $item) {
                // Verifica se cada item possui os campos obrigatórios
                $itemRequiredFields = ['codigo', 'seq', 'quantidade'];
                foreach ($itemRequiredFields as $field) {
                    if (!isset($item[$field])) {
                        return array('success' => false, 'message' => "O campo '$field' é obrigatório para cada item.");
                    }
                }

                // Extraindo os dados do item
                $produto = $item['codigo'];
                $seq = $item['seq'];
                $quantidade = $item['quantidade'];

                // Inserção no banco de dados para o movimento de saída
                $stmt = $pdo->prepare("INSERT INTO movimentacao (system_unit_id, system_unit_id_destino, doc, tipo, produto, seq, data, quantidade, usuario_id) 
                                   VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
                $stmt->execute([$system_unit_id, $system_unit_id_destino, $doc, $tipo_saida, $produto, $seq, $quantidade, $usuario_id]);
            }

            // Criação dos movimentos de entrada
            foreach ($itens as $item) {
                // Extraindo os dados do item
                $produto = $item['codigo'];
                $seq = $item['seq'];
                $quantidade = $item['quantidade'];

                // Busca o saldo atual do produto no estoque
                $stockData = NecessidadesController::getProductStock($system_unit_id_destino, $produto);
                $saldoAtual = $stockData['saldo'] ?? 0;

                // Calcula o novo saldo, somando o valor transferido ao saldo atual
                $novoSaldo = $saldoAtual + $quantidade;

                // Inserção no banco de dados para o movimento de entrada
                $stmt = $pdo->prepare("INSERT INTO movimentacao (system_unit_id, doc, tipo, produto, seq, data, quantidade, usuario_id) 
                                   VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)");
                $stmt->execute([$system_unit_id_destino, $doc, $tipo_entrada, $produto, $seq, $quantidade, $usuario_id]);

                if ($stmt->rowCount() > 0) {
                    // Atualiza o saldo do estoque após a movimentação de entrada com o novo saldo calculado
                    $productResponse = ProductController::updateStockBalance($system_unit_id_destino, $produto, $novoSaldo, $doc);
                    if (!$productResponse['success']) {
                        // Se a atualização do saldo falhar, faz rollback e retorna o erro
                        $pdo->rollBack();
                        return array('success' => false, 'message' => 'Movimentação de entrada criada, mas falha ao atualizar saldo: ' . $productResponse['message']);
                    }
                } else {
                    // Se a inserção do item falhar, faz rollback e retorna o erro
                    $pdo->rollBack();
                    return array('success' => false, 'message' => 'Falha ao criar movimentação de entrada para o item com código ' . $produto);
                }
            }

            // Commit da transação
            $pdo->commit();
            return array('success' => true, 'message' => 'Transferência criada com sucesso', 'transfer_doc' => $doc);

        } catch (Exception $e) {
            // Rollback em caso de erro
            $pdo->rollBack();
            return array('success' => false, 'message' => 'Erro ao criar transferência: ' . $e->getMessage());
        }
    }



    // Listar transferências
    public static function listTransfers($system_unit_id) {
        global $pdo;

        try {
            $stmt = $pdo->prepare("SELECT * FROM movimentacao WHERE system_unit_id = :system_unit_id AND tipo = 't' ORDER BY created_at DESC");
            $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt->execute();
            $transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return ['success' => true, 'transfers' => $transfers];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar transferências: ' . $e->getMessage()];
        }
    }

    // Obter a última transferência
    public static function getLastTransfer($system_unit_id) {
        return self::getLastMov($system_unit_id, 't');
    }

    // Obter transferência por doc
    public static function getTransferByDoc($system_unit_id, $doc) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM movimentacao WHERE system_unit_id = :system_unit_id AND doc = :doc AND tipo = 't'");
        $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
        $stmt->bindParam(':doc', $doc, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
