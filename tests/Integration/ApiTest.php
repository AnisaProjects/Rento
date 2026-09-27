<?php
use PHPUnit\Framework\TestCase;

/** The three JSON endpoints over HTTP: happy paths, validation, method and rate limits. */
final class ApiTest extends TestCase
{
    private SiteClient $http;

    protected function setUp(): void
    {
        $this->http = new SiteClient(getenv('RENTO_TEST_URL'));
    }

    protected function tearDown(): void
    {
        global $dbh;
        $dbh->exec("DELETE FROM tblratelimits WHERE Action LIKE 'api_%' AND IpAddress IN ('127.0.0.1', '::1')");
    }

    private function forecastDays(int $car, int $offset, int $n): array
    {
        global $dbh;
        $q = $dbh->prepare("SELECT ForecastDate, Price FROM tbl_price_forecast WHERE VehicleId = :v ORDER BY ForecastDate LIMIT $n OFFSET $offset");
        $q->execute([':v' => $car]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function testQuoteTotalEqualsTheForecastDays(): void
    {
        $days = $this->forecastDays(7, 12, 3);
        [$status, $q] = $this->http->json("api/price-quote.php?car=7&from={$days[0]['ForecastDate']}&to={$days[2]['ForecastDate']}");
        $this->assertSame(200, $status);
        $this->assertTrue($q['ok']);
        $this->assertSame('NPR', $q['currency']);
        $this->assertSame(3, $q['rental_days']);
        $this->assertSame(array_sum(array_column($days, 'Price')), $q['total']);
        $this->assertSame($q['total'], array_sum(array_column($q['per_day'], 'price')));
        $this->assertNotEmpty($q['reasons'] ?? [], 'smart prices come with reasons');
    }

    public function testQuoteRejectsBadInput(): void
    {
        [$s, $e] = $this->http->json('api/price-quote.php?car=7&from=2026-13-01&to=2026-13-02');
        $this->assertSame(400, $s);
        $this->assertFalse($e['ok']);
        [$s] = $this->http->json('api/price-quote.php?car=7&from=2026-11-10&to=2026-11-01');
        $this->assertSame(400, $s);
        [$s] = $this->http->json('api/price-quote.php?car=99999&from=2026-11-01&to=2026-11-02');
        $this->assertSame(404, $s);
        [$s] = $this->http->json('api/price-quote.php?car=29&from=2026-11-01&to=2026-11-02'); // inactive (and driver-only)
        $this->assertContains($s, [404, 409]);
    }

    public function testOnlyGetIsAllowed(): void
    {
        $r = $this->http->request('POST', 'api/price-quote.php?car=7', ['from' => '2026-11-01']);
        $this->assertSame(405, $r['status']);
        $this->assertMatchesRegularExpression('/^Allow:\s*GET/mi', $r['headers']);
    }

    public function testCalendarListsTheRestOfTheMonth(): void
    {
        $month = date('Y-m', strtotime('first day of next month'));
        [$s, $c] = $this->http->json("api/price-calendar.php?car=7&month=$month");
        $this->assertSame(200, $s);
        $this->assertCount((int)date('t', strtotime("$month-01")), $c['days']);
        foreach ($c['days'] as $d) {
            $this->assertArrayHasKey('price', $d);
            $this->assertArrayHasKey('available', $d);
            $this->assertIsArray($d['reasons']);
        }
    }

    public function testRecommendReturnsRankedMatchesWithReasons(): void
    {
        [$s, $r] = $this->http->json('api/recommend.php?trip=' . rawurlencode('mountain roads to Mustang, 5 people'));
        $this->assertSame(200, $s);
        $this->assertNotEmpty($r['matches']);
        $this->assertSame(7, $r['matches'][0]['car']['id'], 'the rugged Fortuner ranks first');
        $pct = array_column($r['matches'], 'match_percent');
        $sorted = $pct;
        rsort($sorted);
        $this->assertSame($sorted, $pct);
        foreach ($r['matches'] as $m) {
            $this->assertGreaterThanOrEqual(40, $m['match_percent']);
            $this->assertGreaterThanOrEqual(5, $m['car']['seats']);
            $this->assertNotEmpty($m['reasons']);
        }
        [$s] = $this->http->json('api/recommend.php');
        $this->assertSame(400, $s);
        [$s] = $this->http->json('api/recommend.php?budget=abc');
        $this->assertSame(400, $s);
    }

    public function testRateLimitAnswers429(): void
    {
        global $dbh;
        $ins = $dbh->prepare("INSERT INTO tblratelimits (Action, IpAddress) VALUES ('api_calendar', :ip)");
        foreach (['127.0.0.1', '::1'] as $ip) for ($i = 0; $i < 120; $i++) $ins->execute([':ip' => $ip]);
        $r = $this->http->get('api/price-calendar.php?car=7&month=' . date('Y-m'));
        $this->assertSame(429, $r['status']);
        $this->assertMatchesRegularExpression('/^Retry-After:\s*\d+/mi', $r['headers']);
    }
}
