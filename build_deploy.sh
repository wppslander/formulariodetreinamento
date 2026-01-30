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

# Copia os arquivos do sistema (Bootstrap Pattern)
cp public/index.php $DIR/
cp public/config.php $DIR/
cp public/functions.php $DIR/
cp public/controller.php $DIR/
cp public/view.php $DIR/

# 4. Ajustar index.php para Ambiente Flat (Deploy)
echo "🔧 Ajustando caminhos no index.php..."
# Substitui BASE_PATH = __DIR__ . '/..' por BASE_PATH = __DIR__
sed -i "s|define('BASE_PATH', __DIR__ . '/..');|define('BASE_PATH', __DIR__);|g" $DIR/index.php

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

# Bloquear acesso direto aos arquivos de include PHP
<FilesMatch "^(config|functions|controller|view)\.php$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Bloquear acesso direto ao Composer/Vendor
<IfModule mod_alias.c>
    RedirectMatch 403 ^/vendor/.*
</IfModule>

# Bloquear acesso direto aos Relatórios (CSV)
<IfModule mod_alias.c>
    RedirectMatch 403 ^/reports/.*
</IfModule>
EOF

# 6. Criar Instruções de Deploy (README_DEPLOY.md)
echo "📄 Criando manual de deploy..."
cat > $DIR/README_DEPLOY.md <<EOF
# 🚀 Manual de Instalação (Deploy)

Este pacote contém a versão "Flat" (sem Docker) do sistema de Treinamentos.

## 📂 Estrutura
*   `index.php`: Ponto de entrada (Bootstrap).
*   `config.php, functions.php...`: Núcleo do sistema.
*   `vendor/`: Bibliotecas (NÃO mexa aqui).
*   `reports/`: Onde os CSVs de auditoria serão salvos.
*   `.env.example`: Modelo de configuração.
*   `.htaccess`: Regras de segurança.

## ⚠️ SEGURANÇA CRÍTICA

1.  **Proteja o .env:** Certifique-se que ninguém consegue baixar o arquivo `.env`.
2.  **Proteja os Includes:** O `.htaccess` já bloqueia acesso direto a `config.php`, `view.php`, etc.

## 📝 Passo a Passo

1.  Copie todos os arquivos desta pasta para o servidor.
2.  Renomeie `.env.example` para `.env`.
3.  Configure o `.env`.
4.  Garanta permissão de escrita na pasta `reports/`.
EOF

# 7. Permissões finais
chmod -R 755 $DIR

echo ""
echo "✅ Pacote de Deploy pronto na pasta: /$DIR"
echo "🚀 Estrutura modularizada pronta para upload!"