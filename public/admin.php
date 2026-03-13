<?php
/**
 * ADMIN.PHP - Painel Analítico e Administrativo do RH
 * Acesso protegido: admin.php?token=SEU_TOKEN
 */

// Define Caminhos Base
define('BASE_PATH', dirname(__DIR__));
define('REPORTS_DIR', BASE_PATH . '/reports');
require_once BASE_PATH . '/vendor/autoload.php';

// 1. Carregar Variáveis de Ambiente (.env)
if (file_exists(BASE_PATH . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
    $dotenv->safeLoad();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// 2. Segurança do Token
$token_valido = $_ENV['ADMIN_TOKEN'] ?? 'digitalsat_segredo';
$token_recebido = $_GET['token'] ?? '';

if ($token_recebido !== $token_valido) {
    header('HTTP/1.0 403 Forbidden');
    die("Acesso Negado: Token inválido.");
}

// 3. Rotinas Automáticas (Envio Semanal)
verificar_e_enviar_relatorio_semanal(REPORTS_DIR);

// 4. Ações Simples (Download e E-mail)
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'download_csv') {
        $arquivo = REPORTS_DIR . '/treinamentos_master.csv';
        if (file_exists($arquivo)) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="treinamentos_master_' . date('Ymd') . '.csv"');
            registrar_audit_admin('Download CSV', 'Geral', REPORTS_DIR);
            readfile($arquivo);
            exit;
        } else {
            die("Erro: Arquivo não encontrado.");
        }
    }
    if ($_GET['action'] === 'enviar_relatorio') {
        enviar_relatorio_rh(REPORTS_DIR);
        registrar_audit_admin('Disparo Manual E-mail RH', 'Geral', REPORTS_DIR);
    }
    if ($_GET['action'] === 'validar' && isset($_GET['id'])) {
        atualizar_status_treinamento($_GET['id'], 'Validado', REPORTS_DIR);
        registrar_audit_admin('Validar Treinamento', $_GET['id'], REPORTS_DIR);
        header("Location: admin.php?token=$token_recebido&msg=validado");
        exit;
    }
    if ($_GET['action'] === 'excluir' && isset($_GET['id'])) {
        excluir_treinamento($_GET['id'], REPORTS_DIR);
        registrar_audit_admin('Excluir Treinamento', $_GET['id'], REPORTS_DIR);
        header("Location: admin.php?token=$token_recebido&msg=excluido");
        exit;
    }
}

// 5. Mensagens de Feedback
$feedback_msg = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'validado') $feedback_msg = '<div class="alert alert-success">Treinamento validado com sucesso!</div>';
    if ($_GET['msg'] === 'excluido') $feedback_msg = '<div class="alert alert-danger">Registro removido com sucesso.</div>';
}

// 6. Captura de Filtros e Ordenação (GET)
$filtro_inicio = $_GET['data_inicio'] ?? '';
$filtro_fim = $_GET['data_fim'] ?? '';
$filtro_filial = $_GET['filial'] ?? '';
$ordenacao = $_GET['ordenacao'] ?? 'data_desc'; // Padrão: Mais recentes

// 7. Processamento e Leitura do CSV
$registros = [];
$stats = [
    'total_treinamentos' => 0,
    'total_minutos' => 0,
    'por_filial' => [],
    'por_tipo' => []
];

$arquivo = REPORTS_DIR . '/treinamentos_master.csv';
if (file_exists($arquivo)) {
    $handle = fopen($arquivo, 'r');
    if ($handle) {
        fseek($handle, 3); // Pula BOM
        $header = fgetcsv($handle, 0, ';');
        
        while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
            // Ajuste para suportar arquivos antigos e novos (compatibilidade)
            if (count($data) >= 8) {
                // Se o arquivo for antigo (sem ID no início), o count será 9 (sem ID, sem Status)
                // Se o arquivo for novo, o count será 11
                if (count($data) == 9) {
                    // Mapeamento antigo: Data/Hora, Nome, Email, Filial, Departamento, Curso, Tipo, Duracao_Minutos, IP
                    $row = [
                        'ID' => 'legacy_' . md5($data[0] . $data[2]), // ID gerado na hora
                        'Data/Hora' => $data[0],
                        'Nome' => $data[1],
                        'Email' => $data[2],
                        'Filial' => $data[3],
                        'Departamento' => $data[4],
                        'Curso' => $data[5],
                        'Tipo' => $data[6],
                        'Duracao_Minutos' => $data[7],
                        'Status' => 'Pendente',
                        'IP' => $data[8]
                    ];
                } else {
                    $row = array_combine($header, $data);
                }
                
                // Aplicar Filtros
                $data_treinamento = substr($row['Data/Hora'], 0, 10); // Pega só YYYY-MM-DD
                
                if ($filtro_inicio && $data_treinamento < $filtro_inicio) continue;
                if ($filtro_fim && $data_treinamento > $filtro_fim) continue;
                if ($filtro_filial && $row['Filial'] !== $filtro_filial) continue;

                $registros[] = $row;

                // Agregação para Estatísticas
                $stats['total_treinamentos']++;
                $stats['total_minutos'] += intval($row['Duracao_Minutos']);
                
                $filial = $row['Filial'];
                $stats['por_filial'][$filial] = ($stats['por_filial'][$filial] ?? 0) + 1;
                
                $tipo = $row['Tipo'];
                $stats['por_tipo'][$tipo] = ($stats['por_tipo'][$tipo] ?? 0) + 1;
            }
        }
        fclose($handle);
        
        // Aplica a Ordenação Solicitada
        usort($registros, function($a, $b) use ($ordenacao) {
            switch ($ordenacao) {
                case 'data_asc':
                    return strtotime($a['Data/Hora']) <=> strtotime($b['Data/Hora']);
                case 'duracao_desc':
                    return intval($b['Duracao_Minutos']) <=> intval($a['Duracao_Minutos']);
                case 'duracao_asc':
                    return intval($a['Duracao_Minutos']) <=> intval($b['Duracao_Minutos']);
                case 'data_desc':
                default:
                    return strtotime($b['Data/Hora']) <=> strtotime($a['Data/Hora']);
            }
        });
    }
}

// 8. Paginação e Fatiamento
$itens_por_pagina = 50;
$total_registros_filtrados = count($registros);
$total_paginas = ceil($total_registros_filtrados / $itens_por_pagina);
$pagina_atual = max(1, intval($_GET['page'] ?? 1));
$offset = ($pagina_atual - 1) * $itens_por_pagina;

$registros_paginados = array_slice($registros, $offset, $itens_por_pagina);

// Cálculos de Tempo Formato Amigável
$horas_totais = floor($stats['total_minutos'] / 60);
$minutos_restantes = $stats['total_minutos'] % 60;
$tempo_formatado = "{$horas_totais}h {$minutos_restantes}m";

// Prepara dados para os Gráficos (JSON)
$chart_filiais_labels = json_encode(array_keys($stats['por_filial']));
$chart_filiais_data = json_encode(array_values($stats['por_filial']));

$chart_tipos_labels = json_encode(array_keys($stats['por_tipo']));
$chart_tipos_data = json_encode(array_values($stats['por_tipo']));

// Coleta lista única de filiais para o select do filtro
$todas_filiais_existentes = array_keys($stats['por_filial']);
if (empty($filtro_filial)) {
    // Se não há filtro, as filiais retornadas na agregação já são todas
    $filiais_dropdown = $todas_filiais_existentes;
} else {
    // Se há filtro, precisamos ler o arquivo de novo ou usar a variável global $filiais_permitidas de config.php
    $filiais_dropdown = array_values($filiais_permitidas ?? []);
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RH - Dashboard Analítico | DigitalSat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- Chart.js para Gráficos -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        body { background-color: #f4f6f9; font-family: 'Roboto', sans-serif; }
        .admin-header { background-color: #252224; color: #fff; padding: 20px 0; margin-bottom: 30px; }
        .card { border-radius: 12px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: transform 0.2s; }
        .card:hover { transform: translateY(-3px); }
        .stat-value { font-size: 2.5rem; font-weight: 700; color: #DC0C15; line-height: 1; }
        .stat-label { color: #6c757d; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 500; margin-top: 10px; }
        .table-responsive { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .badge-duracao { background-color: #e9ecef; color: #495057; font-weight: 500; }
        .filter-bar { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 30px; }
    </style>
</head>
<body>

<div class="admin-header shadow-sm">
    <div class="container d-flex justify-content-between align-items-center flex-wrap gap-3">
        <h3 class="mb-0 d-flex align-items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="currentColor" class="bi bi-bar-chart-line-fill" viewBox="0 0 16 16">
              <path d="M11 2a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v12h.5a.5.5 0 0 1 0 1H.5a.5.5 0 0 1 0-1H1v-3a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3h1V7a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v7h1V2zm1 12h2V2h-2v12zm-3 0V7H7v7h2zm-5 0v-3H2v3h2z"/>
            </svg>
            Dashboard de Treinamentos
        </h3>
        <div>
            <a href="?token=<?php echo $token_recebido; ?>&action=download_csv" class="btn btn-light btn-sm me-2 fw-medium shadow-sm">📥 Exportar CSV</a>
            <a href="?token=<?php echo $token_recebido; ?>&action=enviar_relatorio" class="btn btn-outline-light btn-sm fw-medium">📧 Disparar P/ RH</a>
        </div>
    </div>
</div>

<div class="container pb-5">
    
    <!-- Mensagens de Feedback -->
    <?php echo $feedback_msg; ?>

    <!-- Barra de Filtros -->
    <div class="filter-bar">
        <form method="GET" action="" class="row g-3 align-items-end">
            <input type="hidden" name="token" value="<?php echo $token_recebido; ?>">
            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold">Data Início</label>
                <input type="date" class="form-control" name="data_inicio" value="<?php echo htmlspecialchars($filtro_inicio); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold">Data Fim</label>
                <input type="date" class="form-control" name="data_fim" value="<?php echo htmlspecialchars($filtro_fim); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold">Filial</label>
                <select class="form-select" name="filial">
                    <option value="">Todas as Filiais</option>
                    <?php 
                    $todas = array_values($filiais_permitidas ?? []);
                    foreach ($todas as $f): 
                        $selected = ($filtro_filial === $f) ? 'selected' : '';
                    ?>
                        <option value="<?php echo $f; ?>" <?php echo $selected; ?>><?php echo $f; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small fw-bold">Ordenar por</label>
                <select class="form-select" name="ordenacao">
                    <option value="data_desc" <?php echo $ordenacao === 'data_desc' ? 'selected' : ''; ?>>Mais Recentes</option>
                    <option value="data_asc" <?php echo $ordenacao === 'data_asc' ? 'selected' : ''; ?>>Mais Antigos</option>
                    <option value="duracao_desc" <?php echo $ordenacao === 'duracao_desc' ? 'selected' : ''; ?>>Maior Duração</option>
                    <option value="duracao_asc" <?php echo $ordenacao === 'duracao_asc' ? 'selected' : ''; ?>>Menor Duração</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-dark w-100 fw-bold">Filtrar</button>
            </div>
        </form>
    </div>

    <!-- Cards de KPI -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card h-100 p-4 text-center">
                <div class="stat-value"><?php echo $stats['total_treinamentos']; ?></div>
                <div class="stat-label">Cursos Concluídos</div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100 p-4 text-center">
                <div class="stat-value text-dark"><?php echo $tempo_formatado; ?></div>
                <div class="stat-label">Carga Horária Total Investida</div>
            </div>
        </div>
    </div>

    <!-- Gráficos -->
    <?php if ($stats['total_treinamentos'] > 0): ?>
    <div class="row g-4 mb-4">
        <div class="col-md-8">
            <div class="card h-100 p-3">
                <h6 class="text-muted mb-3 fw-bold text-center">Treinamentos por Filial</h6>
                <div style="height: 300px; display: flex; justify-content: center;">
                    <canvas id="barChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 p-3">
                <h6 class="text-muted mb-3 fw-bold text-center">Modalidade</h6>
                <div style="height: 300px; display: flex; justify-content: center;">
                    <canvas id="pieChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tabela de Registros -->
    <h5 class="mb-3 mt-5 text-secondary fw-bold">Detalhamento dos Registros</h5>
    <?php if (empty($registros)): ?>
        <div class="alert alert-warning text-center border-0 shadow-sm">Nenhum treinamento encontrado para os filtros aplicados.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Data/Hora</th>
                        <th>Colaborador</th>
                        <th>Local</th>
                        <th>Treinamento</th>
                        <th class="text-center">Duração</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registros_paginados as $reg): ?>
                        <tr>
                            <td class="small text-muted" style="white-space: nowrap;"><?php echo date('d/m/Y H:i', strtotime($reg['Data/Hora'])); ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($reg['Nome']); ?></div>
                                <div class="small text-muted"><?php echo htmlspecialchars($reg['Email']); ?></div>
                                <div class="small text-muted fst-italic"><?php echo htmlspecialchars($reg['Departamento']); ?></div>
                            </td>
                            <td>
                                <span class="d-block text-dark"><?php echo htmlspecialchars($reg['Filial']); ?></span>
                                <span class="badge bg-light text-secondary border"><?php echo htmlspecialchars($reg['Tipo']); ?></span>
                            </td>
                            <td class="fw-medium text-dark"><?php echo htmlspecialchars($reg['Curso']); ?></td>
                            <td class="text-center">
                                <span class="badge badge-duracao rounded-pill px-3 py-2">
                                    <?php 
                                        $m = intval($reg['Duracao_Minutos']);
                                        echo floor($m/60) . 'h ' . ($m%60) . 'm';
                                    ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if (($reg['Status'] ?? 'Pendente') === 'Validado'): ?>
                                    <span class="badge bg-success">Validado</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Pendente</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm shadow-sm">
                                    <?php if (($reg['Status'] ?? 'Pendente') !== 'Validado'): ?>
                                        <a href="?token=<?php echo $token_recebido; ?>&action=validar&id=<?php echo $reg['ID']; ?>" 
                                           class="btn btn-outline-success" title="Validar Certificado">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check-lg" viewBox="0 0 16 16">
                                              <path d="M12.736 3.97a.733.733 0 0 1 1.047 0c.286.289.29.756.01 1.05L7.88 12.01a.733.733 0 0 1-1.065.02L3.217 8.384a.757.757 0 0 1 0-1.06.733.733 0 0 1 1.047 0l3.052 3.093 5.4-6.425z"/>
                                            </svg>
                                        </a>
                                    <?php endif; ?>
                                    <a href="?token=<?php echo $token_recebido; ?>&action=excluir&id=<?php echo $reg['ID']; ?>" 
                                       class="btn btn-outline-danger" title="Excluir Registro"
                                       onclick="return confirm('Tem certeza que deseja remover este registro permanentemente?')">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash3" viewBox="0 0 16 16">
                                          <path d="M6.5 1h3a.5.5 0 0 1 .5.5v1H6v-1a.5.5 0 0 1 .5-.5M11 2.5v-1A1.5 1.5 0 0 0 9.5 0h-3A1.5 1.5 0 0 0 5 1.5v1H1.5a.5.5 0 0 0 0 1h.538l.853 10.66A2 2 0 0 0 4.935 16h6.13a2 2 0 0 0 1.987-1.84L13.902 3.5h.538a.5.5 0 0 0 0-1zm-5 11.5a.5.5 0 0 1-1 0V5a.5.5 0 0 1 1 0v8.5zm2 0a.5.5 0 0 1-1 0V5a.5.5 0 0 1 1 0v8.5zm2 0a.5.5 0 0 1-1 0V5a.5.5 0 0 1 1 0v8.5z"/>
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                </table>

                <!-- Navegação da Paginação -->
                <?php if ($total_paginas > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?token=<?php echo $token_recebido; ?>&page=<?php echo $pagina_atual - 1; ?>&data_inicio=<?php echo $filtro_inicio; ?>&data_fim=<?php echo $filtro_fim; ?>&filial=<?php echo $filtro_filial; ?>&ordenacao=<?php echo $ordenacao; ?>">Anterior</a>
                        </li>

                        <li class="page-item disabled">
                            <span class="page-link text-dark fw-bold">Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?></span>
                        </li>

                        <li class="page-item <?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?token=<?php echo $token_recebido; ?>&page=<?php echo $pagina_atual + 1; ?>&data_inicio=<?php echo $filtro_inicio; ?>&data_fim=<?php echo $filtro_fim; ?>&filial=<?php echo $filtro_filial; ?>&ordenacao=<?php echo $ordenacao; ?>">Próxima</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>

                <div class="text-center text-muted small mt-3">
                Exibindo <?php echo count($registros_paginados); ?> de <?php echo $total_registros_filtrados; ?> registros.
                </div>
                </div>
                <?php endif; ?>
</div>

<!-- Script dos Gráficos -->
<?php if ($stats['total_treinamentos'] > 0): ?>
<script>
    // Paleta de Cores DigitalSat + Elegantes
    const colors = ['#DC0C15', '#252224', '#4a4e69', '#6c757d', '#adb5bd', '#e9ecef', '#ced4da'];

    // Gráfico de Barras (Filiais)
    const ctxBar = document.getElementById('barChart').getContext('2d');
    new Chart(ctxBar, {
        type: 'bar',
        data: {
            labels: <?php echo $chart_filiais_labels; ?>,
            datasets: [{
                label: 'Treinamentos Realizados',
                data: <?php echo $chart_filiais_data; ?>,
                backgroundColor: '#DC0C15',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

    // Gráfico de Rosca (Tipos)
    const ctxPie = document.getElementById('pieChart').getContext('2d');
    new Chart(ctxPie, {
        type: 'doughnut',
        data: {
            labels: <?php echo $chart_tipos_labels; ?>,
            datasets: [{
                data: <?php echo $chart_tipos_data; ?>,
                backgroundColor: colors,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12 } }
            },
            cutout: '70%'
        }
    });
</script>
<?php endif; ?>

</body>
</html>
