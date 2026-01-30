# Projeto: Ferramenta de Cadastro de Treinamentos (DigitalSat)

Aplicação web leve e segura para registro de treinamentos de funcionários, utilizando PHP puro e Docker.

## 📋 Visão Geral

O sistema apresenta um formulário para coleta de dados de cursos realizados pelos colaboradores e envia essas informações por e-mail para a administração via SMTP. O foco do projeto é simplicidade (KISS), segurança e portabilidade.

### Stack Tecnológica
*   **Backend/Frontend:** PHP 8.2 (Apache) - Renderização Server-Side.
*   **Estilização:** Bootstrap 5 (via CDN).
*   **Infraestrutura:** Docker & Docker Compose.
*   **Dependências PHP:** `phpmailer/phpmailer`, `vlucas/phpdotenv`.

---

## 🏗️ Estrutura de Arquivos

```
/
├── .env              # Variáveis de ambiente (não comitado)
├── composer.json     # Dependências PHP
├── docker-compose.yml
├── Dockerfile        # Imagem otimizada (PHP 8.2 + Apache)
└── public/
    └── index.php     # Aplicação Completa (View + Controller + CSRF)
```

---

## ⚙️ Configuração e Execução

### Pré-requisitos
*   Docker e Docker Compose instalados.

### Comandos Rápidos

| Ação | Comando | Descrição |
| :--- | :--- | :--- |
| **Iniciar** | `docker compose up -d --build` | Inicia o servidor em `localhost:8080`. |
| **Parar** | `docker compose down` | Para os containers. |
| **Logs** | `docker compose logs -f` | Acompanha logs do servidor. |

### Configuração `.env`

Crie um arquivo `.env` na raiz (baseado no `.env.example`) com as credenciais SMTP:

```ini
APP_ENV=production
SMTP_HOST=smtp.exemplo.com
SMTP_PORT=587
SMTP_USER=seu_email@exemplo.com
SMTP_PASS=sua_senha
```

*   **Modo Local:** Se `APP_ENV=local`, os e-mails não são enviados via SMTP, mas sim gerados como arquivos HTML de mock (`email_mock.html`) na raiz do container para testes seguros.

---

## 🛡️ Segurança Implementada

1.  **CSRF Protection:** Token único gerado por sessão para evitar submissões falsas.
2.  **Sanitização:** Todos os inputs são limpos (`htmlspecialchars`, `strip_tags`) antes do processamento.
3.  **Validação:** Validação visual no frontend (Bootstrap) e verificação de integridade no backend.
4.  **Docker:** Imagem baseada em container oficial PHP, sem build tools desnecessárias em produção.
