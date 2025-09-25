<?php
// Increase execution time and memory limits for API calls
ini_set('max_execution_time', 120);
ini_set('memory_limit', '256M');
set_time_limit(120);

require_once __DIR__ . '/../../vendor/autoload.php';

use Gondwana\BookingApi\Router;
use Gondwana\BookingApi\Controllers\RatesController;

// Enable CORS for frontend development - COMPLETELY OPEN
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: *');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Allow-Credentials: false');
header('Access-Control-Max-Age: 86400');

// Debug: Log request details
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Origin: " . ($_SERVER['HTTP_ORIGIN'] ?? 'Not set'));
error_log("Request URI: " . $_SERVER['REQUEST_URI']);

// Handle preflight OPTIONS requests first
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    error_log("Handling OPTIONS preflight request");
    http_response_code(200);
    header('Content-Length: 0');
    exit();
}

// Set content type after OPTIONS check
header('Content-Type: application/json');

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
