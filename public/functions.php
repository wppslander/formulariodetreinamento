<?php
/**
 * FUNCTIONS.PHP
 * Biblioteca de funções auxiliares e utilitárias.
 */

/**
 * Estabelece conexão com o banco de dados SQLite via PDO.
 function conectar_db() {
     $db_dir = dirname(__DIR__) . '/reports';
     $db_file = $db_dir . '/database.sqlite';

     // Verificação de Produção: Se a pasta ou arquivo não forem graváveis, avisa no log
     if (file_exists($db_file) && !is_writable($db_file)) {
         error_log("AVISO: O arquivo do banco de dados existe mas NÃO é gravável pelo servidor.");
     }
     if (is_dir($db_dir) && !is_writable($db_dir)) {
         error_log("AVISO: A pasta de relatórios NÃO é gravável. O SQLite pode falhar ao criar travas (locks).");
     }

     try {
         $pdo = new PDO('sqlite:' . $db_file);
 ...
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (Exception $e) {
        error_log("Erro de conexão com o banco: " . $e->getMessage());
        return null;
    }
}

/**
 * Registra um novo treinamento no banco de dados SQLite (Normalizado).
 */
function registrar_treinamento_db($dados) {
    $db = conectar_db();
    if (!$db) return false;

    try {
        // Busca IDs das tabelas auxiliares baseado nos slugs enviados do formulário
        
        // 1. Filial ID
        $stmtF = $db->prepare("SELECT id FROM filiais WHERE slug = ?");
        $stmtF->execute([$dados['filial']]);
        $filial_id = $stmtF->fetchColumn();

        // 2. Departamento ID
        $stmtD = $db->prepare("SELECT id FROM departamentos WHERE slug = ?");
        $stmtD->execute([$dados['departamento']]);
        $departamento_id = $stmtD->fetchColumn();

        // 3. Modalidade ID
        $stmtM = $db->prepare("SELECT id FROM modalidades WHERE slug = ?");
        $stmtM->execute([$dados['tipo']]);
        $modalidade_id = $stmtM->fetchColumn();

        // IP Real
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (strpos($ip, ',') !== false) $ip = explode(',', $ip)[0];

        // Insert Principal
        $sql = "INSERT INTO treinamentos 
                (nome, email, filial_id, departamento_id, curso, data_conclusao, modalidade_id, outro_texto, duracao_minutos, status, ip_origem) 
                VALUES (:nome, :email, :filial_id, :departamento_id, :curso, :data_conclusao, :modalidade_id, :outro_texto, :duracao_minutos, :status, :ip_origem)";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':nome'            => $dados['nome'],
            ':email'           => $dados['email'],
            ':filial_id'       => $filial_id,
            ':departamento_id' => $departamento_id,
            ':curso'           => $dados['curso'],
            ':data_conclusao'  => $dados['data_conclusao'],
            ':modalidade_id'   => $modalidade_id,
            ':outro_texto'     => $dados['outro_texto'] ?? null,
            ':duracao_minutos' => $dados['duracao'], // Já vem convertido em minutos do controller
            ':status'          => 'Pendente',
            ':ip_origem'       => trim($ip)
        ]);

        return true;
    } catch (Exception $e) {
        error_log("Erro ao salvar treinamento no DB: " . $e->getMessage());
        return false;
    }
}

/**
 * Formata minutos para o padrão "Xh Ym" para exibição.
 */
function exibir_duracao_formatada($minutos) {
    $minutos = intval($minutos);
    $h = floor($minutos / 60);
    $m = $minutos % 60;
    
    if ($h > 0 && $m > 0) return "{$h}h {$m}m";
    if ($h > 0) return "{$h}h";
    return "{$m}min";
}

/**
 * Sanitiza um campo para evitar CSV Injection (Formula Injection).
 */
function sanitizar_csv_campo($valor) {
    if (is_numeric($valor)) return $valor;
    $perigosos = ['=', '+', '-', '@', "\t", "\r"];
    $primeiroChar = substr((string)$valor, 0, 1);
    if (in_array($primeiroChar, $perigosos, true)) {
        return "'" . $valor;
    }
    return $valor;
}

/**
 * Registra o log de auditoria no CSV Master (Mantido como redundância por enquanto).
 */
function registrar_log_master($dados, $reportsDir) {
    $arquivo = $reportsDir . '/treinamentos_master.csv';
    if (!is_dir($reportsDir)) mkdir($reportsDir, 0755, true);

    $novoArquivo = !file_exists($arquivo);
    $handle = fopen($arquivo, 'a');
    if (!$handle) return;
    
    flock($handle, LOCK_EX);
    if ($novoArquivo) {
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['ID', 'Data/Hora', 'Nome', 'Email', 'Filial', 'Departamento', 'Curso', 'Tipo', 'Duracao_Minutos', 'Status', 'IP'], ';');
    }

    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (strpos($ip, ',') !== false) $ip = explode(',', $ip)[0];

    $linha = [
        uniqid('tr_'),
        date('Y-m-d H:i:s'),
        sanitizar_csv_campo($dados['nome']),
        sanitizar_csv_campo($dados['email']),
        sanitizar_csv_campo($dados['filial']),
        sanitizar_csv_campo($dados['departamento']),
        sanitizar_csv_campo($dados['curso']),
        sanitizar_csv_campo($dados['tipo']),
        $dados['duracao'],
        'Pendente',
        trim($ip)
    ];

    fputcsv($handle, $linha, ';');
    flock($handle, LOCK_UN);
    fclose($handle);
}

/**
 * Atualiza o status de um treinamento.
 * Agora suporta tanto DB quanto CSV (migração gradual).
 */
function atualizar_status_treinamento($id, $novoStatus, $reportsDir) {
    // 1. Atualiza no Banco de Dados
    $db = conectar_db();
    if ($db) {
        $stmt = $db->prepare("UPDATE treinamentos SET status = ? WHERE id = ?");
        $stmt->execute([$novoStatus, $id]);
    }

    // 2. Atualiza no CSV (Redundância legada)
    $arquivo = $reportsDir . '/treinamentos_master.csv';
    if (!file_exists($arquivo)) return true;

    $temp = tempnam(sys_get_temp_dir(), 'csv');
    $handle = fopen($arquivo, 'r');
    $out = fopen($temp, 'w');
    
    if ($handle && $out) {
        while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
            if ($data[0] === $id || $data[0] === "tr_$id" || "tr_".$id === $data[0]) {
                if (!isset($statusIdx)) {
                    $statusIdx = array_search('Status', $data);
                    if ($statusIdx === false) $statusIdx = 9; 
                }
                $data[$statusIdx] = $novoStatus;
            }
            fputcsv($out, $data, ';');
        }
        fclose($handle);
        fclose($out);
        copy($temp, $arquivo);
        unlink($temp);
    }
    return true;
}

/**
 * Exclui um treinamento.
 */
function excluir_treinamento($id, $reportsDir) {
    // 1. Exclui no Banco de Dados
    $db = conectar_db();
    if ($db) {
        $stmt = $db->prepare("DELETE FROM treinamentos WHERE id = ?");
        $stmt->execute([$id]);
    }

    // 2. Exclui no CSV
    $arquivo = $reportsDir . '/treinamentos_master.csv';
    if (!file_exists($arquivo)) return true;

    $temp = tempnam(sys_get_temp_dir(), 'csv');
    $handle = fopen($arquivo, 'r');
    $out = fopen($temp, 'w');
    
    if ($handle && $out) {
        while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
            if ($data[0] === $id) continue;
            fputcsv($out, $data, ';');
        }
        fclose($handle);
        fclose($out);
        copy($temp, $arquivo);
        unlink($temp);
    }
    return true;
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
 * Verifica se já se passaram 7 dias desde o último envio automático.
 */
function verificar_e_enviar_relatorio_semanal($reportsDir) {
    $controleFile = $reportsDir . '/last_automated_send.txt';
    $hoje = time();
    $enviar = false;

    if (!file_exists($controleFile)) {
        $enviar = true;
    } else {
        $ultimaVez = (int)file_get_contents($controleFile);
        if (($hoje - $ultimaVez) >= 604800) {
            $enviar = true;
        }
    }

    if ($enviar) {
        if (enviar_relatorio_rh($reportsDir, true)) {
            file_put_contents($controleFile, $hoje);
            return true;
        }
    }
    return false;
}

/**
 * Envia o relatório CSV por e-mail para o RH.
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
            $mail->addAddress($_ENV['REPORT_DESTINATION'] ?? 'rh@digitalsat.com.br');
            $mail->isHTML(true);
            $mail->Subject = "[AUTOMÁTICO] Relatório Semanal de Treinamentos - " . date('d/m/Y');
            $mail->Body = "Olá RH,<br><br>Este é o relatório semanal automático.<br>Anexo atualizado em: " . date('d/m/Y H:i');
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
