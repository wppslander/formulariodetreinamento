# Ferramenta de Cadastro de Treinamentos - DigitalSat

Aplicação web leve e segura para registro interno de treinamentos. Desenvolvida em **PHP 8.2** puro, utilizando **Docker** para portabilidade e **Bootstrap 5** (CDN) para o frontend. Focada na simplicidade (KISS) e segurança.

## 🚀 Como Iniciar

### 1. Pré-requisitos
Certifique-se de ter o [Docker](https://www.docker.com/) e o [Docker Compose](https://docs.docker.com/compose/) instalados.

### 2. Configuração Inicial
Configure as credenciais de e-mail (SMTP) antes de rodar:

1.  Crie o arquivo `.env` na raiz (copie do exemplo):
    ```bash
    cp .env.example .env
    ```
2.  Edite o `.env` com seus dados:
    ```ini
    APP_ENV=production          # Use 'local' para simular envio (cria arquivo .html)
    
    # Configurações SMTP
    SMTP_HOST=smtp.exemplo.com
    SMTP_PORT=587
    SMTP_USER=email@digitalsat.com.br
    SMTP_PASS=sua_senha
    
    # Token de Segurança para Relatórios (RH)
    ADMIN_TOKEN=defina_uma_senha_forte_aqui
    ```

### 3. Executando (Docker)
Na raiz do projeto:

```bash
docker compose up -d --build
```
Acesse: **http://localhost:{porta_designada}**

---

## 📊 Auditoria e Relatórios (NOVO)

O sistema mantém um registro permanente (CSV) de todos os envios para fins de auditoria.

*   **Localização:** Os arquivos são salvos na pasta `./reports/` (persistida fora do container).
*   **Dados Coletados:** Data/Hora, Dados do Funcionário, Curso, Duração e **IP de Origem** (com suporte a Proxy/X-Forwarded-For).

### Envio de Relatório para o RH
Para enviar o CSV acumulado para o e-mail de auditoria, acesse a seguinte URL no navegador:

```
http://seu-servidor/?action=enviar_relatorio&token=SEU_TOKEN_AQUI
```

*   O relatório é enviado para o e-mail configurado em `REPORT_DESTINATION` no arquivo `.env`.
*   O token deve ser o mesmo configurado em `ADMIN_TOKEN` no arquivo `.env`.
*   Se o token for inválido, o acesso será negado.

#### ⏰ Automação (Cron Job)
Para que o relatório seja enviado automaticamente (ex: todo dia 23 do mês), configure um **Cron Job** no painel da sua hospedagem (cPanel/Tarefa Agendada) para executar o seguinte comando:

```bash
# Exemplo usando CURL (Chamada via URL)
curl -s "http://seu-servidor/?action=enviar_relatorio&token=SEU_TOKEN_AQUI" > /dev/null 2>&1
```

Configure a frequência para: `0 9 23 * *` (Todo dia 23 às 09:00h).

---

## 🛠️ Desenvolvimento e Manutenção

Toda a lógica e visual estão centralizados em um único arquivo para facilitar a manutenção.

### Arquivo Principal: `public/index.php`
*   **PHP (Topo):** Contém a lógica de segurança (CSRF, Rate Limit), validação de formulário e envio de e-mail (PHPMailer).
*   **HTML (Meio):** Estrutura do formulário.
*   **CSS/JS (Fim):** Estilos customizados e validações de frontend.

### Configurações Importantes
No início do arquivo `public/index.php`, você pode alterar:
*   `$filiais_permitidas`: Lista de filiais aceitas no formulário.
*   `$tipos_permitidos`: Tipos de treinamento válidos.

### Logs e Debug
Se `APP_ENV=local`, os e-mails **não** são enviados de verdade. Eles são salvos como `email_mock.html` na raiz do container/projeto para validação visual.

---

## 🔒 Segurança Implementada
*   **CSRF Protection:** Token único por sessão.
*   **Rate Limiting:** Bloqueia múltiplos envios rápidos.
*   **Strict Whitelisting:** Valida opções de select contra arrays permitidos.
*   **Upload Seguro:** Validação de MIME Type real e limite de 5MB.
*   **Sessão:** Cookies `HttpOnly` e `SameSite=Strict`.
