<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once "db.php";

try {
    $stmt = $conn->query("SELECT * FROM produtos");
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result = [];
    
    foreach ($produtos as $p) {
        $desc = isset($p['description']) ? $p['description'] : (isset($p['descricao']) ? $p['descricao'] : '');
        
        $rawCat = '';
        if (isset($p['category']) && trim($p['category']) !== '') {
            $rawCat = trim($p['category']);
        } elseif (isset($p['categoria']) && trim($p['categoria']) !== '') {
            $rawCat = trim($p['categoria']);
        }

        // Fallback inteligente caso a categoria no banco esteja vazia
        if ($rawCat === '') {
            $nomeLower = strtolower($p['nome']);
            if (strpos($nomeLower, 'gift') !== false || strpos($nomeLower, 'card') !== false || strpos($nomeLower, 'cupom') !== false) {
                $rawCat = 'Gift Cards';
            } elseif (strpos($nomeLower, 'skin') !== false || strpos($nomeLower, 'moletom') !== false || strpos($nomeLower, 'cyber') !== false) {
                $rawCat = 'Skins';
            } else {
                $rawCat = 'Periféricos';
            }
        }
        
        $result[] = [
            "id" => (int)$p['id'],
            "name" => $p['nome'],
            "price" => (int)$p['preco'],
            "image_url" => trim($p['imagem_url']),
            "category" => $rawCat,
            "description" => $desc
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
