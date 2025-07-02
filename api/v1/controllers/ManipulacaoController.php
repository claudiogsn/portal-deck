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
            // Primeiro busca os itens da ficha
            $stmt = $pdo->prepare("
            SELECT *
            FROM ficha_manipulacao_itens
            WHERE id_ficha = :id_ficha AND system_unit_id = :unit_id
            ORDER BY id ASC
        ");
            $stmt->execute([':id_ficha' => $id_ficha, ':unit_id' => $system_unit_id]);
            $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Para cada item, busca o nome do produto
            foreach ($itens as &$item) {
                if (!empty($item['codigo_insumo'])) {
                    $stmtProd = $pdo->prepare("
                    SELECT nome 
                    FROM products 
                    WHERE system_unit_id = :unit_id 
                    AND codigo = :codigo
                    LIMIT 1
                ");
                    $stmtProd->execute([
                        ':unit_id' => $system_unit_id,
                        ':codigo' => $item['codigo_insumo']
                    ]);
                    $produto = $stmtProd->fetch(PDO::FETCH_ASSOC);

                    $item['produto_nome'] = $produto ? $produto['nome'] : null;
                } else {
                    $item['produto_nome'] = null;
                }
            }

            return ['success' => true, 'data' => $itens];
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

    public static function getDetalhesMovimentacao($documento, $system_unit_id)
    {
        global $pdo, $pdop;
    
        try {
            // 1. Buscar cabeçalho da movimentação
            $stmtCabecalho = $pdo->prepare("
                SELECT *
                FROM ficha_manipulacao_mov
                WHERE documento = :documento AND system_unit_id = :unit_id
                LIMIT 1
            ");
            $stmtCabecalho->execute([
                ':documento' => $documento,
                ':unit_id' => $system_unit_id
            ]);
            $cabecalho = $stmtCabecalho->fetch(PDO::FETCH_ASSOC);
    
            if (!$cabecalho) {
                return ['success' => false, 'message' => 'Movimentação não encontrada.'];
            }
    
            // 1.1 Buscar nome do produto principal
            $stmtProduto = $pdo->prepare("
                SELECT nome 
                FROM products 
                WHERE system_unit_id = :unit_id AND codigo = :codigo
                LIMIT 1
            ");
            $stmtProduto->execute([
                ':unit_id' => $system_unit_id,
                ':codigo' => $cabecalho['codigo_produto']
            ]);
            $produto = $stmtProduto->fetch(PDO::FETCH_ASSOC);
            $cabecalho['nome_produto'] = $produto['nome'] ?? '(produto não encontrado)';
    
            // 2. Buscar nome do operador e nome da unidade
            $stmtOperador = $pdop->prepare("
                SELECT u.name AS nome_operador, s.name AS nome_unidade
                FROM system_users u
                LEFT JOIN system_unit s ON u.system_unit_id = s.id
                WHERE u.id = :user_id
                LIMIT 1
            ");
            $stmtOperador->execute([':user_id' => $cabecalho['operador']]);
            $dadosOperador = $stmtOperador->fetch(PDO::FETCH_ASSOC) ?? [];
    
            $cabecalho['nome_operador'] = $dadosOperador['nome_operador'] ?? '';
            $cabecalho['nome_unidade'] = $dadosOperador['nome_unidade'] ?? '';
    
            // 3. Calcular percentuais
            $pesoBruto = (float) $cabecalho['peso_bruto'];
            $descarte = (float) $cabecalho['descarte'];
            $cabecalho['percentual_descarte'] = $pesoBruto > 0 ? round(($descarte / $pesoBruto) * 100, 2) : 0;
            $cabecalho['percentual_aproveitamento'] = 100 - $cabecalho['percentual_descarte'];
    
            // 4. Itens da movimentação
            $stmtItens = $pdo->prepare("
                SELECT m.*, p.nome AS nome_produto
                FROM ficha_manipulacao_itens_mov m
                LEFT JOIN products p 
                    ON p.system_unit_id = m.system_unit_id AND p.codigo = m.codigo_insumo
                WHERE m.documento = :documento AND m.system_unit_id = :unit_id
                ORDER BY m.id ASC
            ");
            $stmtItens->execute([
                ':documento' => $documento,
                ':unit_id' => $system_unit_id
            ]);
            $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);
    
            foreach ($itens as &$item) {
                $quantidadeGramas = (float) $item['quantidade'];
                $quantidadeKg = round($quantidadeGramas / 1000, 3);
    
                if ($quantidadeKg <= 0) {
                    $item = null;
                    continue;
                }
    
                $item['quantidade'] = $quantidadeKg;
                $item['percentual_item'] = $pesoBruto > 0 ? round(($quantidadeKg / $pesoBruto) * 100, 2) : 0;
            }
    
            // Remove itens nulos
            $itens = array_filter($itens);
    
            // 5. Anexos
            $stmtAnexos = $pdo->prepare("
                SELECT *
                FROM ficha_manipulacao_mov_anexo
                WHERE documento = :documento AND system_unit_id = :unit_id
                ORDER BY id ASC
            ");
            $stmtAnexos->execute([
                ':documento' => $documento,
                ':unit_id' => $system_unit_id
            ]);
            $anexos = $stmtAnexos->fetchAll(PDO::FETCH_ASSOC);
    
            // 6. Retorno final com itens como array numérico
            return [
                'success' => true,
                'data' => [
                    'cabecalho' => $cabecalho,
                    'itens' => array_values($itens),
                    'anexos' => $anexos
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao buscar detalhes: ' . $e->getMessage()];
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
                descarte, peso_bruto, operador, data,observacao
            ) VALUES (
                :system_unit_id, :id_ficha, :documento, :codigo_produto,
                :descarte, :peso_bruto, :operador, :data,:observacao
            )
        ");
            $stmt->execute([
                ':system_unit_id' => $data['system_unit_id'],
                ':id_ficha' => $data['id_ficha'],
                ':documento' => $documento,
                ':codigo_produto' => $data['codigo_produto'],
                ':descarte' => $data['descarte'],
                ':peso_bruto' => $data['peso_bruto'],
                ':operador' => $data['operador'],
                ':observacao' => $data['observacao'],
                ':data' => date('Y-m-d H:i:s')
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
                $quantidade = (float) $item['quantidade'];
                if ($quantidade <= 0) {
                    continue; // ignora itens com quantidade zero ou negativa
                }
            
                $stmtItem->execute([
                    ':system_unit_id' => $data['system_unit_id'],
                    ':documento' => $documento,
                    ':codigo_insumo' => $item['codigo_insumo'],
                    ':unidade' => $item['unidade'],
                    ':quantidade' => $quantidade
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
            $pdo->beginTransaction();

            $now = date('Y-m-d H:i:s');

            if (!empty($data['id'])) {
                // Atualizar ficha
                $stmt = $pdo->prepare("
                UPDATE ficha_manipulacao
                SET nome_produto = :nome_produto, updated_at = :updated_at
                WHERE id = :id AND system_unit_id = :system_unit_id
            ");
                $stmt->execute([
                    ':nome_produto' => $data['nome_produto'],
                    ':updated_at' => $now,
                    ':id' => $data['id'],
                    ':system_unit_id' => $data['system_unit_id']
                ]);

                // Remove insumos antigos
                $stmt = $pdo->prepare("DELETE FROM ficha_manipulacao_itens WHERE id_ficha = :id AND system_unit_id = :unit_id");
                $stmt->execute([
                    ':id' => $data['id'],
                    ':unit_id' => $data['system_unit_id']
                ]);

                $fichaId = $data['id'];
            } else {
                // Inserir nova ficha
                $stmt = $pdo->prepare("
                INSERT INTO ficha_manipulacao (
                    system_unit_id, codigo_produto, nome_produto, created_at, updated_at, status
                ) VALUES (
                    :system_unit_id, :codigo_produto, :nome_produto, :created_at, :updated_at, 1
                )
            ");
                $stmt->execute([
                    ':system_unit_id' => $data['system_unit_id'],
                    ':codigo_produto' => $data['codigo_produto'],
                    ':nome_produto' => $data['nome_produto'],
                    ':created_at' => $now,
                    ':updated_at' => $now
                ]);

                $fichaId = $pdo->lastInsertId();
            }

            // Inserir os insumos
            if (!empty($data['insumos'])) {
                $stmt = $pdo->prepare("
                INSERT INTO ficha_manipulacao_itens (system_unit_id, id_ficha,codigo_insumo)
                VALUES (:system_unit_id, :id_ficha, :codigo_insumo)
            ");

                foreach ($data['insumos'] as $insumoCodigo) {
                    $stmt->execute([
                        ':system_unit_id' => $data['system_unit_id'],
                        ':id_ficha' => $fichaId,
                        ':codigo_insumo' => $insumoCodigo,
                    ]);
                }
            }

            $pdo->commit();
            return ['success' => true, 'message' => 'Ficha salva com sucesso.'];

        } catch (Exception $e) {
            $pdo->rollBack();
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

    public static function getUsuariosEstoqueManipulacao()
    {
        global $pdop;

        try {
            $stmt = $pdop->prepare("
            SELECT u.id, u.name, u.login
            FROM system_users u
            JOIN system_user_role ur ON ur.system_user_id = u.id
            WHERE ur.system_role_id = 4
              AND u.active = 'Y'
            ORDER BY u.name
        ");
            $stmt->execute();
            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return ['success' => true, 'usuarios' => $usuarios];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao buscar usuários: ' . $e->getMessage()];
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

    public static function getFichaDetalhada($ficha_id, $system_unit_id)
    {
        global $pdo;

        try {
            // 1) Busca a ficha
            $stmt = $pdo->prepare("
            SELECT id, codigo_produto, nome_produto
            FROM ficha_manipulacao
            WHERE id = :id AND system_unit_id = :unit_id
            LIMIT 1
        ");
            $stmt->execute([
                ':id' => $ficha_id,
                ':unit_id' => $system_unit_id
            ]);
            $ficha = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ficha) throw new Exception("Ficha não encontrada");

            // 2) Busca os insumos associados (supondo tabela ficha_manipulacao_itens com campo codigo_insumo)
            $stmt2 = $pdo->prepare("
            SELECT codigo_insumo AS codigo_produto
            FROM ficha_manipulacao_itens
            WHERE id_ficha = :id_ficha AND system_unit_id = :unit_id
        ");
            $stmt2->execute([
                ':id_ficha' => $ficha_id,
                ':unit_id' => $system_unit_id
            ]);
            $insumos = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => [
                    'id' => (int) $ficha['id'],
                    'codigo_produto' => (int) $ficha['codigo_produto'],
                    'nome_produto' => $ficha['nome_produto'],
                    'insumos' => $insumos
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao buscar ficha detalhada: ' . $e->getMessage()
            ];
        }
    }

    public static function listarMovimentacaoPorPeriodo($data_inicio, $data_fim,$user)
    {
        global $pdo, $pdop;

        try {
            $stmtUnidades = $pdop->prepare("select suu.system_unit_id as id,su.name as name from system_user_unit suu left join system_unit as su on suu.system_unit_id = su.id where suu.system_user_id = :user");
            $stmtUnidades->execute([':user' => $user]);
            $unidades = $stmtUnidades->fetchAll(PDO::FETCH_ASSOC);

            $resultado = [];

            foreach ($unidades as $unidade) {
                $unit_id = $unidade['id'];
                $nome_unidade = $unidade['name'];

                $stmtMov = $pdo->prepare("
                    SELECT 
                        m.id, m.system_unit_id, m.id_ficha, m.documento,
                        m.codigo_produto, m.descarte, m.peso_bruto,
                        m.operador, m.data
                    FROM ficha_manipulacao_mov m
                    WHERE m.system_unit_id = :unit_id
                    AND m.data BETWEEN :data_inicio AND :data_fim
                    ORDER BY m.data DESC
                ");
                $stmtMov->execute([
                    ':unit_id' => $unit_id,
                    ':data_inicio' => $data_inicio . ' 00:00:00',
                    ':data_fim' => $data_fim . ' 23:59:59',
                ]);

                $movimentacoes = $stmtMov->fetchAll(PDO::FETCH_ASSOC);

                foreach ($movimentacoes as $mov) {
                    $pesoBruto = (float) $mov['peso_bruto'];
                    $descarte = (float) $mov['descarte'];

                    // Calcular percentual de aproveitamento
                    $percentual_aproveitamento = ($pesoBruto > 0)
                        ? round(100 - (($descarte / $pesoBruto) * 100), 2)
                        : 0;

                    // Buscar nome e login do operador
                    $stmtOperador = $pdop->prepare("
                        SELECT name, login FROM system_users WHERE id = :id LIMIT 1
                    ");
                    $stmtOperador->execute([':id' => $mov['operador']]);
                    $operadorData = $stmtOperador->fetch(PDO::FETCH_ASSOC);

                    $nome_operador = $operadorData['name'] ?? 'Desconhecido';
                    $login_operador = $operadorData['login'] ?? null;

                    // Buscar nome do produto
                    $stmtProduto = $pdo->prepare("
                        SELECT nome FROM products
                        WHERE system_unit_id = :unit_id AND codigo = :codigo
                        LIMIT 1
                    ");
                    $stmtProduto->execute([
                        ':unit_id' => $unit_id,
                        ':codigo' => $mov['codigo_produto']
                    ]);
                    $nome_produto = $stmtProduto->fetchColumn() ?: '(produto não encontrado)';

                    // Montar retorno
                    $mov['nome_unidade'] = $nome_unidade;
                    $mov['nome_operador'] = $nome_operador;
                    $mov['login_operador'] = $login_operador;
                    $mov['nome_produto'] = $nome_produto;
                    $mov['percentual_aproveitamento'] = $percentual_aproveitamento;

                    $resultado[] = $mov;
                }

            }

            return ['success' => true, 'data' => $resultado];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar movimentações: ' . $e->getMessage()];
        }
    }







}
