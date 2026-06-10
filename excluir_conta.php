<?php
require_once "db.php";
$data = json_decode(file_get_contents("php://input"), true);

if (!empty($data['user_id'])) {
    $userId = (int)$data['user_id'];

    try {
        $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$userId]);

        echo json_encode([
            "status" => "success",
            "message" => "Sua conta foi permanentemente excluída da arena."
        ]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Erro ao excluir conta: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "ID do usuário não fornecido"]);
}
?>
