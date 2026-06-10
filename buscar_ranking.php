<?php
require_once "db.php";
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : -1;

try {
    $stmt = $conn->query("SELECT id, nome, game_coins, patente FROM usuarios ORDER BY game_coins DESC LIMIT 50");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result = [];
    $position = 1;
    foreach ($users as $u) {
        $result[] = [
            "position" => $position++,
            "nome" => $u['nome'],
            "game_coins" => (int)$u['game_coins'],
            "patente" => $u['patente'],
            "is_current_user" => ($userId != -1 && (int)$u['id'] === $userId)
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
