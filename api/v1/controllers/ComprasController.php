<?php
date_default_timezone_set('America/Rio_Branco');

require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../database/db-permission.php';

class ComprasController
{
    public static function listarRequisicoes($system_unit_id)
    {
        global $pdo, $pdop;

        try {
            // Busca os dados da unidade no banco global (system_unit)
            $stmtUnit = $pdop->prepare("SELECT name, custom_code FROM system_unit WHERE id = :id LIMIT 1");
            $stmtUnit->execute([':id' => $system_unit_id]);
            $unit = $stmtUnit->fetch(PDO::FETCH_ASSOC);

            if (!$unit) {
                return ['success' => false, 'message' => 'Unidade não encontrada.'];
            }

            // Busca as requisições de compra no banco local
            $stmt = $pdo->prepare("
                SELECT *
                FROM requisicao_compras
                WHERE system_unit_id = :unit_id
                ORDER BY created_at DESC
            ");
            $stmt->execute([':unit_id' => $system_unit_id]);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Adiciona os dados da unidade em cada requisição
            foreach ($dados as &$requisicao) {
                $requisicao['nome_unidade'] = $unit['name'];
                $requisicao['custom_code'] = $unit['custom_code'];
            }

            return ['success' => true, 'data' => $dados];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar requisições: ' . $e->getMessage()];
        }
    }

    public static function listarItensDaRequisicao($requisicao_id, $system_unit_id)
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("
                SELECT *
                FROM requisicao_compras_itens
                WHERE requisicao_id = :requisicao_id AND system_unit_id = :unit_id
                ORDER BY seq ASC
            ");
            $stmt->execute([
                ':requisicao_id' => $requisicao_id,
                ':unit_id' => $system_unit_id
            ]);

            return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar itens: ' . $e->getMessage()];
        }
    }

    public static function listarLogDaRequisicao($requisicao_id, $system_unit_id)
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("
                SELECT *
                FROM requisicao_compras_log
                WHERE requisicao_id = :requisicao_id AND system_unit_id = :unit_id
                ORDER BY created_at DESC
            ");
            $stmt->execute([
                ':requisicao_id' => $requisicao_id,
                ':unit_id' => $system_unit_id
            ]);

            return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar log: ' . $e->getMessage()];
        }
    }

    public static function changeStatus($doc, $system_unit_id, $new_status_id, $username)
    {
        global $pdo;

        try {
            // Verifica se o status existe e pega a descrição
            $stmtStatus = $pdo->prepare("SELECT id, descricao FROM requisicao_status WHERE id = :id");
            $stmtStatus->execute([':id' => $new_status_id]);
            $statusData = $stmtStatus->fetch(PDO::FETCH_ASSOC);

            if (!$statusData) {
                return ['success' => false, 'message' => 'Status inválido'];
            }

            $statusDescricao = $statusData['descricao'];

            // Busca a requisição pelo doc e system_unit_id
            $stmtReq = $pdo->prepare("SELECT id FROM requisicao_compras WHERE doc = :doc AND system_unit_id = :unit_id LIMIT 1");
            $stmtReq->execute([
                ':doc' => $doc,
                ':unit_id' => $system_unit_id
            ]);
            $requisicao = $stmtReq->fetch(PDO::FETCH_ASSOC);

            if (!$requisicao) {
                return ['success' => false, 'message' => 'Requisição não encontrada'];
            }

            $requisicao_id = $requisicao['id'];

            // Atualiza o status da requisição
            $stmtUpdate = $pdo->prepare("
            UPDATE requisicao_compras
            SET status = :status, updated_at = :updated_at
            WHERE id = :id
        ");
            $stmtUpdate->execute([
                ':status' => $new_status_id,
                ':id' => $requisicao_id,
                ':updated_at' => date('Y-m-d H:i:s')
            ]);


            // Insere log com observação do status
            $stmtLog = $pdo->prepare("
                    INSERT INTO requisicao_compras_log (requisicao_id, system_unit_id, status, observacao, usuario_id, created_at)
                    VALUES (:requisicao_id, :system_unit_id, :status, :observacao, :usuario_id, :created_at)
                ");
            $stmtLog->execute([
                ':requisicao_id' => $requisicao_id,
                ':system_unit_id' => $system_unit_id,
                ':status' => $new_status_id,
                ':observacao' => 'Status alterado para: ' . $statusDescricao,
                ':usuario_id' => $username,
                ':created_at' => date('Y-m-d H:i:s')
            ]);


            return ['success' => true, 'message' => 'Status alterado com sucesso para: ' . $statusDescricao];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao alterar status: ' . $e->getMessage()];
        }
    }

    public static function realizarEntrega($doc, $system_unit_id, $username, $itens)
    {
        global $pdo;
        global $pdop;

        try {
            // Verifica se o status 4 existe
            $stmtStatus = $pdo->prepare("SELECT descricao FROM requisicao_status WHERE id = 4");
            $stmtStatus->execute();
            $statusData = $stmtStatus->fetch(PDO::FETCH_ASSOC);

            if (!$statusData) {
                return ['success' => false, 'message' => 'Status "Entregue" (id=4) não encontrado.'];
            }

            // Busca a requisição pelo doc e unidade
            $stmtReq = $pdo->prepare("SELECT id FROM requisicao_compras WHERE doc = :doc AND system_unit_id = :unit_id LIMIT 1");
            $stmtReq->execute([
                ':doc' => $doc,
                ':unit_id' => $system_unit_id
            ]);
            $requisicao = $stmtReq->fetch(PDO::FETCH_ASSOC);

            if (!$requisicao) {
                return ['success' => false, 'message' => 'Requisição não encontrada'];
            }

            $requisicao_id = $requisicao['id'];

            // Buscar ID do usuário pelo login
            $stmtUser = $pdop->prepare("SELECT id FROM system_users WHERE id = :id LIMIT 1");
            $stmtUser->execute([':id' => $username]);
            $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return ['success' => false, 'message' => 'Usuário não encontrado'];
            }

            $usuario_id = $user['id'];

            // Atualiza os itens com a quantidade entregue
            $stmtUpdateItem = $pdo->prepare("
            UPDATE requisicao_compras_itens
            SET quantidade_comprada = :quantidade, updated_at = :updated_at
            WHERE id_produto = :item_id AND requisicao_id = :requisicao_id AND system_unit_id = :unit_id
        ");

            foreach ($itens as $item) {
                $stmtUpdateItem->execute([
                    ':quantidade' => $item['quantidade'],
                    ':item_id' => $item['codigo'],
                    ':requisicao_id' => $requisicao_id,
                    ':unit_id' => $system_unit_id,
                    ':updated_at' => date('Y-m-d H:i:s')
                ]);
            }

            // Atualiza o status da requisição para 4
            $stmtUpdateReq = $pdo->prepare("
            UPDATE requisicao_compras
            SET status = 4, updated_at = :updated_at
            WHERE id = :id
        ");
            $stmtUpdateReq->execute([
                ':id' => $requisicao_id,
                ':updated_at' => date('Y-m-d H:i:s')
            ]);

            // Verifica se alguma linha foi atualizada (opcional para debug)
            if ($stmtUpdateReq->rowCount() === 0) {
                return ['success' => false, 'message' => 'Nenhuma linha foi atualizada na requisição.'];
            }

            // Insere log
            $stmtLog = $pdo->prepare("
            INSERT INTO requisicao_compras_log (requisicao_id, system_unit_id, status, observacao, usuario_id, created_at)
            VALUES (:requisicao_id, :system_unit_id, 4, :observacao, :usuario_id, :created_at)
        ");
            $stmtLog->execute([
                ':requisicao_id' => $requisicao_id,
                ':system_unit_id' => $system_unit_id,
                ':observacao' => 'Status alterado para: ' . $statusData['descricao'],
                ':usuario_id' => $usuario_id,
                ':created_at' => date('Y-m-d H:i:s')
            ]);

            return ['success' => true, 'message' => 'Entrega registrada com sucesso e status alterado para: ' . $statusData['descricao']];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao registrar entrega: ' . $e->getMessage()];
        }
    }

}
