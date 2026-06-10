<?php
require_once "db.php";

try {
    $stmt = $conn->query("SELECT * FROM produtos");
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result = [];
    foreach ($produtos as $p) {
        $result[] = [
            "id" => (int)$p['id'],
            "name" => $p['nome'],
            "price" => (int)$p['preco'],
            "image_url" => $p['imagem_url'],
            "category" => $p['categoria']
        ];
    }
    echo json_encode([
        "status" => "success",
        "data" => $result
    ]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
