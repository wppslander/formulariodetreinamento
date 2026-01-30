<?php

$message = '';

// 1. Rota Administrativa (Envio de Relatório)
if (isset($_GET['action']) && $_GET['action'] === 'enviar_relatorio') {
    $token = $_GET['token'] ?? '';
    if ($token === ($_ENV['ADMIN_TOKEN'] ?? '')) {
        enviar_relatorio_rh(REPORTS_DIR);
    } else {
        die("Acesso Negado.");
    }
}

// 2. Processamento do Formulário (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validações de Sessão e Rate Limit
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Sessão expirada. Recarregue a página.");
        }

        if (isset($_SESSION['last_submit']) && (time() - $_SESSION['last_submit'] < 10)) {
            throw new Exception("Aguarde alguns segundos antes de enviar novamente.");
        }

        // Sanitização e Validação
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $nome = trim(htmlspecialchars(strip_tags($_POST['nome'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $filial = $_POST['filial'] ?? '';
        $departamento = trim(htmlspecialchars(strip_tags($_POST['departamento'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $curso = trim(htmlspecialchars(strip_tags($_POST['curso'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $tipo = $_POST['tipo_treinamento'] ?? '';
        
        // Duração (Horas/Minutos)
        $horas = filter_input(INPUT_POST, 'duracao_horas', FILTER_VALIDATE_INT) ?: 0;
        $minutos = filter_input(INPUT_POST, 'duracao_minutos', FILTER_VALIDATE_INT) ?: 0;

        if ($horas < 0 || $minutos < 0 || ($horas == 0 && $minutos == 0)) {
            throw new Exception("Informe uma duração válida para o curso.");
        }
        
        $total_minutos = ($horas * 60) + $minutos;
        $duracao_formatada = sprintf("%02dh %02dm", $horas, $minutos);

        // Validações Lógicas
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception("E-mail inválido.");
        if (!in_array($filial, $filiais_permitidas)) throw new Exception("Filial inválida.");
        if (!in_array($tipo, $tipos_permitidos)) throw new Exception("Tipo inválido.");

        if ($tipo === 'outro') {
            $outro_texto = trim(htmlspecialchars(strip_tags($_POST['outro_texto'] ?? ''), ENT_QUOTES, 'UTF-8'));
            $tipo = "Outro: " . $outro_texto;
        }

        // Envio do E-mail (PHPMailer)
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

        // Registro no CSV (Auditoria)
        registrar_log_master([
            'nome' => $nome,
            'email' => $email,
            'filial' => $filial,
            'departamento' => $departamento,
            'curso' => $curso,
            'tipo' => $tipo,
            'duracao' => $total_minutos
        ], REPORTS_DIR);
        
        $_SESSION['last_submit'] = time();
        $message = '<div class="alert alert-success border-0 shadow-sm">✅ Registro enviado e arquivado com sucesso!</div>';

    } catch (Exception $e) {
        $message = '<div class="alert alert-danger border-0 shadow-sm">❌ ' . $e->getMessage() . '</div>';
    }
}
