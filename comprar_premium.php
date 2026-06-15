<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once "db.php";

$data = json_decode(file_get_contents("php://input"), true);

if (!empty($data['user_id'])) {
    $userId = (int)$data['user_id'];

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("UPDATE usuarios SET is_premium = 1 WHERE id = ?");
        $stmt->execute([$userId]);

        $conn->commit();

        // Obter usuário atualizado
        $finalQuery = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
        $finalQuery->execute([$userId]);
        $finalUser = $finalQuery->fetch(PDO::FETCH_ASSOC);

        if ($finalUser) {
            echo json_encode([
                "status" => "success",
                "message" => "Upgrade realizado! Bem-vindo ao Passe de Elite Premium.",
                "data" => [
                    "id" => (int)$finalUser['id'],
                    "nome" => $finalUser['nome'],
                    "email" => $finalUser['email'],
                    "game_coins" => (int)$finalUser['game_coins'],
                    "patente" => obterPatente($conn, (int)$finalUser['id'], (int)$finalUser['game_coins']),
                    "imagem_url" => $finalUser['imagem_url'],
                    "xp_total" => (int)$finalUser['xp_total'],
                    "nivel_atual" => (int)$finalUser['nivel_atual'],
                    "is_premium" => (int)$finalUser['is_premium'] == 1,
                    "moldura_neon" => $finalUser['moldura_neon'],
                    "clan_id" => $finalUser['clan_id'] ? (int)$finalUser['clan_id'] : null,
                    "descricao" => $finalUser['descricao']
                ]
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "Usuário não encontrado."]);
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        echo json_encode(["status" => "error", "message" => "Erro de banco de dados: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Dados incompletos"]);
}
?>
