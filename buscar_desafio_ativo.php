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
                try {
                    $conn->beginTransaction();
                    $update = $conn->prepare("UPDATE desafios_usuarios SET status = 'FALHOU' WHERE usuario_id = ? AND desafio_id = ? AND status = 'ACTIVE'");
                    $update->execute([$userId, $row['desafio_id']]);
                    
                    $penalty = 50;
                    $deduct = $conn->prepare("UPDATE usuarios SET game_coins = GREATEST(0, game_coins - ?) WHERE id = ?");
                    $deduct->execute([$penalty, $userId]);
                    
                    $userQuery = $conn->prepare("SELECT game_coins FROM usuarios WHERE id = ?");
                    $userQuery->execute([$userId]);
                    $u = $userQuery->fetch(PDO::FETCH_ASSOC);
                    $totalCoins = (int)$u['game_coins'];
                    
                    $newRank = "RECRUTA";
                    if ($totalCoins >= 10000) $newRank = "LEGENDARY EXPLORER";
                    else if ($totalCoins >= 5000) $newRank = "ELITE FIGHTER";
                    else if ($totalCoins >= 2500) $newRank = "VETERANO";
                    
                    $rankUp = $conn->prepare("UPDATE usuarios SET patente = ? WHERE id = ?");
                    $rankUp->execute([$newRank, $userId]);
                    
                    $conn->commit();
                    
                    $userFetch = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
                    $userFetch->execute([$userId]);
                    $userData = $userFetch->fetch(PDO::FETCH_ASSOC);
                    
                    echo json_encode([
                        "status" => "expired",
                        "message" => "O tempo expirou! Você falhou no desafio '" . $row['titulo'] . "' e perdeu " . $penalty . " GC.",
                        "data" => null,
                        "updated_user" => [
                            "id" => (int)$userData['id'],
                            "nome" => $userData['nome'],
                            "email" => $userData['email'],
                            "game_coins" => (int)$userData['game_coins'],
                            "patente" => $userData['patente'],
                            "imagem_url" => $userData['imagem_url']
                        ]
                    ]);
                } catch (Exception $ex) {
                    $conn->rollBack();
                    echo json_encode(["status" => "error", "message" => $ex->getMessage()]);
                }
            } else {
                    $dificuldade = (int)$row['dificuldade'];
                    $tempoTotal = 86400; // 24h default
                    if ($dificuldade == 1) $tempoTotal = 300; // 5m
                    else if ($dificuldade == 2) $tempoTotal = 900; // 15m
                    else if ($dificuldade == 3) $tempoTotal = 3600; // 1h
                    else if ($dificuldade == 4) $tempoTotal = 14400; // 4h

                    echo json_encode([
                        "status" => "success",
                        "data" => [
                            "id" => (int)$row['desafio_id'],
                            "jogo_id" => (int)$row['jogo_id'],
                            "titulo" => $row['titulo'],
                            "descricao" => $row['descricao'],
                            "recompensa" => (int)$row['recompensa'],
                            "dificuldade" => $dificuldade,
                            "raridade" => $row['raridade'],
                            "status" => "ACTIVE",
                            "tempo_restante_segundos" => (int)$remaining,
                            "tempo_total_segundos" => $tempoTotal
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
