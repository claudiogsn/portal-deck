<?php
date_default_timezone_set('America/Rio_Branco');


ini_set('post_max_size', '100M');
ini_set('upload_max_filesize', '100M');
ini_set('max_execution_time', '600');
ini_set('max_input_time', '600');
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../database/db.php';// Ajustando o caminho para o arquivo db.php
require_once __DIR__ . '/../database/db-permission.php';// Ajustando o caminho para o arquivo db.php


class UserController {

    public static function getUserDetails($user)
    {
        global $pdop;

        // Primeiro busca os detalhes do usuário
        $stmt = $pdop->prepare("SELECT id, name, login, function_name, system_unit_id FROM system_users WHERE login = :user AND active = 'Y' LIMIT 1");
        $stmt->bindParam(':user', $user, PDO::PARAM_STR);
        $stmt->execute();
        $userDetails = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$userDetails) {
            return array('success' => false, 'message' => 'Usuário não encontrado');
        }

        // Busca o nome da unidade
        if (!empty($userDetails['system_unit_id'])) {
            $stmtUnit = $pdop->prepare("SELECT name FROM system_unit WHERE id = :unit_id LIMIT 1");
            $stmtUnit->bindParam(':unit_id', $userDetails['system_unit_id'], PDO::PARAM_INT);
            $stmtUnit->execute();
            $unit = $stmtUnit->fetch(PDO::FETCH_ASSOC);

            if ($unit) {
                $userDetails['unit_name'] = $unit['name'];
            } else {
                $userDetails['unit_name'] = null;
            }
        } else {
            $userDetails['unit_name'] = null;
        }

        // Busca o último acesso do usuário onde não foi feito logout
        $stmtLog = $pdop->prepare("
        SELECT sessionid 
        FROM system_access_log 
        WHERE login = :user 
        AND logout_time = '0000-00-00 00:00:00'
        ORDER BY login_time DESC 
        LIMIT 1
    ");
        $stmtLog->bindParam(':user', $user, PDO::PARAM_STR);
        $stmtLog->execute();
        $lastAccess = $stmtLog->fetch(PDO::FETCH_ASSOC);

        // Adiciona o token aos detalhes do usuário
        if ($lastAccess && isset($lastAccess['sessionid'])) {
            $userDetails['token'] = $lastAccess['sessionid'];
            $userDetails['is_logged'] = true;
        } else {
            $userDetails['token'] = null;
            $userDetails['is_logged'] = false;
        }

        return array('success' => true, 'userDetails' => $userDetails);
    }

    public static function getUnitsUser($user_id)
    {
        global $pdop;

        $stmt = $pdop->prepare("
        SELECT su.id, su.name 
        FROM system_unit su
        INNER JOIN system_user_unit suu ON su.id = suu.system_unit_id
        WHERE suu.system_user_id = :user_id
    ");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $units = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$units) {
            return ['success' => false, 'message' => 'Nenhuma unidade encontrada para o usuário.'];
        }

        return ['success' => true, 'units' => $units];
    }


}

?>
