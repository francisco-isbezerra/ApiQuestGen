<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once "db.php";

$data = json_decode(file_get_contents("php://input"), true);

if (!empty($data['user_id']) && !empty($data['product_id'])) {
    $userId = (int)$data['user_id'];
    $productId = (int)$data['product_id'];

    try {
        // Obter produto
        $pStmt = $conn->prepare("SELECT nome, preco, categoria FROM produtos WHERE id = ?");
        $pStmt->execute([$productId]);
        $prod = $pStmt->fetch(PDO::FETCH_ASSOC);

        if (!$prod) {
            echo json_encode(["status" => "error", "message" => "Produto não encontrado."]);
            exit();
        }

        $price = (int)$prod['preco'];
        $category = $prod['categoria'];
        $name = $prod['nome'];

        // Obter saldo do usuário
        $uStmt = $conn->prepare("SELECT game_coins FROM usuarios WHERE id = ?");
        $uStmt->execute([$userId]);
        $u = $uStmt->fetch(PDO::FETCH_ASSOC);

        if (!$u) {
            echo json_encode(["status" => "error", "message" => "Usuário não encontrado."]);
            exit();
        }

        $userCoins = (int)$u['game_coins'];

        if ($userCoins < $price) {
            echo json_encode(["status" => "error", "message" => "Saldo insuficiente! Você precisa de " . $price . " GC."]);
            exit();
        }

        $conn->beginTransaction();

        // Deduzir moedas
        $deduct = $conn->prepare("UPDATE usuarios SET game_coins = game_coins - ? WHERE id = ?");
        $deduct->execute([$price, $userId]);

        // Se for um cosmético (moldura), aplicar moldura_neon no usuário
        if ($category === "Cosméticos") {
            $borderStyle = "neon_ciano";
            $lowerName = strtolower($name);
            if (strpos($lowerName, 'roxo') !== false) {
                $borderStyle = "neon_roxo";
            } else if (strpos($lowerName, 'ouro') !== false || strpos($lowerName, 'lend') !== false) {
                $borderStyle = "ouro_lendario";
            }
            
            $applyBorder = $conn->prepare("UPDATE usuarios SET moldura_neon = ? WHERE id = ?");
            $applyBorder->execute([$borderStyle, $userId]);
        }

        $conn->commit();

        // Obter usuário atualizado
        $finalQuery = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
        $finalQuery->execute([$userId]);
        $finalUser = $finalQuery->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            "status" => "success",
            "message" => "Compra efetuada! '" . $name . "' resgatado com sucesso.",
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
        echo json_encode(["status" => "error", "message" => "Erro na compra: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Dados incompletos"]);
}
?>
