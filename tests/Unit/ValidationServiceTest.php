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

    public function testValidateBookingRequestWithValidData(): void
    {
        $validData = [
            'Unit Name' => 'Test Property',
            'Arrival' => '01/02/2026',
            'Departure' => '05/02/2026',
            'Occupants' => 2,
            'Ages' => [30, 25]
        ];

        $result = $this->validationService->validateBookingRequest($validData);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidateBookingRequestWithMissingUnitName(): void
    {
        $invalidData = [
            'Arrival' => '01/02/2026',
            'Departure' => '05/02/2026',
            'Occupants' => 2,
            'Ages' => [30, 25]
        ];

        $result = $this->validationService->validateBookingRequest($invalidData);

        $this->assertFalse($result['valid']);
        $this->assertContains('Unit Name is required and must be a non-empty string', $result['errors']);
    }

    public function testValidateBookingRequestWithInvalidDate(): void
    {
        $invalidData = [
            'Unit Name' => 'Test Property',
            'Arrival' => 'invalid-date',
            'Departure' => '05/02/2026',
            'Occupants' => 2,
            'Ages' => [30, 25]
        ];

        $result = $this->validationService->validateBookingRequest($invalidData);

        $this->assertFalse($result['valid']);
        $this->assertContains('Arrival date is required and must be in dd/mm/yyyy format', $result['errors']);
    }

    public function testValidateBookingRequestWithInvalidOccupants(): void
    {
        $invalidData = [
            'Unit Name' => 'Test Property',
            'Arrival' => '01/02/2026',
            'Departure' => '05/02/2026',
            'Occupants' => 0,
            'Ages' => [30, 25]
        ];

        $result = $this->validationService->validateBookingRequest($invalidData);

        $this->assertFalse($result['valid']);
        $this->assertContains('Occupants is required and must be a positive integer', $result['errors']);
    }

    public function testValidateBookingRequestWithMismatchedAgesAndOccupants(): void
    {
        $invalidData = [
            'Unit Name' => 'Test Property',
            'Arrival' => '01/02/2026',
            'Departure' => '05/02/2026',
            'Occupants' => 2,
            'Ages' => [30, 25, 20] // 3 ages but 2 occupants
        ];

        $result = $this->validationService->validateBookingRequest($invalidData);

        $this->assertFalse($result['valid']);
        $this->assertContains('Number of ages must match number of occupants', $result['errors']);
    }
}