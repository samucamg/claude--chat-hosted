# Claude Chat Hosted - Workspace Stateless

Uma interface web robusta, leve e autohospedada para interacao com a API Anthropic (Modelos Claude: Opus, Sonnet, Haiku). 

Este projeto foi arquitetado utilizando PHP puro e um padrao estrito de estado isolado (Stateless), eliminando completamente a necessidade de bancos de dados relacionais (MySQL/PostgreSQL) para garantir maxima performance, facilidade de deploy e baixo consumo no servidor.


## 1. Visao Geral da Arquitetura

O sistema atua como um Proxy de API seguro entre o navegador do usuario e o endpoint da Anthropic, garantinte que as chaves de API nunca sejam expostas no frontend.

Z ***Gerenciamento de Estado no Cliente:** O historico da conversa e armazenado localmente no navegador via `localStorage`.
* **Fatiamento Dinamico de Contexto (Sliding Window):** Apenas as ultimas mensagens selecionadas pelo usuario sao enviadas ao backend a cada nova interacao, prevenindo o estouro de limites de tokens.
* **Processamento de Midia Inteligente:**
  * **PDFs:** Extraidos nativamente no backend utilizando o utilitario `pdftotext`.
  * **Imagens:** Arquivos (JPG, PNG, WEBP, GIF) sao redimensionados e comprimidos on-the-fly via extensao PHP GD (dimensao maxima travada em 512px e compressao em 65%) antes da conversao para Base64. Isso previne o erro HTTP 422 (Payload Too Large).

## 2. Requisitos do Servidor Linux

Para o funcionamento integral, seu servidor web (Apache/Nginx) deve atender aos seguintes requisitos:

Z **PHP** >= 7.4 ou 8.x
* **Extensao PHP-cURL** ativa
* **Extensao PHP-GD** ativa
* **Utilitario pdftotext** (`poppler-utils`)

### Como instalar as dependencias (Ubuntu/Debian)
```bash
sudo apt-get update
sudo apt-get install php-curl php-gd poppler-utils
```

## 3. Deploy e Instalacao

Siga os passos abaixo para implantar o sistema.

### 3.1: Clone o Repositorio
Navegue ate a pasta publica do seu servidor web e clone o projeto:
```bash
git clone https://github.com/samucamg/claude--chat-hosted.git
cd claude--chat-hosted
```

### 3.2: Ajuste Permissoes de Escrita
O instalador precisa de permissoes temporarias para gravar o arquivo final de configuracao.
```bash
sudo chown -R www-data:www-data .
sudo chmod 755 .
```J

### 3.3: Configure via Navegador
1. Acesse o instalador pela URL: `https://seu-dominio.com/caminho-da-pasta/install.php
2. Preencha os campos obrigatorios:
   * **Endpoint:** O endereco base da API ou do proxy.
   * **Chave:** Sua API Key (Auth Token).
3. O sistema gerara o `config.json` definitivo.

### 3.4: Seguranca (Passo Critico)
Imediatamente apos gerar o `config.json` com sucesso, apague o instalador para que terceiros nao sequestrem seu endpoint.
```bash
rm install.php
```J

## 4. Estrutura de Arquivos

* `api.php`: Gateway proxy seguro e higienizador de payloads.
* `index.php`: Interface frontend e controladora de sessoes locais.
* `install.php`: Instalador web inicial.
* `config.json.example`: Template de variaveis e modelos.
* `documentacao.html`: Versao amigavel desta documentacao.
[root@veroclass install_claude]# 
