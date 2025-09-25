<?php
// Increase execution time and memory limits for API calls
ini_set('max_execution_time', 120);
ini_set('memory_limit', '256M');
set_time_limit(120);

require_once __DIR__ . '/../../vendor/autoload.php';

use Gondwana\BookingApi\Router;
use Gondwana\BookingApi\Controllers\RatesController;

// Enable CORS for frontend development
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $router = new Router();
    
    // Define API routes
    $router->post('/api/rates', [RatesController::class, 'getRates']);
    $router->get('/api/health', function() {
        return [
            'status' => 'healthy',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0.0'
        ];
    });
    
    // Route the request
    $result = $router->route($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
    
    if ($result === null) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Endpoint not found',
            'message' => 'The requested API endpoint does not exist.'
        ]);
    } else {
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal Server Error',
        'message' => $e->getMessage()
    ]);
}
