<?php

namespace Gondwana\BookingApi\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Gondwana\BookingApi\Exceptions\ApiException;

class RatesService
{
    private Client $httpClient;
    private string $gondwanaApiUrl;
    private array $unitTypeIds;
    
    // Constants for repeated literals
    private const UNIT_NAME_KEY = 'Unit Name';
    private const ACCOMMODATION_SUFFIX = ' - Accommodation';
    private const DATE_FORMAT_DM_Y = 'd/m/Y';
    private const TOTAL_CHARGE_KEY = 'Total Charge';
    private const ERROR_MESSAGE_KEY = 'Error Message';
    private const SPECIAL_RATE_DESCRIPTION_KEY = 'Special Rate Description';
    private const SPECIAL_RATE_CODE_KEY = 'Special Rate Code';

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
                            throw new ApiException("Invalid JSON response from Gondwana API");
                        }
                    } else {
                        throw new ApiException("HTTP {$statusCode}: " . substr($responseBody, 0, 200), $responseBody);
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
                            'unitName' => $bookingData[self::UNIT_NAME_KEY],
                            'accommodationType' => 'Accommodation',
                            'fullName' => $bookingData[self::UNIT_NAME_KEY] . self::ACCOMMODATION_SUFFIX,
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
                            'unitName' => $bookingData[self::UNIT_NAME_KEY],
                            'accommodationType' => 'Accommodation',
                            'fullName' => $bookingData[self::UNIT_NAME_KEY] . self::ACCOMMODATION_SUFFIX,
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
        $arrival = $this->convertDateFormat($inputData['Arrival'], self::DATE_FORMAT_DM_Y, 'Y-m-d');
        $departure = $this->convertDateFormat($inputData['Departure'], self::DATE_FORMAT_DM_Y, 'Y-m-d');

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
        $locationId = null;

        // Extract basic info
        if (isset($responseData['Location ID'])) {
            $locationId = $responseData['Location ID'];
        }

        // Check if response contains rate information
        if (isset($responseData[self::TOTAL_CHARGE_KEY]) && is_numeric($responseData[self::TOTAL_CHARGE_KEY])) {
            $totalCharge = (float) $responseData[self::TOTAL_CHARGE_KEY];

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
                                if (isset($guest[self::ERROR_MESSAGE_KEY]) && !empty($guest[self::ERROR_MESSAGE_KEY])) {
                                    $error = $guest[self::ERROR_MESSAGE_KEY];
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
        $propertyName = $this->extractPropertyName($responseData, $originalData[self::UNIT_NAME_KEY]);

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
                if (
                    isset($leg[self::SPECIAL_RATE_DESCRIPTION_KEY]) &&
                    $leg[self::SPECIAL_RATE_DESCRIPTION_KEY] !== 'Not Found' &&
                    !empty($leg[self::SPECIAL_RATE_DESCRIPTION_KEY])
                ) {
                    $description = $leg[self::SPECIAL_RATE_DESCRIPTION_KEY];

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
            'full' => $fallbackName . self::ACCOMMODATION_SUFFIX
        ];
    }

    private function extractRateCode(array $responseData): ?string
    {
        // Look through legs to find the first valid Special Rate Code
        if (isset($responseData['Legs']) && is_array($responseData['Legs'])) {
            foreach ($responseData['Legs'] as $leg) {
                if (
                    isset($leg[self::SPECIAL_RATE_CODE_KEY]) &&
                    $leg[self::SPECIAL_RATE_CODE_KEY] !== 'Not_Found' &&
                    !empty($leg[self::SPECIAL_RATE_CODE_KEY])
                ) {
                    return $leg[self::SPECIAL_RATE_CODE_KEY];
                }
            }
        }
        return null;
    }

    private function formatDateRange(string $arrival, string $departure): string
    {
        $arrivalFormatted = $this->convertDateFormat($arrival, self::DATE_FORMAT_DM_Y, 'Y-m-d');
        $departureFormatted = $this->convertDateFormat($departure, self::DATE_FORMAT_DM_Y, 'Y-m-d');

        return "{$arrivalFormatted} to {$departureFormatted}";
    }
}
