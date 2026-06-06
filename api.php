<?php
declare(strict_types=1);
header('Content-Type: application/json');

$configFile = __DIR__ . '/config.json';
if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'Sistema nao instalado. Execute o install.php.']);
    exit;
}

$config = json_decode(file_get_contents($configFile), true);
$env = $config['env'] ?? [];
$apiUrl = rtrim($env['ANTHROPIC_BASE_URL'] ?? '', '/') . "/v1/messages";
$apiKey = $env['ANTHROPIC_AUTH_TOKEN'] ?? '';

if (empty($apiKey) || empty($env['ANTHROPIC_BASE_URL'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Configuracoes de API vazias no config.json.']);
    exit;
}

$messageText = $_POST['message'] ?? '';
$model = $_POST['model'] ?? ($env['ANTHROPIC_DEFAULT_SONNET_MODEL'] ?? 'claude-sonnet-4-6');
$historyPayload = $_POST['history'] ?? '[]';
$history = json_decode($historyPayload, true);

$pdfText = '';
$imageBase64 = null;
$userMessageContent = $messageText;

if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $tmpName = $_FILES['file']['tmp_name'];
    $mimeType = mime_content_type($tmpName);

    if ($mimeType === 'application/pdf') {
        $extracted = shell_exec("pdftotext " . escapeshellarg($tmpName) . " -");
        if ($extracted !== null) {
            $pdfText = trim(mb_convert_encoding($extracted, 'UTF-8', 'UTF-8'));
            $pdfText = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/u', '', $pdfText); 
            $userMessageContent .= "\n\n[Conteudo do PDF]:\n" . $pdfText;
        } else {
            echo json_encode(['error' => 'Falha extraindo PDF. pdftotext instalado?']);
            exit;
        }
    } elseif (in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'])) {
        $img = null;
        switch($mimeType) {
            case 'image/jpeg': $img = @imagecreatefromjpeg($tmpName); break;
            case 'image/png':  $img = @imagecreatefrompng($tmpName); break;
            case 'image/webp': $img = @imagecreatefromwebp($tmpName); break;
            case 'image/gif':  $img = @imagecreatefromgif($tmpName); break;
        }
        if ($img) {
            $w = imagesx($img); $h = imagesy($img); $maxDim = 512;
            $ratio = min($maxDim/$w, $maxDim/$h);
            $newW = $ratio < 1 ? (int)($w * $ratio) : $w;
            $newH = $ratio < 1 ? (int)($h * $ratio) : $h;
            $newImg = imagecreatetruecolor($newW, $newH);
            $bg = imagecolorallocate($newImg, 255, 255, 255);
            imagefill($newImg, 0, 0, $bg);
            imagecopyresampled($newImg, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);
            ob_start();
            imagejpeg($newImg, null, 65);
            $imageBase64 = base64_encode(ob_get_clean());
            imagedestroy($img); imagedestroy($newImg);
        } else {
            $imageBase64 = base64_encode(file_get_contents($tmpName));
        }
    } else {
        echo json_encode(['error' => 'Formato de arquivo invalido.']);
        exit;
    }
}

if ($messageText === '' && !$pdfText && !$imageBase64) {
    echo json_encode(['error' => 'Mensagem vazia.']);
    exit;
}

try {
    $apiMessages = [];
    if (is_array($history)) {
        foreach ($history as $msg) {
            if (isset($msg['role'], $msg['content'])) {
                $apiMessages[] = ["role" => (string)$msg['role'], "content" => (string)$msg['content']];
            }
        }
    }

    if ($imageBase64) {
        $currentContent = [
            ["type" => "image", "source" => ["type" => "base64", "media_type" => "image/jpeg", "data" => $imageBase64]]
        ];
        if ($messageText !== '') $currentContent[] = ["type" => "text", "text" => (string)$messageText];
        $apiMessages[] = ["role" => "user", "content" => $currentContent];
    } else {
        $apiMessages[] = ["role" => "user", "content" => (string)$userMessageContent];
    }

    $payload = ["model" => (string)$model, "max_tokens" => 4096, "messages" => $apiMessages];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "x-api-key: " . $apiKey, "api-key: " . $apiKey,
        "Content-Type: application/json", "anthropic-version: 2023-06-01"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        echo json_encode(['error' => "Erro API HTTP $httpCode"]);
        exit;
    }
    
    $resData = json_decode((string)$response, true);
    $reply = $resData['content'][0]['text'] ?? null;

    if ($reply !== null) {
        echo json_encode(['reply' => $reply, 'dbMessageContent' => $userMessageContent]);
    } else {
        echo json_encode(['error' => 'Resposta API invalida', 'raw' => $response]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Erro servidor: ' . $e->getMessage()]);
}
