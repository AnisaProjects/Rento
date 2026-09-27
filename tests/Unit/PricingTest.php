<?php
use PHPUnit\Framework\TestCase;

/** Stored booking totals, package surcharges and market categories (includes/pricing.php, price-insights.php). */
final class PricingTest extends TestCase
{
    public function testBookingTotalUsesTheStoredQuotedPrice(): void
    {
        $b = (object)['FromDate' => '2026-10-14', 'ToDate' => '2026-10-16', 'PricePerDay' => 10000, 'quoted_price' => 36300,
            'pricing_details' => json_encode(['rental_days' => 3, 'base_total' => 30000, 'pricing' => 'smart', 'per_day' => [['price' => 12100]]])];
        $t = booking_total($b);
        $this->assertSame(36300, $t['total']);
        $this->assertSame(3, $t['rental_days']);
        $this->assertSame(30000, $t['base_total']);
        $this->assertSame('quoted', $t['source']);
    }

    public function testOldBookingsWithoutAQuoteUseDaysTimesBasePrice(): void
    {
        $b = (object)['FromDate' => '2026-10-14', 'ToDate' => '2026-10-16', 'PricePerDay' => 5000, 'quoted_price' => null];
        $t = booking_total($b);
        $this->assertSame(15000, $t['total']);
        $this->assertSame('base', $t['source']);
    }

    public function testPackageSurchargeIsRoundedToFiftyRupees(): void
    {
        $this->assertSame(36800, package_price_with(32000, 15));
        $this->assertSame(35850, package_price_with(32000, 12));
        $this->assertSame(0, package_price_with(32000, 15) % 50);
        $this->assertSame(32000, package_price_with(32000, 0));
    }

    public function testMarketCategoryNeverComparesLuxuryCarsWithOrdinaryOnes(): void
    {
        $car = fn($type, $seats = 5, $fuel = 'Petrol', $brand = 'Nissan') => (object)['CarType' => $type, 'SeatingCapacity' => $seats, 'FuelType' => $fuel, 'BrandName' => $brand];
        $this->assertSame('hatchback', market_category($car('Hatchback')));
        $this->assertSame('sedan', market_category($car('Sedan')));
        $this->assertSame('suv', market_category($car('SUV')));
        $this->assertSame('suv_7seat', market_category($car('SUV', 7)));
        $this->assertSame('suv_ev', market_category($car('SUV', 5, 'Electric')));
        $this->assertSame('suv_luxury', market_category($car('SUV', 5, 'Petrol', 'Audi')));
        $this->assertNull(market_category($car('Sedan', 5, 'Petrol', 'BMW')));
        $this->assertNull(market_category($car('Coupe', 4)));
        $this->assertNull(market_category($car('Van', 14)));
    }

    public function testQuoteReasonsAreReadableLines(): void
    {
        $quote = ['ok' => true, 'pricing' => 'smart', 'reasons' => [['label' => 'Dashain 2026', 'pct' => 12.0, 'days' => 2], ['label' => 'Weekend', 'pct' => 8.0, 'days' => 1]],
            'rental_days' => 3, 'total' => 11800, 'base_total' => 10000, 'difference' => 1800, 'capped_days' => 0];
        $lines = pricing_reason_lines($quote);
        $this->assertNotEmpty($lines);
        $this->assertStringContainsString('Dashain 2026', implode(' ', $lines));
    }
}
