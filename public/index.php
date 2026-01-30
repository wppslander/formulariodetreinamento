<?php
// Segurança de Sessão (Cookie Hardening)
$cookieParams = [
    'lifetime' => 0, // Até fechar o navegador
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // Apenas HTTPS se disponível
    'httponly' => true, // Impede acesso via JS (Mitiga XSS)
    'samesite' => 'Strict' // Mitiga CSRF
];
session_set_cookie_params($cookieParams);
session_start();

require_once __DIR__ . '/../vendor/autoload.php';

// Carregar variáveis de ambiente
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
} catch (Exception $e) {
    // Ignorar se não houver .env
}

// Configurações e Listas Permitidas (Whitelisting)
$filiais_permitidas = [
    'matriz', 'aptec', 'blumenau', 'itapema', 'balneario_camboriu', 
    'itajai', 'brusque', 'joinville', 'rio_do_sul', 'gravatai', 
    'lages', 'sao_jose', 'tubarao'
];

$tipos_permitidos = ['presencial', 'online_vivo', 'online_gravado', 'outro'];

// Gerar Token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';

// Processar Formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1. Validação CSRF
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Erro de Segurança: Sessão expirada ou inválida. Recarregue a página.");
        }

        // 2. Rate Limiting (Anti-Spam) - 30 segundos
        if (isset($_SESSION['last_submit']) && (time() - $_SESSION['last_submit'] < 30)) {
            throw new Exception("Aguarde alguns segundos antes de enviar novamente.");
        }

        // 3. Validação de Inputs (Whitelisting & Sanitização)
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("E-mail inválido.");
        }

        $nome = trim(htmlspecialchars(strip_tags($_POST['nome'] ?? ''), ENT_QUOTES, 'UTF-8'));
        if (strlen($nome) < 3) throw new Exception("Nome muito curto.");

        $filial = $_POST['filial'] ?? '';
        if (!in_array($filial, $filiais_permitidas)) {
            throw new Exception("Filial selecionada inválida.");
        }

        $departamento = trim(htmlspecialchars(strip_tags($_POST['departamento'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $curso = trim(htmlspecialchars(strip_tags($_POST['curso'] ?? ''), ENT_QUOTES, 'UTF-8'));
        
        $tipo = $_POST['tipo_treinamento'] ?? '';
        if (!in_array($tipo, $tipos_permitidos)) {
            throw new Exception("Tipo de treinamento inválido.");
        }

        $duracao = trim(htmlspecialchars(strip_tags($_POST['duracao'] ?? ''), ENT_QUOTES, 'UTF-8'));

        // Tratamento para "Outro"
        if ($tipo === 'outro') {
            $outro_texto = trim(htmlspecialchars(strip_tags($_POST['outro_texto'] ?? ''), ENT_QUOTES, 'UTF-8'));
            if (empty($outro_texto)) throw new Exception("Especifique o tipo de treinamento.");
            $tipo .= " ({$outro_texto})";
        }

        // 4. Validação e Segurança de Arquivo
        $anexoPath = null;
        $anexoNome = null;

        if (isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] !== UPLOAD_ERR_NO_FILE) {
            $arquivo = $_FILES['comprovante'];

            // Erro de Upload
            if ($arquivo['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Erro no upload do arquivo.");
            }

            // Validar Tamanho (Max 5MB)
            if ($arquivo['size'] > 5 * 1024 * 1024) {
                throw new Exception("O arquivo excede o limite de 5MB.");
            }

            // Validar Tipo Real (MIME Type)
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeReal = $finfo->file($arquivo['tmp_name']);
            $mimesPermitidos = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];

            if (!in_array($mimeReal, $mimesPermitidos)) {
                throw new Exception("Formato de arquivo não permitido. Apenas PDF, JPG ou PNG.");
            }

            $anexoPath = $arquivo['tmp_name'];
            $anexoNome = $arquivo['name'];
        }

        // --- Envio do E-mail ---
        
        $body = "<h2>Registro de Treinamento</h2>";
        $body .= "<p><strong>Funcionário:</strong> {$nome}</p>";
        $body .= "<p><strong>E-mail:</strong> {$email}</p>";
        $body .= "<p><strong>Filial:</strong> " . ucfirst($filial) . "</p>";
        $body .= "<p><strong>Departamento:</strong> {$departamento}</p>";
        $body .= "<hr>";
        $body .= "<p><strong>Curso:</strong> {$curso}</p>";
        $body .= "<p><strong>Tipo:</strong> " . ucfirst(str_replace('_', ' ', $tipo)) . "</p>";
        $body .= "<p><strong>Duração:</strong> {$duracao}</p>";
        $body .= "<p><small>Enviado em: " . date('d/m/Y H:i:s') . "</small></p>";

        // Mock Local
        $isDev = ($_ENV['APP_ENV'] ?? 'production') === 'local';
        if ($isDev) {
            $mockFile = __DIR__ . '/email_mock.html';
            file_put_contents($mockFile, $body);
            $message = '<div class="alert alert-info border-0 shadow-sm">Ambiente Local: <a href="email_mock.html" target="_blank" class="fw-bold">Ver e-mail simulado</a></div>';
        }

        // PHPMailer
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->CharSet    = 'UTF-8';
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'];
        $mail->Password   = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $_ENV['SMTP_PORT'];

        $mail->setFrom($_ENV['SMTP_USER'], 'DigitalSat Treinamentos');
        $mail->addAddress($_ENV['SMTP_USER']); 
        if(filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($email, $nome);
        }
        
        $mail->isHTML(true);
        $mail->Subject = "Treinamento: {$nome} - " . ucfirst($filial);
        $mail->Body = $body;

        if ($anexoPath) {
            $mail->addAttachment($anexoPath, $anexoNome);
        }

        $mail->send();
        
        // Sucesso: Atualizar timestamp de envio
        $_SESSION['last_submit'] = time();
        $message .= '<div class="alert alert-success border-0 shadow-sm">✅ Registro enviado com sucesso!</div>';

    } catch (Exception $e) {
        $msgErro = $e->getMessage();
        if ($e instanceof PHPMailer\PHPMailer\Exception) {
             // Esconder erro técnico do PHPMailer em produção, mas mostrar em dev
             $msgErro = $isDev ? $mail->ErrorInfo : "Erro ao conectar com servidor de e-mail.";
        }
        $message = '<div class="alert alert-danger border-0 shadow-sm">❌ ' . $msgErro . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Levantamento de Treinamentos - DigitalSat</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

    <style>
        body { background-color: #f4f6f9; font-family: 'Roboto', sans-serif; color: #333; }
        .main-container { max-width: 800px; margin: 40px auto; background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border-top: 6px solid #0056b3; }
        .logo-container { text-align: center; margin-bottom: 30px; }
        .logo-container img { max-width: 220px; height: auto; }
        .form-header { text-align: center; margin-bottom: 30px; }
        .form-header h2 { font-weight: 700; color: #0056b3; font-size: 1.6rem; }
        .intro-text { background-color: #eef2f7; padding: 20px; border-radius: 8px; font-size: 0.95rem; color: #495057; margin-bottom: 30px; border-left: 4px solid #0056b3; }
        .form-label { font-weight: 500; margin-bottom: 0.5rem; color: #555; }
        .btn-submit { background-color: #0056b3; color: white; padding: 14px 30px; font-size: 1.1rem; border-radius: 8px; border: none; width: 100%; font-weight: 500; transition: all 0.3s ease; }
        .btn-submit:hover { background-color: #004494; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,86,179,0.2); }
        .was-validated .form-control:invalid { border-color: #dc3545; background-image: url("data:image/svg+xml,..."); }
    </style>
</head>
<body>

<div class="container pb-5">
    <div class="main-container">
        <?php echo $message; ?>

        <div class="logo-container">
            <img src="https://loja.digitalsat.com.br/imagem/logo-store?v=68468041b7f0a7698a97772f2c9fda4d" alt="DigitalSat Logo">
        </div>

        <div class="form-header">
            <h2>Cadastro de Treinamentos Internos</h2>
            <p class="text-muted">Ciclo 2026</p>
        </div>

        <div class="intro-text">
            Prezado(a) colaborador(a), utilize este formulário para registrar oficialmente seus treinamentos realizados.
        </div>

        <form action="" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="email" class="form-label">Seu E-mail Corporativo</label>
                    <input type="email" class="form-control" id="email" name="email" required placeholder="nome@digitalsat.com.br">
                </div>
                <div class="col-md-6">
                    <label for="nome" class="form-label">Nome Completo</label>
                    <input type="text" class="form-control" id="nome" name="nome" required minlength="3">
                </div>

                <div class="col-md-6">
                    <label for="filial" class="form-label">Filial</label>
                    <select class="form-select" id="filial" name="filial" required>
                        <option value="" selected disabled>Selecione...</option>
                        <?php foreach($filiais_permitidas as $f): ?>
                            <option value="<?php echo $f; ?>"><?php echo ucfirst(str_replace('_', ' ', $f)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="departamento" class="form-label">Departamento</label>
                    <input type="text" class="form-control" id="departamento" name="departamento" required>
                </div>
            </div>

            <hr class="my-4 text-muted">

            <h5 class="mb-3 text-primary">Dados do Treinamento</h5>

            <div class="mb-3">
                <label for="curso" class="form-label">Nome do Curso / Treinamento</label>
                <input type="text" class="form-control" id="curso" name="curso" required>
            </div>

            <div class="mb-3">
                <label class="form-label d-block">Modalidade</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="tipo_treinamento" id="presencial" value="presencial" required>
                    <label class="form-check-label" for="presencial">Presencial</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="tipo_treinamento" id="online_vivo" value="online_vivo">
                    <label class="form-check-label" for="online_vivo">Online (Ao Vivo)</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="tipo_treinamento" id="online_gravado" value="online_gravado">
                    <label class="form-check-label" for="online_gravado">Online (Gravado)</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="tipo_treinamento" id="outro" value="outro">
                    <label class="form-check-label" for="outro">Outro</label>
                </div>
                <input type="text" class="form-control mt-2" id="outro_texto" name="outro_texto" placeholder="Especifique qual..." style="display:none;">
            </div>

            <div class="mb-3">
                <label for="duracao" class="form-label">Carga Horária</label>
                <input type="text" class="form-control" id="duracao" name="duracao" required placeholder="Ex: 4h">
            </div>

            <div class="mb-4">
                <label for="comprovante" class="form-label">Certificado ou Comprovante (Opcional)</label>
                <input class="form-control" type="file" id="comprovante" name="comprovante" accept=".pdf,.jpg,.png,.jpeg">
                <div class="form-text">Aceita PDF, JPG, PNG (Max 5MB).</div>
            </div>

            <button type="submit" class="btn btn-submit">
                Enviar Registro
            </button>
        </form>
    </div>
    
    <div class="text-center text-muted small">
        &copy; 2026 DigitalSat
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const radioButtons = document.querySelectorAll('input[name="tipo_treinamento"]');
    const outroInput = document.getElementById('outro_texto');

    radioButtons.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'outro') {
                outroInput.style.display = 'block';
                outroInput.required = true;
                outroInput.focus();
            } else {
                outroInput.style.display = 'none';
                outroInput.required = false;
                outroInput.value = '';
            }
        });
    });

    (() => {
        'use strict'
        const forms = document.querySelectorAll('.needs-validation')
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                form.classList.add('was-validated')
            }, false)
        })
    })()
</script>
</body>
</html>
