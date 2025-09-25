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
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ]
        ]);
        
        $this->gondwanaApiUrl = 'https://dev.gondwana-collection.com/Web-Store/Rates/Rates.php';
        
        // Test Unit Type IDs provided in the assignment
        $this->unitTypeIds = [-2147483637, -2147483456];
    }

    public function fetchRates(array $bookingData): array
    {
        $results = [];

        foreach ($this->unitTypeIds as $unitTypeId) {
            try {
                // Transform the payload
                $transformedPayload = $this->transformPayload($bookingData, $unitTypeId);
                
                // Make API call to Gondwana
                $response = $this->httpClient->post($this->gondwanaApiUrl, [
                    'json' => $transformedPayload
                ]);

                $responseData = json_decode($response->getBody()->getContents(), true);
                
                // Process and format the response
                $processedResult = $this->processGondwanaResponse($responseData, $bookingData, $unitTypeId);
                $results[] = $processedResult;

            } catch (RequestException $e) {
                // Handle API errors gracefully
                $results[] = [
                    'unitTypeId' => $unitTypeId,
                    'unitName' => $bookingData['Unit Name'],
                    'error' => 'API request failed: ' . $e->getMessage(),
                    'rate' => null,
                    'dateRange' => $this->formatDateRange($bookingData['Arrival'], $bookingData['Departure']),
                    'availability' => false
                ];
            } catch (\Exception $e) {
                // Handle other errors
                $results[] = [
                    'unitTypeId' => $unitTypeId,
                    'unitName' => $bookingData['Unit Name'],
                    'error' => 'Processing error: ' . $e->getMessage(),
                    'rate' => null,
                    'dateRange' => $this->formatDateRange($bookingData['Arrival'], $bookingData['Departure']),
                    'availability' => false
                ];
            }
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
        // This will depend on the actual structure of Gondwana's API response
        // For now, we'll create a standardized response format
        
        $rate = null;
        $availability = false;
        $error = null;

        // Check if response contains rate information
        if (isset($responseData['Total Charge']) && is_numeric($responseData['Total Charge'])) {
            // Convert from cents to dollars (assuming Gondwana API returns cents)
            $rate = (float) $responseData['Total Charge'] / 100;
            $availability = $rate > 0; // Only available if there's a charge
        } elseif (isset($responseData['error'])) {
            $error = $responseData['error'];
            $availability = false;
        } elseif (isset($responseData['message'])) {
            $error = $responseData['message'];
            $availability = false;
        } else {
            // Fallback - if we get any response, consider it available even without rate
            $availability = !empty($responseData);
            $rate = null;
        }

        return [
            'unitTypeId' => $unitTypeId,
            'unitName' => $originalData['Unit Name'],
            'rate' => $rate,
            'dateRange' => $this->formatDateRange($originalData['Arrival'], $originalData['Departure']),
            'availability' => $availability,
            'occupants' => $originalData['Occupants'],
            'error' => $error,
            'rawResponse' => $responseData // Include for debugging
        ];
    }

    private function formatDateRange(string $arrival, string $departure): string
    {
        $arrivalFormatted = $this->convertDateFormat($arrival, 'd/m/Y', 'Y-m-d');
        $departureFormatted = $this->convertDateFormat($departure, 'd/m/Y', 'Y-m-d');
        
        return "{$arrivalFormatted} to {$departureFormatted}";
    }
}
