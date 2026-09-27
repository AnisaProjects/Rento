<?php
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Natural-language trip parsing for Find My Car (includes/trip-parser.php). */
final class TripParserTest extends TestCase
{
    public static function budgets(): array
    {
        return [['under Rs 8,000', 8000], ['budget 5k', 5000], ['max Rs 12000 a day', 12000], ['below 7.5k', 7500], ['family trip', null]];
    }

    #[DataProvider('budgets')]
    public function testBudget(string $text, ?int $expected): void
    {
        $this->assertSame($expected, trip_understand($text)['budget']);
    }

    public function testNamedTypeIsMarkedAsNamed(): void
    {
        $t = trip_understand('SUV for 5 people');
        $this->assertSame('SUV', $t['type']);
        $this->assertTrue($t['typeNamed']);
        $this->assertSame(5, $t['people']);
    }

    public function testInferredTypeIsNotMarkedAsNamed(): void
    {
        $t = trip_understand('mountain roads to Mustang');
        $this->assertSame('SUV', $t['type']);
        $this->assertFalse($t['typeNamed']);
        $this->assertSame('mountain', $t['tripType']);
    }

    public function testFamilyContextAndCheapDefaultBudget(): void
    {
        $this->assertContains('family', trip_understand('family trip to Pokhara')['context']);
        $this->assertSame(TRIP_BUDGET_CHEAP, trip_understand('cheap car for the city')['budget']);
    }

    public function testEmptyInputGivesNothing(): void
    {
        $t = trip_understand('');
        $this->assertNull($t['type']);
        $this->assertNull($t['budget']);
        $this->assertNull($t['people']);
    }
}
