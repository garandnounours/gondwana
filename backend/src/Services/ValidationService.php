<?php

namespace Gondwana\BookingApi\Services;

class ValidationService
{
    // Constants for repeated literals
    private const UNIT_NAME_KEY = 'Unit Name';
    private const DATE_FORMAT_DM_Y = 'd/m/Y';
    
    public function validateBookingRequest(array $data): array
    {
        $errors = [];

        $this->validateUnitName($data, $errors);
        $this->validateDates($data, $errors);
        $this->validateOccupants($data, $errors);
        $this->validateAges($data, $errors);

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    private function validateUnitName(array $data, array &$errors): void
    {
        if (!isset($data[self::UNIT_NAME_KEY]) || !is_string($data[self::UNIT_NAME_KEY]) || empty(trim($data[self::UNIT_NAME_KEY]))) {
            $errors[] = 'Unit Name is required and must be a non-empty string';
        }
    }

    private function validateDates(array $data, array &$errors): void
    {
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
    }

    private function validateOccupants(array $data, array &$errors): void
    {
        if (!isset($data['Occupants']) || !is_int($data['Occupants']) || $data['Occupants'] < 1) {
            $errors[] = 'Occupants is required and must be a positive integer';
        }
    }

    private function validateAges(array $data, array &$errors): void
    {
        if (!isset($data['Ages']) || !is_array($data['Ages'])) {
            $errors[] = 'Ages is required and must be an array';
            return;
        }

        if (count($data['Ages']) !== $data['Occupants']) {
            $errors[] = 'Number of ages must match number of occupants';
            return;
        }

        foreach ($data['Ages'] as $age) {
            if (!is_int($age) || $age < 0 || $age > 120) {
                $errors[] = 'All ages must be integers between 0 and 120';
                break;
            }
        }
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
