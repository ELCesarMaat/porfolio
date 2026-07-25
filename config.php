<?php
/**
 * Configuración de base de datos
 * Rellena estos valores con los datos de tu hosting / phpMyAdmin
 */
define('DB_HOST',   'localhost');
define('DB_NAME',   'u205637003_main');
define('DB_USER',   'u205637003_main');
define('DB_PASS',   'itxLKGoX4a');
define('DB_CHARSET','utf8mb4');

date_default_timezone_set('America/Mexico_City');

/**
 * Contraseña dinámica para acceder a /stats.php
 * Formato: admin123 + día del mes en zona horaria local (ej: admin12324 si hoy es el día 24)
 */
function check_stats_password(string $input): bool {
    date_default_timezone_set('America/Mexico_City');
    
    /* Día local (UTC-6) */
    $day_local_j = date('j');
    $day_local_d = date('d');
    $valid_local_j = 'admin123' . $day_local_j;
    $valid_local_d = 'admin123' . $day_local_d;

    /* Día UTC (servidor) como respaldo */
    $utc = new DateTime('now', new DateTimeZone('UTC'));
    $valid_utc_j = 'admin123' . $utc->format('j');
    $valid_utc_d = 'admin123' . $utc->format('d');

    return hash_equals($valid_local_j, $input) ||
           hash_equals($valid_local_d, $input) ||
           hash_equals($valid_utc_j, $input) ||
           hash_equals($valid_utc_d, $input);
}

/**
 * Formatea una fecha/hora a la zona horaria local (America/Mexico_City)
 */
function format_local_date(?string $dtStr): string {
    if (empty($dtStr)) return '';
    try {
        $dt = new DateTime($dtStr);
        $dt->setTimezone(new DateTimeZone('America/Mexico_City'));
        return $dt->format('d/m/Y h:i:s A');
    } catch (Exception $e) {
        return $dtStr;
    }
}

/**
 * Crea la conexión PDO
 */
function db_connect(): PDO {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST, DB_NAME, DB_CHARSET
    );
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    return new PDO($dsn, DB_USER, DB_PASS, $options);
}
