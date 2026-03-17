<?php
/**
 * ADMIN2.PHP - Painel Administrativo via SQLite (Melhorado com Busca por Nome/Email)
 * Acesso: admin.php?token=QsEtSkL7YGjAz5u
 */

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';

if (file_exists(BASE_PATH . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
    $dotenv->safeLoad();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// 1. Segurança do Token
$token_valido = "QsEtSkL7YGjAz5u";
$token_recebido = $_GET['token'] ?? '';

if ($token_recebido !== $token_valido) {
    header('HTTP/1.0 403 Forbidden');
    die("Acesso Negado.");
}

$db = conectar_db();
if (!$db) die("Erro ao conectar ao banco de dados.");

$reports_dir = dirname(__DIR__) . '/reports';

// Rotinas Automáticas (Envio Semanal)
verificar_e_enviar_relatorio_semanal($reports_dir);

// 2. Ações (Validar / Excluir / Download / Email)
if (isset($_GET['action'])) {
    $id = intval($_GET['id'] ?? 0);
    
    // Ações de Registro Individual
    if ($_GET['action'] === 'validar' && $id > 0) {
        $db->prepare("UPDATE treinamentos SET status = 'Validado' WHERE id = ?")->execute([$id]);
        atualizar_status_treinamento("tr_$id", 'Validado', $reports_dir); // Mantém sync com CSV se existir
        registrar_audit_admin('Validar Treinamento (DB)', $id, $reports_dir);
        header("Location: admin.php?token=$token_recebido&msg=validado"); exit;
    }
    if ($_GET['action'] === 'excluir' && $id > 0) {
        $db->prepare("DELETE FROM treinamentos WHERE id = ?")->execute([$id]);
        excluir_treinamento("tr_$id", $reports_dir); // Mantém sync com CSV se existir
        registrar_audit_admin('Excluir Treinamento (DB)', $id, $reports_dir);
        header("Location: admin.php?token=$token_recebido&msg=excluido"); exit;
    }

    // Ações Globais
    if ($_GET['action'] === 'download_csv') {
        $sql_export = "SELECT t.id, t.data_hora, t.nome, t.email, f.nome as filial, d.nome as departamento, t.curso, m.nome as modalidade, t.duracao_minutos, t.status, t.ip_origem 
                       FROM treinamentos t
                       JOIN filiais f ON t.filial_id = f.id
                       JOIN departamentos d ON t.departamento_id = d.id
                       JOIN modalidades m ON t.modalidade_id = m.id
                       ORDER BY t.data_hora DESC";
        $stmt = $db->query($sql_export);
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="treinamentos_export_' . date('Ymd_Hi') . '.csv"');
        
        $output = fopen('php://output', 'w');
        // BOM for Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Header
        fputcsv($output, ['ID', 'Data/Hora', 'Nome', 'Email', 'Filial', 'Departamento', 'Treinamento', 'Modalidade', 'Duração (Minutos)', 'Status', 'IP Origem'], ';');
        
        // Data
        foreach ($registros as $row) {
            fputcsv($output, [
                $row['id'],
                $row['data_hora'],
                $row['nome'],
                $row['email'],
                $row['filial'],
                $row['departamento'],
                $row['curso'],
                $row['modalidade'],
                $row['duracao_minutos'],
                $row['status'],
                $row['ip_origem']
            ], ';');
        }
        
        fclose($output);
        registrar_audit_admin('Download CSV (Gerado DB)', 'Geral', $reports_dir);
        exit;
    }
    if ($_GET['action'] === 'enviar_relatorio') {
        enviar_relatorio_rh($reports_dir);
        registrar_audit_admin('Disparo Manual E-mail RH (DB)', 'Geral', $reports_dir);
        header("Location: admin.php?token=$token_recebido&msg=enviado"); exit;
    }
}

// Mensagens de Feedback
$feedback_msg = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'validado') $feedback_msg = '<div class="alert alert-success mt-3 mb-0">Treinamento validado com sucesso!</div>';
    if ($_GET['msg'] === 'excluido') $feedback_msg = '<div class="alert alert-danger mt-3 mb-0">Registro removido com sucesso.</div>';
    if ($_GET['msg'] === 'enviado') $feedback_msg = '<div class="alert alert-info mt-3 mb-0">Relatório enviado por e-mail com sucesso!</div>';
}

// 3. Filtros, Busca e Ordenação
$f_inicio = $_GET['data_inicio'] ?? '';
$f_fim = $_GET['data_fim'] ?? '';
$f_filial = $_GET['filial'] ?? '';
$f_busca = trim($_GET['busca'] ?? ''); // Novo: Filtro de Busca (Nome ou Email)
$ordenacao = $_GET['ordenacao'] ?? 'novo';

// 4. Query de Estatísticas (Globais para os KPIs)
$stats_geral = $db->query("SELECT 
    COUNT(*) as total, 
    SUM(duracao_minutos) as minutos,
    SUM(CASE WHEN status = 'Validado' THEN 1 ELSE 0 END) as validados,
    SUM(CASE WHEN status = 'Pendente' THEN 1 ELSE 0 END) as pendentes
    FROM treinamentos")->fetch();

// Dados para Gráficos
$data_filiais = $db->query("SELECT f.nome, COUNT(t.id) as total FROM treinamentos t JOIN filiais f ON t.filial_id = f.id GROUP BY f.id ORDER BY total DESC")->fetchAll();
$data_modalidades = $db->query("SELECT m.nome, COUNT(t.id) as total FROM treinamentos t JOIN modalidades m ON t.modalidade_id = m.id GROUP BY m.id")->fetchAll();

$chart_filiais_labels = json_encode(array_column($data_filiais, 'nome'));
$chart_filiais_data = json_encode(array_column($data_filiais, 'total'));

$chart_tipos_labels = json_encode(array_column($data_modalidades, 'nome'));
$chart_tipos_data = json_encode(array_column($data_modalidades, 'total'));

// 5. Query Principal (Com Filtros e Busca)
$where = ["1=1"];
$params = [];
if ($f_inicio) { $where[] = "date(t.data_hora) >= ?"; $params[] = $f_inicio; }
if ($f_fim) { $where[] = "date(t.data_hora) <= ?"; $params[] = $f_fim; }
if ($f_filial) { $where[] = "f.slug = ?"; $params[] = $f_filial; }

// Adicionando a busca por Nome ou Email via LIKE
if (!empty($f_busca)) {
    $where[] = "(t.nome LIKE ? OR t.email LIKE ?)";
    $params[] = "%$f_busca%";
    $params[] = "%$f_busca%";
}

$order_sql = "t.data_hora DESC";
if ($ordenacao === 'antigo') $order_sql = "t.data_hora ASC";
if ($ordenacao === 'duracao') $order_sql = "t.duracao_minutos DESC";

$sql = "SELECT t.*, f.nome as filial_nome, d.nome as depto_nome, m.nome as modalidade_nome 
        FROM treinamentos t
        JOIN filiais f ON t.filial_id = f.id
        JOIN departamentos d ON t.departamento_id = d.id
        JOIN modalidades m ON t.modalidade_id = m.id
        WHERE " . implode(" AND ", $where) . "
        ORDER BY $order_sql";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$registros = $stmt->fetchAll();

$tempo_total = exibir_duracao_formatada($stats_geral['minutos'] ?? 0);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>RH - Dashboard v2 SQL | DigitalSat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: #f4f6f9; font-family: 'Roboto', sans-serif; }
        .header { background: #252224; color: #fff; padding: 25px 0; margin-bottom: 30px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .stat-value { font-size: 2.2rem; font-weight: 700; color: #DC0C15; line-height: 1; }
        .stat-label { color: #6c757d; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; margin-top: 10px; }
        .table-box { background: #fff; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .filter-bar { background: white; padding: 25px; border-radius: 12px; margin-bottom: 30px; border-top: 4px solid #DC0C15; }
        .badge-pendente { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .badge-validado { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    </style>
</head>
<body>

<div class="header shadow-sm">
    <div class="container d-flex justify-content-between align-items-center">
        <h3 class="mb-0 d-flex align-items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="currentColor" class="bi bi-cpu" viewBox="0 0 16 16">
              <path d="M5 0a.5.5 0 0 1 .5.5V2h1V.5a.5.5 0 0 1 1 0V2h1V.5a.5.5 0 0 1 1 0V2h1V.5a.5.5 0 0 1 1 0V2A2.5 2.5 0 0 1 14 4.5h1.5a.5.5 0 0 1 0 1H14v1h1.5a.5.5 0 0 1 0 1H14v1h1.5a.5.5 0 0 1 0 1H14v1h1.5a.5.5 0 0 1 0 1H14a2.5 2.5 0 0 1-2.5 2.5v1.5a.5.5 0 0 1-1 0V14h-1v1.5a.5.5 0 0 1-1 0V14h-1v1.5a.5.5 0 0 1-1 0V14h-1v1.5a.5.5 0 0 1-1 0V14A2.5 2.5 0 0 1 2 11.5H.5a.5.5 0 0 1 0-1H2v-1H.5a.5.5 0 0 1 0-1H2v-1H.5a.5.5 0 0 1 0-1H2v-1H.5a.5.5 0 0 1 0-1H2A2.5 2.5 0 0 1 4.5 2V.5A.5.5 0 0 1 5 0zm-.5 3A1.5 1.5 0 0 0 3 4.5V11.5A1.5 1.5 0 0 0 4.5 13h7a1.5 1.5 0 0 0 1.5-1.5V4.5A1.5 1.5 0 0 0 11.5 3h-7zM5 6.5A1.5 1.5 0 0 1 6.5 5h3A1.5 1.5 0 0 1 11 6.5v3A1.5 1.5 0 0 1 9.5 11h-3A1.5 1.5 0 0 1 5 9.5v-3zM6.5 6a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3z"/>
            </svg>
            Dashboard de Treinamentos (v2 SQL)
        </h3>
        <div>
            <a href="?token=<?php echo $token_recebido; ?>&action=download_csv" class="btn btn-light btn-sm me-2 fw-medium shadow-sm">📥 Exportar CSV</a>
            <a href="?token=<?php echo $token_recebido; ?>&action=enviar_relatorio" class="btn btn-outline-light btn-sm fw-medium me-3">📧 Disparar P/ RH</a>
            <span class="badge bg-danger rounded-pill px-3">SQLite Engine</span>
        </div>
    </div>
</div>

<div class="container pb-5">
    <?php echo $feedback_msg; ?>

    <!-- KPI Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card p-4 text-center">
                <div class="stat-value"><?php echo $stats_geral['total']; ?></div>
                <div class="stat-label">Total Registros</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-4 text-center">
                <div class="stat-value text-dark"><?php echo $tempo_total; ?></div>
                <div class="stat-label">Carga Horária Total</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-4 text-center border-start border-4 border-success">
                <div class="stat-value text-success"><?php echo $stats_geral['validados'] ?? 0; ?></div>
                <div class="stat-label">Validados</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-4 text-center border-start border-4 border-warning">
                <div class="stat-value text-warning"><?php echo $stats_geral['pendentes'] ?? 0; ?></div>
                <div class="stat-label">Pendentes</div>
            </div>
        </div>
    </div>

    <!-- Gráficos -->
    <?php if ($stats_geral['total'] > 0): ?>
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

    <!-- Barra de Filtros e Busca -->
    <div class="filter-bar shadow-sm">
        <form class="row g-3 align-items-end">
            <input type="hidden" name="token" value="<?php echo $token_recebido; ?>">
            
            <!-- Novo: Campo de Busca -->
            <div class="col-md-3">
                <label class="small fw-bold text-muted">Buscar Colaborador</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-search" viewBox="0 0 16 16"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/></svg></span>
                    <input type="text" name="busca" class="form-control border-start-0 ps-0" placeholder="Nome ou E-mail..." value="<?php echo htmlspecialchars($f_busca); ?>">
                </div>
            </div>

            <div class="col-md-2">
                <label class="small fw-bold text-muted">Data Início</label>
                <input type="date" name="data_inicio" class="form-control" value="<?php echo $f_inicio; ?>">
            </div>
            <div class="col-md-2">
                <label class="small fw-bold text-muted">Data Fim</label>
                <input type="date" name="data_fim" class="form-control" value="<?php echo $f_fim; ?>">
            </div>
            <div class="col-md-2">
                <label class="small fw-bold text-muted">Filial</label>
                <select name="filial" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach($filiais_permitidas as $s => $n): ?>
                        <option value="<?php echo $s; ?>" <?php echo $f_filial==$s?'selected':''; ?>><?php echo $n; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="small fw-bold text-muted">Ordem</label>
                <select name="ordenacao" class="form-select">
                    <option value="novo" <?php echo $ordenacao=='novo'?'selected':''; ?>>Mais Recentes</option>
                    <option value="antigo" <?php echo $ordenacao=='antigo'?'selected':''; ?>>Mais Antigos</option>
                    <option value="duracao" <?php echo $ordenacao=='duracao'?'selected':''; ?>>Carga Horária</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-dark w-100 fw-bold">OK</button>
            </div>
        </form>
    </div>

    <!-- Tabela -->
    <div class="table-box">
        <h5 class="mb-4 text-secondary fw-bold">Detalhamento dos Registros</h5>
        <?php if (empty($registros)): ?>
            <div class="alert alert-warning text-center py-5 border-0 shadow-sm">
                <h5 class="mb-0">Nenhum registro encontrado para os filtros aplicados.</h5>
            </div>
        <?php else: ?>
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Data/Hora</th>
                    <th>Colaborador</th>
                    <th>Unidade/Setor</th>
                    <th>Treinamento</th>
                    <th class="text-center">Duração</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($registros as $r): ?>
                <tr>
                    <td class="small text-muted"><?php echo date('d/m/Y H:i', strtotime($r['data_hora'])); ?></td>
                    <td>
                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($r['nome']); ?></div>
                        <div class="small text-muted"><?php echo htmlspecialchars($r['email']); ?></div>
                    </td>
                    <td>
                        <div class="text-dark fw-medium small"><?php echo $r['filial_nome']; ?></div>
                        <div class="small text-muted"><?php echo $r['depto_nome']; ?></div>
                    </td>
                    <td class="fw-medium text-dark"><?php echo htmlspecialchars($r['curso']); ?></td>
                    <td class="text-center">
                        <span class="badge bg-light text-dark border rounded-pill px-3"><?php echo exibir_duracao_formatada($r['duracao_minutos']); ?></span>
                    </td>
                    <td class="text-center">
                        <?php if($r['status'] === 'Validado'): ?>
                            <span class="badge badge-validado rounded-pill px-3">Validado</span>
                        <?php else: ?>
                            <span class="badge badge-pendente rounded-pill px-3 text-warning">Pendente</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm">
                            <?php if($r['status'] !== 'Validado'): ?>
                                <a href="?token=<?php echo $token_recebido; ?>&action=validar&id=<?php echo $r['id']; ?>&busca=<?php echo urlencode($f_busca); ?>&filial=<?php echo $f_filial; ?>" class="btn btn-outline-success" title="Validar">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check-lg" viewBox="0 0 16 16">
                                      <path d="M12.736 3.97a.733.733 0 0 1 1.047 0c.286.289.29.756.01 1.05L7.88 12.01a.733.733 0 0 1-1.065.02L3.217 8.384a.757.757 0 0 1 0-1.06.733.733 0 0 1 1.047 0l3.052 3.093 5.4-6.425z"/>
                                    </svg>
                                </a>
                            <?php endif; ?>
                            <a href="?token=<?php echo $token_recebido; ?>&action=excluir&id=<?php echo $r['id']; ?>&busca=<?php echo urlencode($f_busca); ?>&filial=<?php echo $f_filial; ?>" class="btn btn-outline-danger" onclick="return confirm('Excluir este registro?')" title="Excluir">
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
        <?php endif; ?>
    </div>

</div>

<!-- Script dos Gráficos -->
<?php if ($stats_geral['total'] > 0): ?>
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
            plugins: { legend: { position: 'bottom' } },
            cutout: '60%'
        }
    });
</script>
<?php endif; ?>

</body>
</html>
