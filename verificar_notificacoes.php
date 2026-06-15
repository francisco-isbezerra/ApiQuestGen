<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

require_once "db.php";

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : -1;

if ($userId != -1) {
    try {
        $conn->beginTransaction();

        // Query for any challenge that completed or failed and has not been notified yet
        $stmt = $conn->prepare("
            SELECT du.id as row_id, du.desafio_id, du.status, dd.titulo, dd.recompensa
            FROM desafios_usuarios du
            JOIN desafios_disponiveis dd ON du.desafio_id = dd.id
            WHERE du.usuario_id = ? AND du.notificado_app = 0 AND (du.status = 'COMPLETED' OR du.status = 'FAILED' OR du.status = 'FALHOU')
            ORDER BY du.data_upload DESC, du.id DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $rowId = (int)$row['row_id'];
            
            // Mark as notified in app
            $update = $conn->prepare("UPDATE desafios_usuarios SET notificado_app = 1 WHERE id = ?");
            $update->execute([$rowId]);

            $conn->commit();

            echo json_encode([
                "status" => "success",
                "houve_mudanca" => true,
                "resultado" => $row['status'],
                "titulo_desafio" => $row['titulo'],
                "recompensa" => (int)$row['recompensa']
            ]);
        } else {
            $conn->commit();
            echo json_encode([
                "status" => "success",
                "houve_mudanca" => false
            ]);
        }
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "ID do usuário faltando"]);
}
?>
