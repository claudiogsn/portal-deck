<?php
date_default_timezone_set('America/Rio_Branco');

require_once __DIR__ . '/../database/db.php';

class ManipulacaoController
{
    public static function listarFichas($system_unit_id)
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("
                SELECT *
                FROM ficha_manipulacao
                WHERE system_unit_id = :unit_id
                ORDER BY updated_at DESC
            ");
            $stmt->execute([':unit_id' => $system_unit_id]);
            return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar fichas: ' . $e->getMessage()];
        }
    }

    public static function listarItensFicha($id_ficha, $system_unit_id)
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("
                SELECT *
                FROM ficha_manipulacao_itens
                WHERE id_ficha = :id_ficha AND system_unit_id = :unit_id
                ORDER BY id ASC
            ");
            $stmt->execute([':id_ficha' => $id_ficha, ':unit_id' => $system_unit_id]);
            return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar itens da ficha: ' . $e->getMessage()];
        }
    }

    public static function listarMovimentacoes($id_ficha, $system_unit_id)
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("
                SELECT *
                FROM ficha_manipulacao_mov
                WHERE id_ficha = :id_ficha AND system_unit_id = :unit_id
                ORDER BY data DESC
            ");
            $stmt->execute([':id_ficha' => $id_ficha, ':unit_id' => $system_unit_id]);
            return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar movimentações: ' . $e->getMessage()];
        }
    }

    public static function listarItensMovimentacao($documento, $system_unit_id)
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("
                SELECT *
                FROM ficha_manipulacao_itens_mov
                WHERE documento = :documento AND system_unit_id = :unit_id
                ORDER BY id ASC
            ");
            $stmt->execute([':documento' => $documento, ':unit_id' => $system_unit_id]);
            return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar itens da movimentação: ' . $e->getMessage()];
        }
    }

    public static function listarAnexosMovimentacao($documento, $system_unit_id)
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("
                SELECT *
                FROM ficha_manipulacao_mov_anexo
                WHERE documento = :documento AND system_unit_id = :unit_id
                ORDER BY id ASC
            ");
            $stmt->execute([':documento' => $documento, ':unit_id' => $system_unit_id]);
            return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar anexos: ' . $e->getMessage()];
        }
    }

    public static function registrarMovimentacao($data)
    {
        global $pdo;

        try {
            // 1. Gerar documento automaticamente
            $prefixo = 'fm';
            $ultimoDoc = self::getLastDocFichaManipulacao($data['system_unit_id'], $prefixo);
            $documento = self::incrementDoc($ultimoDoc, $prefixo);

            // 2. Insere a movimentação principal
            $stmt = $pdo->prepare("
            INSERT INTO ficha_manipulacao_mov (
                system_unit_id, id_ficha, documento, codigo_produto,
                descarte, liquido, operador, data
            ) VALUES (
                :system_unit_id, :id_ficha, :documento, :codigo_produto,
                :descarte, :liquido, :operador, :data
            )
        ");
            $stmt->execute([
                ':system_unit_id' => $data['system_unit_id'],
                ':id_ficha' => $data['id_ficha'],
                ':documento' => $documento,
                ':codigo_produto' => $data['codigo_produto'],
                ':descarte' => $data['descarte'],
                ':liquido' => $data['liquido'],
                ':operador' => $data['operador'],
                ':data' => $data['data']
            ]);

            // 3. Insere os itens da movimentação
            if (!empty($data['itens'])) {
                $stmtItem = $pdo->prepare("
                INSERT INTO ficha_manipulacao_itens_mov (
                    system_unit_id, documento, codigo_insumo,
                    unidade, quantidade
                ) VALUES (
                    :system_unit_id, :documento, :codigo_insumo,
                    :unidade, :quantidade
                )
            ");
                foreach ($data['itens'] as $item) {
                    $stmtItem->execute([
                        ':system_unit_id' => $data['system_unit_id'],
                        ':documento' => $documento,
                        ':codigo_insumo' => $item['codigo_insumo'],
                        ':unidade' => $item['unidade'],
                        ':quantidade' => $item['quantidade']
                    ]);
                }
            }

            return [
                'success' => true,
                'message' => 'Movimentação registrada com sucesso.',
                'documento' => $documento
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao registrar movimentação: ' . $e->getMessage()];
        }
    }

    public static function saveFicha($data)
    {
        global $pdo;

        try {
            if (!empty($data['id'])) {
                // Atualizar ficha existente
                $stmt = $pdo->prepare("
                    UPDATE ficha_manipulacao
                    SET nome_produto = :nome_produto,
                        descarte = 0,
                        updated_at = :updated_at
                    WHERE id = :id AND system_unit_id = :system_unit_id
                ");
                $stmt->execute([
                    ':nome_produto' => $data['nome_produto'],
                    ':updated_at' => date('Y-m-d H:i:s'),
                    ':id' => $data['id'],
                    ':system_unit_id' => $data['system_unit_id']
                ]);
                return ['success' => true, 'message' => 'Ficha atualizada com sucesso.'];
            } else {
                // Nova ficha
                $stmt = $pdo->prepare("
                    INSERT INTO ficha_manipulacao (
                        system_unit_id, codigo_produto, nome_produto,
                        descarte, created_at, updated_at, status
                    ) VALUES (
                        :system_unit_id, :codigo_produto, :nome_produto,
                        :descarte, :created_at, :updated_at, 1
                    )
                ");
                $stmt->execute([
                    ':system_unit_id' => $data['system_unit_id'],
                    ':codigo_produto' => $data['codigo_produto'],
                    ':nome_produto' => $data['nome_produto'],
                    ':descarte' => $data['descarte'],
                    ':created_at' => date('Y-m-d H:i:s'),
                    ':updated_at' => date('Y-m-d H:i:s')
                ]);
                return ['success' => true, 'message' => 'Ficha criada com sucesso.'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao salvar ficha: ' . $e->getMessage()];
        }
    }

    public static function toggleStatusFicha($id, $system_unit_id)
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("
                UPDATE ficha_manipulacao
                SET status = IF(status = 1, 0, 1),
                    updated_at = :updated_at
                WHERE id = :id AND system_unit_id = :unit_id
            ");
            $stmt->execute([
                ':id' => $id,
                ':unit_id' => $system_unit_id,
                ':updated_at' => date('Y-m-d H:i:s')
            ]);

            return ['success' => true, 'message' => 'Status da ficha alterado.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao alterar status da ficha: ' . $e->getMessage()];
        }
    }

    public static function getFichasByUser($user_id, $system_unit_id)
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("
                SELECT f.*
                FROM ficha_manipulacao f
                INNER JOIN ficha_manipulacao_user fu ON fu.id_ficha = f.id
                WHERE fu.user_id = :user_id
                  AND f.system_unit_id = :unit_id
                  AND f.status = 1
                ORDER BY f.nome_produto
            ");
            $stmt->execute([
                ':user_id' => $user_id,
                ':unit_id' => $system_unit_id
            ]);

            return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao buscar fichas do usuário: ' . $e->getMessage()];
        }
    }

    public static function saveUsuariosPorFicha($ficha_id, $array_user_ids)
    {
        global $pdo;

        try {
            // Remove vínculos existentes
            $stmtDelete = $pdo->prepare("DELETE FROM ficha_manipulacao_user WHERE id_ficha = :id_ficha");
            $stmtDelete->execute([':id_ficha' => $ficha_id]);

            if (!empty($array_user_ids)) {
                // Insere os novos vínculos
                $stmtInsert = $pdo->prepare("
                INSERT INTO ficha_manipulacao_user (id_ficha, user_id)
                VALUES (:id_ficha, :user_id)
            ");

                foreach ($array_user_ids as $user_id) {
                    $stmtInsert->execute([
                        ':id_ficha' => $ficha_id,
                        ':user_id' => $user_id
                    ]);
                }
            }

            return ['success' => true, 'message' => 'Usuários vinculados com sucesso.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao salvar usuários: ' . $e->getMessage()];
        }
    }

    public static function listarUsuariosDaFicha($ficha_id)
    {
        global $pdo, $pdop;

        try {
            // 1. Buscar os IDs dos usuários no banco local
            $stmt = $pdo->prepare("
            SELECT user_id
            FROM ficha_manipulacao_user
            WHERE id_ficha = :ficha_id
        ");
            $stmt->execute([':ficha_id' => $ficha_id]);

            $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($userIds)) {
                return ['success' => true, 'data' => []];
            }

            // 2. Buscar os dados dos usuários no banco global
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $stmtUsers = $pdop->prepare("
            SELECT id, name
            FROM system_users
            WHERE id IN ($placeholders)
            ORDER BY name
        ");
            $stmtUsers->execute($userIds);

            return ['success' => true, 'data' => $stmtUsers->fetchAll(PDO::FETCH_ASSOC)];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar usuários: ' . $e->getMessage()];
        }
    }

    public static function registrarAnexoMovimentacao($documento, $system_unit_id, $url, $descricao = null)
    {
        global $pdo;

        try {
            // Verifica se o documento existe antes de registrar
            $stmtCheck = $pdo->prepare("
            SELECT id
            FROM ficha_manipulacao_mov
            WHERE documento = :documento AND system_unit_id = :unit_id
            LIMIT 1
        ");
            $stmtCheck->execute([
                ':documento' => $documento,
                ':unit_id' => $system_unit_id
            ]);

            $mov = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if (!$mov) {
                return ['success' => false, 'message' => 'Movimentação não encontrada.'];
            }

            // Insere o anexo
            $stmt = $pdo->prepare("
            INSERT INTO ficha_manipulacao_mov_anexo (
                system_unit_id, documento, url, descricao
            ) VALUES (
                :unit_id, :documento, :url, :descricao
            )
        ");
            $stmt->execute([
                ':unit_id' => $system_unit_id,
                ':documento' => $documento,
                ':url' => $url,
                ':descricao' => $descricao
            ]);

            return ['success' => true, 'message' => 'Anexo registrado com sucesso.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao registrar anexo: ' . $e->getMessage()];
        }
    }

    public static function getLastDocFichaManipulacao($system_unit_id, $prefixo)
    {
        global $pdo;

        $stmt = $pdo->prepare("
        SELECT documento
        FROM ficha_manipulacao_mov
        WHERE system_unit_id = :system_unit_id AND documento LIKE :prefixo
        ORDER BY id DESC
        LIMIT 1
    ");
        $stmt->execute([
            ':system_unit_id' => $system_unit_id,
            ':prefixo' => "$prefixo-%"
        ]);
        $last = $stmt->fetch(PDO::FETCH_ASSOC);

        return $last ? $last['documento'] : $prefixo . '-000000';
    }

    private static function incrementDoc($ultimoDoc, $prefixo)
    {
        if (preg_match('/^' . preg_quote($prefixo, '/') . '-(\d+)$/', $ultimoDoc, $matches)) {
            $numero = (int)$matches[1] + 1;
            return $prefixo . '-' . str_pad($numero, 6, '0', STR_PAD_LEFT);
        }
        return $prefixo . '-000001';
    }





}
