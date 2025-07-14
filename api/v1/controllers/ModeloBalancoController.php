<?php
date_default_timezone_set('America/Rio_Branco');

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php
require_once __DIR__ . '/../database/db-permission.php'; // Ajustando o caminho para o arquivo db.php

class ModeloBalancoController
{

    private static function sendResponse($success, $message, $data = [], $httpCode = 200)
    {
        http_response_code($httpCode);
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
        exit;
    }

    // Cria um novo modelo de balanço junto com seus itens
    public static function createModelo($data)
    {
        global $pdo;

        try {
            // Inicia a transação
            $pdo->beginTransaction();

            // Verificar campos obrigatórios
            $requiredFields = ['nome', 'usuario_id', 'system_unit_id', 'itens'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    self::sendResponse(false, "O campo '$field' é obrigatório.", [], 400); // 400 Bad Request
                }
            }

            // Extrair dados
            $nome = $data['nome'];
            $usuario_id = $data['usuario_id'];
            $system_unit_id = $data['system_unit_id'];
            $ativo = isset($data['ativo']) ? $data['ativo'] : 1; // Padrão: ativo
            $tag = isset($data['tag']) ? $data['tag'] : null;
            $itens = $data['itens']; // Array contendo códigos dos produtos

            // Validar se existem itens
            if (!is_array($itens) || count($itens) == 0) {
                self::sendResponse(false, "É necessário incluir ao menos um item (produto) no modelo.", [], 400);
            }

            // Inserir o modelo de balanço
            $stmt = $pdo->prepare("INSERT INTO modelos_balanco (nome, usuario_id, system_unit_id, ativo, tag, created_at, updated_at) 
                               VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$nome, $usuario_id, $system_unit_id, $ativo, $tag]);

            if ($stmt->rowCount() <= 0) {
                self::sendResponse(false, "Falha ao criar o modelo de balanço.", [], 500); // 500 Internal Server Error
            }

            // Obter o ID do novo modelo
            $modelo_id = $pdo->lastInsertId();

            // Inserir os itens associados ao modelo
            $stmtItem = $pdo->prepare("INSERT INTO modelos_balanco_itens (id_modelo, system_unit_id, id_produto, created_at, updated_at) 
                           VALUES (?, ?, ?, NOW(), NOW())");

            foreach ($itens as $item) {
                if (!isset($item['codigo'])) {
                    self::sendResponse(false, "O campo 'codigo' é obrigatório para cada item.", [], 400);
                }

                $codigo_produto = $item['codigo'];

                $stmtItem->execute([$modelo_id, $system_unit_id, $codigo_produto]);

                if ($stmtItem->rowCount() <= 0) {
                    self::sendResponse(false, "Falha ao inserir item no modelo de balanço.", [], 500);
                }
            }

            // Commit da transação
            $pdo->commit();

            self::sendResponse(true, 'Modelo de balanço criado com sucesso.', ['modelo_id' => $modelo_id], 201); // 201 Created

        } catch (Exception $e) {
            // Rollback em caso de erro
            $pdo->rollBack();

            self::sendResponse(false, 'Erro ao criar modelo de balanço: ' . $e->getMessage(), [], 500);
        }
    }

    public static function editModelo($data)
    {
        global $pdo;

        try {
            // Inicia a transação
            $pdo->beginTransaction();

            // Verificar campos obrigatórios
            $requiredFields = ['nome', 'usuario_id', 'system_unit_id', 'itens'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    self::sendResponse(false, "O campo '$field' é obrigatório.", [], 400);
                }
            }

            // Extrair dados
            $nome = $data['nome'];
            $usuario_id = $data['usuario_id'];
            $system_unit_id = $data['system_unit_id'];
            $tag = isset($data['tag']) ? $data['tag'] : null;
            $itens = $data['itens'];

            // Validar ID do modelo (por tag ou outro campo, adapte se precisar)
            $stmt = $pdo->prepare("SELECT id FROM modelos_balanco WHERE tag = ? AND system_unit_id = ?");
            $stmt->execute([$tag, $system_unit_id]);
            $modelo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$modelo) {
                self::sendResponse(false, "Modelo de balanço não encontrado.", [], 404);
            }

            $modelo_id = $modelo['id'];

            // Atualizar o modelo
            $stmtUpdate = $pdo->prepare("UPDATE modelos_balanco SET nome = ?, usuario_id = ?, tag = ?, updated_at = NOW() WHERE id = ?");
            $stmtUpdate->execute([$nome, $usuario_id, $tag, $modelo_id]);

            if ($stmtUpdate->rowCount() < 0) {
                self::sendResponse(false, "Falha ao atualizar o modelo de balanço.", [], 500);
            }

            // Apagar todos os itens antigos do modelo
            $stmtDelete = $pdo->prepare("DELETE FROM modelos_balanco_itens WHERE id_modelo = ?");
            $stmtDelete->execute([$modelo_id]);

            // Inserir os novos itens
            $stmtItem = $pdo->prepare("
            INSERT INTO modelos_balanco_itens (id_modelo, system_unit_id, id_produto, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW())
        ");

            foreach ($itens as $item) {
                if (!isset($item['codigo'])) {
                    self::sendResponse(false, "O campo 'codigo' é obrigatório para cada item.", [], 400);
                }
                $codigo_produto = $item['codigo'];

                $stmtItem->execute([$modelo_id, $system_unit_id, $codigo_produto]);

                if ($stmtItem->rowCount() <= 0) {
                    self::sendResponse(false, "Falha ao inserir item no modelo de balanço.", [], 500);
                }
            }

            // Finaliza a transação
            $pdo->commit();

            self::sendResponse(true, 'Modelo de balanço editado com sucesso.', ['modelo_id' => $modelo_id], 200);
        } catch (Exception $e) {
            $pdo->rollBack();
            self::sendResponse(false, 'Erro ao editar modelo de balanço: ' . $e->getMessage(), [], 500);
        }
    }

    // Listar todos os modelos de balanço
    public static function listModelos($ativo = null)
    {
        global $pdo;

        try {
            $sql = "SELECT * FROM modelos_balanco";
            if ($ativo !== null) {
                $sql .= " WHERE ativo = :ativo";
            }

            $stmt = $pdo->prepare($sql);
            if ($ativo !== null) {
                $stmt->bindParam(':ativo', $ativo, PDO::PARAM_INT);
            }
            $stmt->execute();
            $modelos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            self::sendResponse(true, 'Modelos listados com sucesso.', ['modelos' => $modelos], 200); // 200 OK

        } catch (Exception $e) {
            self::sendResponse(false, 'Erro ao listar modelos: ' . $e->getMessage(), [], 500);
        }
    }

    // Listar itens de um modelo de balanço
    public static function listItensByModelo($modelo_id, $system_unit_id)
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("
                SELECT mbi.*, p.nome AS nome_produto
                FROM modelos_balanco_itens mbi
                LEFT JOIN products p ON mbi.id_produto = p.id and mbi.system_unit_id = p.system_unit_id
                WHERE mbi.id_modelo = :modelo_id
                AND mbi.system_unit_id = :system_unit_id
            ");
            $stmt->bindParam(':modelo_id', $modelo_id, PDO::PARAM_INT);
            $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt->execute();
            $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($itens) {
                self::sendResponse(true, 'Itens listados com sucesso.', ['itens' => $itens], 200);
            } else {
                self::sendResponse(false, 'Nenhum item encontrado para este modelo.', [], 404); // 404 Not Found
            }

        } catch (Exception $e) {
            self::sendResponse(false, 'Erro ao listar itens: ' . $e->getMessage(), [], 500);
        }
    }

    // Atualizar um modelo de balanço
    public static function updateModelo($id, $data)
    {
        global $pdo;

        try {
            // Verificar se o modelo existe
            $stmt = $pdo->prepare("SELECT * FROM modelos_balanco WHERE id = ?");
            $stmt->execute([$id]);
            $modelo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$modelo) {
                self::sendResponse(false, 'Modelo de balanço não encontrado.', [], 404); // 404 Not Found
            }

            // Atualizar o modelo
            $sql = "UPDATE modelos_balanco SET ";
            $values = [];
            foreach ($data as $key => $value) {
                $sql .= "$key = :$key, ";
                $values[":$key"] = $value;
            }
            $sql = rtrim($sql, ", ");
            $sql .= " WHERE id = :id";
            $values[':id'] = $id;

            $stmtUpdate = $pdo->prepare($sql);
            $stmtUpdate->execute($values);

            if ($stmtUpdate->rowCount() > 0) {
                self::sendResponse(true, 'Modelo de balanço atualizado com sucesso.', [], 200);
            } else {
                self::sendResponse(false, 'Nenhuma alteração foi realizada.', [], 304); // 304 Not Modified
            }

        } catch (Exception $e) {
            self::sendResponse(false, 'Erro ao atualizar modelo: ' . $e->getMessage(), [], 500);
        }
    }

    // Deletar modelo de balanço
    public static function deleteModelo($id)
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("DELETE FROM modelos_balanco WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() > 0) {
                self::sendResponse(true, 'Modelo de balanço excluído com sucesso.', [], 200);
            } else {
                self::sendResponse(false, 'Falha ao excluir o modelo de balanço.', [], 404);
            }

        } catch (Exception $e) {
            self::sendResponse(false, 'Erro ao excluir modelo: ' . $e->getMessage(), [], 500);
        }
    }

    // Deletar item de um modelo de balanço
    public static function deleteItemFromModelo($modelo_id, $produto_id)
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("DELETE FROM modelos_balanco_itens WHERE id_modelo = ? AND id_produto = ?");
            $stmt->execute([$modelo_id, $produto_id]);

            if ($stmt->rowCount() > 0) {
                self::sendResponse(true, 'Item removido do modelo de balanço com sucesso.', [], 200);
            } else {
                self::sendResponse(false, 'Falha ao remover o item do modelo de balanço.', [], 404);
            }

        } catch (Exception $e) {
            self::sendResponse(false, 'Erro ao remover item: ' . $e->getMessage(), [], 500);
        }
    }

    public static function validateTagExists($tag)
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM modelos_balanco WHERE tag = :tag");
            $stmt->bindParam(':tag', $tag, PDO::PARAM_STR);
            $stmt->execute();

            $count = $stmt->fetchColumn();

            if ($count > 0) {
                // Retorna true indicando que a tag já existe
                self::sendResponse(false, "A tag já existe.", [], 200);
            } else {
                // Retorna false indicando que a tag não existe
                self::sendResponse(true, "A tag não existe.", [], 200);
            }

        } catch (Exception $e) {
            self::sendResponse(false, 'Erro ao validar a tag: ' . $e->getMessage(), [], 500);
        }
    }

    public static function getModelByTag($tag)
    {
        global $pdo;
        global $pdop;

        try {
            // Extrair system_unit_id e tag do valor recebido
            if (strpos($tag, '-') === false) {
                self::sendResponse(false, "Formato de tag inválido.", [], 400);
                return;
            }

            list($system_unit_id, $actual_tag) = explode('-', $tag, 2);

            // Buscar o modelo pelo system_unit_id e tag no banco `$pdo`
            $stmt = $pdo->prepare("
            SELECT *
            FROM modelos_balanco
            WHERE system_unit_id = :system_unit_id AND tag = :tag
        ");
            $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt->bindParam(':tag', $tag, PDO::PARAM_STR);
            $stmt->execute();

            $modelo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$modelo) {
                self::sendResponse(false, 'Modelo de balanço não encontrado.', [], 404);
                return;
            }

            // Buscar o nome da unidade do banco `$pdop`
            $stmtUnit = $pdop->prepare("
            SELECT name AS system_unit_name
            FROM system_unit
            WHERE id = :system_unit_id
        ");
            $stmtUnit->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmtUnit->execute();

            $systemUnit = $stmtUnit->fetch(PDO::FETCH_ASSOC);
            $modelo['system_unit_name'] = $systemUnit ? $systemUnit['system_unit_name'] : null;

            // Buscar itens associados ao modelo e agrupá-los por categoria no banco `$pdo`
            $stmtItens = $pdo->prepare("
            SELECT mbi.*, p.nome AS nome_produto, p.und AS und_produto, p.codigo AS codigo_produto, c.nome AS nome_categoria
            FROM modelos_balanco_itens mbi
            LEFT JOIN products p ON mbi.id_produto = p.codigo AND mbi.system_unit_id = p.system_unit_id
            LEFT JOIN categorias c ON p.categoria = c.codigo AND p.system_unit_id = c.system_unit_id
            WHERE mbi.id_modelo = :modelo_id AND mbi.system_unit_id = :system_unit_id
        ");
            $stmtItens->bindParam(':modelo_id', $modelo['id'], PDO::PARAM_INT);
            $stmtItens->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmtItens->execute();

            $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

            // Agrupar itens por categoria
            $itensPorCategoria = [];
            foreach ($itens as $item) {
                $categoria = $item['nome_categoria'] ?: 'Sem Categoria';
                if (!isset($itensPorCategoria[$categoria])) {
                    $itensPorCategoria[$categoria] = [];
                }
                $itensPorCategoria[$categoria][] = $item;
            }

            // Retornar as informações do modelo e seus itens agrupados por categoria
            self::sendResponse(true, 'Modelo de balanço encontrado.', [
                'modelo' => [
                    'id' => $modelo['id'],
                    'tag' => $modelo['tag'],
                    'nome' => $modelo['nome'],
                    'system_unit_id' => $modelo['system_unit_id'],
                    'system_unit_name' => $modelo['system_unit_name'],
                    // Adicione outros campos relevantes do modelo, se necessário
                ],
                'itens' => $itensPorCategoria
            ], 200);

        } catch (Exception $e) {
            self::sendResponse(false, 'Erro ao buscar modelo por tag: ' . $e->getMessage(), [], 500);
        }
    }

    public static function listModelosWithProducts($system_unit_id)
    {
        global $pdo;
        global $pdop;

        try {
            // Buscar todos os modelos de balanço pelo system_unit_id
            $sqlModelos = "
            SELECT mb.*
            FROM modelos_balanco mb
            WHERE mb.system_unit_id = :system_unit_id
        ";

            $stmtModelos = $pdo->prepare($sqlModelos);
            $stmtModelos->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmtModelos->execute();
            $modelos = $stmtModelos->fetchAll(PDO::FETCH_ASSOC);

            if (!$modelos) {
                self::sendResponse(false, 'Nenhum modelo encontrado.', [], 404); // 404 Not Found
                return;
            }

            $modelosComProdutos = [];
            foreach ($modelos as $modelo) {
                $modelo_id = $modelo['id'];

                // Obter o login do usuário do banco `pdop`
                $stmtUsuario = $pdop->prepare("
                SELECT login AS usuario_login
                FROM system_users
                WHERE id = :usuario_id
            ");
                $stmtUsuario->bindParam(':usuario_id', $modelo['usuario_id'], PDO::PARAM_INT);
                $stmtUsuario->execute();
                $usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

                // Adicionar login do usuário ao modelo
                $modelo['usuario_login'] = $usuario ? $usuario['usuario_login'] : null;

                // Buscar os produtos associados ao modelo no banco `$pdo`
                $stmtItens = $pdo->prepare("
                SELECT mbi.*, p.nome AS nome_produto, p.und AS und_produto, p.codigo AS codigo_produto
                FROM modelos_balanco_itens mbi
                LEFT JOIN products p ON mbi.id_produto = p.id AND mbi.system_unit_id = p.system_unit_id
                WHERE mbi.id_modelo = :modelo_id AND mbi.system_unit_id = :system_unit_id
            ");
                $stmtItens->bindParam(':modelo_id', $modelo_id, PDO::PARAM_INT);
                $stmtItens->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
                $stmtItens->execute();
                $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

                // Adicionar os itens ao modelo atual
                $modelo['itens'] = $itens;
                $modelosComProdutos[] = $modelo;
            }

            // Retornar os modelos e seus produtos
            self::sendResponse(true, 'Modelos com produtos listados com sucesso.', ['modelos' => $modelosComProdutos], 200); // 200 OK

        } catch (Exception $e) {
            self::sendResponse(false, 'Erro ao listar modelos com produtos: ' . $e->getMessage(), [], 500);
        }
    }

    public static function toggleModeloStatus($system_unit_id, $tag, $status)
    {
        global $pdo;

        try {
            // Validar o status recebido (deve ser 0 ou 1)
            if (!in_array($status, [0, 1])) {
                self::sendResponse(false, "O valor de status deve ser 0 (inativo) ou 1 (ativo).", [], 400); // 400 Bad Request
            }

            // Atualizar o status do modelo de balanço com base no system_unit_id e tag
            $stmt = $pdo->prepare("
                UPDATE modelos_balanco
                SET ativo = :status
                WHERE system_unit_id = :system_unit_id AND tag = :tag
            ");
            $stmt->bindParam(':status', $status, PDO::PARAM_INT);
            $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt->bindParam(':tag', $tag, PDO::PARAM_STR);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                self::sendResponse(true, 'success', [], 200); // 200 OK
            } else {
                self::sendResponse(false, 'Nenhum modelo encontrado com a tag e system_unit_id fornecidos.', [], 404); // 404 Not Found
            }

        } catch (Exception $e) {
            self::sendResponse(false, 'Erro ao atualizar status do modelo: ' . $e->getMessage(), [], 500);
        }
    }

    //==========================Compras============================================

    public static function createModeloCompras($data)
    {
        global $pdo;

        try {
            // Inicia a transação
            $pdo->beginTransaction();

            // Verificar campos obrigatórios
            $requiredFields = ['nome', 'usuario_id', 'system_unit_id', 'itens'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    self::sendResponse(false, "O campo '$field' é obrigatório.", [], 400); // 400 Bad Request
                }
            }

            // Extrair dados
            $nome = $data['nome'];
            $usuario_id = $data['usuario_id'];
            $system_unit_id = $data['system_unit_id'];
            $ativo = isset($data['ativo']) ? $data['ativo'] : 1; // Padrão: ativo
            $tag = isset($data['tag']) ? $data['tag'] : null;
            $itens = $data['itens']; // Array contendo códigos dos produtos

            // Validar se existem itens
            if (!is_array($itens) || count($itens) == 0) {
                self::sendResponse(false, "É necessário incluir ao menos um item (produto) no modelo.", [], 400);
            }

            // Inserir o modelo de balanço
            $stmt = $pdo->prepare("INSERT INTO modelos_compras (nome, usuario_id, system_unit_id, ativo, tag, created_at, updated_at) 
                               VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$nome, $usuario_id, $system_unit_id, $ativo, $tag]);

            if ($stmt->rowCount() <= 0) {
                self::sendResponse(false, "Falha ao criar o modelo de balanço.", [], 500); // 500 Internal Server Error
            }

            // Obter o ID do novo modelo
            $modelo_id = $pdo->lastInsertId();

            // Inserir os itens associados ao modelo
            $stmtItem = $pdo->prepare("INSERT INTO modelos_compras_itens (id_modelo, system_unit_id, id_produto, created_at, updated_at) 
                                   SELECT ?, ?, id, NOW(), NOW() FROM products WHERE codigo = ? AND system_unit_id = ?");
            foreach ($itens as $item) {
                if (!isset($item['codigo'])) {
                    self::sendResponse(false, "O campo 'codigo' é obrigatório para cada item.", [], 400);
                }
                $codigo_produto = $item['codigo'];
                $stmtItem->execute([$modelo_id, $system_unit_id, $codigo_produto, $system_unit_id]);

                if ($stmtItem->rowCount() <= 0) {
                    self::sendResponse(false, "Falha ao inserir item no modelo de balanço.", [], 500);
                }
            }

            // Commit da transação
            $pdo->commit();

            self::sendResponse(true, 'Modelo de balanço criado com sucesso.', ['modelo_id' => $modelo_id], 201); // 201 Created

        } catch (Exception $e) {
            // Rollback em caso de erro
            $pdo->rollBack();

            self::sendResponse(false, 'Erro ao criar modelo de balanço: ' . $e->getMessage(), [], 500);
        }
    }

    public static function toggleModeloStatusCompras($system_unit_id, $tag, $status)
    {
        global $pdo;

        try {
            // Validar o status recebido (deve ser 0 ou 1)
            if (!in_array($status, [0, 1])) {
                self::sendResponse(false, "O valor de status deve ser 0 (inativo) ou 1 (ativo).", [], 400); // 400 Bad Request
            }

            // Atualizar o status do modelo de balanço com base no system_unit_id e tag
            $stmt = $pdo->prepare("
                UPDATE modelos_balanco
                SET ativo = :status
                WHERE system_unit_id = :system_unit_id AND tag = :tag
            ");
            $stmt->bindParam(':status', $status, PDO::PARAM_INT);
            $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt->bindParam(':tag', $tag, PDO::PARAM_STR);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                self::sendResponse(true, 'success', [], 200); // 200 OK
            } else {
                self::sendResponse(false, 'Nenhum modelo encontrado com a tag e system_unit_id fornecidos.', [], 404); // 404 Not Found
            }

        } catch (Exception $e) {
            self::sendResponse(false, 'Erro ao atualizar status do modelo: ' . $e->getMessage(), [], 500);
        }
    }


    public static function listItensByModeloCompras($modelo_id, $system_unit_id)
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("
                SELECT mbi.*, p.nome AS nome_produto
                FROM modelos_balanco_itens mbi
                LEFT JOIN products p ON mbi.id_produto = p.id and mbi.system_unit_id = p.system_unit_id
                WHERE mbi.id_modelo = :modelo_id
                AND mbi.system_unit_id = :system_unit_id
            ");
            $stmt->bindParam(':modelo_id', $modelo_id, PDO::PARAM_INT);
            $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt->execute();
            $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($itens) {
                self::sendResponse(true, 'Itens listados com sucesso.', ['itens' => $itens], 200);
            } else {
                self::sendResponse(false, 'Nenhum item encontrado para este modelo.', [], 404); // 404 Not Found
            }

        } catch (Exception $e) {
            self::sendResponse(false, 'Erro ao listar itens: ' . $e->getMessage(), [], 500);
        }
    }

    public static function validateTagExistsCompras($tag)
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM modelos_compras WHERE tag = :tag");
            $stmt->bindParam(':tag', $tag, PDO::PARAM_STR);
            $stmt->execute();

            $count = $stmt->fetchColumn();

            if ($count > 0) {
                // Retorna true indicando que a tag já existe
                self::sendResponse(false, "A tag já existe.", [], 200);
            } else {
                // Retorna false indicando que a tag não existe
                self::sendResponse(true, "A tag não existe.", [], 200);
            }

        } catch (Exception $e) {
            self::sendResponse(false, 'Erro ao validar a tag: ' . $e->getMessage(), [], 500);
        }
    }

    public static function getModelByTagCompras($tag)
    {
        global $pdo;
        global $pdop;

        try {
            // Extrair system_unit_id e tag do valor recebido
            if (strpos($tag, '-') === false) {
                self::sendResponse(false, "Formato de tag inválido.", [], 400);
                return;
            }

            list($system_unit_id, $actual_tag) = explode('-', $tag, 2);

            // Buscar o modelo pelo system_unit_id e tag no banco `$pdo`
            $stmt = $pdo->prepare("
            SELECT *
            FROM modelos_compras
            WHERE system_unit_id = :system_unit_id AND tag = :tag
        ");
            $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt->bindParam(':tag', $tag, PDO::PARAM_STR);
            $stmt->execute();

            $modelo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$modelo) {
                self::sendResponse(false, 'Modelo de balanço não encontrado.', [], 404);
                return;
            }

            // Buscar o nome da unidade do banco `$pdop`
            $stmtUnit = $pdop->prepare("
            SELECT name AS system_unit_name
            FROM system_unit
            WHERE id = :system_unit_id
        ");
            $stmtUnit->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmtUnit->execute();

            $systemUnit = $stmtUnit->fetch(PDO::FETCH_ASSOC);
            $modelo['system_unit_name'] = $systemUnit ? $systemUnit['system_unit_name'] : null;

            // Buscar itens associados ao modelo e agrupá-los por categoria no banco `$pdo`
            $stmtItens = $pdo->prepare("
            SELECT mbi.*, p.nome AS nome_produto, p.und AS und_produto, p.codigo AS codigo_produto, c.nome AS nome_categoria, p.preco_custo as preco_custo
            FROM modelos_compras_itens mbi
            LEFT JOIN products p ON mbi.id_produto = p.id AND mbi.system_unit_id = p.system_unit_id
            LEFT JOIN categorias c ON p.categoria = c.codigo AND p.system_unit_id = c.system_unit_id
            WHERE mbi.id_modelo = :modelo_id AND mbi.system_unit_id = :system_unit_id
        ");
            $stmtItens->bindParam(':modelo_id', $modelo['id'], PDO::PARAM_INT);
            $stmtItens->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmtItens->execute();

            $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

            // Agrupar itens por categoria
            $itensPorCategoria = [];
            foreach ($itens as $item) {
                $categoria = $item['nome_categoria'] ?: 'Sem Categoria';
                if (!isset($itensPorCategoria[$categoria])) {
                    $itensPorCategoria[$categoria] = [];
                }
                $itensPorCategoria[$categoria][] = $item;
            }

            // Retornar as informações do modelo e seus itens agrupados por categoria
            self::sendResponse(true, 'Modelo de balanço encontrado.', [
                'modelo' => [
                    'id' => $modelo['id'],
                    'tag' => $modelo['tag'],
                    'nome' => $modelo['nome'],
                    'system_unit_id' => $modelo['system_unit_id'],
                    'system_unit_name' => $modelo['system_unit_name'],
                    // Adicione outros campos relevantes do modelo, se necessário
                ],
                'itens' => $itensPorCategoria
            ], 200);

        } catch (Exception $e) {
            self::sendResponse(false, 'Erro ao buscar modelo por tag: ' . $e->getMessage(), [], 500);
        }
    }

    public static function listModelosWithProductsCompras($system_unit_id)
    {
        global $pdo;
        global $pdop;

        try {
            // Buscar todos os modelos de balanço pelo system_unit_id
            $sqlModelos = "
            SELECT mb.*
            FROM modelos_compras mb
            WHERE mb.system_unit_id = :system_unit_id
        ";

            $stmtModelos = $pdo->prepare($sqlModelos);
            $stmtModelos->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmtModelos->execute();
            $modelos = $stmtModelos->fetchAll(PDO::FETCH_ASSOC);

            if (!$modelos) {
                self::sendResponse(false, 'Nenhum modelo encontrado.', [], 404); // 404 Not Found
                return;
            }

            $modelosComProdutos = [];
            foreach ($modelos as $modelo) {
                $modelo_id = $modelo['id'];

                // Obter o login do usuário do banco `pdop`
                $stmtUsuario = $pdop->prepare("
                SELECT login AS usuario_login
                FROM system_users
                WHERE id = :usuario_id
            ");
                $stmtUsuario->bindParam(':usuario_id', $modelo['usuario_id'], PDO::PARAM_INT);
                $stmtUsuario->execute();
                $usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

                // Adicionar login do usuário ao modelo
                $modelo['usuario_login'] = $usuario ? $usuario['usuario_login'] : null;

                // Buscar os produtos associados ao modelo no banco `$pdo`
                $stmtItens = $pdo->prepare("
                SELECT mbi.*, p.nome AS nome_produto, p.und AS und_produto, p.codigo AS codigo_produto
                FROM modelos_compras_itens mbi
                LEFT JOIN products p ON mbi.id_produto = p.id AND mbi.system_unit_id = p.system_unit_id
                WHERE mbi.id_modelo = :modelo_id AND mbi.system_unit_id = :system_unit_id
            ");
                $stmtItens->bindParam(':modelo_id', $modelo_id, PDO::PARAM_INT);
                $stmtItens->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
                $stmtItens->execute();
                $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

                // Adicionar os itens ao modelo atual
                $modelo['itens'] = $itens;
                $modelosComProdutos[] = $modelo;
            }

            // Retornar os modelos e seus produtos
            self::sendResponse(true, 'Modelos com produtos listados com sucesso.', ['modelos' => $modelosComProdutos], 200); // 200 OK

        } catch (Exception $e) {
            self::sendResponse(false, 'Erro ao listar modelos com produtos: ' . $e->getMessage(), [], 500);
        }
    }

    public static function editModeloCompras($data)
    {
        global $pdo;

        try {
            // Inicia a transação
            $pdo->beginTransaction();

            // Verificar campos obrigatórios
            $requiredFields = ['nome', 'usuario_id', 'system_unit_id', 'itens'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    self::sendResponse(false, "O campo '$field' é obrigatório.", [], 400);
                }
            }

            // Extrair dados
            $nome = $data['nome'];
            $usuario_id = $data['usuario_id'];
            $system_unit_id = $data['system_unit_id'];
            $tag = isset($data['tag']) ? $data['tag'] : null;
            $itens = $data['itens'];

            // Validar ID do modelo (por tag ou outro campo, adapte se precisar)
            $stmt = $pdo->prepare("SELECT id FROM modelos_compras WHERE tag = ? AND system_unit_id = ?");
            $stmt->execute([$tag, $system_unit_id]);
            $modelo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$modelo) {
                self::sendResponse(false, "Modelo de balanço não encontrado.", [], 404);
            }

            $modelo_id = $modelo['id'];

            // Atualizar o modelo
            $stmtUpdate = $pdo->prepare("UPDATE modelos_compras SET nome = ?, usuario_id = ?, tag = ?, updated_at = NOW() WHERE id = ?");
            $stmtUpdate->execute([$nome, $usuario_id, $tag, $modelo_id]);

            if ($stmtUpdate->rowCount() < 0) {
                self::sendResponse(false, "Falha ao atualizar o modelo de balanço.", [], 500);
            }

            // Apagar todos os itens antigos do modelo
            $stmtDelete = $pdo->prepare("DELETE FROM modelos_compras_itens WHERE id_modelo = ?");
            $stmtDelete->execute([$modelo_id]);

            // Inserir os novos itens
            $stmtItem = $pdo->prepare("
                INSERT INTO modelos_compras_itens (id_modelo, system_unit_id, id_produto, created_at, updated_at)
                SELECT ?, ?, id, NOW(), NOW() 
                FROM products 
                WHERE codigo = ? AND system_unit_id = ?
            ");

            foreach ($itens as $item) {
                if (!isset($item['codigo'])) {
                    self::sendResponse(false, "O campo 'codigo' é obrigatório para cada item.", [], 400);
                }

                $codigo_produto = $item['codigo'];

                $stmtItem->execute([$modelo_id, $system_unit_id, $codigo_produto, $system_unit_id]);

                if ($stmtItem->rowCount() <= 0) {
                    self::sendResponse(false, "Falha ao inserir item no modelo de compras.", [], 500);
                }
            }

            // Finaliza a transação
            $pdo->commit();

            self::sendResponse(true, 'Modelo de balanço editado com sucesso.', ['modelo_id' => $modelo_id], 200);
        } catch (Exception $e) {
            $pdo->rollBack();
            self::sendResponse(false, 'Erro ao editar modelo de compras: ' . $e->getMessage(), [], 500);
        }
    }

    public static function listPurchaseRequests($system_unit_id, $data_inicial = null, $data_final = null)
    {
        global $pdo;

        try {
            if (!empty($data_inicial) && !empty($data_final) && $data_inicial > $data_final) {
                http_response_code(400);
                return ['success' => false, 'message' => 'A data inicial não pode ser maior que a data final.'];
            }

            $query = "
        SELECT
            rc.id,
            rc.status,
            rs.descricao AS status_descricao,
            rc.system_unit_id,
            rc.doc,
            rc.data,
            rc.usuario_id,
            rc.created_at,
            rc.updated_at,
            rc.solicitante_nome,
            (
                SELECT SUM(ri.preco)
                FROM requisicao_compras_itens ri
                WHERE ri.requisicao_id = rc.id
            ) AS valor_total
        FROM requisicao_compras rc
        INNER JOIN requisicao_status rs ON rs.id = rc.status
        WHERE rc.system_unit_id = :system_unit_id";

            if (!empty($data_inicial) && !empty($data_final)) {
                $query .= " AND rc.data BETWEEN :data_inicial AND :data_final";
            } elseif (!empty($data_inicial)) {
                $query .= " AND rc.data >= :data_inicial";
            } elseif (!empty($data_final)) {
                $query .= " AND rc.data <= :data_final";
            }

            $query .= " ORDER BY rc.data DESC";

            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);

            if (!empty($data_inicial)) {
                $stmt->bindParam(':data_inicial', $data_inicial);
            }
            if (!empty($data_final)) {
                $stmt->bindParam(':data_final', $data_final);
            }

            $stmt->execute();
            $requisicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($requisicoes as &$req) {
                if (!empty($req['created_at'])) {
                    $created = new DateTime($req['created_at']);
                    $created->modify('-5 hours');
                    $req['created_at'] = $created->format('Y-m-d H:i:s');
                }

                $valor = (float) ($req['valor_total'] ?? 0);
                $req['valor_total'] = 'R$ ' . number_format($valor, 2, ',', '.');

            }

            return ['success' => true, 'requisicoes' => $requisicoes];
        } catch (Exception $e) {
            http_response_code(500);
            return ['success' => false, 'message' => 'Erro ao listar requisições: ' . $e->getMessage()];
        }
    }


    public static function getPurchaseRequestByDoc($system_unit_id, $doc)
    {
        global $pdo;
        global $pdop;

            // 1. Buscar o cabeçalho da requisição com descrição do status
            $stmtHeader = $pdo->prepare("
            SELECT
                rc.*,
                rs.descricao AS status_descricao
            FROM requisicao_compras rc
            LEFT JOIN requisicao_status rs ON rs.id = rc.status
            WHERE rc.system_unit_id = :system_unit_id AND rc.doc = :doc
            LIMIT 1
            ");
            $stmtHeader->execute([
                ':system_unit_id' => $system_unit_id,
                ':doc' => $doc
            ]);

            $requisicao = $stmtHeader->fetch(PDO::FETCH_ASSOC);

            if (!$requisicao) {
                http_response_code(404);
                return ['success' => false, 'message' => 'Requisição não encontrada.'];
            }

            $requisicao_id = $requisicao['id'];

            // 2. Buscar os itens da requisição
            $stmtItens = $pdo->prepare("
            SELECT
                i.id,
                i.seq,
                p.codigo as codigo,
                TRIM(REPLACE(REPLACE(REPLACE(i.produto, '\n', ''), '\r', ''), '\t', '')) AS produto,
                p.categoria,
                c.nome as categoria_nome,
                i.observacao,
                i.preco,
                i.quantidade,
                i.quantidade_comprada,
                i.created_at,
                i.updated_at
            FROM requisicao_compras_itens i
            LEFT JOIN products p ON p.id = i.id_produto AND p.system_unit_id = i.system_unit_id
            LEFT JOIN categorias c ON p.categoria = c.codigo AND p.system_unit_id = c.system_unit_id
            WHERE i.requisicao_id = :requisicao_id AND i.system_unit_id = :system_unit_id
            ORDER BY i.seq ASC
            ");
            $stmtItens->execute([
                ':requisicao_id' => $requisicao_id,
                ':system_unit_id' => $system_unit_id
            ]);
            $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

            // 3. Buscar os logs
            $stmtLogs = $pdo->prepare("
            SELECT
                l.id,
                l.status,
                rs.descricao AS status_descricao,
                l.observacao,
                l.usuario_id,
                l.created_at
            FROM requisicao_compras_log l
            LEFT JOIN requisicao_status rs ON rs.id = l.status
            WHERE l.requisicao_id = :requisicao_id AND l.system_unit_id = :system_unit_id
            ORDER BY l.created_at DESC
            ");
            $stmtLogs->execute([
                ':requisicao_id' => $requisicao_id,
                ':system_unit_id' => $system_unit_id
            ]);
            $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

            // 4. Buscar nomes dos usuários no outro banco
            $userIds = array_unique(array_column($logs, 'usuario_id'));
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));

            $userNamesMap = [];
            if (!empty($userIds)) {
                $stmtUsers = $pdop->prepare("
                SELECT id, name
                FROM system_users
                WHERE id IN ($placeholders)
            ");
                $stmtUsers->execute($userIds);
                $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

                foreach ($users as $user) {
                    $userNamesMap[$user['id']] = $user['name'];
                }
            }

            // 5. Atribuir nome ao log
            foreach ($logs as &$log) {
                $log['usuario_nome'] = $userNamesMap[$log['usuario_id']] ?? 'Formulário Mobile';

                // Ajustar horário do status PEDIDO REALIZADO (status = 1)
                if ((int)$log['status'] === 1 && !empty($log['created_at'])) {
                    $created = new DateTime($log['created_at']);
                    $created->modify('-5 hours');
                    $log['created_at'] = $created->format('Y-m-d H:i:s');
                }
            }

            return [
                'success' => true,
                'requisicao' => $requisicao,
                'itens' => $itens,
                'logs' => $logs
            ];

    }


}

?>
