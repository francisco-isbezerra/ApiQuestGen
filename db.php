<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

$host = "localhost";
$db_name = "db_questgen";
$username = "root";
$password = "";

try {
    $conn = new PDO("mysql:host=" . $host . ";dbname=" . $db_name . ";charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Erro de conexão com o banco de dados: " . $e->getMessage()
    ]);
    exit();
}

function obterPatente($conn, $userId, $coins) {
    $patente = "BRONZE";
    if ($coins < 1000) $patente = "BRONZE";
    else if ($coins < 3000) $patente = "PRATA";
    else if ($coins < 10000) $patente = "OURO";
    else if ($coins < 30000) $patente = "PLATINA";
    else if ($coins < 100000) $patente = "DIAMANTE";
    else $patente = "LENDÁRIO";
    
    try {
        $stmt = $conn->prepare("UPDATE usuarios SET patente = ? WHERE id = ? AND (patente IS NULL OR patente != ?)");
        $stmt->execute([$patente, $userId, $patente]);
    } catch (Exception $e) {
        // Ignorar erros silenciosamente
    }
    
    return $patente;
}
?>
