<?php
require_once "db.php";

try {
    $stmt = $conn->query("SELECT * FROM jogos");
    $jogos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $result = [];
    foreach ($jogos as $j) {
        $result[] = [
            "id" => (int)$j['id'],
            "titulo" => $j['titulo'],
            "imagem_url" => $j['imagem_url'],
            "categoria" => $j['categoria']
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
