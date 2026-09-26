<?php
// Session, CSRF, password and HTML-cleaning helpers shared by the site and admin.
// Every page loads this instead of calling session_start() directly.

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('RENTOSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---------- CSRF ----------

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Hidden input to place inside every POST form.
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_is_valid(): bool
{
    $sent = $_POST['csrf_token'] ?? '';
    return is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent);
}

// Every POST request must carry a valid token; reject it before any page code runs.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !csrf_is_valid()) {
    http_response_code(400);
    $tooLarge = empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
    $message = $tooLarge
        ? 'The upload was too large. Please choose smaller files and try again.'
        : 'Your session expired or the form was not valid. Please go back, refresh the page and try again.';
    exit('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Request rejected</title><p style="font-family:sans-serif;margin:40px">' . $message
        . '</p><p style="font-family:sans-serif;margin:0 40px"><a href="javascript:history.back()">Go back</a></p>');
}

// ---------- Login sessions ----------

// Call right after a successful login so a pre-set session ID can't be reused (session fixation).
function session_login_regenerate(): void
{
    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);
}

// ---------- Passwords ----------

function password_hash_for_storage(string $plain): string
{
    // BCRYPT (60 chars) always fits the existing varchar(100) Password columns.
    return password_hash($plain, PASSWORD_BCRYPT);
}

// Checks $plain against a stored hash. Old unsalted MD5 hashes are still accepted once;
// $upgrade is then called with a new bcrypt hash so the caller can save it.
function password_check_and_upgrade(string $plain, ?string $stored, callable $upgrade): bool
{
    if ($stored === null || $stored === '') {
        return false;
    }
    if (preg_match('/^[a-f0-9]{32}$/i', $stored)) {
        if (!hash_equals(strtolower($stored), md5($plain))) {
            return false;
        }
        $upgrade(password_hash_for_storage($plain));
        return true;
    }
    if (!password_verify($plain, $stored)) {
        return false;
    }
    if (password_needs_rehash($stored, PASSWORD_BCRYPT)) {
        $upgrade(password_hash_for_storage($plain));
    }
    return true;
}

// ---------- CMS HTML ----------

// Keeps formatting in admin-edited page HTML but removes anything that can run script:
// script-like elements, on* event attributes and javascript:/vbscript:/data: URLs.
function clean_cms_html(?string $html): string
{
    if ($html === null || trim($html) === '') {
        return '';
    }
    $doc = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div id="cms-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $blocked = ['script', 'iframe', 'object', 'embed', 'applet', 'form', 'input', 'button', 'textarea', 'select', 'link', 'meta', 'base', 'style', 'frame', 'frameset'];
    $xpath = new DOMXPath($doc);
    foreach ($blocked as $tag) {
        foreach (iterator_to_array($doc->getElementsByTagName($tag)) as $node) {
            $node->parentNode->removeChild($node);
        }
    }
    foreach (iterator_to_array($xpath->query('//@*')) as $attr) {
        $name = strtolower($attr->nodeName);
        $value = strtolower(preg_replace('/[\s\x00-\x1f]+/', '', $attr->nodeValue));
        $isUrlAttr = in_array($name, ['href', 'src', 'action', 'formaction', 'xlink:href', 'background', 'poster'], true);
        if (str_starts_with($name, 'on')
            || ($isUrlAttr && preg_match('/^(javascript|vbscript|data):/', $value))
            || ($name === 'style' && (str_contains($value, 'expression(') || str_contains($value, 'javascript:')))) {
            $attr->ownerElement->removeAttributeNode($attr);
        }
    }

    $root = $doc->getElementById('cms-root');
    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }
    return $out;
}
?>