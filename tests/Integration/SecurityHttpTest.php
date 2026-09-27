<?php
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Web-server level protections (.htaccess) and CSRF on login, over HTTP. */
final class SecurityHttpTest extends TestCase
{
    public static function protectedPaths(): array
    {
        return array_map(fn($p) => [$p], ['includes/config.php', 'includes/config.local.php', 'database/migrations/017_phase5_data_update.sql',
            'ml/pricing_rules.py', 'ml/models/demand_model.pkl', 'python/sajilo_vehicle_dataset.csv', 'logs/forecast.log', 'docs/decisions.md',
            'vendor/autoload.php', 'composer.json', '.git/HEAD', 'tools/run-forecast.php', 'phpunit.xml', 'tests/bootstrap.php']);
    }

    #[DataProvider('protectedPaths')]
    public function testInternalFileIsNotServed(string $path): void
    {
        $status = (new SiteClient(getenv('RENTO_TEST_URL')))->get($path)['status'];
        $this->assertContains($status, [403, 404], "$path returned $status");
    }

    public function testLoginWithoutCsrfTokenDoesNotLogIn(): void
    {
        $http = new SiteClient(getenv('RENTO_TEST_URL'));
        $http->request('POST', '', ['email' => 'nobody@example.com', 'password' => 'x', 'login' => 'Login']);
        $this->assertSame(303, $http->get('my-booking')['status'], 'still logged out');
    }
}
