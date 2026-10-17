<?php
$databaseUrl = getenv('SUPABASE_DB_URL') ?: getenv('DATABASE_URL');

if ($databaseUrl) {
    $connection = parse_url($databaseUrl);
    if ($connection === false || empty($connection['host']) || empty($connection['path'])) {
        error_log('Invalid SUPABASE_DB_URL / DATABASE_URL format.');
        http_response_code(500);
        exit('Database configuration error.');
    }

    parse_str($connection['query'] ?? '', $query);
    $sslMode = $query['sslmode'] ?? 'require';
    if (!in_array($sslMode, ['disable', 'allow', 'prefer', 'require', 'verify-ca', 'verify-full'], true)) {
        error_log('Unsupported PostgreSQL SSL mode.');
        http_response_code(500);
        exit('Database configuration error.');
    }

    $host = $connection['host'];
    $port = $connection['port'] ?? 5432;
    $database = ltrim($connection['path'], '/');
    $username = rawurldecode($connection['user'] ?? '');
    $password = rawurldecode($connection['pass'] ?? '');
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;sslmode=$sslMode";
} else {
    $host = getenv('PGHOST') ?: getenv('DB_HOST');
    $port = getenv('PGPORT') ?: getenv('DB_PORT') ?: '5432';
    $database = getenv('PGDATABASE') ?: getenv('DB_NAME');
    $username = getenv('PGUSER') ?: getenv('DB_USER');
    $password = getenv('PGPASSWORD');
    if ($password === false) {
        $password = getenv('DB_PASS') ?: '';
    }
    $sslMode = getenv('PGSSLMODE') ?: 'require';

    if (!$host || !$database || !$username) {
        error_log('PostgreSQL configuration is missing. Set SUPABASE_DB_URL or PGHOST, PGDATABASE, and PGUSER.');
        http_response_code(500);
        exit('Database configuration error.');
    }
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;sslmode=$sslMode";
}

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => true
    ]);
} catch (PDOException $e) {
    error_log('PostgreSQL connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection failed. Check the Supabase connection settings.');
}
?>
