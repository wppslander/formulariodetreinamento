<?php
/**
 * ADMIN2.PHP - Painel Administrativo via SQLite (Melhorado com Gráficos e KPIs)
 * Acesso: admin2.php?token=QsEtSkL7YGjAz5u
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

// 2. Ações (Validar / Excluir)
if (isset($_GET['action'])) {
    $id = intval($_GET['id'] ?? 0);
    if ($_GET['action'] === 'validar' && $id > 0) {
        $db->prepare("UPDATE treinamentos SET status = 'Validado' WHERE id = ?")->execute([$id]);
        registrar_audit_admin('Validar Treinamento (DB)', $id, dirname(__DIR__) . '/reports');
        header("Location: admin2.php?token=$token_recebido&msg=validado"); exit;
    }
    if ($_GET['action'] === 'excluir' && $id > 0) {
        $db->prepare("DELETE FROM treinamentos WHERE id = ?")->execute([$id]);
        registrar_audit_admin('Excluir Treinamento (DB)', $id, dirname(__DIR__) . '/reports');
        header("Location: admin2.php?token=$token_recebido&msg=excluido"); exit;
    }
}

// 3. Filtros e Ordenação
$f_inicio = $_GET['data_inicio'] ?? '';
$f_fim = $_GET['data_fim'] ?? '';
$f_filial = $_GET['filial'] ?? '';
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

// 5. Query Principal (Com Filtros)
$where = ["1=1"];
$params = [];
if ($f_inicio) { $where[] = "date(t.data_hora) >= ?"; $params[] = $f_inicio; }
if ($f_fim) { $where[] = "date(t.data_hora) <= ?"; $params[] = $f_fim; }
if ($f_filial) { $where[] = "f.slug = ?"; $params[] = $f_filial; }

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
        .filter-bar { background: white; padding: 20px; border-radius: 12px; margin-bottom: 30px; }
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
            <span class="badge bg-danger rounded-pill px-3">SQLite Engine</span>
        </div>
    </div>
</div>

<div class="container pb-5">

    <!-- KPI Cards (Sumário) -->
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
    <div class="row g-4 mb-5">
        <div class="col-md-8">
            <div class="card h-100 p-3">
                <h6 class="text-muted mb-3 fw-bold text-center">Registros por Filial</h6>
                <div style="height: 250px;"><canvas id="barChart"></canvas></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 p-3">
                <h6 class="text-muted mb-3 fw-bold text-center">Modalidades</h6>
                <div style="height: 250px;"><canvas id="pieChart"></canvas></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Barra de Filtros -->
    <div class="filter-bar shadow-sm">
        <form class="row g-3 align-items-end">
            <input type="hidden" name="token" value="<?php echo $token_recebido; ?>">
            <div class="col-md-2">
                <label class="small fw-bold text-muted">Data Início</label>
                <input type="date" name="data_inicio" class="form-control" value="<?php echo $f_inicio; ?>">
            </div>
            <div class="col-md-2">
                <label class="small fw-bold text-muted">Data Fim</label>
                <input type="date" name="data_fim" class="form-control" value="<?php echo $f_fim; ?>">
            </div>
            <div class="col-md-3">
                <label class="small fw-bold text-muted">Filial</label>
                <select name="filial" class="form-select">
                    <option value="">Todas as Filiais</option>
                    <?php foreach($filiais_permitidas as $s => $n): ?>
                        <option value="<?php echo $s; ?>" <?php echo $f_filial==$s?'selected':''; ?>><?php echo $n; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="small fw-bold text-muted">Ordenação</label>
                <select name="ordenacao" class="form-select">
                    <option value="novo" <?php echo $ordenacao=='novo'?'selected':''; ?>>Mais Recentes primeiro</option>
                    <option value="antigo" <?php echo $ordenacao=='antigo'?'selected':''; ?>>Mais Antigos primeiro</option>
                    <option value="duracao" <?php echo $ordenacao=='duracao'?'selected':''; ?>>Maior Carga Horária</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-dark w-100 fw-bold">Filtrar</button>
            </div>
        </form>
    </div>

    <!-- Tabela -->
    <div class="table-box">
        <h5 class="mb-4 text-secondary fw-bold">Detalhamento dos Registros</h5>
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Data/Hora</th>
                    <th>Colaborador</th>
                    <th>Unidade/Setor</th>
                    <th>Curso/Treinamento</th>
                    <th class="text-center">Duração</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($registros as $r): ?>
                <tr>
                    <td class="small text-muted" style="white-space: nowrap;"><?php echo date('d/m/Y H:i', strtotime($r['data_hora'])); ?></td>
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
                                <a href="?token=<?php echo $token_recebido; ?>&action=validar&id=<?php echo $r['id']; ?>" class="btn btn-outline-success" title="Validar">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check-lg" viewBox="0 0 16 16">
                                      <path d="M12.736 3.97a.733.733 0 0 1 1.047 0c.286.289.29.756.01 1.05L7.88 12.01a.733.733 0 0 1-1.065.02L3.217 8.384a.757.757 0 0 1 0-1.06.733.733 0 0 1 1.047 0l3.052 3.093 5.4-6.425z"/>
                                    </svg>
                                </a>
                            <?php endif; ?>
                            <a href="?token=<?php echo $token_recebido; ?>&action=excluir&id=<?php echo $r['id']; ?>" class="btn btn-outline-danger" onclick="return confirm('Excluir este registro?')" title="Excluir">
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
        <div class="text-center text-muted small mt-4">
            Exibindo <?php echo count($registros); ?> registros filtrados.
        </div>
    </div>

</div>

<!-- Scripts dos Gráficos -->
<?php if ($stats_geral['total'] > 0): ?>
<script>
    const ctxBar = document.getElementById('barChart').getContext('2d');
    new Chart(ctxBar, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($data_filiais, 'nome')); ?>,
            datasets: [{
                label: 'Cursos',
                data: <?php echo json_encode(array_column($data_filiais, 'total')); ?>,
                backgroundColor: '#DC0C15',
                borderRadius: 5
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
    });

    const ctxPie = document.getElementById('pieChart').getContext('2d');
    new Chart(ctxPie, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($data_modalidades, 'nome')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($data_modalidades, 'total')); ?>,
                backgroundColor: ['#DC0C15', '#252224', '#6c757d', '#adb5bd'],
                borderWidth: 0
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { position: 'bottom' } } }
    });
</script>
<?php endif; ?>

</body>
</html>
