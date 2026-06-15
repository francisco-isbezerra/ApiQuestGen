<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once "db.php";

$data = json_decode(file_get_contents("php://input"), true);

if (!empty($data['user_id']) && !empty($data['challenge_id'])) {
    $userId = (int)$data['user_id'];
    $challengeId = (int)$data['challenge_id'];

    try {
        $stmt = $conn->prepare("
            SELECT du.id, dd.recompensa, dd.titulo
            FROM desafios_usuarios du
            JOIN desafios_disponiveis dd ON du.desafio_id = dd.id
            WHERE du.usuario_id = ? AND du.desafio_id = ? AND du.status = 'PENDING_VALIDATION'
        ");
        $stmt->execute([$userId, $challengeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(["status" => "error", "message" => "Nenhum comprovante pendente encontrado para este desafio!"]);
            exit();
        }

        $conn->beginTransaction();

        // Obter informações atuais do usuário
        $userQuery = $conn->prepare("SELECT game_coins, xp_total, is_premium, clan_id FROM usuarios WHERE id = ?");
        $userQuery->execute([$userId]);
        $u = $userQuery->fetch(PDO::FETCH_ASSOC);
        
        $isPremium = (int)$u['is_premium'];
        $clanId = $u['clan_id'] ? (int)$u['clan_id'] : null;

        // Calcular recompensas
        $baseReward = (int)$row['recompensa'];
        $rewardCoins = $isPremium ? ($baseReward * 2) : $baseReward;
        
        $baseXp = 25;
        $rewardXp = $isPremium ? ($baseXp * 2) : $baseXp;

        // Atualizar status do desafio
        $up = $conn->prepare("UPDATE desafios_usuarios SET status = 'COMPLETED' WHERE id = ?");
        $up->execute([$row['id']]);

        // Atualizar saldo, XP do usuário
        $newXpTotal = (int)$u['xp_total'] + $rewardXp;
        $newLevel = 1 + intval($newXpTotal / 100);
        $newCoins = (int)$u['game_coins'] + $rewardCoins;

        // Recalcular patente com o sistema centralizado
        $newRank = obterPatente($conn, $userId, $newCoins);

        $userUp = $conn->prepare("UPDATE usuarios SET game_coins = ?, xp_total = ?, nivel_atual = ?, patente = ? WHERE id = ?");
        $userUp->execute([$newCoins, $newXpTotal, $newLevel, $newRank, $userId]);

        // Se o usuário faz parte de um clã, creditar o XP ao clã também
        if ($clanId !== null) {
            $clanUp = $conn->prepare("UPDATE clans SET xp_total = xp_total + ? WHERE id = ?");
            $clanUp->execute([$rewardXp, $clanId]);
        }

        $conn->commit();

        // Obter usuário atualizado
        $finalQuery = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
        $finalQuery->execute([$userId]);
        $finalUser = $finalQuery->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            "status" => "success",
            "message" => "Desafio validado! +" . $rewardCoins . " GC e +" . $rewardXp . " XP creditados.",
            "data" => [
                "id" => (int)$finalUser['id'],
                "nome" => $finalUser['nome'],
                "email" => $finalUser['email'],
                "game_coins" => (int)$finalUser['game_coins'],
                "patente" => $finalUser['patente'],
                "imagem_url" => $finalUser['imagem_url'],
                "xp_total" => (int)$finalUser['xp_total'],
                "nivel_atual" => (int)$finalUser['nivel_atual'],
                "is_premium" => (int)$finalUser['is_premium'] == 1,
                "moldura_neon" => $finalUser['moldura_neon'],
                "clan_id" => $finalUser['clan_id'] ? (int)$finalUser['clan_id'] : null,
                "descricao" => $finalUser['descricao']
            ]
        ]);

    } catch (PDOException $e) {
        $conn->rollBack();
        echo json_encode(["status" => "error", "message" => "Erro na validação: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Dados incompletos"]);
}
?>
