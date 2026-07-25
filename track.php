<?php
/**
 * track.php
 * Registra una visita en la base de datos.
 * Llamado desde index.html via fetch() en JavaScript.
 */

require_once __DIR__ . '/config.php';

/* ── Cabeceras ── */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');          // permite llamada desde el mismo dominio
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

/* IP completa sin anonimizar */
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

$browser = detect_browser($ua);
$os      = detect_os($ua);

/* ── Guardar en base de datos ── */
try {
    $pdo = db_connect();

    /* Crear tabla si no existe (auto-instalación) */
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS page_visits (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            page       VARCHAR(255)  NOT NULL DEFAULT '/',
            referrer   VARCHAR(500)  NOT NULL DEFAULT '',
            browser    VARCHAR(50)   NOT NULL DEFAULT '',
            os         VARCHAR(50)   NOT NULL DEFAULT '',
            ip_anon    VARCHAR(20)   NOT NULL DEFAULT '',
            visited_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_visited_at (visited_at),
            INDEX idx_page (page)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $stmt = $pdo->prepare("
        INSERT INTO page_visits (page, referrer, browser, os, ip_anon, visited_at)
        VALUES (:page, :referrer, :browser, :os, :ip_anon, NOW())
    ");
    $stmt->execute([
        ':page'     => $page,
        ':referrer' => $referrer,
        ':browser'  => $browser,
        ':os'       => $os,
        ':ip_anon'  => $ip_full,
    ]);

    echo json_encode(['ok' => true]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error']);  // no exponer detalles al cliente
}
