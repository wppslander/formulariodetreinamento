<?php

/**
 * Registra o log de auditoria no CSV Master
 */
function registrar_log_master($dados, $reportsDir) {
    $arquivo = $reportsDir . '/treinamentos_master.csv';

    // Cria diretório se não existir
    if (!is_dir($reportsDir)) {
        mkdir($reportsDir, 0755, true);
    }

    $novoArquivo = !file_exists($arquivo);
    $handle = fopen($arquivo, 'a');
    
    if ($handle) {
        flock($handle, LOCK_EX);

        if ($novoArquivo) {
            fputcsv($handle, ['Data/Hora', 'Nome', 'Email', 'Filial', 'Departamento', 'Curso', 'Tipo', 'Duracao', 'IP']);
        }

        // Detectar IP Real (Proxy/Docker)
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
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
            $dados['duracao'], // Minutos totais
            trim($ip)
        ]);

        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

/**
 * Envia o relatório CSV por e-mail (Admin)
 */
function enviar_relatorio_rh($reportsDir) {
    $arquivo = $reportsDir . '/treinamentos_master.csv';
    
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
