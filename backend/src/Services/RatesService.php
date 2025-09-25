<?php

namespace Gondwana\BookingApi\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class RatesService
{
    private Client $httpClient;
    private string $gondwanaApiUrl;
    private array $unitTypeIds;

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 60,
            'connect_timeout' => 30,
            'read_timeout' => 60,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'Gondwana-Booking-API/1.0'
            ],
            'verify' => false, // Skip SSL verification for dev environment
            'http_errors' => false // Don't throw exceptions on HTTP errors
        ]);
        
        $this->gondwanaApiUrl = 'https://dev.gondwana-collection.com/Web-Store/Rates/Rates.php';
        
        // Test Unit Type IDs provided in the assignment
        $this->unitTypeIds = [-2147483637, -2147483456];
    }

    public function fetchRates(array $bookingData): array
    {
        $results = [];
        
        // If a specific unit type is selected, only process that one
        $unitTypesToProcess = isset($bookingData['selectedUnitTypeId']) 
            ? [$bookingData['selectedUnitTypeId']]
            : $this->unitTypeIds;

        foreach ($unitTypesToProcess as $unitTypeId) {
            $maxRetries = 2;
            $retryCount = 0;
            $success = false;
            
            while (!$success && $retryCount <= $maxRetries) {
                try {
                    // Transform the payload
                    $transformedPayload = $this->transformPayload($bookingData, $unitTypeId);
                    
                    // Make API call to Gondwana with improved error handling
                    $response = $this->httpClient->post($this->gondwanaApiUrl, [
                        'json' => $transformedPayload,
                        'timeout' => 30 + ($retryCount * 15) // Increase timeout with each retry
                    ]);

                    $statusCode = $response->getStatusCode();
                    $responseBody = $response->getBody()->getContents();
                    
                    // Check if we got a successful response
                    if ($statusCode >= 200 && $statusCode < 300) {
                        $responseData = json_decode($responseBody, true);
                        
                        if (json_last_error() === JSON_ERROR_NONE && $responseData !== null) {
                            // Process and format the response
                            $processedResult = $this->processGondwanaResponse($responseData, $bookingData, $unitTypeId);
                            $results[] = $processedResult;
                            $success = true; // Mark as successful
                        } else {
                            throw new \Exception("Invalid JSON response from Gondwana API");
                        }
                    } else {
                        throw new \Exception("HTTP {$statusCode}: " . substr($responseBody, 0, 200));
                    }

                } catch (RequestException $e) {
                    $retryCount++;
                    if ($retryCount > $maxRetries) {
                        // Handle API errors gracefully
                        $errorMessage = 'Connection timeout or network error';
                        if ($e->hasResponse()) {
                            $errorMessage = 'API returned error: ' . $e->getResponse()->getStatusCode();
                        }
                        
                        $results[] = [
                            'unitTypeId' => $unitTypeId,
                            'unitName' => $bookingData['Unit Name'],
                            'accommodationType' => 'Accommodation',
                            'fullName' => $bookingData['Unit Name'] . ' - Accommodation',
                            'error' => $errorMessage,
                            'rate' => null,
                            'dateRange' => $this->formatDateRange($bookingData['Arrival'], $bookingData['Departure']),
                            'availability' => false
                        ];
                        break; // Exit retry loop
                    } else {
                        // Wait before retrying
                        usleep(500000); // 0.5 second delay
                    }
                } catch (\Exception $e) {
                    $retryCount++;
                    if ($retryCount > $maxRetries) {
                        // Handle other errors
                        $results[] = [
                            'unitTypeId' => $unitTypeId,
                            'unitName' => $bookingData['Unit Name'],
                            'accommodationType' => 'Accommodation',
                            'fullName' => $bookingData['Unit Name'] . ' - Accommodation',
                            'error' => 'Service temporarily unavailable',
                            'rate' => null,
                            'dateRange' => $this->formatDateRange($bookingData['Arrival'], $bookingData['Departure']),
                            'availability' => false
                        ];
                        break; // Exit retry loop
                    } else {
                        // Wait before retrying
                        usleep(500000); // 0.5 second delay
                    }
                }
            } // End of retry while loop
        }

        return $results;
    }

    private function transformPayload(array $inputData, int $unitTypeId): array
    {
        // Convert date format from dd/mm/yyyy to yyyy-mm-dd
        $arrival = $this->convertDateFormat($inputData['Arrival'], 'd/m/Y', 'Y-m-d');
        $departure = $this->convertDateFormat($inputData['Departure'], 'd/m/Y', 'Y-m-d');

        // Transform ages to guest objects with age groups
        $guests = [];
        foreach ($inputData['Ages'] as $age) {
            $guests[] = [
                'Age Group' => $this->determineAgeGroup($age)
            ];
        }

        return [
            'Unit Type ID' => $unitTypeId,
            'Arrival' => $arrival,
            'Departure' => $departure,
            'Guests' => $guests
        ];
    }

    private function convertDateFormat(string $date, string $fromFormat, string $toFormat): string
    {
        $dateTime = \DateTime::createFromFormat($fromFormat, $date);
        return $dateTime->format($toFormat);
    }

    private function determineAgeGroup(int $age): string
    {
        // Typically, children are under 18, but this could be configurable
        return $age < 18 ? 'Child' : 'Adult';
    }

    private function processGondwanaResponse(array $responseData, array $originalData, int $unitTypeId): array
    {
        $rate = null;
        $availability = false;
        $error = null;
        $rateCode = null;
        $locationId = null;

        // Extract basic info
        if (isset($responseData['Location ID'])) {
            $locationId = $responseData['Location ID'];
        }

        // Check if response contains rate information
        if (isset($responseData['Total Charge']) && is_numeric($responseData['Total Charge'])) {
            $totalCharge = (float) $responseData['Total Charge'];
            
            if ($totalCharge > 0) {
                // Convert from cents to dollars (Gondwana returns cents)
                $rate = $totalCharge / 100;
                $availability = true;
            } else {
                // Zero charge might indicate unavailability or missing member code
                $rate = 0;
                $availability = false;
                
                // Check for error messages in legs
                if (isset($responseData['Legs']) && is_array($responseData['Legs'])) {
                    foreach ($responseData['Legs'] as $leg) {
                        if (isset($leg['Guests']) && is_array($leg['Guests'])) {
                            foreach ($leg['Guests'] as $guest) {
                                if (isset($guest['Error Message']) && !empty($guest['Error Message'])) {
                                    $error = $guest['Error Message'];
                                    break 2; // Break out of both loops
                                }
                            }
                        }
                    }
                }
            }
        } else {
            // No total charge field
            $availability = false;
            $error = 'No rate information available';
        }

        // Extract real property name from API response
        $propertyName = $this->extractPropertyName($responseData, $originalData['Unit Name']);
        
        return [
            'unitTypeId' => $unitTypeId,
            'unitName' => $propertyName['property'],
            'accommodationType' => $propertyName['type'],
            'fullName' => $propertyName['full'],
            'rate' => $rate,
            'dateRange' => $this->formatDateRange($originalData['Arrival'], $originalData['Departure']),
            'availability' => $availability,
            'occupants' => $originalData['Occupants'],
            'error' => $error,
            'locationId' => $locationId,
            'rateCode' => $this->extractRateCode($responseData)
        ];
    }

    private function extractPropertyName(array $responseData, string $fallbackName): array
    {
        // Look through legs to find the first valid Special Rate Description
        if (isset($responseData['Legs']) && is_array($responseData['Legs'])) {
            foreach ($responseData['Legs'] as $leg) {
                if (isset($leg['Special Rate Description']) && 
                    $leg['Special Rate Description'] !== 'Not Found' &&
                    !empty($leg['Special Rate Description'])) {
                    
                    $description = $leg['Special Rate Description'];
                    
                    // Extract from "* STANDARD RATE CAMPING - Klipspringer Camps"
                    if (preg_match('/\*\s*(.+?)\s*-\s*(.+?)\s*$/', $description, $matches)) {
                        $rateType = trim($matches[1]);
                        $propertyName = trim($matches[2]);
                        
                        return [
                            'property' => $propertyName,
                            'type' => ucwords(strtolower($rateType)), // "Standard Rate Camping"
                            'full' => $propertyName . ' - ' . ucwords(strtolower($rateType))
                        ];
                    }
                    
                    // If regex doesn't match, try to extract property name another way
                    if (strpos($description, ' - ') !== false) {
                        $parts = explode(' - ', $description);
                        if (count($parts) >= 2) {
                            return [
                                'property' => trim($parts[1]),
                                'type' => trim(str_replace('*', '', $parts[0])),
                                'full' => trim($parts[1]) . ' - ' . trim(str_replace('*', '', $parts[0]))
                            ];
                        }
                    }
                }
            }
        }
        
        // Fallback to user input
        return [
            'property' => $fallbackName,
            'type' => 'Accommodation',
            'full' => $fallbackName . ' - Accommodation'
        ];
    }

    private function extractRateCode(array $responseData): ?string
    {
        // Look through legs to find the first valid Special Rate Code
        if (isset($responseData['Legs']) && is_array($responseData['Legs'])) {
            foreach ($responseData['Legs'] as $leg) {
                if (isset($leg['Special Rate Code']) && 
                    $leg['Special Rate Code'] !== 'Not_Found' &&
                    !empty($leg['Special Rate Code'])) {
                    return $leg['Special Rate Code'];
                }
            }
        }
        return null;
    }

    private function formatDateRange(string $arrival, string $departure): string
    {
        $arrivalFormatted = $this->convertDateFormat($arrival, 'd/m/Y', 'Y-m-d');
        $departureFormatted = $this->convertDateFormat($departure, 'd/m/Y', 'Y-m-d');
        
        return "{$arrivalFormatted} to {$departureFormatted}";
    }
}
