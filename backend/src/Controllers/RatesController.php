<?php

namespace Gondwana\BookingApi\Controllers;

use Gondwana\BookingApi\Services\RatesService;
use Gondwana\BookingApi\Services\ValidationService;

class RatesController
{
    private RatesService $ratesService;
    private ValidationService $validationService;

    public function __construct()
    {
        $this->ratesService = new RatesService();
        $this->validationService = new ValidationService();
    }

    public function getRates(): array
    {
        $response = ['success' => false];
        
        try {
            // Get request body
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                $response = [
                    'success' => false,
                    'error' => 'Invalid JSON',
                    'message' => 'Request body must be valid JSON'
                ];
            } elseif (!$this->validationService->validateBookingRequest($data)['valid']) {
                http_response_code(400);
                $validation = $this->validationService->validateBookingRequest($data);
                $response = [
                    'success' => false,
                    'error' => 'Validation Error',
                    'message' => 'Invalid request data',
                    'details' => $validation['errors']
                ];
            } else {
                // Process the request
                $result = $this->ratesService->fetchRates($data);
                $response = [
                    'success' => true,
                    'data' => $result
                ];
            }
        } catch (\Exception $e) {
            http_response_code(500);
            $response = [
                'success' => false,
                'error' => 'Processing Error',
                'message' => $e->getMessage()
            ];
        }
        
        return $response;
    }
}
