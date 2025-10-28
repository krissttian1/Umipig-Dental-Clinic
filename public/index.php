<?php
/**
 * Main entry point for the application
 */

// Define application paths
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/src');

try {
    // Start session
    session_start();

    // Load configuration
    require_once APP_PATH . '/config/db_connection.php';
    require_once APP_PATH . '/includes/autoload.php';

    // Simple router
    $request = $_SERVER['REQUEST_URI'];
    $basePath = '/umipig-dental-clinic/public';
    $request = str_replace($basePath, '', $request);
    $request = explode('?', $request)[0];

    // Route to the appropriate controller/action
    switch ($request) {
        case '/':
        case '':
        case '/home':
            require APP_PATH . '/views/LandingPage.php';
            break;
        case '/about':
            require APP_PATH . '/views/aboutUs.php';
            break;
        case '/contact':
            require APP_PATH . '/views/contactUs.php';
            break;
        case '/services':
            require APP_PATH . '/views/services.php';
            break;
        case '/login':
            require APP_PATH . '/views/login.php';
            break;
        case '/register':
            require APP_PATH . '/views/register.php';
            break;
        case '/admin/dashboard':
            require APP_PATH . '/views/admin_dashboard.php';
            break;
        default:
            http_response_code(404);
            require APP_PATH . '/views/404.php';
            break;
    }
} catch (Exception $e) {
    // Log the error and show a generic error page
    error_log($e->getMessage());
    http_response_code(500);
    require APP_PATH . '/views/500.php';
}
