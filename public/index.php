<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';

// Carregar variáveis de ambiente
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
} catch (Exception $e) {
    // Ignorar se não houver .env (produção pode usar env vars do sistema)
}

// Gerar Token CSRF se não existir
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';

// Processar Formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validação CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Erro de Segurança: Token CSRF inválido.');
    }

    // 2. Sanitização de Inputs
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $nome = htmlspecialchars(strip_tags($_POST['nome'] ?? ''), ENT_QUOTES, 'UTF-8');
    $filial = htmlspecialchars(strip_tags($_POST['filial'] ?? ''), ENT_QUOTES, 'UTF-8');
    $departamento = htmlspecialchars(strip_tags($_POST['departamento'] ?? ''), ENT_QUOTES, 'UTF-8');
    $curso = htmlspecialchars(strip_tags($_POST['curso'] ?? ''), ENT_QUOTES, 'UTF-8');
    $tipo = htmlspecialchars(strip_tags($_POST['tipo_treinamento'] ?? ''), ENT_QUOTES, 'UTF-8');
    $duracao = htmlspecialchars(strip_tags($_POST['duracao'] ?? ''), ENT_QUOTES, 'UTF-8');
    
    // Tratamento para "Outro" tipo
    if ($tipo === 'outro') {
        $outro_texto = htmlspecialchars(strip_tags($_POST['outro_texto'] ?? ''), ENT_QUOTES, 'UTF-8');
        $tipo .= " ({$outro_texto})";
    }

    // Preparar Corpo do E-mail
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

    // Mock para Ambiente Local
    $isDev = ($_ENV['APP_ENV'] ?? 'production') === 'local';
    
    if ($isDev) {
        $mockFile = __DIR__ . '/email_mock.html';
        file_put_contents($mockFile, $body);
        $message = '<div class="alert alert-info border-0 shadow-sm">Ambiente Local: <a href="email_mock.html" target="_blank" class="fw-bold">Ver e-mail simulado</a></div>';
    }

    // Envio Real via PHPMailer
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
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

        // Anexo
        if (isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] == UPLOAD_ERR_OK) {
            $mail->addAttachment($_FILES['comprovante']['tmp_name'], $_FILES['comprovante']['name']);
        }

        $mail->send();
        $message .= '<div class="alert alert-success border-0 shadow-sm">✅ Registro enviado com sucesso!</div>';
        
        // Limpar campos após sucesso (opcional, aqui mantemos o POST para UX ou redirecionamos)
        // header("Location: " . $_SERVER['PHP_SELF'] . "?status=success"); exit;

    } catch (Exception $e) {
        $errorMsg = $isDev ? $mail->ErrorInfo : 'Contate o administrador.';
        $message = '<div class="alert alert-danger border-0 shadow-sm">❌ Erro ao enviar: ' . $errorMsg . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Levantamento de Treinamentos - DigitalSat</title>
    
    <!-- Bootstrap 5 CSS (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Roboto', sans-serif;
            color: #333;
        }
        .main-container {
            max-width: 800px;
            margin: 40px auto;
            background: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            border-top: 6px solid #0056b3;
        }
        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo-container img {
            max-width: 220px;
            height: auto;
        }
        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .form-header h2 {
            font-weight: 700;
            color: #0056b3;
            font-size: 1.6rem;
        }
        .intro-text {
            background-color: #eef2f7;
            padding: 20px;
            border-radius: 8px;
            font-size: 0.95rem;
            color: #495057;
            margin-bottom: 30px;
            border-left: 4px solid #0056b3;
        }
        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #555;
        }
        .btn-submit {
            background-color: #0056b3;
            color: white;
            padding: 14px 30px;
            font-size: 1.1rem;
            border-radius: 8px;
            border: none;
            width: 100%;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-submit:hover {
            background-color: #004494;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,86,179,0.2);
        }
        /* Ajuste para validação do Bootstrap */
        .was-validated .form-control:invalid {
            border-color: #dc3545;
            padding-right: calc(1.5em + .75rem);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5zM6 8.2a.75.75 0 110-1.5.75.75 0 010 1.5z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(.375em + .1875rem) center;
            background-size: calc(.75em + .375rem) calc(.75em + .375rem);
        }
    </style>
</head>
<body>

<div class="container pb-5">
    <div class="main-container">
        <?php echo $message; ?>

        <div class="logo-container">
            <!-- Placeholder para Logo, substitua a URL se necessário -->
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
            <!-- Token CSRF -->
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="email" class="form-label">Seu E-mail Corporativo</label>
                    <input type="email" class="form-control" id="email" name="email" required placeholder="nome@digitalsat.com.br">
                </div>
                <div class="col-md-6">
                    <label for="nome" class="form-label">Nome Completo</label>
                    <input type="text" class="form-control" id="nome" name="nome" required>
                </div>

                <div class="col-md-6">
                    <label for="filial" class="form-label">Filial</label>
                    <select class="form-select" id="filial" name="filial" required>
                        <option value="" selected disabled>Selecione...</option>
                        <option value="matriz">Matriz</option>
                        <option value="aptec">APTEC</option>
                        <option value="blumenau">Blumenau</option>
                        <option value="itapema">Itapema</option>
                        <option value="balneario_camboriu">Balneário Camboriú</option>
                        <option value="itajai">Itajaí</option>
                        <option value="brusque">Brusque</option>
                        <option value="joinville">Joinville</option>
                        <option value="rio_do_sul">Rio do Sul</option>
                        <option value="gravatai">Gravataí</option>
                        <option value="lages">Lages</option>
                        <option value="sao_jose">São José</option>
                        <option value="tubarao">Tubarão</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="departamento" class="form-label">Departamento</label>
                    <input type="text" class="form-control" id="departamento" name="departamento" required placeholder="Ex: Comercial, TI...">
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
                <div class="form-text">Aceita PDF ou Imagens (Max 5MB recomendado).</div>
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

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Lógica para campo 'Outro'
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

    // Validação Bootstrap
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