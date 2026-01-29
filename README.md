# Ferramenta de Cadastro de Treinamentos - DigitalSat

Este projeto é um formulário web moderno para registro interno de treinamentos, utilizando **PHP 8.2** no backend, **Vite** para build de assets (CSS/JS) e **Docker** para containerização.

## 🚀 Como Iniciar

### 1. Pré-requisitos
Certifique-se de ter o [Docker](https://www.docker.com/) e o [Docker Compose](https://docs.docker.com/compose/) instalados.

### 2. Configuração Inicial
Antes de subir o servidor, configure as credenciais de e-mail:

1.  Renomeie o arquivo de exemplo:
    ```bash
    cp .env.example .env
    ```
2.  Abra o arquivo `.env` e preencha com seus dados de SMTP:
    ```ini
    SMTP_HOST=smtp.exemplo.com
    SMTP_PORT=587
    SMTP_USER=seu_email@digitalsat.com.br
    SMTP_PASS=sua_senha_secreta
    ```

### 3. Subindo o Ambiente (Produção)
Execute o comando abaixo na raiz do projeto:

```bash
docker compose up -d
```
Acesse: **http://localhost:8080**

### 💻 4. Modo Desenvolvimento (Live Reload)
Para que o site atualize automaticamente ao mexer no CSS/JS:

1.  No arquivo `.env`, garanta que:
    ```ini
    APP_ENV=local
    ```
2.  Em um terminal separado, inicie o Vite:
    ```bash
    npm run dev
    ```
    *Isso iniciará um servidor local na porta 5173 que conversa com o PHP.*

---

## 🛠️ Como Alterar e Desenvolver

A estrutura do projeto separa claramente o código fonte (frontend) do código público (backend/servidor).

### 🎨 1. Alterar Estilos (CSS/SASS)
Os estilos estão em `src/scss/style.scss`.
O projeto usa **Bootstrap 5**. Você pode sobrescrever variáveis ou adicionar classes personalizadas neste arquivo.

Após alterar, você precisa recompilar os assets:
```bash
npm run build
```

### 🧠 2. Alterar Funcionalidade (PHP/HTML)
O arquivo principal é `public/index.php`.
*   **HTML do Formulário:** Edite este arquivo para adicionar/remover campos ou mudar textos.
*   **Lógica de E-mail:** O código PHP no topo deste arquivo controla o envio.
*   **Listas (Ex: Filiais):** Procure pela tag `<select>` dentro do HTML para adicionar novas opções.

### ⚡ 3. Alterar Scripts (JavaScript)
O JavaScript principal está em `src/js/main.js`.
Atualmente ele apenas importa o Bootstrap, mas você pode adicionar validações ou interações personalizadas aqui.
Lembre-se de rodar `npm run build` após as alterações.

### 📦 4. Instalar Novas Dependências
*   **PHP:** Use `docker run --rm -v $(pwd):/app -w /app composer require nome/pacote`
*   **Node/Frontend:** Use `npm install nome-pacote`

---

## 📂 Estrutura de Pastas

*   `src/` -> Código fonte Frontend (SCSS, JS) - Onde você trabalha o visual.
*   `public/` -> Arquivos servidos pelo Apache (PHP, Assets compilados) - Onde fica a lógica e o HTML.
    *   `assets/` -> Gerado automaticamente pelo Vite (NÃO edite aqui).
*   `docker-compose.yml` -> Configuração dos containers.
*   `vite.config.js` -> Configuração do bundler Vite.
