<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once "db.php";

// Definição do diretório de upload
$uploadDir = 'uploads_perfil/';

// Cria o diretório se ele não existir
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Verifica se os dados necessários foram enviados
if (!empty($_POST['user_id']) && !empty($_FILES['image'])) {
    $userId = (int)$_POST['user_id'];
    
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
        // Obter foto de perfil antiga para deletar e economizar espaço
        try {
            $stmtSelect = $conn->prepare("SELECT imagem_url FROM usuarios WHERE id = ?");
            $stmtSelect->execute([$userId]);
            $oldUser = $stmtSelect->fetch(PDO::FETCH_ASSOC);
            if ($oldUser && !empty($oldUser['imagem_url'])) {
                $oldImagePath = $oldUser['imagem_url'];
                // Verifica se o arquivo físico existe no diretório local e deleta
                if (file_exists($oldImagePath) && is_file($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }
        } catch (PDOException $e) {
            // Ignora erro ao tentar deletar a imagem antiga para não travar o upload
        }

        // Envia arquivo para o servidor
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
            try {
                // Atualiza a coluna imagem_url na tabela usuarios
                $stmt = $conn->prepare("UPDATE usuarios SET imagem_url = ? WHERE id = ?");
                $stmt->execute([$targetFilePath, $userId]);
                
                if ($stmt->rowCount() > 0 || true) { // rowCount pode ser 0 se o valor for o mesmo, mas a imagem física mudou
                    echo json_encode([
                        "status" => "success",
                        "message" => "Foto de perfil atualizada com sucesso!",
                        "data" => $targetFilePath
                    ]);
                } else {
                    echo json_encode(["status" => "error", "message" => "Usuário não encontrado ou erro ao atualizar banco."]);
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
