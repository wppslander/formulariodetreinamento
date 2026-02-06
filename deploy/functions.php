<?php
/**
 * FUNCTIONS.PHP
 * Biblioteca de funções auxiliares e utilitárias.
 */

/**
 * Sanitiza um campo para evitar CSV Injection (Formula Injection).
 * Se o campo começar com caracteres que o Excel interpreta como fórmula, 
 * adiciona uma aspa simples no início.
 * 
 * @param string|int $valor
 * @return string|int
 */
function sanitizar_csv_campo($valor) {
    if (is_numeric($valor)) return $valor;
    
    // Caracteres perigosos no início de uma célula do Excel
    $perigosos = ['=', '+', '-', '@', "\t", "\r"];
    
    $primeiroChar = substr((string)$valor, 0, 1);
    
    if (in_array($primeiroChar, $perigosos, true)) {
        return "'" . $valor;
    }
    
    return $valor;
}

/**
 * Registra o log de auditoria no CSV Master.
 * Utiliza Locking (trava de arquivo) para suportar escritas concorrentes.
 * 
 * @param array $dados Array associativo com os dados do formulário
 * @param string $reportsDir Caminho absoluto para a pasta de relatórios
 */
function registrar_log_master($dados, $reportsDir) {
    $arquivo = $reportsDir . '/treinamentos_master.csv';

    // Garante que a pasta existe (com permissão segura 0755)
    if (!is_dir($reportsDir)) {
        mkdir($reportsDir, 0755, true);
    }

    $novoArquivo = !file_exists($arquivo);
    $handle = fopen($arquivo, 'a'); // Abre para append (adicionar no final)
    
    if ($handle) {
        // Bloqueia o arquivo para escrita exclusiva (evita corrupção)
        flock($handle, LOCK_EX);

        // Se o arquivo acabou de ser criado, adiciona o BOM UTF-8 e o cabeçalho
        if ($novoArquivo) {
            // BOM UTF-8 para o Excel reconhecer a codificação corretamente
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Data/Hora', 'Nome', 'Email', 'Filial', 'Departamento', 'Curso', 'Tipo', 'Duracao_Minutos', 'IP'], ';');
        }

        // --- Detecção de IP Real ---
        // Tenta pegar o IP real mesmo se estiver atrás de um Proxy ou Docker
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        // Se houver uma lista de IPs (ex: "client, proxy1, proxy2"), pega o primeiro
        if (strpos($ip, ',') !== false) {
            $ip = explode(',', $ip)[0];
        }

        // Prepara os dados sanitizando contra CSV Injection
        $linha = [
            date('Y-m-d H:i:s'),
            sanitizar_csv_campo($dados['nome']),
            sanitizar_csv_campo($dados['email']),
            sanitizar_csv_campo($dados['filial']),
            sanitizar_csv_campo($dados['departamento']),
            sanitizar_csv_campo($dados['curso']),
            sanitizar_csv_campo($dados['tipo']),
            $dados['duracao'], // Já é numérico
            trim($ip)
        ];

        // Escreve a linha no CSV usando ponto e vírgula como delimitador (padrão Brasil/Excel)
        fputcsv($handle, $linha, ';');

        // Libera o arquivo e fecha
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

/**
 * Envia o relatório CSV completo por e-mail para o RH.
 * Função disparada apenas pela rota administrativa segura.
 * 
 * @param string $reportsDir Caminho absoluto para a pasta de relatórios
 */
function enviar_relatorio_rh($reportsDir) {
    $arquivo = $reportsDir . '/treinamentos_master.csv';
    
    if (!file_exists($arquivo)) {
        die("Nenhum relatório encontrado para enviar (o arquivo CSV ainda não existe).");
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        // Configurações SMTP
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'];
        $mail->Password   = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $_ENV['SMTP_PORT'];

        $mail->setFrom($_ENV['SMTP_USER'], 'DigitalSat Sistema');
        // O relatório vai para o destino específico definido no .env
        $destinatario = $_ENV['REPORT_DESTINATION'] ?? 'rh@digitalsat.com.br';
        $mail->addAddress($destinatario);
        
        $mail->isHTML(true);
        $mail->Subject = "[AUDITORIA] Relatório Geral de Treinamentos - " . date('d/m/Y');
        $mail->Body = "Seguem em anexo todos os registros de treinamento até o momento.<br>Gerado automaticamente pelo sistema.";
        
        // Anexa o arquivo CSV
        $mail->addAttachment($arquivo, 'relatorio_treinamentos_' . date('Ymd') . '.csv');
        
        $mail->send();
        echo "✅ Relatório enviado com sucesso para {$destinatario}!";
    } catch (Exception $e) {
        echo "❌ Erro ao enviar relatório: {$mail->ErrorInfo}";
    }
    exit; // Encerra a execução após enviar (não carrega o HTML da página)
}