<?php
require_once "db.php";
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : -1;

if ($userId != -1) {
    try {
        $stmt = $conn->prepare("
            SELECT du.desafio_id, du.data_limite, dd.jogo_id, dd.titulo, dd.descricao, dd.recompensa, dd.dificuldade, dd.raridade, 
                   TIMESTAMPDIFF(SECOND, NOW(), du.data_limite) as remaining_sec
            FROM desafios_usuarios du
            JOIN desafios_disponiveis dd ON du.desafio_id = dd.id
            WHERE du.usuario_id = ? AND du.status = 'ACTIVE'
            ORDER BY du.id DESC LIMIT 1
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $remaining = (int)$row['remaining_sec'];
            
            if ($remaining <= 0) {
                $update = $conn->prepare("UPDATE desafios_usuarios SET status = 'FALHOU' WHERE usuario_id = ? AND desafio_id = ? AND status = 'ACTIVE'");
                $update->execute([$userId, $row['desafio_id']]);
                
                echo json_encode([
                    "status" => "success",
                    "data" => null
                ]);
            } else {
                echo json_encode([
                    "status" => "success",
                    "data" => [
                        "id" => (int)$row['desafio_id'],
                        "jogo_id" => (int)$row['jogo_id'],
                        "titulo" => $row['titulo'],
                        "descricao" => $row['descricao'],
                        "recompensa" => (int)$row['recompensa'],
                        "dificuldade" => (int)$row['dificuldade'],
                        "raridade" => $row['raridade'],
                        "status" => "ACTIVE",
                        "tempo_restante_segundos" => (int)$remaining
                    ]
                ]);
            }
        } else {
            echo json_encode([
                "status" => "success",
                "data" => null
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "ID do usuário faltando"]);
}
?>
