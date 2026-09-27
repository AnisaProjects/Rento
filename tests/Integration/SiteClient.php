<?php
/** Minimal HTTP client for the integration tests: cookies, CSRF tokens, timings. */
final class SiteClient
{
    private string $jar;
    public float $lastMs = 0;

    public function __construct(public string $base)
    {
        $this->base = rtrim($base, '/');
        $this->jar = tempnam(sys_get_temp_dir(), 'rento-test-');
    }

    public function __destruct()
    {
        @unlink($this->jar);
    }

    /** @return array{status:int, body:string, headers:string, location:?string} */
    public function request(string $method, string $path, array $post = []): array
    {
        $ch = curl_init($this->base . '/' . ltrim($path, '/'));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 30]);
        if ($method === 'POST') curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        $t = microtime(true);
        $raw = (string)curl_exec($ch);
        $this->lastMs = (microtime(true) - $t) * 1000;
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $size = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($raw, 0, $size);
        preg_match('/^Location:\s*(\S+)/mi', $headers, $m);
        return ['status' => $status, 'body' => substr($raw, $size), 'headers' => $headers, 'location' => $m[1] ?? null];
    }

    public function get(string $path): array { return $this->request('GET', $path); }

    public function json(string $path): array
    {
        $r = $this->get($path);
        return [$r['status'], json_decode($r['body'], true)];
    }

    public function csrf(string $path): string
    {
        preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $this->get($path)['body'], $m);
        return $m[1] ?? '';
    }
}
