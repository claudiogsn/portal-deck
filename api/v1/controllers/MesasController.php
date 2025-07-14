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

        // Verifica se já existe a mesa para essa unidade
        $stmt = $pdo->prepare("SELECT id FROM mesas_identificadas WHERE numero_mesa = :numero AND system_unit_id = :unidade");
        $stmt->bindParam(':numero', $numero_mesa, PDO::PARAM_INT);
        $stmt->bindParam(':unidade', $system_unit_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return ['success' => false, 'message' => 'Mesa já cadastrada para esta unidade.'];
        }

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

        $stmt = $pdo->prepare("SELECT id, numero_mesa, identificador, created_at FROM mesas_identificadas WHERE identificador = :identificador");
        $stmt->bindParam(':identificador', $identificador, PDO::PARAM_STR);
        $stmt->execute();
        $mesas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['success' => true, 'mesas' => $mesas];
    }
}
?>
