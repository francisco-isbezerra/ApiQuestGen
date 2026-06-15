<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

require_once "db.php";
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : -1;

if ($userId != -1) {
    try {
        $stmt = $conn->prepare("
            SELECT du.desafio_id as id, dd.jogo_id, dd.titulo, dd.descricao, dd.recompensa, dd.dificuldade, dd.raridade, du.status, du.data_aceito
            FROM desafios_usuarios du
            JOIN desafios_disponiveis dd ON du.desafio_id = dd.id
            WHERE du.usuario_id = ? AND du.status IN ('COMPLETED', 'FAILED', 'FORFEITED', 'FALHOU', 'DESISTIU', 'DESISTIDO')
            ORDER BY du.data_aceito DESC, du.id DESC
        ");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $history = [];
        foreach ($rows as $row) {
            $status = $row['status'];
            // Normalize status strings for android UI
            if ($status === 'FALHOU') $status = 'FAILED';
            if ($status === 'DESISTIU') $status = 'FORFEITED';
            
            $history[] = [
                "id" => (int)$row['id'],
                "jogo_id" => (int)$row['jogo_id'],
                "titulo" => $row['titulo'],
                "descricao" => $row['descricao'],
                "recompensa" => (int)$row['recompensa'],
                "dificuldade" => (int)$row['dificuldade'],
                "raridade" => $row['raridade'],
                "status" => $status
            ];
        }

        echo json_encode([
            "status" => "success",
            "data" => $history
        ]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "ID do usuário faltando"]);
}
?>
