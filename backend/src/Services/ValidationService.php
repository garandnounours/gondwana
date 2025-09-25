<?php

namespace Gondwana\BookingApi\Services;

class ValidationService
{
    // Constants for repeated literals
    private const UNIT_NAME_KEY = self::UNIT_NAME_KEY;
    private const DATE_FORMAT_DM_Y = self::DATE_FORMAT_DM_Y;
    
    public function validateBookingRequest(array $data): array
    {
        $errors = [];

        // Validate Unit Name
        if (!isset($data[self::UNIT_NAME_KEY]) || !is_string($data[self::UNIT_NAME_KEY]) || empty(trim($data[self::UNIT_NAME_KEY]))) {
            $errors[] = 'Unit Name is required and must be a non-empty string';
        }

        // Validate Arrival date
        if (!isset($data['Arrival']) || !$this->isValidDateFormat($data['Arrival'], self::DATE_FORMAT_DM_Y)) {
            $errors[] = 'Arrival date is required and must be in dd/mm/yyyy format';
        }

        // Validate Departure date
        if (!isset($data['Departure']) || !$this->isValidDateFormat($data['Departure'], self::DATE_FORMAT_DM_Y)) {
            $errors[] = 'Departure date is required and must be in dd/mm/yyyy format';
        }

        // Validate date logic (departure after arrival)
        if (isset($data['Arrival']) && isset($data['Departure'])) {
            $arrival = $this->parseDate($data['Arrival'], self::DATE_FORMAT_DM_Y);
            $departure = $this->parseDate($data['Departure'], self::DATE_FORMAT_DM_Y);

            if ($arrival && $departure && $departure <= $arrival) {
                $errors[] = 'Departure date must be after arrival date';
            }
        }

        // Validate Occupants
        if (!isset($data['Occupants']) || !is_int($data['Occupants']) || $data['Occupants'] < 1) {
            $errors[] = 'Occupants is required and must be a positive integer';
        }

        // Validate Ages array
        if (!isset($data['Ages']) || !is_array($data['Ages'])) {
            $errors[] = 'Ages is required and must be an array';
        } elseif (count($data['Ages']) !== $data['Occupants']) {
            $errors[] = 'Number of ages must match number of occupants';
        } else {
            foreach ($data['Ages'] as $age) {
                if (!is_int($age) || $age < 0 || $age > 120) {
                    $errors[] = 'All ages must be integers between 0 and 120';
                    break;
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    private function isValidDateFormat(string $date, string $format): bool
    {
        $dateTime = \DateTime::createFromFormat($format, $date);
        return $dateTime && $dateTime->format($format) === $date;
    }

    private function parseDate(string $date, string $format): ?\DateTime
    {
        $dateTime = \DateTime::createFromFormat($format, $date);
        return $dateTime ?: null;
    }
}
