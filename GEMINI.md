# Projeto: Ferramenta de Cadastro de Treinamentos (DigitalSat)

Aplicação web leve, segura e otimizada para registro de treinamentos de funcionários. O sistema foi refatorado seguindo o princípio **KISS** (Keep It Simple, Stupid) para eliminar complexidade desnecessária e maximizar a segurança.

## 📋 Visão Geral

O sistema coleta dados de treinamentos via formulário web e envia notificações detalhadas por e-mail (com anexos) via SMTP.

### Stack Tecnológica
*   **Backend/Frontend:** PHP 8.2 (Apache) - Single File Architecture (`public/index.php`).
*   **Estilização:** Bootstrap 5.3 (via CDN).
*   **Infraestrutura:** Docker & Docker Compose (Multi-Stage Build).
*   **Libs:** `phpmailer/phpmailer`, `vlucas/phpdotenv`.

---

## 🏗️ Estrutura de Arquivos

A estrutura foi simplificada para facilitar a manutenção e o deploy.

```
/
├── .env.example      # Modelo de configuração
├── composer.json     # Dependências (PHPMailer, Dotenv)
├── docker-compose.yml
├── Dockerfile        # Build Multi-Stage (Builder -> Production)
└── public/
    └── index.php     # Aplicação: Lógica, View, Segurança e Envio.
```

---

## ⚙️ Configuração e Execução

### Comandos Rápidos

| Ação | Comando | Descrição |
| :--- | :--- | :--- |
| **Deploy/Iniciar** | `docker compose up -d --build` | Compila a imagem otimizada e inicia em `:8080`. |
| **Parar** | `docker compose down` | Encerra os containers. |
| **Ver Logs** | `docker compose logs -f` | Monitoramento em tempo real. |

### Configuração `.env`

Copie `.env.example` para `.env` e configure:
*   **`APP_ENV`**: Use `production` para envio real. Se `local`, gera arquivo `public/email_mock.html`.
*   **SMTP Credentials**: Dados do servidor de e-mail.

---

## 🛡️ Segurança (Hardened)

O projeto implementa camadas rigorosas de segurança para operar em produção:

1.  **CSRF Protection:** Token criptográfico único por sessão para prevenir falsificação de requisição.
2.  **Rate Limiting:** Bloqueio de envio em massa (trottle de 30 segundos por sessão).
3.  **Strict Whitelisting:** Validação de entradas (`filial`, `tipo_treinamento`) contra listas permitidas restritas.
4.  **Secure Upload:**
    *   Validação de **MIME Type Real** (conteúdo binário) do arquivo.
    *   Limite de tamanho (5MB).
    *   Permite apenas PDF e Imagens.
5.  **Session Hardening:** Cookies configurados com `HttpOnly`, `Secure` (se HTTPS) e `SameSite=Strict`.
6.  **Sanitização:** Todos os inputs passam por `htmlspecialchars` e `strip_tags`.

## 🐳 Otimização Docker
Utiliza **Multi-Stage Build**:
1.  **Stage 1 (Builder):** Instala dependências do sistema (Git, Zip) e roda `composer install`.
2.  **Stage 2 (Final):** Imagem limpa contendo apenas PHP+Apache e o código fonte. Sem restos de cache ou ferramentas de build.