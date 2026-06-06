<?php
declare(strict_types=1);

$configFile = __DIR__ . '/config.json';
$exampleFile = __DIR__ . '/config.json.example';

$statusMessage = "";
$statusType = "info";
$alreadyInstalled = file_exists($configFile);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $baseUrl = trim($_POST['base_url'] ?? '');
    $authToken = trim($_POST['auth_token'] ?? '');

    if (empty($baseUrl) || empty($authToken)) {
        $statusMessage = "Erro: Endpoint e Chave sao obrigatorios.";
        $statusType = "error";
    } else {
        if (!file_exists($exampleFile)) {
            $statusMessage = "Erro: O arquivo template config.json.example nao foi encontrado.";
            $statusType = "error";
        } else {
            $configData = json_decode(file_get_contents($exampleFile), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $statusMessage = "Erro critico ao processar o arquivo de template.";
                $statusType = "error";
            } else {
                // Injeta os dados do formulario no array de configuracao
                $configData['env']['ANTHROPIC_BASE_URL'] = $baseUrl;
                $configData['env']['ANTHROPIC_AUTH_TOKEN'] = $authToken;

                // Salva o arquivo final config.json
                $writeResult = file_put_contents($configFile, json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                if ($writeResult !== false) {
                    $statusMessage = "Instalacao concluida com sucesso! O arquivo config.json foi gerado.";
                    $statusType = "success";
                    $alreadyInstalled = true;
                } else {
                    $statusMessage = "Erro: Falha de permissao ao escrever o arquivo config.json (chmod 755 / chown www-data).";
                    $statusType = "error";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador Workspace</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background-color: #1e1e1e; color: #ececec; margin: 0; padding: 40px 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .card { background-color: #252526; border: 1px solid #444; border-radius: 12px; padding: 30px; width: 100%; max-width: 500px; box-shadow: 0 8px 24px rgba(0,0,0,0.3); }
        h2 { margin-top: 0; color: #cb7b58; border-bottom: 1px solid #444; padding-bottom: 12px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 0.95em; }
        input[type="text"] { width: 100%; background-color: #2d2d2d; color: #ececec; border: 1px solid #444; padding: 10px 14px; border-radius: 6px; box-sizing: border-box; font-size: 0.95em; outline: none; }
        input[type="text"]:focus { border-color: #cb7b58; }
        .btn { background-color: #cb7b58; color: white; border: none; padding: 12px 20px; border-radius: 6px; font-weight: 600; cursor: pointer; width: 100%; font-size: 1em; transition: 0.2s; }
        .btn:hover { opacity: 0.9; }
        .btn-secondary { background-color: #3c3c3c; border: 1px solid #444; color: #ececec; margin-top: 10px; display: inline-block; text-align: center; text-decoration: none; padding: 10px; border-radius: 6px; width: 100%; box-sizing: border-box; }
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 0.9em; line-height: 1.5; }
        .alert-error { background-color: #5a2323; border: 1px solid #ff6b6b; color: #ffdbdb; }
        .alert-success { background-color: #1e4620; border: 1px solid #4caf50; color: #e8f5e9; }
        .alert-info { background-color: #2a3b4c; border: 1px solid #2196f3; color: #e3f2fd; }
        .help-text { font-size: 0.8em; color: #888; margin-top: 4px; display: block; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Instalador - Configurar API</h2>
        
        <?php if (!empty($statusMessage)): ?>
            <div class="alert alert-<?= $statusType ?>"><?= htmlspecialchars($statusMessage) ?></div>
        <?php endif; ?>

        <?php if ($alreadyInstalled && empty($statusMessage)): ?>
            <div class="alert alert-info">Aviso: O arquivo config.json ja existe. Preencher o formulario ira sobrescreve-lo.</div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label for="base_url">Endpoint (Anthropic Base URL)</label>
                <input type="text" id="base_url" name="base_url" placeholder="https://api.opus-sem-limites.com.br" required>
                <span class="help-text">URL do servico (sem o sufixo /v1/messages).</span>
            </div>
            <div class="form-group">
                <label for="auth_token">Chave (Auth Token)</label>
                <input type="text" id="auth_token" name="auth_token" placeholder="sk-virt-..." required>
            </div>
            <button type="submit" class="btn">Concluir Instalacao</button>
            <?php if ($alreadyInstalled): ?>
                <a href="index.php" class="btn-secondary">Acessar o Chat</a>
            <?php endif; ?>
        </form>
    </div>
</body>
</html>
