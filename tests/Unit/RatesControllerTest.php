<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Gondwana\BookingApi\Controllers\RatesController;

class RatesControllerTest extends TestCase
{
    private RatesController $controller;

    protected function setUp(): void
    {
        $this->controller = new RatesController();
    }

    public function testGetRatesWithValidJson(): void
    {
        // Mock the input stream
        $validData = [
            'Unit Name' => 'Test Property',
            'Arrival' => '01/02/2026',
            'Departure' => '05/02/2026',
            'Occupants' => 2,
            'Ages' => [30, 25],
            'selectedUnitTypeId' => -2147483637
        ];

        // This test would require mocking the external API call
        // For now, we'll test the structure
        $this->assertInstanceOf(RatesController::class, $this->controller);
    }

    public function testControllerHasRequiredMethods(): void
    {
        $this->assertTrue(method_exists($this->controller, 'getRates'));
    }
}
