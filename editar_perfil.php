<?php
require_once "db.php";
$data = json_decode(file_get_contents("php://input"), true);

if (!empty($data['user_id']) && !empty($data['name'])) {
    $userId = (int)$data['user_id'];
    $nome = $data['name'];
    $imageUrl = isset($data['image_url']) ? $data['image_url'] : null;
    $descricao = isset($data['description']) ? $data['description'] : null;
    
    // Treat empty image url as null (no profile picture)
    if (trim($imageUrl) === "") {
        $imageUrl = null;
    }

    try {
        $stmt = $conn->prepare("UPDATE usuarios SET nome = ?, imagem_url = ?, descricao = ? WHERE id = ?");
        $stmt->execute([$nome, $imageUrl, $descricao, $userId]);

        // Fetch refreshed user info
        $query = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
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
                "patente" => obterPatente($conn, (int)$user['id'], (int)$user['game_coins']),
                "imagem_url" => $user['imagem_url'],
                "xp_total" => (int)$user['xp_total'],
                "nivel_atual" => (int)$user['nivel_atual'],
                "is_premium" => (int)$user['is_premium'] == 1,
                "moldura_neon" => $user['moldura_neon'],
                "clan_id" => $user['clan_id'] ? (int)$user['clan_id'] : null,
                "descricao" => $user['descricao']
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Erro ao atualizar: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Dados incompletos"]);
}
?>
