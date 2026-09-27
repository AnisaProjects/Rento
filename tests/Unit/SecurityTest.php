<?php
use PHPUnit\Framework\TestCase;

/** Password hashing with MD5 upgrade, CSRF, HTML cleaning, secret encryption (includes/security.php, mailer.php). */
final class SecurityTest extends TestCase
{
    public function testBcryptHashesVerifyAndAreNotRehashed(): void
    {
        $hash = password_hash_for_storage('correct horse battery');
        $this->assertStringStartsWith('$2y$', $hash);
        $upgraded = null;
        $this->assertTrue(password_check_and_upgrade('correct horse battery', $hash, function ($h) use (&$upgraded) { $upgraded = $h; }));
        $this->assertNull($upgraded);
        $this->assertFalse(password_check_and_upgrade('wrong', $hash, fn() => null));
    }

    public function testOldMd5HashesAreAcceptedOnceAndUpgraded(): void
    {
        $upgraded = null;
        $this->assertTrue(password_check_and_upgrade('secret123', md5('secret123'), function ($h) use (&$upgraded) { $upgraded = $h; }));
        $this->assertNotNull($upgraded);
        $this->assertTrue(password_verify('secret123', $upgraded));
        $this->assertFalse(password_check_and_upgrade('other', md5('secret123'), fn() => null));
        $this->assertFalse(password_check_and_upgrade('x', null, fn() => null));
        $this->assertFalse(password_check_and_upgrade('x', '', fn() => null));
    }

    public function testCsrfTokenMustMatch(): void
    {
        $token = csrf_token();
        $this->assertSame(64, strlen($token));
        $_POST['csrf_token'] = $token;
        $this->assertTrue(csrf_is_valid());
        $_POST['csrf_token'] = str_repeat('0', 64);
        $this->assertFalse(csrf_is_valid());
        unset($_POST['csrf_token']);
        $this->assertFalse(csrf_is_valid());
    }

    public function testCmsHtmlLosesScriptsButKeepsFormatting(): void
    {
        $dirty = '<h2>Title</h2><p onclick="alert(1)">Hi <strong>there</strong> <a href="javascript:alert(1)">x</a></p><script>alert(2)</script><img src=x onerror=alert(3)>';
        $clean = clean_cms_html($dirty);
        $this->assertStringContainsString('<h2>Title</h2>', $clean);
        $this->assertStringContainsString('<strong>there</strong>', $clean);
        $this->assertStringNotContainsStringIgnoringCase('<script', $clean);
        $this->assertStringNotContainsStringIgnoringCase('onclick', $clean);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $clean);
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $clean);
    }

    public function testSecretsAreEncryptedAndDecryptOnlyWithTheKey(): void
    {
        $this->assertNotNull(mail_key(), 'APP_KEY must be set in includes/config.local.php for this test');
        $stored = mail_encrypt('smtp-password-example');
        $this->assertStringStartsWith('v1:', $stored);
        $this->assertStringNotContainsString('smtp-password-example', $stored);
        $this->assertSame('smtp-password-example', mail_decrypt($stored));
        $this->assertNotSame(mail_encrypt('same'), mail_encrypt('same'), 'a new nonce every time');
        $this->assertSame('', mail_decrypt('v1:' . base64_encode(random_bytes(40))));
        $this->assertSame('t***@example.com', mail_mask('temp@example.com'));
    }
}
