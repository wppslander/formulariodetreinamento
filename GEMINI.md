# Contexto do Projeto: Ferramenta de Cadastro de Treinamentos (DigitalSat)

Este arquivo documenta a estrutura, arquitetura e fluxos de trabalho do projeto.

## 📋 Visão Geral do Projeto

Aplicação web interna para registro de treinamentos de funcionários. O sistema coleta dados via formulário e envia por e-mail via SMTP.

### Stack Tecnológica (Atual)
*   **Backend:** PHP 8.2 (Apache).
*   **Frontend:** HTML5, Bootstrap 5, SASS (Vite Build).
*   **Infraestrutura:** Docker & Docker Compose.
*   **Libs:** `phpmailer/phpmailer`, `vlucas/phpdotenv`.

---

## 🔍 Diagnóstico e Planejamento (Refatoração KISS)

Após análise realizada em 30/01/2026, foi identificado que a arquitetura atual possui complexidade desnecessária para o escopo do projeto (Build de frontend com Node.js para um formulário simples).

### Metas da Refatoração
1.  **Eliminar Build Step:** Remover dependência de Node.js/Vite.
2.  **Frontend Leve:** Utilizar Bootstrap 5 via CDN.
3.  **Docker Otimizado:** Migrar para build single-stage (apenas PHP).
4.  **Segurança:** Adicionar proteção CSRF e sanitização de inputs.
5.  **Limpeza:** Remover diretório `src/` e arquivos de configuração JS.

### Estrutura de Arquivos Alvo
```
/
├── .env.example
├── .gitignore
├── composer.json
├── docker-compose.yml
├── Dockerfile
└── public/
    ├── index.php      # Lógica completa (View + Controller)
    └── assets/        # Imagens estáticas (se houver)
```

---

## 🏗️ Arquitetura Atual (Legado - A ser removida)

### Diretórios Principais
*   **`src/`**: Código fonte Frontend (SASS/JS).
*   **`public/`**: Raiz do servidor web.
    *   `index.php`: Ponto de entrada.
*   **`docker-compose.yml`**: Serviço `web` na porta `8080`.

---

## ⚙️ Configuração (Atual)

### Comandos
| Ação | Comando |
| :--- | :--- |
| **Instalar Deps** | `docker run --rm -v $(pwd):/app -w /app composer install` |
| **Subir** | `docker compose up -d` |

### Variáveis (`.env`)
*   `APP_ENV`: `local` vs `production`.
*   SMTP: `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`.