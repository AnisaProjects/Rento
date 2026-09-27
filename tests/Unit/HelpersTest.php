<?php
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Day-count rule, money formatting, clean URLs (includes/helpers.php, includes/packages.php). */
final class HelpersTest extends TestCase
{
    public static function days(): array
    {
        return [
            'same day counts as one' => ['2026-10-10', '2026-10-10', 1],
            'inclusive range' => ['2026-10-10', '2026-10-12', 3],
            'across a month' => ['2026-10-30', '2026-11-02', 4],
            'leap day' => ['2028-02-28', '2028-03-01', 3],
            'return before pickup' => ['2026-10-12', '2026-10-10', 0],
            'invalid date' => ['2026-13-40', '2026-10-10', 0],
            'empty' => ['', '2026-10-10', 0],
            'datetime values' => ['2026-10-10 09:00:00', '2026-10-11 18:00:00', 2],
        ];
    }

    #[DataProvider('days')]
    public function testRentalDaysIsInclusive(string $from, string $to, int $expected): void
    {
        $this->assertSame($expected, rental_days($from, $to));
    }

    public function testRentalDaysSqlMatchesThePhpRule(): void
    {
        global $dbh;
        foreach ([['2026-10-10', '2026-10-10'], ['2026-10-10', '2026-10-12'], ['2026-10-30', '2026-11-02']] as [$a, $b]) {
            $sql = "SELECT " . rental_days_sql(':a', ':b');
            $q = $dbh->prepare(str_replace([':a', ':b'], ["'$a'", "'$b'"], $sql));
            $q->execute();
            $this->assertSame(rental_days($a, $b), (int)$q->fetchColumn(), "$a..$b");
        }
    }

    public function testPricesAreRupeesWithThousandsSeparators(): void
    {
        $this->assertSame('Rs 10,850', format_price(10850));
        $this->assertSame('Rs 0', format_price(0));
        $this->assertStringContainsString('Rs 5,000', format_price_per_day(5000));
        $this->assertStringContainsString('day', format_price_per_day(5000));
    }

    public function testCleanUrls(): void
    {
        $this->assertSame('car/toyota-fortuner-7', vehicle_url(7, 'Toyota', 'Fortuner'));
        $this->assertSame('car/maruti-suzuki-wagon-r-1', vehicle_url(1, 'Maruti', 'Maruti Suzuki Wagon R'));
        $this->assertSame('package/kathmandu-pokhara-lakeside-getaway-9', package_url(9, 'Kathmandu-Pokhara Lakeside Getaway'));
        $this->assertSame('Toyota Fortuner', vehicle_display_name('Toyota', 'Fortuner'));
        $this->assertSame('Maruti Suzuki Wagon R', vehicle_display_name('Maruti', 'Maruti Suzuki Wagon R'));
    }
}
