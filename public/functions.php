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
        if (!mkdir($reportsDir, 0755, true)) {
            throw new Exception("Não foi possível criar o diretório de relatórios. Verifique as permissões da pasta.");
        }
    }

    $novoArquivo = !file_exists($arquivo);
    $handle = fopen($arquivo, 'a'); // Abre para append (adicionar no final)
    
    if (!$handle) {
        throw new Exception("Não foi possível abrir o arquivo de registro para escrita.");
    }
    
    // Bloqueia o arquivo para escrita exclusiva (evita corrupção)
    flock($handle, LOCK_EX);

        // Se o arquivo acabou de ser criado, adiciona o BOM UTF-8 e o cabeçalho
        if ($novoArquivo) {
            // BOM UTF-8 para o Excel reconhecer a codificação corretamente
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['ID', 'Data/Hora', 'Nome', 'Email', 'Filial', 'Departamento', 'Curso', 'Tipo', 'Duracao_Minutos', 'Status', 'IP'], ';');
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
            uniqid('tr_'), // ID Único
            date('Y-m-d H:i:s'),
            sanitizar_csv_campo($dados['nome']),
            sanitizar_csv_campo($dados['email']),
            sanitizar_csv_campo($dados['filial']),
            sanitizar_csv_campo($dados['departamento']),
            sanitizar_csv_campo($dados['curso']),
            sanitizar_csv_campo($dados['tipo']),
            $dados['duracao'], // Já é numérico
            'Pendente', // Status inicial
            trim($ip)
        ];

        // Escreve a linha no CSV usando ponto e vírgula como delimitador (padrão Brasil/Excel)
        fputcsv($handle, $linha, ';');

        // Libera o arquivo e fecha
        flock($handle, LOCK_UN);
        fclose($handle);
}

/**
 * Atualiza o status de um treinamento no CSV.
 */
function atualizar_status_treinamento($id, $novoStatus, $reportsDir) {
    $arquivo = $reportsDir . '/treinamentos_master.csv';
    if (!file_exists($arquivo)) return false;

    $temp = tempnam(sys_get_temp_dir(), 'csv');
    $handle = fopen($arquivo, 'r');
    $out = fopen($temp, 'w');
    
    $sucesso = false;
    if ($handle && $out) {
        while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
            if ($data[0] === $id) {
                // Assume que Status é a penúltima coluna (índice 9 se houver 11 colunas)
                // Vamos encontrar o índice do cabeçalho para ser mais seguro
                if (!isset($statusIdx)) {
                    $statusIdx = array_search('Status', $data);
                    if ($statusIdx === false) $statusIdx = 9; 
                }
                $data[$statusIdx] = $novoStatus;
                $sucesso = true;
            }
            fputcsv($out, $data, ';');
        }
        fclose($handle);
        fclose($out);
        
        if ($sucesso) {
            copy($temp, $arquivo);
        }
        unlink($temp);
    }
    return $sucesso;
}

/**
 * Exclui um treinamento do CSV.
 */
function excluir_treinamento($id, $reportsDir) {
    $arquivo = $reportsDir . '/treinamentos_master.csv';
    if (!file_exists($arquivo)) return false;

    $temp = tempnam(sys_get_temp_dir(), 'csv');
    $handle = fopen($arquivo, 'r');
    $out = fopen($temp, 'w');
    
    $sucesso = false;
    if ($handle && $out) {
        while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
            if ($data[0] === $id) {
                $sucesso = true;
                continue; // Pula a linha para excluir
            }
            fputcsv($out, $data, ';');
        }
        fclose($handle);
        fclose($out);
        
        if ($sucesso) {
            copy($temp, $arquivo);
        }
        unlink($temp);
    }
    return $sucesso;
}

/**
 * Registra logs de auditoria das ações do RH (Admin).
 */
function registrar_audit_admin($acao, $id, $reportsDir) {
    $arquivo = $reportsDir . '/audit_admin.log';
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $log = sprintf("[%s] IP: %s | Ação: %s | Registro ID: %s" . PHP_EOL, date('Y-m-d H:i:s'), $ip, $acao, $id);
    file_put_contents($arquivo, $log, FILE_APPEND);
}

/**
 * Verifica se já se passaram 7 dias desde o último envio automático
 * e dispara o relatório para o RH se necessário.
 */
function verificar_e_enviar_relatorio_semanal($reportsDir) {
    $controleFile = $reportsDir . '/last_automated_send.txt';
    $hoje = time();
    $enviar = false;

    if (!file_exists($controleFile)) {
        $enviar = true;
    } else {
        $ultimaVez = (int)file_get_contents($controleFile);
        // 7 dias em segundos = 7 * 24 * 60 * 60 = 604800
        if (($hoje - $ultimaVez) >= 604800) {
            $enviar = true;
        }
    }

    if ($enviar) {
        // Tenta enviar o relatório silenciosamente
        if (enviar_relatorio_rh($reportsDir, true)) {
            file_put_contents($controleFile, $hoje);
            return true;
        }
    }
    return false;
}

/**
 * Envia o relatório CSV completo por e-mail para o RH.
 * @param bool $silent Se true, não dá echo nem exit (usado no envio automático)
 */
function enviar_relatorio_rh($reportsDir, $silent = false) {
    $arquivo = $reportsDir . '/treinamentos_master.csv';
    
    if (!file_exists($arquivo)) {
        if ($silent) return false;
        die("Nenhum relatório encontrado para enviar.");
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        if (($_ENV['APP_ENV'] ?? 'local') === 'local') {
            $destinatario = $_ENV['REPORT_DESTINATION'] ?? 'rh@digitalsat.com.br';
            $mock_content = "<h2>MOCK RELATÓRIO AUTOMÁTICO RH</h2><hr>";
            $mock_content .= "<strong>Para:</strong> {$destinatario}<hr>";
            $mock_content .= "<strong>Assunto:</strong> [AUTOMÁTICO] Relatório Semanal de Treinamentos - " . date('d/m/Y') . "<br>";
            
            file_put_contents(dirname(__DIR__) . '/public/email_mock_relatorio.html', $mock_content);
            if (!$silent) echo "✅ MOCK: Relatório gerado em email_mock_relatorio.html!";
        } else {
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->Host       = $_ENV['SMTP_HOST'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['SMTP_USER'];
            $mail->Password   = $_ENV['SMTP_PASS'];
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $_ENV['SMTP_PORT'];

            $mail->setFrom($_ENV['SMTP_USER'], 'DigitalSat Sistema');
            $destinatario = $_ENV['REPORT_DESTINATION'] ?? 'rh@digitalsat.com.br';
            $mail->addAddress($destinatario);
            
            $mail->isHTML(true);
            $mail->Subject = "[AUTOMÁTICO] Relatório Semanal de Treinamentos - " . date('d/m/Y');
            $mail->Body = "Olá RH,<br><br>Este é o relatório semanal automático contendo todos os registros de treinamentos.<br>Anexo atualizado em: " . date('d/m/Y H:i');
            
            $mail->addAttachment($arquivo, 'relatorio_semanal_' . date('Ymd') . '.csv');
            $mail->send();
            
            if (!$silent) echo "✅ Relatório enviado com sucesso!";
        }
        
        if (!$silent) exit;
        return true;
    } catch (Exception $e) {
        if (!$silent) echo "❌ Erro ao enviar relatório: {$mail->ErrorInfo}";
        return false;
    }
}