<?php
/**
 * CONTROLLER.PHP
 * Lógica de processamento e gravação (CSV + SQLite).
 */

$message = ''; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Sessão expirada.");
        }

        if (isset($_SESSION['last_submit']) && (time() - $_SESSION['last_submit'] < 10)) {
            throw new Exception("Aguarde alguns segundos.");
        }

        // Dados base
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $nome = trim(htmlspecialchars(strip_tags($_POST['nome'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $filial_slug = $_POST['filial'] ?? '';
        $departamento_slug = $_POST['departamento'] ?? '';
        $curso = trim(htmlspecialchars(strip_tags($_POST['curso'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $tipo_slug = $_POST['tipo_treinamento'] ?? '';
        $data_conclusao = $_POST['data_conclusao'] ?? ''; 
        
        // Data para o Banco
        $data_db = null;
        if (!empty($data_conclusao)) {
            $partes = explode('/', $data_conclusao);
            if (count($partes) === 3) $data_db = "{$partes[2]}-{$partes[1]}-{$partes[0]}";
        }

        // Carga Horária
        $horas = filter_var($_POST['duracao_horas'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
        $minutos = filter_var($_POST['duracao_minutos'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
        $total_minutos = ($horas * 60) + $minutos; 
        $duracao_formatada = exibir_duracao_formatada($total_minutos);

        if (!array_key_exists($filial_slug, $filiais_permitidas)) throw new Exception("Filial inválida.");
        if (!array_key_exists($departamento_slug, $departamentos_permitidos)) throw new Exception("Departamento inválido.");

        $outro_texto = ($tipo_slug === 'outro') ? trim(htmlspecialchars(strip_tags($_POST['outro_texto'] ?? ''), ENT_QUOTES, 'UTF-8')) : null;

        // --- GRAVAÇÃO NO SQLITE (Novo) ---
        registrar_treinamento_db([
            'nome'            => $nome,
            'email'           => $email,
            'filial'          => $filial_slug,
            'departamento'    => $departamento_slug,
            'curso'           => $curso,
            'data_conclusao'  => $data_db,
            'tipo'            => $tipo_slug,
            'outro_texto'     => $outro_texto,
            'duracao'         => $total_minutos
        ]);

        // --- GRAVAÇÃO NO CSV (Legado) ---
        registrar_log_master([
            'nome' => $nome,
            'email' => $email,
            'filial' => $filiais_permitidas[$filial_slug],
            'departamento' => $departamentos_permitidos[$departamento_slug],
            'curso' => $curso,
            'tipo' => ($tipo_slug === 'outro' ? "Outro: $outro_texto" : $tipo_slug),
            'duracao' => $total_minutos
        ], REPORTS_DIR);

        // --- ENVIO DE E-MAIL ---
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        if (($_ENV['APP_ENV'] ?? 'local') === 'local') {
            file_put_contents(__DIR__ . '/email_mock.html', "<h2>MOCK EMAIL</h2><p>Treinamento: {$nome}</p>");
        } else {
            $mail->isSMTP();
            $mail->Host = $_ENV['SMTP_HOST']; $mail->SMTPAuth = true;
            $mail->Username = $_ENV['SMTP_USER']; $mail->Password = $_ENV['SMTP_PASS'];
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; $mail->Port = $_ENV['SMTP_PORT'];
            $mail->setFrom($_ENV['SMTP_USER'], 'DigitalSat Treinamentos');
            $mail->addAddress($_ENV['REPORT_DESTINATION'] ?? 'rh@digitalsat.com.br');
            $mail->isHTML(true);
            $mail->Subject = "Treinamento: {$nome} - " . $filiais_permitidas[$filial_slug];
            $mail->Body = "<h3>Registro</h3><p>Nome: {$nome}</p><p>Curso: {$curso}</p><p>Duração: {$duracao_formatada}</p>";
            if (isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] == UPLOAD_ERR_OK) {
                $mail->addAttachment($_FILES['comprovante']['tmp_name'], $_FILES['comprovante']['name']);
            }
            $mail->send();
        }

        $_SESSION['last_submit'] = time();
        $message = json_encode(['type' => 'success', 'title' => 'Sucesso!', 'body' => '✅ Registro realizado com sucesso.']);

    } catch (Exception $e) {
        $message = json_encode(['type' => 'danger', 'title' => 'Erro', 'body' => '❌ ' . $e->getMessage()]);
    }
}
