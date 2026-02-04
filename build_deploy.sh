#!/bin/bash

echo "📦 Iniciando processo de empacotamento para Deploy (No-Docker)..."

# 1. Limpeza e Criação do Diretório
DIR="deploy"
if [ -d "$DIR" ]; then
    rm -rf "$DIR"
fi
mkdir -p "$DIR"
mkdir -p "$DIR/reports"

# 2. Instalar dependências de produção (Garante que não vai lixo)
if command -v composer &> /dev/null; then
    echo "⬇️  Otimizando dependências (Composer)..."
    composer install --no-dev --optimize-autoloader
else
    echo "⚠️  Composer não encontrado. Pulando otimização (usando vendor existente)..."
fi

# 3. Copiar arquivos essenciais
echo "📂 Copiando arquivos..."
if [ -d "vendor" ]; then
    cp -r vendor "$DIR/"
else
    echo "❌ Erro: Pasta 'vendor' não encontrada. Execute 'composer install' primeiro ou garanta que as dependências existam."
    exit 1
fi

cp .env.example "$DIR/"

# Copia os arquivos do sistema (Bootstrap Pattern)
cp public/index.php "$DIR/"
cp public/config.php "$DIR/"
cp public/functions.php "$DIR/"
cp public/controller.php "$DIR/"
cp public/view.php "$DIR/"

# 4. Ajustar index.php para Ambiente Flat (Deploy)
echo "🔧 Ajustando caminhos no index.php..."
# Substitui BASE_PATH = __DIR__ . '/..' por BASE_PATH = __DIR__
sed -i "s|define('BASE_PATH', __DIR__ . '/..');|define('BASE_PATH', __DIR__);|g" "$DIR/index.php"

# 5. Converter .env em Configuração PHP Segura (Para rodar sem Docker/.htaccess)
echo "🔐 Gerando sistema de configuração seguro (PHP)..."

# Cria o arquivo de credenciais (substituto do .env)
cat > "$DIR/config_credentials.php" <<EOF
<?php
/**
 * ARQUIVO DE CREDENCIAIS - PROTEGIDO
 * Preencha com os dados do servidor. Como é um arquivo PHP,
 * as senhas não vazam mesmo se acessado diretamente pelo navegador.
 */
return [
    'APP_ENV' => 'production', // 'local' ou 'production'
    
    // Configurações de E-mail (SMTP)
    'SMTP_HOST' => 'smtp.exemplo.com',
    'SMTP_PORT' => 587,
    'SMTP_USER' => 'seu@email.com',
    'SMTP_PASS' => 'sua_senha',
    
    // Segurança
    'ADMIN_TOKEN' => 'SEGREDO_SUPER_SEGURO', // Para enviar relatórios
    'REPORT_DESTINATION' => 'rh@digitalsat.com.br'
];
EOF

# Injeta o carregador de credenciais no início do config.php
# Isso faz o PHP ler o arquivo acima e preencher as variáveis de ambiente ($_ENV)
# FIX: Usa ($_ENV ?? []) para evitar erro se $_ENV for null
sed -i "1s|^<?php|<?php\n\$_ENV = array_merge(\$_ENV ?? [], require __DIR__ . '/config_credentials.php');\n|" "$DIR/config.php"

# 6. Proteção de Diretórios (Sem .htaccess)
echo "🛡️  Aplicando proteção contra listagem de diretórios..."
# Cria index.php falso para evitar que listem a pasta vendor ou reports
PROTECT_CODE="<?php http_response_code(403); die('Acesso Negado'); ?>"
echo "\$PROTECT_CODE" > "$DIR/vendor/index.php"
echo "\$PROTECT_CODE" > "$DIR/reports/index.php"

# 7. Criar Instruções de Deploy (README_DEPLOY.md)
echo "📄 Criando manual de deploy..."
cat > "$DIR/README_DEPLOY.md" <<'EOF'
# 🚀 Manual de Instalação (Deploy Seguro)

Este pacote contém a versão "Flat" do sistema, otimizada para rodar dentro de outros sites ou hospedagens compartilhadas.

## 📂 Estrutura
*   `index.php`: Ponto de entrada.
*   `config_credentials.php`: **CONFIGURE AQUI** (Senhas e E-mail).
*   `reports/`: Onde os CSVs são salvos.

## 📝 Passo a Passo

1.  Copie todos os arquivos para a pasta desejada no servidor.
2.  Edite o arquivo **`config_credentials.php`** com seus dados de SMTP.
3.  Garanta permissão de escrita na pasta `reports/`.

## 🛡️ Segurança
*   Este sistema **NÃO** usa `.env` nem `.htaccess`, sendo compatível com qualquer servidor (Apache/Nginx/IIS).
*   As senhas ficam protegidas dentro de um arquivo `.php`.
EOF

# 8. Permissões finais
chmod -R 755 "$DIR"

echo ""
echo "✅ Pacote de Deploy pronto na pasta: /$DIR"
echo "🚀 Estrutura modularizada pronta para upload!"
