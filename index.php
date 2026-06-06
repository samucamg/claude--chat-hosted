<?php
declare(strict_types=1);
$configFile = __DIR__ . '/config.json';
if (!file_exists($configFile)) { header("Location: install.php"); exit; }
$config = json_decode(file_get_contents($configFile), true);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workspace de Chat</title>
    <style>
        body { font-family: -apple-system, sans-serif; background: #1e1e1e; color: #ececec; margin: 0; padding: 0; height: 100vh; display: flex; flex-direction: column; }
        .header { display: flex; justify-content: space-between; padding: 12px 20px; background: #252526; border-bottom: 1px solid #444; }
        .header-controls { display: flex; gap: 15px; }
        select, input[type="number"] { background: #2d2d2d; color: #ececec; border: 1px solid #444; padding: 6px; border-radius: 6px; outline: none; }
        .btn-clear { background: transparent; color: #ff6b6b; border: 1px solid #ff6b6b; padding: 6px 12px; border-radius: 6px; cursor: pointer; }
        .chat-container { flex: 1; overflow-y: auto; padding: 30px 20px; display: flex; flex-direction: column; align-items: center; }
        .chat-content { width: 100%; max-width: 800px; display: flex; flex-direction: column; gap: 24px; }
        .msg-wrapper { display: flex; width: 100%; }
        .msg-wrapper.user { justify-content: flex-end; }
        .msg-wrapper.assistant { justify-content: flex-start; }
        .msg { padding: 14px 18px; border-radius: 12px; max-width: 85%; white-space: pre-wrap; line-height: 1.6;}
        .msg.user { background: #3c3c3c; color: #fff; border-bottom-right-radius: 4px; }
        .input-area-wrapper { padding: 20px; display: flex; justify-content: center; }
        .input-box { width: 100%; max-width: 800px; background: #2d2d2d; border: 1px solid #444; border-radius: 12px; padding: 12px; }
        textarea { width: 100%; background: transparent; border: none; color: #ececec; resize: none; outline: none; font-family: inherit; }
        .tools-row { display: flex; justify-content: space-between; margin-top: 10px; border-top: 1px solid #444; padding-top: 10px; }
        .btn-send { background: #ececec; color: #1e1e1e; border: none; padding: 8px 18px; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .loading { display: none; color: #888; font-size: 0.9em; padding-left: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <div>Workspace</div>
        <div class="header-controls">
            <select id="model-select">
                <?php foreach($config['availableModels'] as $mod): ?>
                    <option value="<?= htmlspecialchars($mod) ?>"><?= htmlspecialchars($mod) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="number" id="context-limit" value="20" style="width: 60px;">
            <button class="btn-clear" id="btn-clear">Limpar</button>
        </div>
    </div>
    <div class="chat-container" id="chat-container">
        <div class="chat-content" id="chat-box"></div>
        <div class="loading" id="loading">Digitando...</div>
    </div>
    <div class="input-area-wrapper">
        <div class="input-box">
            <textarea id="msg-input" rows="2" placeholder="Digite sua mensagem..."></textarea>
            <div class="tools-row">
                <input type="file" id="file-input" accept="image/*, application/pdf">
                <button class="btn-send" id="send-btn">Enviar</button>
            </div>
        </div>
    </div>
    <script>
        const chatBox = document.getElementById('chat-box'), chatContainer = document.getElementById('chat-container');
        const msgInput = document.getElementById('msg-input'), fileInput = document.getElementById('file-input');
        const sendBtn = document.getElementById('send-btn'), loading = document.getElementById('loading');
        
        let sessionHash = localStorage.getItem('chat_session_hash') || Math.random().toString(36).substring(2, 15);
        localStorage.setItem('chat_session_hash', sessionHash);
        let chatHistory = JSON.parse(localStorage.getItem('chat_history_' + sessionHash) || '[]');

        function renderHistory() {
            chatBox.innerHTML = '';
            if(chatHistory.length===0) appendMsgToUI('assistant', 'Ola! Como posso ajudar?');
            else { chatHistory.forEach(msg => appendMsgToUI(msg.role, msg.content, false)); chatContainer.scrollTop = chatContainer.scrollHeight; }
        }

        function appendMsgToUI(role, text, scroll = true) {
            const w = document.createElement('div'); w.className = `msg-wrapper ${role}`;
            const b = document.createElement('div'); b.className = `msg ${role}`;
            b.innerHTML = text.replace(/</g, "&lt;").replace(/>/g, "&gt;"); 
            w.appendChild(b); chatBox.appendChild(w);
            if(scroll) chatContainer.scrollTop = chatContainer.scrollHeight;
        }

        document.getElementById('btn-clear').onclick = () => {
            if(confirm('Apagar historico local?')) { localStorage.removeItem('chat_history_'+sessionHash); location.reload(); }
        };

        sendBtn.onclick = async () => {
            const msg = msgInput.value.trim(), file = fileInput.files[0];
            if (!msg && !file) return;

            let uiMessage = msg; if (file) uiMessage += `\n[Anexo: ${file.name}]`;
            appendMsgToUI('user', uiMessage);

            const payloadHistory = chatHistory.slice(-parseInt(document.getElementById('context-limit').value, 10));
            const fd = new FormData();
            fd.append('message', msg); fd.append('model', document.getElementById('model-select').value);
            fd.append('history', JSON.stringify(payloadHistory)); if (file) fd.append('file', file);

            msgInput.value = ''; fileInput.value = ''; loading.style.display = 'block'; sendBtn.disabled = true;

            try {
                const res = await fetch('api.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.error) appendMsgToUI('assistant', `Erro: ${data.error}`);
                else {
                    chatHistory.push({role: 'user', content: data.dbMessageContent || uiMessage});
                    chatHistory.push({role: 'assistant', content: data.reply});
                    localStorage.setItem('chat_history_' + sessionHash, JSON.stringify(chatHistory));
                    appendMsgToUI('assistant', data.reply);
                }
            } catch (e) { appendMsgToUI('assistant', 'Erro de conexao.'); } 
            finally { loading.style.display = 'none'; sendBtn.disabled = false; chatContainer.scrollTop = chatContainer.scrollHeight; }
        };
        renderHistory();
    </script>
</body>
</html>
