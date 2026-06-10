<?php
require_once "db.php";

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : -1;
$gameId = isset($_GET['game_id']) ? (int)$_GET['game_id'] : -1;

if ($userId != -1 && $gameId != -1) {
    try {
        $check = $conn->prepare("SELECT * FROM desafios_usuarios WHERE usuario_id = ? AND status = 'ACTIVE' AND data_limite > NOW()");
        $check->execute([$userId]);
        if ($check->rowCount() > 0) {
            echo json_encode(["status" => "error", "message" => "Você já possui um desafio ativo pendente"]);
            exit();
        }

        $stmt = $conn->prepare("SELECT * FROM desafios_disponiveis WHERE jogo_id = ? ORDER BY RAND() LIMIT 1");
        $stmt->execute([$gameId]);
        $challenge = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($challenge) {
            echo json_encode([
                "status" => "success",
                "data" => [
                    "id" => (int)$challenge['id'],
                    "jogo_id" => (int)$challenge['jogo_id'],
                    "titulo" => $challenge['titulo'],
                    "descricao" => $challenge['descricao'],
                    "recompensa" => (int)$challenge['recompensa'],
                    "dificuldade" => (int)$challenge['dificuldade'],
                    "raridade" => $challenge['raridade'],
                    "status" => "AVAILABLE",
                    "tempo_restante_segundos" => 900 
                ]
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "Não há missões disponíveis para este jogo"]);
        }
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Faltam parâmetros obrigatórios"]);
}
?>
