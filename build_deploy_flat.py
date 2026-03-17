import os
import shutil
import glob
import re

def create_deploy_package():
    deploy_dir = 'deploy_flat'
    print(f"📦 Iniciando processo de empacotamento para Deploy (Flat) em '{deploy_dir}'...")

    # 1. Limpeza e Criação do Diretório
    if os.path.exists(deploy_dir):
        shutil.rmtree(deploy_dir)
    os.makedirs(deploy_dir)
    os.makedirs(os.path.join(deploy_dir, 'reports'))

    # 2. Copiar arquivos da pasta public (onde roda a aplicação)
    print("📂 Copiando arquivos do diretório public...")
    for item in os.listdir('public'):
        s = os.path.join('public', item)
        d = os.path.join(deploy_dir, item)
        if os.path.isdir(s):
            shutil.copytree(s, d)
        else:
            shutil.copy2(s, d)

    # 3. Copiar dependências (vendor)
    print("📂 Copiando dependências (vendor)...")
    if os.path.exists('vendor'):
        shutil.copytree('vendor', os.path.join(deploy_dir, 'vendor'))
    else:
        print("⚠️ Aviso: Pasta 'vendor' não encontrada. Certifique-se de ter rodado o Composer.")

    # 4. Ajustar caminhos (Flat structure)
    # Como tiramos o código da pasta public/ e colocamos na raiz (junto com vendor e reports),
    # precisamos trocar dirname(__DIR__) por __DIR__
    print("🔧 Ajustando caminhos nos arquivos PHP para estrutura Flat...")
    php_files = glob.glob(f"{deploy_dir}/**/*.php", recursive=True)
    
    for file_path in php_files:
        with open(file_path, 'r', encoding='utf-8') as f:
            content = f.read()
        
        # Substitui dirname(__DIR__) por __DIR__ em todos os lugares
        new_content = re.sub(r"dirname\(__DIR__\)", "__DIR__", content)
        
        # Se for o index.php ou admin.php, vamos desabilitar a checagem obrigatoria do .env
        if file_path.endswith('index.php') or file_path.endswith('admin.php'):
            new_content = new_content.replace(
                r"$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);",
                "// Dotenv removido para deploy flat (usa config_credentials.php)"
            )
            new_content = new_content.replace(
                "$dotenv->safeLoad();",
                ""
            )

        if content != new_content:
            with open(file_path, 'w', encoding='utf-8') as f:
                f.write(new_content)

    # 5. Criar sistema de configuração seguro baseado em PHP puro
    print("🔐 Gerando sistema de configuração seguro (PHP) sem dependência de .env...")
    credentials_path = os.path.join(deploy_dir, 'config_credentials.php')
    with open(credentials_path, 'w', encoding='utf-8') as f:
        f.write('''<?php
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
    'ADMIN_TOKEN' => 'QsEtSkL7YGjAz5u', // Para acesso ao painel
    'REPORT_DESTINATION' => 'rh@digitalsat.com.br'
];
''')

    # Injetar o carregador de credenciais no config.php
    config_path = os.path.join(deploy_dir, 'config.php')
    if os.path.exists(config_path):
        with open(config_path, 'r', encoding='utf-8') as f:
            config_content = f.read()
        
        injection = "<?php\n$_ENV = array_merge($_ENV ?? [], require __DIR__ . '/config_credentials.php');\n"
        config_content = config_content.replace('<?php', injection, 1)
        
        with open(config_path, 'w', encoding='utf-8') as f:
            f.write(config_content)

    # 6. Proteção de Diretórios
    print("🛡️ Aplicando proteção contra listagem de diretórios...")
    protect_code = "<?php http_response_code(403); die('Acesso Negado'); ?>"
    with open(os.path.join(deploy_dir, 'vendor', 'index.php'), 'w') as f: f.write(protect_code)
    with open(os.path.join(deploy_dir, 'reports', 'index.php'), 'w') as f: f.write(protect_code)

    # Criar um .htaccess para proteção extra no Apache
    with open(os.path.join(deploy_dir, '.htaccess'), 'w') as f:
        f.write(r'''# Bloqueia listagem de diretórios
Options -Indexes

# Bloqueia acesso direto ao banco de dados SQLite e arquivos de log
<FilesMatch "\.(sqlite|log)$">
    Require all denied
</FilesMatch>
''')

    # 7. Criar manual de instruções
    print("📄 Criando manual de deploy...")
    readme_path = os.path.join(deploy_dir, 'README_DEPLOY.md')
    with open(readme_path, 'w', encoding='utf-8') as f:
        f.write('''# 🚀 Manual de Instalação (Hospedagem Compartilhada / VPS sem Docker)

Este pacote contém a versão "Flat" do sistema, totalmente autossuficiente e agnóstica de ambiente. Ela foi estruturada para rodar dentro de qualquer hospedagem PHP padrão (cPanel, HostGator, Locaweb, etc).

## 📂 Como Instalar

1.  Faça o upload de **todo o conteúdo** desta pasta (`deploy_flat`) para o diretório raiz do seu site (geralmente `public_html`, `www` ou `htdocs`).
2.  Abra o arquivo **`config_credentials.php`** (usando o Gerenciador de Arquivos da hospedagem ou via FTP).
3.  Preencha com seus dados reais de E-mail (SMTP) e a senha de administração.
4.  **Atenção:** Garanta que a pasta `reports/` tenha permissão de escrita (CHMOD 755 ou 775), pois é lá que o SQLite salvará o arquivo de banco de dados automaticamente.

## 🛡️ Vantagens de Segurança desta versão
*   **Sem `.env`:** Hospedagens compartilhadas costumam vazar arquivos `.env`. Por isso, suas senhas foram migradas para o `config_credentials.php` (sendo um arquivo PHP, o código-fonte nunca é exibido pelo servidor web).
*   **Rotas Relativas:** Todos os caminhos foram reescritos dinamicamente com `__DIR__`, o que significa que você pode hospedar o sistema na raiz (`meusite.com/`) ou dentro de uma sub-pasta (`meusite.com/treinamentos/`) sem precisar alterar nada no código.
*   **.htaccess Protegido:** Bloqueia o download do banco de dados SQLite.
''')

    print(f"\n✅ Pacote de Deploy Flat criado com sucesso na pasta: ./{deploy_dir}/")
    print("🚀 Pronto para copiar e colar em qualquer servidor PHP!")

if __name__ == "__main__":
    create_deploy_package()
