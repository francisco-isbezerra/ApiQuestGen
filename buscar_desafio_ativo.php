<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

require_once "db.php";
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : -1;

if ($userId != -1) {
    try {
        // 1. Verificar e auto-aprovar TODOS os desafios em PENDING_VALIDATION que já passaram de 60 segundos
        $stmtPending = $conn->prepare("
            SELECT du.desafio_id, dd.recompensa, dd.titulo
            FROM desafios_usuarios du
            JOIN desafios_disponiveis dd ON du.desafio_id = dd.id
            WHERE du.usuario_id = ? AND du.status = 'PENDING_VALIDATION' AND TIMESTAMPDIFF(SECOND, du.data_upload, NOW()) >= 60
        ");
        $stmtPending->execute([$userId]);
        $pendingList = $stmtPending->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($pendingList)) {
            $conn->beginTransaction();
            
            // Obter informações atuais do usuário
            $userQuery = $conn->prepare("SELECT game_coins, xp_total, is_premium, clan_id FROM usuarios WHERE id = ?");
            $userQuery->execute([$userId]);
            $u = $userQuery->fetch(PDO::FETCH_ASSOC);
            
            $isPremium = (int)$u['is_premium'];
            $clanId = $u['clan_id'] ? (int)$u['clan_id'] : null;

            $totalCoinsAdded = 0;
            $totalXpAdded = 0;

            foreach ($pendingList as $pending) {
                // Calcular recompensas
                $baseReward = (int)$pending['recompensa'];
                $rewardCoins = $isPremium ? ($baseReward * 2) : $baseReward;
                $rewardXp = $isPremium ? (25 * 2) : 25;

                $totalCoinsAdded += $rewardCoins;
                $totalXpAdded += $rewardXp;

                // Atualizar status do desafio para COMPLETED no banco de dados
                $up = $conn->prepare("UPDATE desafios_usuarios SET status = 'COMPLETED' WHERE usuario_id = ? AND desafio_id = ? AND status = 'PENDING_VALIDATION'");
                $up->execute([$userId, $pending['desafio_id']]);
            }

            // Atualizar saldo, XP do usuário
            $newXpTotal = (int)$u['xp_total'] + $totalXpAdded;
            $newLevel = 1 + intval($newXpTotal / 100);
            $newCoins = (int)$u['game_coins'] + $totalCoinsAdded;

            // Recalcular patente com o sistema centralizado
            $newRank = obterPatente($conn, $userId, $newCoins);

            $userUp = $conn->prepare("UPDATE usuarios SET game_coins = ?, xp_total = ?, nivel_atual = ?, patente = ? WHERE id = ?");
            $userUp->execute([$newCoins, $newXpTotal, $newLevel, $newRank, $userId]);

            // Se o usuário faz parte de um clã, creditar o XP ao clã também
            if ($clanId !== null) {
                $clanUp = $conn->prepare("UPDATE clans SET xp_total = xp_total + ? WHERE id = ?");
                $clanUp->execute([$totalXpAdded, $clanId]);
            }

            $conn->commit();

            // Retornar aviso de conclusão com o usuário atualizado
            $userFetch = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
            $userFetch->execute([$userId]);
            $userData = $userFetch->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                "status" => "completed",
                "message" => "Expedições aprovadas automaticamente! +" . $totalCoinsAdded . " GC creditados.",
                "data" => null,
                "updated_user" => [
                    "id" => (int)$userData['id'],
                    "nome" => $userData['nome'],
                    "email" => $userData['email'],
                    "game_coins" => (int)$userData['game_coins'],
                    "patente" => $userData['patente'],
                    "imagem_url" => $userData['imagem_url'],
                    "xp_total" => (int)$userData['xp_total'],
                    "nivel_atual" => (int)$userData['nivel_atual'],
                    "is_premium" => (int)$userData['is_premium'] == 1,
                    "moldura_neon" => $userData['moldura_neon'],
                    "clan_id" => $userData['clan_id'] ? (int)$userData['clan_id'] : null,
                    "descricao" => $userData['descricao']
                ]
            ]);
            exit();
        }

        // 2. Buscar o desafio ativo exatamente com status = 'ACTIVE'
        $stmtActive = $conn->prepare("
            SELECT du.desafio_id, du.status as status_usuario, du.data_limite, du.data_upload, du.imagem_comprovante, dd.jogo_id, dd.titulo, dd.descricao, dd.recompensa, dd.dificuldade, dd.raridade, 
                   TIMESTAMPDIFF(SECOND, NOW(), du.data_limite) as remaining_sec
            FROM desafios_usuarios du
            JOIN desafios_disponiveis dd ON du.desafio_id = dd.id
            WHERE du.usuario_id = ? AND du.status = 'ACTIVE'
            LIMIT 1
        ");
        $stmtActive->execute([$userId]);
        $rowActive = $stmtActive->fetch(PDO::FETCH_ASSOC);

        $activeChallenge = null;
        if ($rowActive) {
            $remaining = (int)$rowActive['remaining_sec'];
            
            // Se for ACTIVE e expirou
            if ($remaining <= 0) {
                try {
                    $conn->beginTransaction();
                    $update = $conn->prepare("UPDATE desafios_usuarios SET status = 'FALHOU' WHERE usuario_id = ? AND desafio_id = ? AND status = 'ACTIVE'");
                    $update->execute([$userId, $rowActive['desafio_id']]);
                    
                    $penalty = 50;
                    $deduct = $conn->prepare("UPDATE usuarios SET game_coins = GREATEST(0, game_coins - ?) WHERE id = ?");
                    $deduct->execute([$penalty, $userId]);
                    
                    $userQuery = $conn->prepare("SELECT game_coins, xp_total, is_premium, moldura_neon, clan_id FROM usuarios WHERE id = ?");
                    $userQuery->execute([$userId]);
                    $u = $userQuery->fetch(PDO::FETCH_ASSOC);
                    $totalCoins = (int)$u['game_coins'];
                    
                    $newRank = obterPatente($conn, $userId, $totalCoins);
                    
                    $conn->commit();
                    
                    $userFetch = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
                    $userFetch->execute([$userId]);
                    $userData = $userFetch->fetch(PDO::FETCH_ASSOC);
                    
                    echo json_encode([
                        "status" => "expired",
                        "message" => "O tempo expirou! Você falhou no desafio '" . $rowActive['titulo'] . "' e perdeu " . $penalty . " GC.",
                        "data" => null,
                        "updated_user" => [
                            "id" => (int)$userData['id'],
                            "nome" => $userData['nome'],
                            "email" => $userData['email'],
                            "game_coins" => (int)$userData['game_coins'],
                            "patente" => $userData['patente'],
                            "imagem_url" => $userData['imagem_url'],
                            "xp_total" => (int)$userData['xp_total'],
                            "nivel_atual" => (int)$userData['nivel_atual'],
                            "is_premium" => (int)$userData['is_premium'] == 1,
                            "moldura_neon" => $userData['moldura_neon'],
                            "clan_id" => $userData['clan_id'] ? (int)$userData['clan_id'] : null,
                            "descricao" => $userData['descricao']
                        ]
                    ]);
                    exit();
                } catch (Exception $ex) {
                    $conn->rollBack();
                    echo json_encode(["status" => "error", "message" => $ex->getMessage()]);
                    exit();
                }
            } else {
                $dificuldade = (int)$rowActive['dificuldade'];
                $tempoTotal = 86400; // 24h default
                if ($dificuldade == 1) $tempoTotal = 300; // 5m
                else if ($dificuldade == 2) $tempoTotal = 900; // 15m
                else if ($dificuldade == 3) $tempoTotal = 3600; // 1h
                else if ($dificuldade == 4) $tempoTotal = 14400; // 4h

                $activeChallenge = [
                    "id" => (int)$rowActive['desafio_id'],
                    "jogo_id" => (int)$rowActive['jogo_id'],
                    "titulo" => $rowActive['titulo'],
                    "descricao" => $rowActive['descricao'],
                    "recompensa" => (int)$rowActive['recompensa'],
                    "dificuldade" => $dificuldade,
                    "raridade" => $rowActive['raridade'],
                    "status" => $rowActive['status_usuario'],
                    "tempo_restante_segundos" => $remaining,
                    "tempo_total_segundos" => $tempoTotal,
                    "imagem_comprovante" => $rowActive['imagem_comprovante']
                ];
            }
        }

        // 3. Buscar TODOS os desafios sob análise com status = 'PENDING_VALIDATION'
        $stmtPendingList = $conn->prepare("
            SELECT du.desafio_id, du.status as status_usuario, du.data_limite, du.data_upload, du.imagem_comprovante, dd.jogo_id, dd.titulo, dd.descricao, dd.recompensa, dd.dificuldade, dd.raridade
            FROM desafios_usuarios du
            JOIN desafios_disponiveis dd ON du.desafio_id = dd.id
            WHERE du.usuario_id = ? AND du.status = 'PENDING_VALIDATION'
            ORDER BY du.data_upload DESC, du.id DESC
        ");
        $stmtPendingList->execute([$userId]);
        $rowsPending = $stmtPendingList->fetchAll(PDO::FETCH_ASSOC);

        $pendingChallenges = [];
        foreach ($rowsPending as $rowPending) {
            $dificuldade = (int)$rowPending['dificuldade'];
            $tempoTotal = 86400;
            if ($dificuldade == 1) $tempoTotal = 300;
            else if ($dificuldade == 2) $tempoTotal = 900;
            else if ($dificuldade == 3) $tempoTotal = 3600;
            else if ($dificuldade == 4) $tempoTotal = 14400;

            $pendingChallenges[] = [
                "id" => (int)$rowPending['desafio_id'],
                "jogo_id" => (int)$rowPending['jogo_id'],
                "titulo" => $rowPending['titulo'],
                "descricao" => $rowPending['descricao'],
                "recompensa" => (int)$rowPending['recompensa'],
                "dificuldade" => $dificuldade,
                "raridade" => $rowPending['raridade'],
                "status" => $rowPending['status_usuario'],
                "tempo_restante_segundos" => 0,
                "tempo_total_segundos" => $tempoTotal,
                "imagem_comprovante" => $rowPending['imagem_comprovante']
            ];
        }

        echo json_encode([
            "status" => "success",
            "active_challenge" => $activeChallenge,
            "pending_challenges" => $pendingChallenges
        ]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "ID do usuário faltando"]);
}
?>
