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

        $limitTime = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $ins = $conn->prepare("INSERT INTO desafios_usuarios (usuario_id, desafio_id, status, data_limite) VALUES (?, ?, 'ACTIVE', ?)");
        $ins->execute([$userId, $challengeId, $limitTime]);

        echo json_encode([
            "status" => "success",
            "data" => [
                "id" => (int)$template['id'],
                "jogo_id" => (int)$template['jogo_id'],
                "titulo" => $template['titulo'],
                "descricao" => $template['descricao'],
                "recompensa" => (int)$template['recompensa'],
                "dificuldade" => (int)$template['dificuldade'],
                "raridade" => $template['raridade'],
                "status" => "ACTIVE",
                "tempo_restante_segundos" => 900
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "IDs de usuário ou desafio inválidos"]);
}
?>
