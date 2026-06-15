<?php
require_once "db.php";
$data = json_decode(file_get_contents("php://input"), true);

if (!empty($data['user_id']) && !empty($data['challenge_id'])) {
    $userId = (int)$data['user_id'];
    $challengeId = (int)$data['challenge_id'];

    try {
        $stmt = $conn->prepare("SELECT id FROM desafios_usuarios WHERE usuario_id = ? AND desafio_id = ? AND status = 'ACTIVE'");
        $stmt->execute([$userId, $challengeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(["status" => "error", "message" => "Nenhum desafio ativo para cancelar."]);
            exit();
        }

        $conn->beginTransaction();
        $up = $conn->prepare("UPDATE desafios_usuarios SET status = 'DESISTIU' WHERE id = ?");
        $up->execute([$row['id']]);

        $pen = $conn->prepare("UPDATE usuarios SET game_coins = GREATEST(0, game_coins - 50) WHERE id = ?");
        $pen->execute([$userId]);

        // Recalculate Rank dynamically using centralized obterPatente()
        $userQuery = $conn->prepare("SELECT game_coins FROM usuarios WHERE id = ?");
        $userQuery->execute([$userId]);
        $u = $userQuery->fetch(PDO::FETCH_ASSOC);
        $totalCoins = (int)$u['game_coins'];

        $newRank = obterPatente($conn, $userId, $totalCoins);

        $conn->commit();

        $finalQuery = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
        $finalQuery->execute([$userId]);
        $finalUser = $finalQuery->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            "status" => "success",
            "message" => "Você desistiu do desafio. Penalidade de -50 GC aplicada.",
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
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Parâmetros inválidos"]);
}
?>
