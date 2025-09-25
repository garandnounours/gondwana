<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Gondwana\BookingApi\Services\RatesService;

class RatesServiceTest extends TestCase
{
    private RatesService $ratesService;

    protected function setUp(): void
    {
        $this->ratesService = new RatesService();
    }

    public function testTransformPayloadWithValidData(): void
    {
        $inputData = [
            'Unit Name' => 'Test Property',
            'Arrival' => '01/02/2026',
            'Departure' => '05/02/2026',
            'Ages' => [30, 25]
        ];

        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->ratesService);
        $method = $reflection->getMethod('transformPayload');
        $method->setAccessible(true);

        $result = $method->invoke($this->ratesService, $inputData, -2147483637);

        $this->assertIsArray($result);
        $this->assertEquals(-2147483637, $result['Unit Type ID']);
        $this->assertEquals('2026-02-01', $result['Arrival']);
        $this->assertEquals('2026-02-05', $result['Departure']);
        $this->assertCount(2, $result['Guests']);
    }

    public function testConvertDateFormat(): void
    {
        $reflection = new \ReflectionClass($this->ratesService);
        $method = $reflection->getMethod('convertDateFormat');
        $method->setAccessible(true);

        $result = $method->invoke($this->ratesService, '01/02/2026', 'd/m/Y', 'Y-m-d');
        
        $this->assertEquals('2026-02-01', $result);
    }

    public function testDetermineAgeGroup(): void
    {
        $reflection = new \ReflectionClass($this->ratesService);
        $method = $reflection->getMethod('determineAgeGroup');
        $method->setAccessible(true);

        $this->assertEquals('Child', $method->invoke($this->ratesService, 15));
        $this->assertEquals('Adult', $method->invoke($this->ratesService, 25));
    }

    public function testFormatDateRange(): void
    {
        $reflection = new \ReflectionClass($this->ratesService);
        $method = $reflection->getMethod('formatDateRange');
        $method->setAccessible(true);

        $result = $method->invoke($this->ratesService, '01/02/2026', '05/02/2026');
        
        $this->assertEquals('2026-02-01 to 2026-02-05', $result);
    }
}
