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
 * Contraseña para acceder a /stats.php
 * Cámbiala por algo seguro
 */
define('STATS_PASSWORD', 'admin1234');     // <-- cambia esto

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
