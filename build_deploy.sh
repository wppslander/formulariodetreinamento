#!/bin/bash

echo "📦 Iniciando processo de empacotamento para Deploy (No-Docker)..."

# 1. Limpeza e Criação do Diretório
DIR="deploy"
rm -rf $DIR
mkdir -p $DIR
mkdir -p $DIR/reports

# 2. Instalar dependências de produção (Garante que não vai lixo)
echo "⬇️  Otimizando dependências (Composer)..."
composer install --no-dev --optimize-autoloader --quiet

# 3. Copiar arquivos essenciais
echo "📂 Copiando arquivos..."
cp -r vendor $DIR/
cp .env.example $DIR/

# 4. Processar index.php (Ajustar caminhos para estrutura plana)
echo "🔧 Ajustando caminhos no index.php..."

# Lê o arquivo original
CONTENT=$(cat public/index.php)

# Ajuste 1: Autoload (De /../vendor para /vendor)
CONTENT=${CONTENT//__DIR__ . '\/..\/vendor\/autoload.php'/__DIR__ . '\/vendor\/autoload.php'}

# Ajuste 2: Dotenv (De /../ para atual)
CONTENT=${CONTENT//__DIR__ . '\/..\/'/__DIR__}

# Ajuste 3: Pasta Reports (De /../reports para /reports)
CONTENT=${CONTENT//__DIR__ . '\/..\/reports'/__DIR__ . '\/reports'}

# Salva o novo arquivo na raiz do deploy
echo "$CONTENT" > $DIR/index.php

# 5. Criar .htaccess de Segurança (CRÍTICO)
echo "🔒 Criando regras de segurança (.htaccess)..."
cat > $DIR/.htaccess <<EOF
<IfModule mod_rewrite.c>
    RewriteEngine On
    # Redireciona tudo para index.php se não for arquivo/diretório real
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>

# Bloquear acesso direto ao .env e outros arquivos de sistema
<FilesMatch "^\.">
    Order allow,deny
    Deny from all
</FilesMatch>

# Bloquear acesso direto ao Composer/Vendor
<IfModule mod_alias.c>
    RedirectMatch 403 ^/vendor/.*$
</IfModule>

# Bloquear acesso direto aos Relatórios (CSV)
<IfModule mod_alias.c>
    RedirectMatch 403 ^/reports/.*$
</IfModule>
EOF

# 6. Criar Instruções de Deploy (README_DEPLOY.md)
echo "📄 Criando manual de deploy..."
cat > $DIR/README_DEPLOY.md <<EOF
# 🚀 Manual de Instalação (Deploy)

Este pacote contém a versão "Flat" (sem Docker) do sistema de Treinamentos.
Pode ser colocado em qualquer hospedagem PHP (Apache/Nginx).

## 📂 Estrutura
*   \`index.php\`: O sistema completo.
*   \`vendor/\`: Bibliotecas (NÃO mexa aqui).
*   \`reports/\`: Onde os CSVs de auditoria serão salvos.
*   \`.env.example\`: Modelo de configuração.
*   \`.htaccess\`: Regras de segurança.

## ⚠️ SEGURANÇA CRÍTICA (Leia com Atenção!)

O sistema utiliza um arquivo \`.env\` para guardar senhas de e-mail.
**Este arquivo NUNCA pode ser acessível publicamente pelo navegador.**

### Cenário 1: Hospedagem Padrão (Apache)
O arquivo \`.htaccess\` incluído neste pacote já contém regras para bloquear acesso ao \`.env\`.
*   **Teste:** Após subir os arquivos, tente acessar \`seu-site.com/pasta-do-sistema/.env\`.
*   **Esperado:** Erro 403 (Forbidden) ou 404.
*   **Falha:** Se o navegador baixar o arquivo, **PARE TUDO**. Seu servidor não está lendo o \`.htaccess\`. Contate o suporte da hospedagem.

### Cenário 2: Nginx ou IIS
Se o servidor não for Apache, o arquivo \`.htaccess\` será ignorado. Você deve configurar o bloqueio manualmente.
*   **Nginx:** Adicione \`location ~ /\\.env { deny all; }\` na configuração do site.

## 📝 Passo a Passo

1.  Copie todos os arquivos desta pasta para o servidor (ex: \`public_html/formulario\`).
2.  Renomeie \`.env.example\` para \`.env\`.
3.  Edite o \`.env\` e coloque as senhas do SMTP e o Token de Admin.
4.  Garanta que a pasta \`reports/\` tenha permissão de escrita pelo PHP (chmod 755 ou 775).

EOF

# 7. Permissões finais (Garantir que scripts sejam executáveis)
chmod -R 755 $DIR

echo ""
echo "✅ Pacote de Deploy pronto na pasta: /$DIR"
echo "   Estrutura gerada:"
echo "   ├── README_DEPLOY.md  (Instruções para o SysAdmin ⚠️)"
echo "   ├── index.php         (Arquivo único)"
echo "   ├── .htaccess         (Blindagem de Segurança 🛡️)"
echo "   ├── vendor/           (Bloqueado pelo .htaccess)"
echo "   ├── reports/          (Bloqueado pelo .htaccess)"
echo "   └── .env.example      (Configuração)"
echo ""
echo "🚀 Basta copiar o conteúdo de '$DIR' para o servidor!"
