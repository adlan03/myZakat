<?php
session_start();

$rootPath = dirname(__DIR__);

$routes = [
    'dashboard'    => $rootPath . '/app/Controllers/dashboard.php',
    'lihat_data'   => $rootPath . '/app/Controllers/lihat_data.php',
    'export_excel' => $rootPath . '/app/Controllers/export_excel.php',
    'masyarakat'   => $rootPath . '/app/Controllers/masyarakat.php',
    'ceklogin'     => $rootPath . '/app/Controllers/ceklogin.php',
];

$route = $_GET['page'] ?? 'dashboard';

if (!array_key_exists($route, $routes) || !file_exists($routes[$route])) {
    http_response_code(404);
    echo '<h1>404 - Halaman tidak ditemukan</h1>';
    exit;
}

require_once $routes[$route];
