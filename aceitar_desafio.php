<?php
require_once "db.php";
$data = json_decode(file_get_contents("php://input"), true);

if (!empty($data['user_id']) && !empty($data['challenge_id'])) {
    $userId = (int)$data['user_id'];
    $challengeId = (int)$data['challenge_id'];

    try {
        $stmt = $conn->prepare("SELECT * FROM desafios_disponiveis WHERE id = ?");
        $stmt->execute([$challengeId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$template) {
            echo json_encode(["status" => "error", "message" => "Desafio inexistente"]);
            exit();
        }

        $dificuldade = (int)$template['dificuldade'];
        $tempoTotal = 86400; // 24h default
        if ($dificuldade == 1) $tempoTotal = 300; // 5m
        else if ($dificuldade == 2) $tempoTotal = 900; // 15m
        else if ($dificuldade == 3) $tempoTotal = 3600; // 1h
        else if ($dificuldade == 4) $tempoTotal = 14400; // 4h

        $ins = $conn->prepare("INSERT INTO desafios_usuarios (usuario_id, desafio_id, status, data_limite) VALUES (?, ?, 'ACTIVE', DATE_ADD(NOW(), INTERVAL ? SECOND))");
        $ins->execute([$userId, $challengeId, $tempoTotal]);

        echo json_encode([
            "status" => "success",
            "data" => [
                "id" => (int)$template['id'],
                "jogo_id" => (int)$template['jogo_id'],
                "titulo" => $template['titulo'],
                "descricao" => $template['descricao'],
                "recompensa" => (int)$template['recompensa'],
                "dificuldade" => $dificuldade,
                "raridade" => $template['raridade'],
                "status" => "ACTIVE",
                "tempo_restante_segundos" => $tempoTotal,
                "tempo_total_segundos" => $tempoTotal
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "IDs de usuário ou desafio inválidos"]);
}
?>
