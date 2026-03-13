<?php
/**
 * CONTROLLER.PHP
 * Cérebro da aplicação. Recebe as requisições, valida os dados e decide o que fazer.
 * Não deve conter HTML, apenas lógica de controle.
 */

$message = ''; // Variável que armazenará feedbacks (sucesso/erro) para a View

// ==============================================================================
// 1. PROCESSAMENTO DO FORMULÁRIO (POST)
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
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $nome = trim(htmlspecialchars(strip_tags($_POST['nome'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $filial = $_POST['filial'] ?? '';
        $departamento_slug = $_POST['departamento'] ?? '';
        $curso = trim(htmlspecialchars(strip_tags($_POST['curso'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $tipo = $_POST['tipo_treinamento'] ?? '';
        
        // Duração: Converte inputs separados em total de minutos
        $horas = filter_var($_POST['duracao_horas'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
        $minutos = filter_var($_POST['duracao_minutos'] ?? 0, FILTER_VALIDATE_INT) ?: 0;

        // Valida se a duração faz sentido (não pode ser zero ou negativa)
        if ($horas < 0 || $minutos < 0 || ($horas == 0 && $minutos == 0)) {
            throw new Exception("Informe uma duração válida para o curso.");
        }
        
        $total_minutos = ($horas * 60) + $minutos; // Para o CSV
        $duracao_formatada = sprintf("%02dh %02dm", $horas, $minutos); // Para o E-mail

        // --- C. Validações de Regra de Negócio (Whitelisting) ---
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception("E-mail inválido.");
        
        // Garante que o valor escolhido está na lista de permitidos (config.php)
        if (!array_key_exists($filial, $filiais_permitidas)) throw new Exception("Filial inválida.");
        if (!array_key_exists($departamento_slug, $departamentos_permitidos)) throw new Exception("Departamento inválido.");
        if (!in_array($tipo, $tipos_permitidos)) throw new Exception("Tipo inválido.");

        // Nomes formatados para e-mail e logs
        $filial_nome = $filiais_permitidas[$filial];
        $departamento_nome = $departamentos_permitidos[$departamento_slug];

        // Tratamento especial para campo "Outro"
        if ($tipo === 'outro') {
            $outro_texto = trim(htmlspecialchars(strip_tags($_POST['outro_texto'] ?? ''), ENT_QUOTES, 'UTF-8'));
            $tipo = "Outro: " . $outro_texto;
        }

        // --- D. Envio do E-mail (PHPMailer) ---
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        
        // Verifica se estamos em ambiente de teste/local para não disparar e-mail real
        if (($_ENV['APP_ENV'] ?? 'local') === 'local') {
            // MOCK: Em vez de enviar, gera um arquivo HTML para visualização
            $mock_content = "<h2>MOCK EMAIL - AMBIENTE LOCAL</h2><hr>";
            $mock_content .= "<strong>De:</strong> DigitalSat Treinamentos <br>";
            $mock_content .= "<strong>Para:</strong> " . ($_ENV['SMTP_USER'] ?? 'rh@digitalsat.com.br') . "<br>";
            $mock_content .= "<strong>Assunto:</strong> Treinamento: {$nome} - {$filial_nome}<hr>";
            $mock_content .= "<h3>Registro de Treinamento</h3><p><strong>Nome:</strong> {$nome}</p><p><strong>Curso:</strong> {$curso}</p><p><strong>Duração:</strong> {$duracao_formatada}</p>";
            
            // Se houver anexo, avisa no mock
            if (isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] == UPLOAD_ERR_OK) {
                $mock_content .= "<hr><p>📎 <strong>Anexo:</strong> " . $_FILES['comprovante']['name'] . "</p>";
            }

            file_put_contents(__DIR__ . '/email_mock.html', $mock_content);
        } else {
            // ENVIO REAL (Produção)
            $mail->isSMTP();
            $mail->Host       = $_ENV['SMTP_HOST'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['SMTP_USER'];
            $mail->Password   = $_ENV['SMTP_PASS'];
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $_ENV['SMTP_PORT'];

            $mail->setFrom($_ENV['SMTP_USER'], 'DigitalSat Treinamentos');
            // O registro agora vai para o e-mail de relatório definido no .env
            $destinatario = $_ENV['REPORT_DESTINATION'] ?? 'daniel.ti@digitalsat.com.br';
            $mail->addAddress($destinatario);
            $mail->addReplyTo($email, $nome);
            
            $mail->isHTML(true);
            $mail->Subject = "Treinamento: {$nome} - {$filial_nome}";
            $mail->Body = "<h3>Registro de Treinamento</h3>
                           <p><strong>Nome:</strong> {$nome}</p>
                           <p><strong>Filial:</strong> {$filial_nome}</p>
                           <p><strong>Departamento:</strong> {$departamento_nome}</p>
                           <p><strong>Curso:</strong> {$curso}</p>
                           <p><strong>Duração:</strong> {$duracao_formatada}</p>";

            // Anexo (Upload Seguro)
            if (isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] == UPLOAD_ERR_OK) {
                $mail->addAttachment($_FILES['comprovante']['tmp_name'], $_FILES['comprovante']['name']);
            }

            $mail->send();
        }

        // --- E. Auditoria (CSV) ---
        
        registrar_log_master([
            'nome' => $nome,
            'email' => $email,
            'filial' => $filial_nome,
            'departamento' => $departamento_nome,
            'curso' => $curso,
            'tipo' => $tipo,
            'duracao' => $total_minutos // Salva como Inteiro
        ], REPORTS_DIR);
        
        // Atualiza timestamp para o Rate Limit
        $_SESSION['last_submit'] = time();
        
        // Mensagem de Sucesso
        $message = json_encode([
            'type' => 'success',
            'title' => 'Sucesso!',
            'body' => '✅ Seu treinamento foi registrado e enviado com sucesso para o RH.'
        ]);

    } catch (Exception $e) {
        // Mensagem de Erro (captura qualquer problema acima)
        // Se for um erro do PHPMailer, o $e->getMessage() já deve conter detalhes.
        $error_msg = $e->getMessage();
        
        // Se estivermos usando o PHPMailer e houver um erro específico dele, podemos tentar capturá-lo
        if (isset($mail) && !empty($mail->ErrorInfo)) {
            $error_msg .= " (SMTP Error: " . $mail->ErrorInfo . ")";
        }

        $message = json_encode([
            'type' => 'danger',
            'title' => 'Ops! Algo deu errado',
            'body' => '❌ Erro: ' . $error_msg . '<br><br><strong>Por favor, entre em contato com o departamento de RH para realizar seu registro manualmente.</strong>'
        ]);
    }
}