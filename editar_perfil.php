<?php
require_once "db.php";
$data = json_decode(file_get_contents("php://input"), true);

if (!empty($data['user_id']) && !empty($data['name'])) {
    $userId = (int)$data['user_id'];
    $nome = $data['name'];
    $imageUrl = isset($data['image_url']) ? $data['image_url'] : null;
    
    // Treat empty image url as null (no profile picture)
    if (trim($imageUrl) === "") {
        $imageUrl = null;
    }

    try {
        $stmt = $conn->prepare("UPDATE usuarios SET nome = ?, imagem_url = ? WHERE id = ?");
        $stmt->execute([$nome, $imageUrl, $userId]);

        // Fetch refreshed user info
        $query = $conn->prepare("SELECT id, nome, email, game_coins, patente, imagem_url FROM usuarios WHERE id = ?");
        $query->execute([$userId]);
        $user = $query->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            "status" => "success",
            "message" => "Perfil atualizado com sucesso!",
            "data" => [
                "id" => (int)$user['id'],
                "nome" => $user['nome'],
                "email" => $user['email'],
                "game_coins" => (int)$user['game_coins'],
                "patente" => $user['patente'],
                "imagem_url" => $user['imagem_url']
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Erro ao atualizar: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Dados incompletos"]);
}
?>
