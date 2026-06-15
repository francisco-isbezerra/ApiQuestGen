<?php
require_once "db.php";
$data = json_decode(file_get_contents("php://input"), true);

if (!empty($data['email']) && !empty($data['password'])) {
    $email = $data['email'];
    $password = $data['password'];

    try {
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['senha'])) {
            echo json_encode([
                "status" => "success",
                "message" => "Login efetuado na arena",
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
        } else {
            echo json_encode(["status" => "error", "message" => "Credenciais inválidas"]);
        }
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Erro no servidor: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Preencha os campos obrigatórios"]);
}
?>
