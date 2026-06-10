<?php
require_once "db.php";
$data = json_decode(file_get_contents("php://input"), true);

if (!empty($data['user_id']) && !empty($data['challenge_id'])) {
    $userId = (int)$data['user_id'];
    $challengeId = (int)$data['challenge_id'];

    try {
        $stmt = $conn->prepare("
            SELECT du.id, dd.recompensa 
            FROM desafios_usuarios du
            JOIN desafios_disponiveis dd ON du.desafio_id = dd.id
            WHERE du.usuario_id = ? AND du.desafio_id = ? AND du.status = 'ACTIVE' AND du.data_limite > NOW()
        ");
        $stmt->execute([$userId, $challengeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(["status" => "error", "message" => "Não há desafio ativo ou o tempo expirou!"]);
            exit();
        }

        $reward = (int)$row['recompensa'];
        $conn->beginTransaction();

        $up = $conn->prepare("UPDATE desafios_usuarios SET status = 'COMPLETED' WHERE id = ?");
        $up->execute([$row['id']]);

        $coinsUp = $conn->prepare("UPDATE usuarios SET game_coins = game_coins + ? WHERE id = ?");
        $coinsUp->execute([$reward, $userId]);

        $userQuery = $conn->prepare("SELECT game_coins FROM usuarios WHERE id = ?");
        $userQuery->execute([$userId]);
        $u = $userQuery->fetch(PDO::FETCH_ASSOC);
        $totalCoins = (int)$u['game_coins'];

        $newRank = "RECRUTA";
        if ($totalCoins >= 10000) $newRank = "LEGENDARY EXPLORER";
        else if ($totalCoins >= 5000) $newRank = "ELITE FIGHTER";
        else if ($totalCoins >= 2500) $newRank = "VETERANO";

        $rankUp = $conn->prepare("UPDATE usuarios SET patente = ? WHERE id = ?");
        $rankUp->execute([$newRank, $userId]);

        $conn->commit();

        $finalQuery = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
        $finalQuery->execute([$userId]);
        $finalUser = $finalQuery->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            "status" => "success",
            "message" => "Recompensa resgatada com sucesso!",
            "data" => [
                "id" => (int)$finalUser['id'],
                "nome" => $finalUser['nome'],
                "email" => $finalUser['email'],
                "game_coins" => (int)$finalUser['game_coins'],
                "patente" => $finalUser['patente']
            ]
        ]);
    } catch (PDOException $e) {
        $conn->rollBack();
        echo json_encode(["status" => "error", "message" => "Erro de transação: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Dados incompletos"]);
}
?>
