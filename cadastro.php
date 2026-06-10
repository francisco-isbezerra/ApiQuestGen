<?php
require_once "db.php";
$data = json_decode(file_get_contents("php://input"), true);

if (!empty($data['name']) && !empty($data['email']) && !empty($data['password'])) {
    $nome = $data['name'];
    $email = $data['email'];
    $senha = password_hash($data['password'], PASSWORD_DEFAULT);

    try {
        $check = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
        $check->execute([$email]);
        if ($check->rowCount() > 0) {
            echo json_encode(["status" => "error", "message" => "E-mail já está cadastrado"]);
            exit();
        }

        $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha) VALUES (?, ?, ?)");
        $stmt->execute([$nome, $email, $senha]);
        
        $userId = $conn->lastInsertId();
        
        echo json_encode([
            "status" => "success",
            "message" => "Cadastro realizado",
            "data" => [
                "id" => (int)$userId,
                "nome" => $nome,
                "email" => $email,
                "game_coins" => 1000,
                "patente" => "RECRUTA"
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Erro no servidor: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Preencha todos os campos obrigatórios"]);
}
?>
