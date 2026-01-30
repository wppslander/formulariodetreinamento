<?php
// Segurança de Sessão (Cookie Hardening)
$cookieParams = [
    'lifetime' => 0, // Até fechar o navegador
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Strict'
];
session_set_cookie_params($cookieParams);
session_start();

require_once __DIR__ . '/../vendor/autoload.php';

// Carregar variáveis de ambiente
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
} catch (Exception $e) {}

// Configurações
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

// Função para Registrar Log Master (CSV)
function registrar_log_master($dados) {
    $diretorio = __DIR__ . '/../reports';
    $arquivo = $diretorio . '/treinamentos_master.csv';

    // Cria diretório se não existir
    if (!is_dir($diretorio)) {
        mkdir($diretorio, 0755, true);
    }

    $novoArquivo = !file_exists($arquivo);
    $handle = fopen($arquivo, 'a');
    
    if ($handle) {
        // Trava o arquivo para evitar corrupção em acessos simultâneos
        flock($handle, LOCK_EX);

        // Cabeçalho se for arquivo novo
        if ($novoArquivo) {
            fputcsv($handle, ['Data/Hora', 'Nome', 'Email', 'Filial', 'Departamento', 'Curso', 'Tipo', 'Duracao', 'IP']);
        }

        // Dados da linha
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        // Se houver múltiplos IPs no X-Forwarded-For (vírgula), pega o primeiro
        if (strpos($ip, ',') !== false) {
            $ip = explode(',', $ip)[0];
        }

        fputcsv($handle, [
            date('Y-m-d H:i:s'),
            $dados['nome'],
            $dados['email'],
            $dados['filial'],
            $dados['departamento'],
            $dados['curso'],
            $dados['tipo'],
            $dados['duracao'],
            trim($ip)
        ]);

        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

// Função para Enviar Relatório (Admin)
function enviar_relatorio_rh() {
    $arquivo = __DIR__ . '/../reports/treinamentos_master.csv';
    
    if (!file_exists($arquivo)) {
        die("Nenhum relatório encontrado para enviar.");
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'];
        $mail->Password   = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $_ENV['SMTP_PORT'];

        $mail->setFrom($_ENV['SMTP_USER'], 'DigitalSat Sistema');
        $mail->addAddress('rh@digitalsat.com.br');
        
        $mail->isHTML(true);
        $mail->Subject = "[AUDITORIA] Relatório Geral de Treinamentos - " . date('d/m/Y');
        $mail->Body = "Seguem em anexo todos os registros de treinamento até o momento.";
        
        $mail->addAttachment($arquivo, 'relatorio_treinamentos_' . date('Ymd') . '.csv');
        
        $mail->send();
        echo "Relatório enviado com sucesso para rh@digitalsat.com.br!";
    } catch (Exception $e) {
        echo "Erro ao enviar relatório: {$mail->ErrorInfo}";
    }
    exit;
}

$message = '';

// Gatilho Admin (Ex: ?action=enviar_relatorio&token=SEGREDO)
if (isset($_GET['action']) && $_GET['action'] === 'enviar_relatorio') {
    $token = $_GET['token'] ?? '';
    if ($token === ($_ENV['ADMIN_TOKEN'] ?? '')) {
        enviar_relatorio_rh();
    } else {
        die("Acesso Negado.");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Sessão expirada. Recarregue a página.");
        }

        if (isset($_SESSION['last_submit']) && (time() - $_SESSION['last_submit'] < 10)) {
            throw new Exception("Aguarde alguns segundos antes de enviar novamente.");
        }

        $tipo = $_POST['tipo_treinamento'] ?? '';
        if (!in_array($tipo, $tipos_permitidos)) {
            throw new Exception("Tipo de treinamento inválido.");
        }

        // Processamento de Duração (Horas + Minutos) -> Total Minutos
        $horas = filter_input(INPUT_POST, 'duracao_horas', FILTER_VALIDATE_INT) ?: 0;
        $minutos = filter_input(INPUT_POST, 'duracao_minutos', FILTER_VALIDATE_INT) ?: 0;
        
        if ($horas < 0 || $minutos < 0 || ($horas == 0 && $minutos == 0)) {
            throw new Exception("Informe uma duração válida para o curso.");
        }
        
        // CSV: Salva total em minutos (Inteiro)
        $total_minutos = ($horas * 60) + $minutos;
        
        // E-mail: Exibe formatado (04h 30m)
        $duracao_formatada = sprintf("%02dh %02dm", $horas, $minutos);

        if ($tipo === 'outro') {
            $outro_texto = trim(htmlspecialchars(strip_tags($_POST['outro_texto'] ?? ''), ENT_QUOTES, 'UTF-8'));
            $tipo = "Outro: " . $outro_texto;
        }

        // --- Envio E-mail ---
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'];
        $mail->Password   = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $_ENV['SMTP_PORT'];

        $mail->setFrom($_ENV['SMTP_USER'], 'DigitalSat Treinamentos');
        $mail->addAddress($_ENV['SMTP_USER']);
        $mail->addReplyTo($email, $nome);
        
        $mail->isHTML(true);
        $mail->Subject = "Treinamento: {$nome} - " . ucfirst($filial);
        $mail->Body = "<h3>Registro de Treinamento</h3><p><strong>Nome:</strong> {$nome}</p><p><strong>Curso:</strong> {$curso}</p><p><strong>Duração:</strong> {$duracao_formatada}</p>";

        if (isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] == UPLOAD_ERR_OK) {
            $mail->addAttachment($_FILES['comprovante']['tmp_name'], $_FILES['comprovante']['name']);
        }

        $mail->send();

        // --- LOG MASTER CSV ---
        registrar_log_master([
            'nome' => $nome,
            'email' => $email,
            'filial' => $filial,
            'departamento' => $departamento,
            'curso' => $curso,
            'tipo' => $tipo,
            'duracao' => $total_minutos // Salva como INTEIRO (minutos totais)
        ]);
        
        $_SESSION['last_submit'] = time();
        $message = '<div class="alert alert-success border-0 shadow-sm">✅ Registro enviado e arquivado com sucesso!</div>';

    } catch (Exception $e) {
        $message = '<div class="alert alert-danger border-0 shadow-sm">❌ ' . $e->getMessage() . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Levantamento de Treinamentos - DigitalSat</title>
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
                    <label for="email" class="form-label">E-mail</label>
                    <input type="email" class="form-control" id="email" name="email" required placeholder="seu@email.com">
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
                <label class="form-label">Carga Horária</label>
                <div class="row g-2">
                    <div class="col-6">
                        <div class="input-group">
                            <input type="number" class="form-control" id="duracao_horas" name="duracao_horas" min="0" required placeholder="0">
                            <span class="input-group-text">Horas</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="input-group">
                            <input type="number" class="form-control" id="duracao_minutos" name="duracao_minutos" min="0" max="59" required placeholder="0">
                            <span class="input-group-text">Minutos</span>
                        </div>
                    </div>
                </div>
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