<?php
/**
 * CONTROLLER.PHP
 * Cérebro da aplicação. Recebe as requisições, valida os dados e decide o que fazer.
 * Não deve conter HTML, apenas lógica de controle.
 */

$message = ''; // Variável que armazenará feedbacks (sucesso/erro) para a View

// ==============================================================================
// 1. ROTA ADMINISTRATIVA (Envio de Relatório para RH)
// ==============================================================================
// Gatilho via URL: ?action=enviar_relatorio&token=SEGREDO
if (isset($_GET['action']) && $_GET['action'] === 'enviar_relatorio') {
    $token = $_GET['token'] ?? '';
    
    // Verifica se o token bate com o definido no .env
    if ($token === ($_ENV['ADMIN_TOKEN'] ?? '')) {
        enviar_relatorio_rh(REPORTS_DIR); // Chama função em functions.php
    } else {
        die("Acesso Negado: Token inválido.");
    }
}

// ==============================================================================
// 2. PROCESSAMENTO DO FORMULÁRIO (POST)
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // --- A. Validações de Segurança ---
        
        // Verifica Token CSRF (Anti-Falsificação)
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Sessão expirada. Recarregue a página.");
        }

        // Rate Limit (Impede envio rápido demais - 10 segundos)
        if (isset($_SESSION['last_submit']) && (time() - $_SESSION['last_submit'] < 10)) {
            throw new Exception("Aguarde alguns segundos antes de enviar novamente.");
        }

        // --- B. Coleta e Sanitização de Dados ---
        
        // Limpa tags HTML e espaços em branco para evitar XSS
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $nome = trim(htmlspecialchars(strip_tags($_POST['nome'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $filial = $_POST['filial'] ?? '';
        $departamento = trim(htmlspecialchars(strip_tags($_POST['departamento'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $curso = trim(htmlspecialchars(strip_tags($_POST['curso'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $tipo = $_POST['tipo_treinamento'] ?? '';
        
        // Duração: Converte inputs separados em total de minutos
        $horas = filter_input(INPUT_POST, 'duracao_horas', FILTER_VALIDATE_INT) ?: 0;
        $minutos = filter_input(INPUT_POST, 'duracao_minutos', FILTER_VALIDATE_INT) ?: 0;

        // Valida se a duração faz sentido (não pode ser zero ou negativa)
        if ($horas < 0 || $minutos < 0 || ($horas == 0 && $minutos == 0)) {
            throw new Exception("Informe uma duração válida para o curso.");
        }
        
        $total_minutos = ($horas * 60) + $minutos; // Para o CSV
        $duracao_formatada = sprintf("%02dh %02dm", $horas, $minutos); // Para o E-mail

        // --- C. Validações de Regra de Negócio (Whitelisting) ---
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception("E-mail inválido.");
        
        // Garante que o valor escolhido está na lista de permitidos (config.php)
        if (!in_array($filial, $filiais_permitidas)) throw new Exception("Filial inválida.");
        if (!in_array($tipo, $tipos_permitidos)) throw new Exception("Tipo inválido.");

        // Tratamento especial para campo "Outro"
        if ($tipo === 'outro') {
            $outro_texto = trim(htmlspecialchars(strip_tags($_POST['outro_texto'] ?? ''), ENT_QUOTES, 'UTF-8'));
            $tipo = "Outro: " . $outro_texto;
        }

        // --- D. Envio do E-mail (PHPMailer) ---
        
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

        // Anexo (Upload Seguro)
        if (isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] == UPLOAD_ERR_OK) {
            $mail->addAttachment($_FILES['comprovante']['tmp_name'], $_FILES['comprovante']['name']);
        }

        $mail->send();

        // --- E. Auditoria (CSV) ---
        
        registrar_log_master([
            'nome' => $nome,
            'email' => $email,
            'filial' => $filial,
            'departamento' => $departamento,
            'curso' => $curso,
            'tipo' => $tipo,
            'duracao' => $total_minutos // Salva como Inteiro
        ], REPORTS_DIR);
        
        // Atualiza timestamp para o Rate Limit
        $_SESSION['last_submit'] = time();
        
        // Mensagem de Sucesso
        $message = '<div class="alert alert-success border-0 shadow-sm">✅ Registro enviado e arquivado com sucesso!</div>';

    } catch (Exception $e) {
        // Mensagem de Erro (captura qualquer problema acima)
        $message = '<div class="alert alert-danger border-0 shadow-sm">❌ ' . $e->getMessage() . '</div>';
    }
}