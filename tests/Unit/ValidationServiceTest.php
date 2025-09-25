<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Gondwana\BookingApi\Services\ValidationService;

class ValidationServiceTest extends TestCase
{
    private ValidationService $validationService;

    protected function setUp(): void
    {
        $this->validationService = new ValidationService();
    }

    public function testValidBookingRequest(): void
    {
        $validData = [
            'Unit Name' => 'Test Lodge',
            'Arrival' => '01/12/2025',
            'Departure' => '05/12/2025',
            'Occupants' => 2,
            'Ages' => [30, 25]
        ];

        $result = $this->validationService->validateBookingRequest($validData);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testInvalidUnitName(): void
    {
        $invalidData = [
            'Unit Name' => '',
            'Arrival' => '01/12/2025',
            'Departure' => '05/12/2025',
            'Occupants' => 2,
            'Ages' => [30, 25]
        ];

        $result = $this->validationService->validateBookingRequest($invalidData);

        $this->assertFalse($result['valid']);
        $this->assertContains('Unit Name is required and must be a non-empty string', $result['errors']);
    }

    public function testInvalidDateFormat(): void
    {
        $invalidData = [
            'Unit Name' => 'Test Lodge',
            'Arrival' => '2025-12-01',  // Wrong format
            'Departure' => '05/12/2025',
            'Occupants' => 2,
            'Ages' => [30, 25]
        ];

        $result = $this->validationService->validateBookingRequest($invalidData);

        $this->assertFalse($result['valid']);
        $this->assertContains('Arrival date is required and must be in dd/mm/yyyy format', $result['errors']);
    }

    public function testDepartureBeforeArrival(): void
    {
        $invalidData = [
            'Unit Name' => 'Test Lodge',
            'Arrival' => '05/12/2025',
            'Departure' => '01/12/2025',  // Before arrival
            'Occupants' => 2,
            'Ages' => [30, 25]
        ];

        $result = $this->validationService->validateBookingRequest($invalidData);

        $this->assertFalse($result['valid']);
        $this->assertContains('Departure date must be after arrival date', $result['errors']);
    }

    public function testMismatchedAgesAndOccupants(): void
    {
        $invalidData = [
            'Unit Name' => 'Test Lodge',
            'Arrival' => '01/12/2025',
            'Departure' => '05/12/2025',
            'Occupants' => 3,
            'Ages' => [30, 25]  // Only 2 ages for 3 occupants
        ];

        $result = $this->validationService->validateBookingRequest($invalidData);

        $this->assertFalse($result['valid']);
        $this->assertContains('Number of ages must match number of occupants', $result['errors']);
    }

    public function testInvalidAge(): void
    {
        $invalidData = [
            'Unit Name' => 'Test Lodge',
            'Arrival' => '01/12/2025',
            'Departure' => '05/12/2025',
            'Occupants' => 2,
            'Ages' => [30, 150]  // Invalid age
        ];

        $result = $this->validationService->validateBookingRequest($invalidData);

        $this->assertFalse($result['valid']);
        $this->assertContains('All ages must be integers between 0 and 120', $result['errors']);
    }
}
