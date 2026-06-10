<?php
require_once "db.php";
// Busca os desafios pelo ID do jogo
$gameId = isset($_GET['game_id']) ? (int)$_GET['game_id'] : -1;
try {
    if ($gameId != -1) {
        $stmt = $conn->prepare("SELECT * FROM desafios_disponiveis WHERE jogo_id = ? ORDER BY id ASC");
        $stmt->execute([$gameId]);
    } else {
        $stmt = $conn->query("SELECT * FROM desafios_disponiveis ORDER BY id ASC");
    }
    $desafios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $result = [];
    foreach ($desafios as $d) {
        $result[] = [
            "id" => (int)$d['id'],
            "jogo_id" => (int)$d['jogo_id'],
            "titulo" => $d['titulo'],
            "descricao" => $d['descricao'],
            "recompensa" => (int)$d['recompensa'],
            "dificuldade" => (int)$d['dificuldade'],
            "raridade" => $d['raridade']
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