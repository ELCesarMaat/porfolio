<?php
/**
 * track.php
 * Registra una visita en la base de datos con geolocalización (País, Ciudad, ISP).
 * Llamado desde index.html via fetch() en JavaScript.
 */

require_once __DIR__ . '/config.php';

/* ── Cabeceras ── */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');

/* ── Solo aceptar GET o POST ── */
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

/* ── Datos de la visita ── */
$page     = isset($_GET['page'])     ? substr(trim($_GET['page']), 0, 255)     : '/';
$referrer = isset($_GET['referrer']) ? substr(trim($_GET['referrer']), 0, 500) : '';
$ua       = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : '';

/* IP completa */
$ip_raw  = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$ip_full = trim(explode(',', $ip_raw)[0]);

/* ── Detectar navegador a partir del User-Agent ── */
function detect_browser(string $ua): string {
    $browsers = [
        'Edge'    => 'Edg/',
        'Chrome'  => 'Chrome/',
        'Firefox' => 'Firefox/',
        'Safari'  => 'Safari/',
        'Opera'   => 'OPR/',
    ];
    foreach ($browsers as $name => $token) {
        if (str_contains($ua, $token)) return $name;
    }
    return 'Otro';
}

/* ── Detectar SO ── */
function detect_os(string $ua): string {
    $systems = [
        'Windows' => 'Windows',
        'macOS'   => 'Macintosh',
        'Linux'   => 'Linux',
        'Android' => 'Android',
        'iOS'     => 'iPhone',
    ];
    foreach ($systems as $name => $token) {
        if (str_contains($ua, $token)) return $name;
    }
    return 'Otro';
}

/* ── Obtener geolocalización por IP ── */
function get_ip_geo(string $ip): array {
    if (empty($ip) || $ip === '127.0.0.1' || $ip === '::1' || str_starts_with($ip, '192.168.')) {
        return ['country' => 'Localhost', 'city' => 'Localhost', 'country_code' => 'XX', 'isp' => 'Localhost'];
    }
    
    $url = "http://ip-api.com/json/" . urlencode($ip) . "?fields=status,country,countryCode,city,regionName,isp";
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 2,
            'header'  => "User-Agent: Mozilla/5.0\r\n"
        ]
    ]);
    
    $json = @file_get_contents($url, false, $ctx);
    if ($json && ($data = json_decode($json, true)) && ($data['status'] ?? '') === 'success') {
        return [
            'country'      => $data['country']      ?? 'Desconocido',
            'city'         => $data['city']         ?? 'Desconocido',
            'country_code' => $data['countryCode']  ?? '',
            'isp'          => $data['isp']          ?? ''
        ];
    }
    return ['country' => 'Desconocido', 'city' => 'Desconocido', 'country_code' => '', 'isp' => ''];
}

$browser = detect_browser($ua);
$os      = detect_os($ua);
$geo     = get_ip_geo($ip_full);

/* ── Guardar en base de datos ── */
try {
    $pdo = db_connect();

    /* Crear tabla si no existe (auto-instalación) */
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS page_visits (
            id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            page         VARCHAR(255)  NOT NULL DEFAULT '/',
            referrer     VARCHAR(500)  NOT NULL DEFAULT '',
            browser      VARCHAR(50)   NOT NULL DEFAULT '',
            os           VARCHAR(50)   NOT NULL DEFAULT '',
            ip_anon      VARCHAR(100)  NOT NULL DEFAULT '',
            country      VARCHAR(100)  NOT NULL DEFAULT '',
            city         VARCHAR(100)  NOT NULL DEFAULT '',
            country_code VARCHAR(10)   NOT NULL DEFAULT '',
            isp          VARCHAR(150)  NOT NULL DEFAULT '',
            visited_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_visited_at (visited_at),
            INDEX idx_page (page),
            INDEX idx_country (country)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    /* Migraciones automáticas para tablas ya creadas */
    $newCols = [
        "country VARCHAR(100) NOT NULL DEFAULT ''",
        "city VARCHAR(100) NOT NULL DEFAULT ''",
        "country_code VARCHAR(10) NOT NULL DEFAULT ''",
        "isp VARCHAR(150) NOT NULL DEFAULT ''"
    ];
    foreach ($newCols as $colDef) {
        try {
            $pdo->exec("ALTER TABLE page_visits ADD COLUMN $colDef");
        } catch (PDOException $e) { /* Columna ya existe */ }
    }

    $stmt = $pdo->prepare("
        INSERT INTO page_visits (page, referrer, browser, os, ip_anon, country, city, country_code, isp, visited_at)
        VALUES (:page, :referrer, :browser, :os, :ip_anon, :country, :city, :country_code, :isp, :visited_at)
    ");
    $stmt->execute([
        ':page'         => $page,
        ':referrer'     => $referrer,
        ':browser'      => $browser,
        ':os'           => $os,
        ':ip_anon'      => $ip_full,
        ':country'      => $geo['country'],
        ':city'         => $geo['city'],
        ':country_code' => $geo['country_code'],
        ':isp'          => $geo['isp'],
        ':visited_at'   => date('Y-m-d H:i:s'),
    ]);

    echo json_encode(['ok' => true]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error']);  // no exponer detalles al cliente
}
