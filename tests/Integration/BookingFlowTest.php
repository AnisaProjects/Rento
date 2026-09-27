<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../admin/includes/bookings.php';

/**
 * Booking end to end: log in, book, stored quoted_price = the API quote, invoice shows it, the admin confirms
 * (admin_booking_set_status, as the Confirm button does) and the dashboard revenue rises by exactly that amount.
 * Uses a temporary customer account with a random password; everything is deleted afterwards.
 */
final class BookingFlowTest extends TestCase
{
    private const EMAIL = 'phpunit-temp@example.com';
    private const CAR = 4; // Nissan Kicks
    private SiteClient $http;
    private string $password;

    protected function setUp(): void
    {
        global $dbh;
        $this->cleanup();
        $this->password = bin2hex(random_bytes(12));
        $dbh->prepare("INSERT INTO tblusers (FullName, EmailId, Password, ContactNo) VALUES ('PHPUnit Temp', :e, :p, '9800000000')")
            ->execute([':e' => self::EMAIL, ':p' => password_hash_for_storage($this->password)]);
        $this->http = new SiteClient(getenv('RENTO_TEST_URL'));
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        global $dbh;
        $uid = $dbh->query("SELECT id FROM tblusers WHERE EmailId = " . $dbh->quote(self::EMAIL))->fetchColumn();
        $dbh->prepare("DELETE FROM tblbooking WHERE userEmail = :e")->execute([':e' => self::EMAIL]);
        if ($uid) $dbh->prepare("DELETE FROM tblpasswordresets WHERE AccountType = 'user' AND UserId = :u")->execute([':u' => $uid]);
        $dbh->prepare("DELETE FROM tblusers WHERE EmailId = :e")->execute([':e' => self::EMAIL]);
        $dbh->exec("DELETE FROM tblratelimits WHERE Email = " . $dbh->quote(self::EMAIL) . " OR (Action LIKE 'api_%' AND IpAddress IN ('127.0.0.1', '::1'))");
    }

    private function revenue(): int
    {
        global $dbh;
        return (int)$dbh->query("SELECT COALESCE(SUM(" . booking_total_sql() . "), 0) FROM tblbooking b JOIN tblvehicles v ON v.id = b.VehicleId WHERE b.Status = 1")->fetchColumn();
    }

    /** Two consecutive free days inside the forecast. */
    private function freeDates(): array
    {
        global $dbh;
        $days = $dbh->query("SELECT ForecastDate FROM tbl_price_forecast WHERE VehicleId = " . self::CAR . " AND IsAvailable = 1 AND ForecastDate > CURDATE() + INTERVAL 5 DAY ORDER BY ForecastDate")->fetchAll(PDO::FETCH_COLUMN);
        for ($i = 0; $i + 1 < count($days); $i++) {
            if (strtotime($days[$i + 1]) - strtotime($days[$i]) === 86400 && vehicle_is_free($dbh, self::CAR, $days[$i], $days[$i + 1])) return [$days[$i], $days[$i + 1]];
        }
        $this->fail('no two free days in the forecast');
    }

    private function login(): void
    {
        $token = $this->http->csrf('');
        $r = $this->http->request('POST', '', ['email' => self::EMAIL, 'password' => $this->password, 'login' => 'Login', 'csrf_token' => $token]);
        $this->assertSame(303, $r['status']);
        $this->assertSame(200, $this->http->get('my-booking')['status'], 'logged in');
    }

    private function book(string $from, string $to, array $extra = [], bool $withToken = true): array
    {
        $token = $withToken ? $this->http->csrf('car/nissan-kicks-' . self::CAR) : '';
        return $this->http->request('POST', 'car/nissan-kicks-' . self::CAR, $extra + ['fromdate' => $from, 'todate' => $to,
            'message' => 'PHPUnit integration test', 'submit' => 'Book Now', 'csrf_token' => $token]);
    }

    private function bookings(): array
    {
        global $dbh;
        $q = $dbh->prepare("SELECT * FROM tblbooking WHERE userEmail = :e ORDER BY id");
        $q->execute([':e' => self::EMAIL]);
        return $q->fetchAll(PDO::FETCH_OBJ);
    }

    public function testBookingStoredPriceInvoiceAndRevenueAgree(): void
    {
        [$from, $to] = $this->freeDates();
        $this->login();
        [, $quote] = $this->http->json('api/price-quote.php?car=' . self::CAR . "&from=$from&to=$to");
        $revenueBefore = $this->revenue();

        // The browser may send anything; the server works the price out itself.
        $r = $this->book($from, $to, ['price' => '1', 'total' => '1', 'quoted_price' => '1']);
        $this->assertSame(303, $r['status']);
        $this->assertStringEndsWith('my-booking', (string)$r['location']);
        $rows = $this->bookings();
        $this->assertCount(1, $rows);
        $b = $rows[0];
        $this->assertSame($quote['total'], (int)$b->quoted_price, 'stored price = API quote, not the posted value');
        $details = json_decode($b->pricing_details, true);
        $this->assertSame(2, $details['rental_days']);
        $this->assertCount(2, $details['per_day']);
        $this->assertSame((int)$b->quoted_price, array_sum(array_column($details['per_day'], 'price')));

        $invoice = $this->http->get('invoice?booking=' . $b->BookingNumber);
        $this->assertSame(200, $invoice['status']);
        $this->assertStringContainsString(htmlentities(format_price((int)$b->quoted_price)), $invoice['body']);

        [$type] = admin_booking_set_status($GLOBALS['dbh'], (int)$b->id, 1);
        $this->assertSame('success', $type);
        $this->assertSame($revenueBefore + (int)$b->quoted_price, $this->revenue());

        // Overlapping request for the same car and dates is refused.
        $this->book($from, $to);
        $this->assertCount(1, $this->bookings());
    }

    public function testBookingWithoutCsrfTokenIsRejected(): void
    {
        [$from, $to] = $this->freeDates();
        $this->login();
        $r = $this->book($from, $to, [], false);
        $this->assertNotSame(200, $r['status'] === 303 && str_ends_with((string)$r['location'], 'my-booking') ? 200 : 0);
        $this->assertCount(0, $this->bookings());
    }

    public function testInvoiceIsOnlyForTheOwner(): void
    {
        global $dbh;
        $other = $dbh->query("SELECT BookingNumber FROM tblbooking WHERE userEmail <> " . $dbh->quote(self::EMAIL) . " LIMIT 1")->fetchColumn();
        $this->assertSame(303, (new SiteClient(getenv('RENTO_TEST_URL')))->get('invoice?booking=' . $other)['status'], 'logged out: sent to log in');
        $this->login();
        $this->assertSame(404, $this->http->get('invoice?booking=' . $other)['status'], "someone else's booking");
    }
}
