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

/**
 * Contraseña dinámica para acceder a /stats.php
 * Formato: admin123 + día del mes (ej: admin12324 si hoy es 24)
 */
function check_stats_password(string $input): bool {
    $valid_j = 'admin123' . date('j');
    $valid_d = 'admin123' . date('d');
    return hash_equals($valid_j, $input) || hash_equals($valid_d, $input);
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
