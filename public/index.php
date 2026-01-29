<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Load Env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Helper to determine asset paths
function get_vite_assets() {
    $isDev = ($_ENV['APP_ENV'] ?? 'production') === 'local';
    
    if ($isDev) {
        return [
            'head' => '
                <script type="module" src="http://localhost:5173/@vite/client"></script>
                <script type="module" src="http://localhost:5173/src/js/main.js"></script>
            ',
            'body' => ''
        ];
    }

    // Production: Read Manifest
    $manifestPath = __DIR__ . '/assets/.vite/manifest.json';
    if (!file_exists($manifestPath)) {
        return ['head' => '', 'body' => ''];
    }

    $manifest = json_decode(file_get_contents($manifestPath), true);
    $entry = 'src/js/main.js'; // Must match vite.config.js input
    
    $head = '';
    $body = '';

    if (isset($manifest[$entry])) {
        // JS File
        $jsFile = $manifest[$entry]['file'];
        $body = '<script type="module" src="assets/' . $jsFile . '"></script>';

        // CSS Files
        if (isset($manifest[$entry]['css'])) {
            foreach ($manifest[$entry]['css'] as $css) {
                $head .= '<link rel="stylesheet" href="assets/' . $css . '">';
            }
        }
    }

    return ['head' => $head, 'body' => $body];
}

$assets = get_vite_assets();
$message = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    // Prepare Body Content
    $body = "<p><strong>E-mail do Funcionário:</strong> {$_POST['email']}</p>";
    $body .= "<p><strong>Nome:</strong> {$_POST['nome']}</p>";
    $body .= "<p><strong>Filial:</strong> {$_POST['filial']}</p>";
    $body .= "<p><strong>Departamento:</strong> {$_POST['departamento']}</p>";
    $body .= "<p><strong>Curso:</strong> {$_POST['curso']}</p>";
    $body .= "<p><strong>Tipo:</strong> {$_POST['tipo_treinamento']}</p>";
    $body .= "<p><strong>Duração:</strong> {$_POST['duracao']}</p>";

    // Mock Feature (Local Dev)
    $isDev = ($_ENV['APP_ENV'] ?? 'production') === 'local';
    $mockMessage = '';
    
    if ($isDev) {
        $mockFile = __DIR__ . '/email_mock.html';
        file_put_contents($mockFile, $body . "<hr><small>Mock gerado em: " . date('Y-m-d H:i:s') . "</small>");
        $mockMessage = '<br><small class="text-muted">Ambiente Local: <a href="email_mock.html" target="_blank">Ver e-mail simulado (Mock)</a></small>';
    }

    try {
        // Server settings
        $mail->CharSet    = 'UTF-8';
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'];
        $mail->Password   = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $_ENV['SMTP_PORT'];

        // Recipients
        $mail->setFrom($_ENV['SMTP_USER'], 'DigitalSat Treinamentos');
        $mail->addAddress($_ENV['SMTP_USER']); // Send to Admin (Self)
        $mail->addReplyTo($_POST['email'], $_POST['nome']); // Allow reply to employee
        
        $mail->isHTML(true);
        $mail->Subject = $_POST['nome'] . ' - ' . ucfirst($_POST['filial']) . ' - ' . $_POST['departamento'];
        $mail->Body = $body;

        // Attachment
        if (isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] == UPLOAD_ERR_OK) {
            $mail->addAttachment($_FILES['comprovante']['tmp_name'], $_FILES['comprovante']['name']);
        }

        $mail->send();
        $message = '<div class="alert alert-success">Registro enviado com sucesso!' . $mockMessage . '</div>';
    } catch (Exception $e) {
        if ($isDev) {
            $message = '<div class="alert alert-warning">Erro no SMTP (Normal em Dev), mas o Mock foi gerado!' . $mockMessage . '<br>Erro Real: ' . $mail->ErrorInfo . '</div>';
        } else {
            $message = '<div class="alert alert-danger">Erro ao enviar: ' . $mail->ErrorInfo . '</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Levantamento de Treinamentos - DigitalSat</title>
    
    <!-- Vite Assets (Head) -->
    <?php echo $assets['head']; ?>

</head>
<body>

<div class="container">
    <div class="main-container">
        <?php echo $message; ?>

        <!-- Logo -->
        <div class="logo-container">
            <img src="https://loja.digitalsat.com.br/imagem/logo-store?v=68468041b7f0a7698a97772f2c9fda4d" alt="DigitalSat Logo">
        </div>

        <!-- Header -->
        <div class="form-header">
            <h2>Ferramenta interna de cadastro de treinamentos</h2>
            <h4>Levantamento de Treinamentos - 2026</h4>
        </div>

        <!-- Intro Text -->
        <div class="intro-text">
            Prezado(a) funcionário(a), por favor, utilize este formulário para registrar todos os treinamentos e cursos realizados ao longo do ano de 2026.
        </div>

        <!-- Form -->
        <form action="" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
            
            <div class="mb-3">
                <label for="email" class="form-label">E-mail:</label>
                <input type="email" class="form-control" id="email" name="email" required placeholder="exemplo@exemplo.com.br">
            </div>

            <div class="mb-3">
                <label for="nome" class="form-label">Nome do Funcionário(a):</label>
                <input type="text" class="form-control" id="nome" name="nome" required placeholder="Nome completo">
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="filial" class="form-label">Filial:</label>
                    <select class="form-select" id="filial" name="filial" required>
                        <option value="" selected disabled>Selecione a filial...</option>
                        <option value="aptec">APTEC</option>
                        <option value="matriz">Matriz</option>
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
                <div class="col-md-6 mb-3">
                    <label for="departamento" class="form-label">Departamento:</label>
                    <input type="text" class="form-control" id="departamento" name="departamento" required placeholder="Ex: TI, Vendas, RH">
                </div>
            </div>

            <hr class="my-4">

            <h5 class="mb-3 text-muted">Detalhes do Curso</h5>

            <div class="mb-3">
                <label for="curso" class="form-label">Nome do Curso:</label>
                <input type="text" class="form-control" id="curso" name="curso" required placeholder="Título do curso realizado">
            </div>

            <div class="mb-3">
                <label class="form-label d-block">Tipo de Treinamento:</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="tipo_treinamento" id="presencial" value="presencial" required>
                    <label class="form-check-label" for="presencial">Presencial</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="tipo_treinamento" id="online_vivo" value="online_vivo">
                    <label class="form-check-label" for="online_vivo">Online (ao vivo)</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="tipo_treinamento" id="online_gravado" value="online_gravado">
                    <label class="form-check-label" for="online_gravado">Online (gravado)</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="tipo_treinamento" id="outro" value="outro">
                    <label class="form-check-label" for="outro">Outro</label>
                </div>
                <input type="text" class="form-control mt-2" id="outro_texto" name="outro_texto" placeholder="Se outro, especifique" style="display:none;">
            </div>

            <div class="mb-3">
                <label for="duracao" class="form-label">Duração do Curso:</label>
                <input type="text" class="form-control" id="duracao" name="duracao" required placeholder="Ex: 4 horas e 30 minutos">
            </div>

            <div class="mb-4">
                <label for="comprovante" class="form-label">Anexar Comprovante</label>
                <input class="form-control" type="file" id="comprovante" name="comprovante" accept=".pdf,.jpg,.png,.jpeg">
                <div class="form-text">Formatos aceitos: PDF, JPG, PNG.</div>
            </div>

            <button type="submit" class="btn btn-primary btn-submit">Enviar Registro</button>

        </form>
    </div>
</div>

<footer class="text-center mt-5 mb-5 text-muted" style="font-size: 0.8rem;">
    &copy; 2026 DigitalSat - Todos os direitos reservados.
</footer>

<!-- Vite Assets (Body) -->
<?php echo $assets['body']; ?>

<!-- Script for 'Outro' logic -->
<script>
    const radioButtons = document.querySelectorAll('input[name="tipo_treinamento"]');
    const outroInput = document.getElementById('outro_texto');

    radioButtons.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'outro') {
                outroInput.style.display = 'block';
                outroInput.required = true;
            } else {
                outroInput.style.display = 'none';
                outroInput.required = false;
                outroInput.value = '';
            }
        });
    });
</script>

</body>
</html>
