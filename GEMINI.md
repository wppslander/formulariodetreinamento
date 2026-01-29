# Contexto do Projeto: Ferramenta de Cadastro de Treinamentos (DigitalSat)

Este arquivo documenta a estrutura, arquitetura e fluxos de trabalho do projeto para facilitar interações futuras.

## 📋 Visão Geral do Projeto

Uma aplicação web interna para registro de treinamentos de funcionários. O sistema apresenta um formulário responsivo que coleta dados do colaborador e do curso realizado, enviando essas informações por e-mail para os administradores (e possivelmente para o usuário).

### Stack Tecnológica
*   **Backend:** PHP 8.2 rodando em servidor Apache.
*   **Frontend:** HTML5, Bootstrap 5 (Estilização), SASS, JavaScript.
*   **Build Tool:** Vite (Gerenciamento de assets e Hot Module Replacement).
*   **Infraestrutura:** Docker & Docker Compose.
*   **Bibliotecas Chave:**
    *   `phpmailer/phpmailer`: Envio de e-mails via SMTP.
    *   `vlucas/phpdotenv`: Gerenciamento de variáveis de ambiente (`.env`).
    *   `bootstrap`: Framework CSS.

---

## 🏗️ Arquitetura e Estrutura de Arquivos

O projeto separa o código fonte de desenvolvimento (`src`) dos arquivos públicos servidos pelo servidor web (`public`).

### Diretórios Principais
*   **`src/`**: Código fonte do Frontend.
    *   `js/main.js`: Ponto de entrada JavaScript. Importa o Bootstrap e o arquivo SCSS principal.
    *   `scss/style.scss`: Estilos globais. Importa o Bootstrap e define a identidade visual (fontes, cores).
*   **`public/`**: Raiz do servidor web (Document Root).
    *   `index.php`: Arquivo único da aplicação. Contém:
        1.  Lógica PHP para processar o formulário (`POST`).
        2.  Lógica PHP para carregar assets (Vite Dev Server ou arquivos compilados).
        3.  HTML do formulário.
    *   `assets/`: Diretório de saída do build do Vite (contém `.js` e `.css` minificados e o `manifest.json`).
*   **`docker-compose.yml`**: Define o serviço `web` (PHP 8.2 + Apache).
    *   Mapeia a porta `8080` (host) para `80` (container).
    *   Configura o Apache para servir a pasta `public/` como raiz.

---

## ⚙️ Configuração e Execução

### Comandos Essenciais

| Ação | Comando | Descrição |
| :--- | :--- | :--- |
| **Instalar Deps (PHP)** | `docker run --rm -v $(pwd):/app -w /app composer install` | Instala pacotes do `composer.json`. |
| **Instalar Deps (JS)** | `npm install` | Instala pacotes do `package.json`. |
| **Subir Servidor** | `docker compose up -d` | Inicia o PHP/Apache em `localhost:8080`. |
| **Modo Dev (Frontend)**| `npm run dev` | Inicia o servidor Vite em `localhost:5173` para HMR. |
| **Build (Produção)** | `npm run build` | Compila assets para a pasta `public/assets`. |

### Variáveis de Ambiente (`.env`)

O sistema depende de um arquivo `.env` (baseado em `.env.example`).
*   **`APP_ENV`**: Define o modo de operação (`local` para desenvolvimento com Vite, qualquer outro valor para produção).
*   **SMTP Credentials**: `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS` para envio de e-mails.

---

## 💻 Convenções de Desenvolvimento

### Fluxo de Assets (Vite + PHP)
O arquivo `public/index.php` possui uma função inteligente (`get_vite_assets`) que decide qual asset carregar:
1.  **Se `APP_ENV=local`**: Injeta scripts apontando para `http://localhost:5173` (Vite Dev Server), permitindo atualizações em tempo real (HMR).
2.  **Se `APP_ENV!=local`**: Lê o arquivo `public/assets/.vite/manifest.json` para encontrar os nomes dos arquivos `.css` e `.js` compilados e os injeta na página.

### Estilização
*   Não escreva CSS inline ou em tags `<style>` no PHP.
*   Adicione estilos em `src/scss/style.scss`.
*   O Bootstrap é importado via SASS, permitindo sobrescrever variáveis se necessário.

### Backend
*   Toda a lógica está contida em `public/index.php` para simplicidade.
*   Usa `PHPMailer` para robustez no envio de e-mails.
*   Uploads de arquivos são anexados diretamente ao e-mail e não são salvos permanentemente no disco do servidor.
