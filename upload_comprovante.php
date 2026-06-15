<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once "db.php";

// Definição do diretório de upload
$uploadDir = 'uploads_comprovantes/';

// Cria o diretório se ele não existir
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Verifica se os dados necessários foram enviados
if (!empty($_POST['user_id']) && !empty($_POST['challenge_id']) && !empty($_FILES['image'])) {
    $userId = (int)$_POST['user_id'];
    $challengeId = (int)$_POST['challenge_id'];
    
    // Obtém informações da imagem
    $fileName = basename($_FILES["image"]["name"]);
    
    // Gera um nome de arquivo seguro para evitar colisões
    $temp = explode(".", $_FILES["image"]["name"]);
    $newfilename = round(microtime(true)) . '_' . rand(1000, 9999) . '.' . end($temp);
    $targetFilePath = $uploadDir . $newfilename;
    
    $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
    
    // Lista de extensões permitidas
    $allowTypes = array('jpg', 'png', 'jpeg', 'gif');
    
    if (in_array($fileType, $allowTypes)) {
        // Envia arquivo para o servidor
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
            // Insere o nome da imagem e atualiza o status no banco de dados
            try {
                $stmt = $conn->prepare("UPDATE desafios_usuarios SET imagem_comprovante = ?, status = 'PENDING_VALIDATION', data_upload = NOW() WHERE usuario_id = ? AND desafio_id = ? AND status = 'ACTIVE' AND data_limite > NOW()");
                $stmt->execute([$newfilename, $userId, $challengeId]);
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode([
                        "status" => "success",
                        "message" => "O comprovante foi enviado para validação."
                    ]);
                } else {
                    echo json_encode(["status" => "error", "message" => "Desafio não encontrado, tempo expirado ou comprovante já enviado."]);
                }
            } catch (PDOException $e) {
                echo json_encode(["status" => "error", "message" => "Erro ao atualizar o banco de dados: " . $e->getMessage()]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Erro ao salvar a imagem no servidor."]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Apenas arquivos JPG, JPEG, PNG e GIF são permitidos."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Dados incompletos."]);
}
?>
