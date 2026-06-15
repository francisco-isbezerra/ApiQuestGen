<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

require_once "db.php";

try {
    $stmt = $conn->query("SELECT id, nome, tag, lider_id, logo_url, xp_total FROM clans ORDER BY xp_total DESC");
    $clans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($clans as $c) {
        $result[] = [
            "id" => (int)$c['id'],
            "nome" => $c['nome'],
            "tag" => $c['tag'],
            "lider_id" => (int)$c['lider_id'],
            "logo_url" => $c['logo_url'],
            "xp_total" => (int)$c['xp_total']
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
