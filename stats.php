<?php
/**
 * stats.php
 * Dashboard de estadísticas de visitas — protegido por contraseña.
 */

require_once __DIR__ . '/config.php';

/* ── Autenticación con protección anti-fuerza bruta ── */
session_start();

if (!isset($_SESSION['failed_attempts'])) {
    $_SESSION['failed_attempts'] = 0;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $submitted = $_POST['password'] ?? '';
    
    /* Si ya alcanzó los 5 intentos fallidos */
    if ($_SESSION['failed_attempts'] >= 5) {
        /* Delay de 5 segundos + login falso (ya no valida nada) */
        sleep(5);
        $_SESSION['failed_attempts']++;
        $error = 'Contraseña incorrecta.';
    } else {
        if (check_stats_password($submitted)) {
            $_SESSION['stats_auth'] = true;
            $_SESSION['failed_attempts'] = 0;
        } else {
            $_SESSION['failed_attempts']++;
            if ($_SESSION['failed_attempts'] >= 5) {
                sleep(5);
            }
            $error = 'Contraseña incorrecta.';
        }
    }
}

if (!($_SESSION['stats_auth'] ?? false)) {
    /* Mostrar formulario de login */
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Stats — César Maat</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #060d1a; --primary: #4f9eff; --secondary: #00d4a0;
      --surface: rgba(12,22,46,.85); --text: #e8eeff; --muted: #7a8db8;
    }
    *, *::before, *::after { box-sizing: border-box; }
    body {
      margin: 0; min-height: 100vh; display: grid; place-items: center;
      font-family: 'Inter', sans-serif;
      background: radial-gradient(ellipse 80% 50% at 10% -5%, rgba(79,158,255,.2) 0%, transparent 55%),
                  radial-gradient(ellipse 60% 40% at 90% 110%, rgba(0,212,160,.13) 0%, transparent 50%),
                  var(--bg);
      color: var(--text);
    }
    .card {
      background: var(--surface); border: 1px solid rgba(79,158,255,.18);
      border-radius: 20px; padding: 2.5rem 2.8rem; width: min(380px, 92%);
      backdrop-filter: blur(18px); box-shadow: 0 24px 60px rgba(4,8,20,.65);
      text-align: center;
    }
    .lock-icon { font-size: 2.2rem; margin-bottom: .75rem; }
    h1 { margin: 0 0 .35rem; font-size: 1.5rem; }
    p  { margin: 0 0 1.8rem; color: var(--muted); font-size: .9rem; }
    .error { color: #f87171; font-size: .85rem; margin: -.8rem 0 1rem; }
    input[type=password] {
      width: 100%; padding: .75rem 1rem; border-radius: 10px;
      background: rgba(79,158,255,.07); border: 1px solid rgba(79,158,255,.25);
      color: var(--text); font-family: inherit; font-size: .95rem; outline: none;
      transition: border-color .25s;
    }
    input[type=password]:focus { border-color: var(--primary); }
    button {
      margin-top: .85rem; width: 100%; padding: .8rem;
      border-radius: 999px; border: none; cursor: pointer; font-family: inherit;
      font-size: .9rem; font-weight: 600;
      background: linear-gradient(135deg, var(--primary), #3a7ee0);
      color: #010d1f; transition: transform .25s, box-shadow .25s;
    }
    button:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(79,158,255,.35); }
  </style>
</head>
<body>
  <div class="card">
    <div class="lock-icon">🔒</div>
    <h1>Panel de stats</h1>
    <p>Acceso restringido — ingresa la contraseña</p>
    <form method="post">
      <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
      <input type="password" name="password" placeholder="Contraseña" autofocus required>
      <button type="submit">Entrar</button>
    </form>
  </div>
</body>
</html>
    <?php
    exit;
}

/* ── Obtener estadísticas de la BD ── */
try {
    $pdo = db_connect();

    /* Total de visitas */
    $total = (int) $pdo->query("SELECT COUNT(*) FROM page_visits")->fetchColumn();

    /* Visitas hoy */
    $today = (int) $pdo->query("SELECT COUNT(*) FROM page_visits WHERE DATE(visited_at) = CURDATE()")->fetchColumn();

    /* Visitas esta semana */
    $week = (int) $pdo->query("SELECT COUNT(*) FROM page_visits WHERE visited_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

    /* Visitas este mes */
    $month = (int) $pdo->query("SELECT COUNT(*) FROM page_visits WHERE visited_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

    /* Visitas por día — últimos 30 días */
    $daily_stmt = $pdo->query("
        SELECT DATE(visited_at) AS day, COUNT(*) AS total
        FROM page_visits
        WHERE visited_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(visited_at)
        ORDER BY day ASC
    ");
    $daily_rows = $daily_stmt->fetchAll();

    /* Top referrers */
    $ref_stmt = $pdo->query("
        SELECT
            CASE WHEN referrer = '' THEN 'Directo' ELSE referrer END AS ref,
            COUNT(*) AS total
        FROM page_visits
        GROUP BY ref
        ORDER BY total DESC
        LIMIT 8
    ");
    $referrers = $ref_stmt->fetchAll();

    /* Navegadores */
    $browser_stmt = $pdo->query("
        SELECT browser, COUNT(*) AS total
        FROM page_visits
        GROUP BY browser
        ORDER BY total DESC
    ");
    $browsers = $browser_stmt->fetchAll();

    /* Sistemas operativos */
    $os_stmt = $pdo->query("
        SELECT os, COUNT(*) AS total
        FROM page_visits
        GROUP BY os
        ORDER BY total DESC
    ");
    $oses = $os_stmt->fetchAll();

    /* Top Países */
    $country_stmt = $pdo->query("
        SELECT
            CASE WHEN country = '' THEN 'Desconocido' ELSE country END AS country,
            country_code,
            COUNT(*) AS total
        FROM page_visits
        GROUP BY country, country_code
        ORDER BY total DESC
        LIMIT 8
    ");
    $countries = $country_stmt->fetchAll();

    /* Top Ciudades */
    $city_stmt = $pdo->query("
        SELECT
            CASE WHEN city = '' THEN 'Desconocido' ELSE city END AS city,
            country,
            COUNT(*) AS total
        FROM page_visits
        GROUP BY city, country
        ORDER BY total DESC
        LIMIT 8
    ");
    $cities = $city_stmt->fetchAll();

    /* Últimas 20 visitas */
    $recent_stmt = $pdo->query("
        SELECT page, browser, os, referrer, ip_anon, country, city, country_code, isp, visited_at
        FROM page_visits
        ORDER BY visited_at DESC
        LIMIT 20
    ");
    $recent = $recent_stmt->fetchAll();

} catch (PDOException $e) {
    die('<pre style="color:red">Error de BD: ' . htmlspecialchars($e->getMessage()) . '</pre>');
}

/* ── Preparar datos para Chart.js ── */
$chart_labels = json_encode(array_column($daily_rows, 'day'));
$chart_data   = json_encode(array_column($daily_rows, 'total'));

$browser_labels = json_encode(array_column($browsers, 'browser'));
$browser_data   = json_encode(array_column($browsers, 'total'));

$os_labels = json_encode(array_column($oses, 'os'));
$os_data   = json_encode(array_column($oses, 'total'));

$ref_labels = json_encode(array_column($referrers, 'ref'));
$ref_data   = json_encode(array_column($referrers, 'total'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Stats — César Maat</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
  <style>
    /* ── TOKENS ── */
    :root {
      --bg:       #060d1a;
      --surface:  rgba(12, 22, 46, 0.85);
      --border:   rgba(79, 158, 255, 0.15);
      --primary:  #4f9eff;
      --secondary:#00d4a0;
      --accent:   #7c3aed;
      --text:     #e8eeff;
      --muted:    #7a8db8;
      --red:      #f87171;
      --orange:   #fb923c;
      --r-md:     14px;
      --r-lg:     20px;
      --shadow:   0 12px 32px rgba(4,8,20,.45);
    }

    *, *::before, *::after { box-sizing: border-box; }

    html { scroll-behavior: smooth; }

    body {
      margin: 0;
      font-family: 'Inter', 'Segoe UI', sans-serif;
      font-size: 15px;
      line-height: 1.6;
      color: var(--text);
      background:
        radial-gradient(ellipse 80% 50% at 10% -5%,  rgba(79,158,255,.18) 0%, transparent 55%),
        radial-gradient(ellipse 60% 40% at 90% 110%, rgba(0,212,160,.12)  0%, transparent 50%),
        var(--bg);
      min-height: 100vh;
    }

    a { color: var(--primary); text-decoration: none; }
    a:hover { color: var(--secondary); }

    /* ── TOPBAR ── */
    .topbar {
      position: sticky;
      top: 0;
      z-index: 100;
      background: rgba(6, 12, 28, 0.92);
      backdrop-filter: blur(16px);
      border-bottom: 1px solid var(--border);
      padding: .9rem 2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
    }

    .topbar-brand {
      display: flex;
      align-items: center;
      gap: .7rem;
      font-weight: 700;
      font-size: 1rem;
    }

    .topbar-brand i { color: var(--primary); }

    .topbar-meta {
      display: flex;
      align-items: center;
      gap: 1rem;
      font-size: .8rem;
      color: var(--muted);
    }

    .btn-logout {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      padding: .45rem 1rem;
      border-radius: 999px;
      border: 1px solid rgba(248,113,113,.35);
      background: rgba(248,113,113,.08);
      color: var(--red);
      font-size: .8rem;
      font-weight: 600;
      font-family: inherit;
      cursor: pointer;
      transition: all .25s;
    }

    .btn-logout:hover {
      background: rgba(248,113,113,.18);
      transform: translateY(-1px);
    }

    /* ── LAYOUT ── */
    .page {
      max-width: 1200px;
      margin: 0 auto;
      padding: 2rem 1.5rem 4rem;
    }

    .page-title {
      font-size: 1.65rem;
      font-weight: 700;
      margin: 0 0 .35rem;
    }

    .page-subtitle {
      color: var(--muted);
      font-size: .9rem;
      margin: 0 0 2rem;
    }

    /* ── STAT CARDS ── */
    .kpi-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
      gap: 1rem;
      margin-bottom: 1.75rem;
    }

    .kpi-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--r-lg);
      padding: 1.4rem 1.5rem;
      box-shadow: var(--shadow);
      display: flex;
      flex-direction: column;
      gap: .4rem;
      transition: border-color .25s, transform .25s;
    }

    .kpi-card:hover {
      border-color: rgba(79,158,255,.3);
      transform: translateY(-3px);
    }

    .kpi-icon {
      width: 38px; height: 38px;
      border-radius: 10px;
      display: grid; place-items: center;
      font-size: .95rem;
      margin-bottom: .2rem;
    }

    .kpi-icon.blue   { background: rgba(79,158,255,.15); color: var(--primary); }
    .kpi-icon.green  { background: rgba(0,212,160,.15);  color: var(--secondary); }
    .kpi-icon.purple { background: rgba(124,58,237,.15); color: #b08fff; }
    .kpi-icon.orange { background: rgba(251,146,60,.15); color: var(--orange); }

    .kpi-value {
      font-size: 2.1rem;
      font-weight: 700;
      font-family: 'JetBrains Mono', monospace;
      color: var(--text);
      line-height: 1;
    }

    .kpi-label {
      font-size: .78rem;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: .12em;
    }

    /* ── CHART CARDS ── */
    .chart-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 1.25rem;
      margin-bottom: 1.75rem;
    }

    .chart-grid-2 {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 1.25rem;
      margin-bottom: 1.75rem;
    }

    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--r-lg);
      padding: 1.5rem;
      box-shadow: var(--shadow);
    }

    .card-title {
      font-size: .95rem;
      font-weight: 600;
      color: var(--text);
      margin: 0 0 1.1rem;
      display: flex;
      align-items: center;
      gap: .5rem;
    }

    .card-title i { color: var(--primary); font-size: .85rem; }

    .chart-wrap {
      position: relative;
      height: 220px;
    }

    /* ── BAR LIST (referrers) ── */
    .bar-list { display: grid; gap: .65rem; }

    .bar-item { }

    .bar-meta {
      display: flex;
      justify-content: space-between;
      font-size: .8rem;
      margin-bottom: .28rem;
      color: var(--muted);
    }

    .bar-meta span:first-child {
      color: var(--text);
      font-weight: 500;
      word-break: break-word;
    }

    .bar-track {
      height: 5px;
      border-radius: 99px;
      background: rgba(79,158,255,.1);
      overflow: hidden;
    }

    .bar-fill {
      height: 100%;
      border-radius: 99px;
      background: linear-gradient(90deg, var(--primary), var(--secondary));
      transition: width .6s ease;
    }

    /* ── TABLE ── */
    .table-wrap {
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: .83rem;
    }

    thead th {
      text-align: left;
      padding: .65rem .9rem;
      color: var(--muted);
      font-size: .72rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .1em;
      border-bottom: 1px solid var(--border);
      white-space: nowrap;
    }

    tbody tr {
      border-bottom: 1px solid rgba(79,158,255,.07);
      transition: background .2s;
    }

    tbody tr:hover { background: rgba(79,158,255,.05); }

    tbody tr:last-child { border-bottom: none; }

    td {
      padding: .65rem .9rem;
      color: rgba(232,238,255,.8);
      white-space: nowrap;
    }

    .badge {
      display: inline-flex;
      align-items: center;
      gap: .28rem;
      padding: .2rem .58rem;
      border-radius: 999px;
      font-size: .72rem;
      font-weight: 500;
    }

    .badge.browser {
      background: rgba(79,158,255,.1);
      color: var(--primary);
      border: 1px solid rgba(79,158,255,.18);
    }

    .badge.os {
      background: rgba(0,212,160,.1);
      color: var(--secondary);
      border: 1px solid rgba(0,212,160,.18);
    }

    /* ── FOOTER ── */
    .stats-footer {
      text-align: center;
      margin-top: 3rem;
      color: var(--muted);
      font-size: .78rem;
    }

    /* ── RESPONSIVE ── */
    @media (max-width: 600px) {
      .topbar { padding: .8rem 1rem; }
      .page   { padding: 1.5rem 1rem 3rem; }
      .kpi-value { font-size: 1.7rem; }
    }
  </style>
</head>
<body>

<!-- Topbar -->
<header class="topbar">
  <div class="topbar-brand">
    <i class="fas fa-chart-line"></i>
    Panel de estadísticas
  </div>
  <div class="topbar-meta">
    <span><i class="fas fa-calendar-day"></i> <?= date('d M Y, H:i') ?></span>
    <form method="post" action="?logout=1" style="margin:0">
      <input type="hidden" name="logout" value="1">
      <button class="btn-logout" type="submit" onclick="<?php
        if (isset($_GET['logout'])) {
          session_destroy();
          header('Location: stats.php');
          exit;
        }
      ?>"><i class="fas fa-right-from-bracket"></i>Salir</button>
    </form>
  </div>
</header>

<?php
/* Manejar logout */
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: stats.php');
    exit;
}
?>

<div class="page">
  <h1 class="page-title">Visitas al portafolio</h1>
  <p class="page-subtitle">César Maat · cesarmaat.com</p>

  <!-- KPIs -->
  <div class="kpi-grid">
    <div class="kpi-card">
      <div class="kpi-icon blue"><i class="fas fa-eye"></i></div>
      <div class="kpi-value"><?= number_format($total) ?></div>
      <div class="kpi-label">Total visitas</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-icon green"><i class="fas fa-sun"></i></div>
      <div class="kpi-value"><?= number_format($today) ?></div>
      <div class="kpi-label">Hoy</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-icon purple"><i class="fas fa-calendar-week"></i></div>
      <div class="kpi-value"><?= number_format($week) ?></div>
      <div class="kpi-label">Últimos 7 días</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-icon orange"><i class="fas fa-calendar"></i></div>
      <div class="kpi-value"><?= number_format($month) ?></div>
      <div class="kpi-label">Últimos 30 días</div>
    </div>
  </div>

  <!-- Gráfica de visitas diarias -->
  <div class="chart-grid">
    <div class="card">
      <p class="card-title"><i class="fas fa-chart-area"></i>Visitas por día — últimos 30 días</p>
      <div class="chart-wrap">
        <canvas id="chartDaily"></canvas>
      </div>
    </div>
  </div>

  <!-- Navegadores + OS -->
  <div class="chart-grid-2">
    <div class="card">
      <p class="card-title"><i class="fas fa-globe"></i>Navegadores</p>
      <div class="chart-wrap">
        <canvas id="chartBrowser"></canvas>
      </div>
    </div>
    <div class="card">
      <p class="card-title"><i class="fas fa-laptop"></i>Sistemas operativos</p>
      <div class="chart-wrap">
        <canvas id="chartOS"></canvas>
      </div>
    </div>
  </div>

  <!-- Top Países y Top Ciudades -->
  <div class="chart-grid-2" style="margin-bottom:1.25rem">
    <div class="card">
      <p class="card-title"><i class="fas fa-flag"></i>Top Países</p>
      <div class="bar-list">
        <?php
          $max_c = $countries[0]['total'] ?? 1;
          foreach ($countries as $c):
            $pct = round(($c['total'] / $max_c) * 100);
            $code = strtolower(trim($c['country_code']));
            $hasFlag = (strlen($code) === 2);
        ?>
        <div class="bar-item">
          <div class="bar-meta">
            <span style="display:inline-flex;align-items:center;gap:.4rem">
              <?php if ($hasFlag): ?>
                <img src="https://flagcdn.com/20x15/<?= htmlspecialchars($code) ?>.png" width="20" height="15" alt="<?= htmlspecialchars($code) ?>" style="border-radius:2px">
              <?php else: ?>
                <i class="fas fa-globe" style="color:var(--muted)"></i>
              <?php endif; ?>
              <?= htmlspecialchars($c['country']) ?>
            </span>
            <span><?= number_format($c['total']) ?> visitas</span>
          </div>
          <div class="bar-track">
            <div class="bar-fill" style="width:<?= $pct ?>%"></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($countries)): ?>
          <p style="color:var(--muted);font-size:.85rem;margin:0">Sin datos de países.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <p class="card-title"><i class="fas fa-city"></i>Top Ciudades</p>
      <div class="bar-list">
        <?php
          $max_city = $cities[0]['total'] ?? 1;
          foreach ($cities as $ci):
            $pct = round(($ci['total'] / $max_city) * 100);
        ?>
        <div class="bar-item">
          <div class="bar-meta">
            <span><i class="fas fa-location-dot" style="color:var(--secondary);margin-right:.3rem"></i><?= htmlspecialchars($ci['city']) ?> <small style="color:var(--muted)">(<?= htmlspecialchars($ci['country']) ?>)</small></span>
            <span><?= number_format($ci['total']) ?> visitas</span>
          </div>
          <div class="bar-track">
            <div class="bar-fill" style="width:<?= $pct ?>%"></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($cities)): ?>
          <p style="color:var(--muted);font-size:.85rem;margin:0">Sin datos de ciudades.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Top referrers -->
  <div class="card" style="margin-bottom:1.25rem">
    <p class="card-title"><i class="fas fa-link"></i>Fuentes de tráfico (Top <?= count($referrers) ?>)</p>
    <div class="bar-list">
      <?php
        $max_ref = $referrers[0]['total'] ?? 1;
        foreach ($referrers as $r):
          $pct = round(($r['total'] / $max_ref) * 100);
          $label = htmlspecialchars($r['ref']);
      ?>
      <div class="bar-item">
        <div class="bar-meta">
          <span title="<?= htmlspecialchars($r['ref']) ?>"><?= $label ?></span>
          <span><?= number_format($r['total']) ?> visitas</span>
        </div>
        <div class="bar-track">
          <div class="bar-fill" style="width:<?= $pct ?>%"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Últimas visitas -->
  <div class="card">
    <p class="card-title"><i class="fas fa-clock-rotate-left"></i>Últimas 20 visitas</p>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Fecha y hora</th>
            <th>Ubicación</th>
            <th>IP Completa</th>
            <th>Proveedor (ISP)</th>
            <th>Navegador / OS</th>
            <th>Página</th>
            <th>Referrer</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent as $v):
              $code = strtolower(trim($v['country_code'] ?? ''));
              $hasFlag = (strlen($code) === 2);
              $country = $v['country'] ?: 'Desconocido';
              $city    = $v['city']    ?: 'Desconocido';
              $isp     = $v['isp']     ?: 'Desconocido';
          ?>
          <tr>
            <td style="font-family:'JetBrains Mono',monospace;font-size:.75rem;color:var(--muted)">
              <?= htmlspecialchars(format_local_date($v['visited_at'])) ?>
            </td>
            <td>
              <div style="display:flex;align-items:center;gap:.4rem">
                <?php if ($hasFlag): ?>
                  <img src="https://flagcdn.com/20x15/<?= htmlspecialchars($code) ?>.png" width="20" height="15" alt="<?= htmlspecialchars($code) ?>" style="border-radius:2px" title="<?= htmlspecialchars($country) ?>">
                <?php else: ?>
                  <i class="fas fa-globe" style="color:var(--muted)"></i>
                <?php endif; ?>
                <span><strong><?= htmlspecialchars($country) ?></strong>, <?= htmlspecialchars($city) ?></span>
              </div>
            </td>
            <td style="font-family:'JetBrains Mono',monospace;font-size:.78rem">
              <?= htmlspecialchars($v['ip_anon']) ?>
            </td>
            <td style="font-size:.78rem;color:var(--muted)">
              <?= htmlspecialchars($isp) ?>
            </td>
            <td>
              <span class="badge browser"><?= htmlspecialchars($v['browser']) ?></span>
              <span class="badge os"><?= htmlspecialchars($v['os']) ?></span>
            </td>
            <td><?= htmlspecialchars($v['page']) ?></td>
            <td style="color:var(--muted)">
              <?= $v['referrer'] ? htmlspecialchars($v['referrer']) : '<em>Directo</em>' ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($recent)): ?>
          <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2rem">Sin visitas registradas aún.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <p class="stats-footer">
    Geolocalización de IPs automática · Uso interno exclusivo ·
    <a href="./">← Volver al portafolio</a>
  </p>
</div>

<!-- ── Charts ── -->
<script>
  Chart.defaults.color          = '#7a8db8';
  Chart.defaults.borderColor    = 'rgba(79,158,255,.1)';
  Chart.defaults.font.family    = "'Inter', sans-serif";
  Chart.defaults.font.size      = 12;

  const PALETTE = ['#4f9eff','#00d4a0','#b08fff','#fb923c','#f87171','#e3b341','#60a5fa','#34d399'];

  /* ── Diario ── */
  new Chart(document.getElementById('chartDaily'), {
    type: 'line',
    data: {
      labels: <?= $chart_labels ?>,
      datasets: [{
        label: 'Visitas',
        data: <?= $chart_data ?>,
        borderColor: '#4f9eff',
        backgroundColor: 'rgba(79,158,255,.12)',
        borderWidth: 2,
        pointRadius: 3,
        pointHoverRadius: 6,
        fill: true,
        tension: 0.4,
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { color: 'rgba(79,158,255,.07)' }, ticks: { maxTicksLimit: 10 } },
        y: { grid: { color: 'rgba(79,158,255,.07)' }, beginAtZero: true, ticks: { stepSize: 1 } }
      }
    }
  });

  /* ── Navegadores ── */
  new Chart(document.getElementById('chartBrowser'), {
    type: 'doughnut',
    data: {
      labels: <?= $browser_labels ?>,
      datasets: [{ data: <?= $browser_data ?>, backgroundColor: PALETTE, borderWidth: 0, hoverOffset: 8 }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom', labels: { padding: 16, boxWidth: 12, borderRadius: 4 } }
      },
      cutout: '62%'
    }
  });

  /* ── OS ── */
  new Chart(document.getElementById('chartOS'), {
    type: 'doughnut',
    data: {
      labels: <?= $os_labels ?>,
      datasets: [{ data: <?= $os_data ?>, backgroundColor: PALETTE.slice(2), borderWidth: 0, hoverOffset: 8 }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom', labels: { padding: 16, boxWidth: 12, borderRadius: 4 } }
      },
      cutout: '62%'
    }
  });
</script>

</body>
</html>
