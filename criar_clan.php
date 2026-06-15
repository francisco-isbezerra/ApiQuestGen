<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once "db.php";

$data = json_decode(file_get_contents("php://input"), true);

if (!empty($data['user_id']) && !empty($data['nome']) && !empty($data['tag'])) {
    $userId = (int)$data['user_id'];
    $nome = trim($data['nome']);
    $tag = strtoupper(trim($data['tag']));
    $descricao = isset($data['description']) ? trim($data['description']) : "";

    if (strlen($tag) < 2 || strlen($tag) > 5) {
        echo json_encode(["status" => "error", "message" => "A tag do clã deve ter entre 2 e 5 caracteres."]);
        exit();
    }

    try {
        // Obter informações do usuário
        $uStmt = $conn->prepare("SELECT game_coins, is_premium, clan_id FROM usuarios WHERE id = ?");
        $uStmt->execute([$userId]);
        $u = $uStmt->fetch(PDO::FETCH_ASSOC);

        if (!$u) {
            echo json_encode(["status" => "error", "message" => "Usuário não encontrado."]);
            exit();
        }

        if ($u['clan_id'] !== null) {
            echo json_encode(["status" => "error", "message" => "Você já faz parte de um clã!"]);
            exit();
        }

        $userCoins = (int)$u['game_coins'];
        $isPremium = (int)$u['is_premium'] == 1;

        // Custo de criação: Grátis se for Premium, caso contrário 5.000 GC
        $cost = $isPremium ? 0 : 5000;

        if ($userCoins < $cost) {
            echo json_encode(["status" => "error", "message" => "Saldo insuficiente! Criar um clã exige status Premium ou 5.000 GameCoins."]);
            exit();
        }

        $conn->beginTransaction();

        // Deduzir moedas se aplicável
        if ($cost > 0) {
            $deduct = $conn->prepare("UPDATE usuarios SET game_coins = game_coins - ? WHERE id = ?");
            $deduct->execute([$cost, $userId]);
        }

        // Criar o clã na tabela clans
        $logoUrl = "https://images.unsplash.com/photo-1542751371-adc38448a05e?q=80&w=100&auto=format&fit=crop"; // Logo padrão
        $clanInsert = $conn->prepare("INSERT INTO clans (nome, tag, lider_id, logo_url, xp_total, descricao) VALUES (?, ?, ?, ?, 0, ?)");
        $clanInsert->execute([$nome, $tag, $userId, $logoUrl, $descricao]);
        $clanId = $conn->lastInsertId();

        // Vincular usuário ao clã
        $userLink = $conn->prepare("UPDATE usuarios SET clan_id = ? WHERE id = ?");
        $userLink->execute([$clanId, $userId]);

        $conn->commit();

        // Obter usuário atualizado
        $finalQuery = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
        $finalQuery->execute([$userId]);
        $finalUser = $finalQuery->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            "status" => "success",
            "message" => "Clã '" . $nome . "' [" . $tag . "] criado com sucesso!",
            "data" => [
                "id" => (int)$finalUser['id'],
                "nome" => $finalUser['nome'],
                "email" => $finalUser['email'],
                "game_coins" => (int)$finalUser['game_coins'],
                "patente" => obterPatente($conn, (int)$finalUser['id'], (int)$finalUser['game_coins']),
                "imagem_url" => $finalUser['imagem_url'],
                "xp_total" => (int)$finalUser['xp_total'],
                "nivel_atual" => (int)$finalUser['nivel_atual'],
                "is_premium" => (int)$finalUser['is_premium'] == 1,
                "moldura_neon" => $finalUser['moldura_neon'],
                "clan_id" => $finalUser['clan_id'] ? (int)$finalUser['clan_id'] : null,
                "descricao" => $finalUser['descricao']
            ]
        ]);

    } catch (PDOException $e) {
        $conn->rollBack();
        echo json_encode(["status" => "error", "message" => "Erro de transação: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Dados do clã incompletos."]);
}
?>
