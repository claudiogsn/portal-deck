<?php
date_default_timezone_set('America/Rio_Branco');

ini_set('post_max_size', '100M');
ini_set('upload_max_filesize', '100M');
ini_set('max_execution_time', '600');
ini_set('max_input_time', '600');
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../database/db.php';

class MesaController
{
    // Gera um identificador único com 8 caracteres
    private static function gerarIdentificadorUnico()
    {
        global $pdo;

        do {
            $identificador = substr(md5(uniqid(rand(), true)), 0, 8);

            $stmt = $pdo->prepare("SELECT id FROM mesas_identificadas WHERE identificador = :identificador");
            $stmt->bindParam(':identificador', $identificador, PDO::PARAM_STR);
            $stmt->execute();
            $existe = $stmt->fetch(PDO::FETCH_ASSOC);

        } while ($existe);

        return $identificador;
    }

    // Cria uma nova mesa
    public static function criarMesa($numero_mesa, $system_unit_id)
    {
        global $pdo;

        // Verifica limite atual de mesas
        $stmtContar = $pdo->prepare("SELECT COUNT(*) AS total FROM mesas_identificadas WHERE system_unit_id = :unidade");
        $stmtContar->bindParam(':unidade', $system_unit_id, PDO::PARAM_INT);
        $stmtContar->execute();
        $totalAtual = (int)$stmtContar->fetch(PDO::FETCH_ASSOC)['total'];

        if ($totalAtual >= 200) {
            return ['success' => false, 'message' => 'Limite máximo de 200 mesas atingido para esta unidade.'];
        }

        // Verifica se já existe a mesa
        $stmt = $pdo->prepare("SELECT id FROM mesas_identificadas WHERE numero_mesa = :numero AND system_unit_id = :unidade");
        $stmt->bindParam(':numero', $numero_mesa, PDO::PARAM_INT);
        $stmt->bindParam(':unidade', $system_unit_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return ['success' => false, 'message' => 'Mesa já cadastrada para esta unidade.'];
        }

        // Insere a nova mesa
        $identificador = self::gerarIdentificadorUnico();
        $stmtInsert = $pdo->prepare("INSERT INTO mesas_identificadas (numero_mesa, identificador, system_unit_id) VALUES (:numero, :identificador, :unidade)");
        $stmtInsert->bindParam(':numero', $numero_mesa, PDO::PARAM_INT);
        $stmtInsert->bindParam(':identificador', $identificador, PDO::PARAM_STR);
        $stmtInsert->bindParam(':unidade', $system_unit_id, PDO::PARAM_INT);
        $stmtInsert->execute();

        return [
            'success' => true,
            'message' => 'Mesa cadastrada com sucesso.',
            'data' => [
                'numero_mesa' => $numero_mesa,
                'identificador' => $identificador,
                'system_unit_id' => $system_unit_id
            ]
        ];
    }


    public static function criarMesasPorRange($inicio, $fim, $system_unit_id)
    {
        global $pdo;

        // Validações iniciais
        if ($inicio > $fim) {
            return ['success' => false, 'message' => 'O número inicial deve ser menor ou igual ao número final.'];
        }

        // Total atual
        $stmtTotal = $pdo->prepare("SELECT COUNT(*) AS total FROM mesas_identificadas WHERE system_unit_id = :unidade");
        $stmtTotal->bindParam(':unidade', $system_unit_id, PDO::PARAM_INT);
        $stmtTotal->execute();
        $totalAtual = (int)$stmtTotal->fetch(PDO::FETCH_ASSOC)['total'];

        $quantidadeRange = $fim - $inicio + 1;
        $espacoDisponivel = 200 - $totalAtual;

        if ($espacoDisponivel <= 0) {
            return ['success' => false, 'message' => 'Limite máximo de 200 mesas já atingido para esta unidade.'];
        }

        if ($quantidadeRange > $espacoDisponivel) {
            return ['success' => false, 'message' => "Você pode cadastrar no máximo $espacoDisponivel mesa(s)."];
        }

        // Busca mesas já existentes no range
        $stmtExistentes = $pdo->prepare("
        SELECT numero_mesa FROM mesas_identificadas 
        WHERE system_unit_id = :unidade AND numero_mesa BETWEEN :inicio AND :fim
    ");
        $stmtExistentes->execute([
            ':unidade' => $system_unit_id,
            ':inicio' => $inicio,
            ':fim' => $fim
        ]);
        $existentes = array_column($stmtExistentes->fetchAll(PDO::FETCH_ASSOC), 'numero_mesa');

        $inseridas = [];
        for ($i = $inicio; $i <= $fim; $i++) {
            if (in_array($i, $existentes)) continue;

            $identificador = self::gerarIdentificadorUnico();
            $stmtInsert = $pdo->prepare("INSERT INTO mesas_identificadas (numero_mesa, identificador, system_unit_id) VALUES (:numero, :identificador, :unidade)");
            $stmtInsert->execute([
                ':numero' => $i,
                ':identificador' => $identificador,
                ':unidade' => $system_unit_id
            ]);
            $inseridas[] = $i;
        }

        return [
            'success' => true,
            'message' => 'Mesas cadastradas com sucesso.',
            'inseridas' => $inseridas,
            'ignoradas' => $existentes
        ];
    }



    // Lista mesas por unidade
    public static function listarMesasPorUnidade($system_unit_id)
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT id, numero_mesa, identificador, created_at FROM mesas_identificadas WHERE system_unit_id = :unidade ORDER BY numero_mesa");
        $stmt->bindParam(':unidade', $system_unit_id, PDO::PARAM_INT);
        $stmt->execute();
        $mesas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['success' => true, 'mesas' => $mesas];
    }

    public static function getMesaByChave($identificador)
    {
        global $pdo;

        $stmt = $pdo->prepare("
        SELECT 
            m.id,
            m.numero_mesa,
            m.identificador,
            m.created_at,
            su.name AS unidade_nome
        FROM mesas_identificadas AS m
        LEFT JOIN system_unit AS su ON su.id = m.system_unit_id
        WHERE m.identificador = :identificador
    ");
        $stmt->bindParam(':identificador', $identificador, PDO::PARAM_STR);
        $stmt->execute();
        $mesas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($mesas)) {
            http_response_code(400);
            return ['error' => 'Mesa não encontrada para o identificador informado.'];
        }

        // Se quiser retornar apenas uma mesa (caso o identificador seja único), retorne só o primeiro elemento
        return count($mesas) === 1 ? $mesas[0] : $mesas;
    }


}
?>
