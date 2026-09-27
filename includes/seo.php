<?php
// Search and sharing tags for the public pages (printed by includes/head.php).
//
// Pages may set, before including head.php:
//   $pageCanonical   clean path relative to the site root ('car/toyota-fortuner-7', 'car-listing?type=SUV');
//                    default: the current clean path without its query string
//   $pageNoindex     true for account, search-result and utility pages (robots noindex, not in the sitemap)
//   $pageImage       share image path relative to the site root (default: Site & SEO share image)
//   $pageOgType      'website' (default), 'product' or 'article'
//   $pageJsonLd      list of schema.org objects (arrays) printed as JSON-LD
//
// The absolute site address comes from admin -> Site content -> Site & SEO -> Site address; when that is
// empty (e.g. on MAMP) it is the address the page was opened at.

// Absolute base URL of the site, with a trailing slash: https://www.example.com/ or http://localhost:8888/Rento/.
function seo_base_url(): string
{
    $configured = trim(content_raw('site_url'));
    if ($configured !== '' && preg_match('#^https?://#i', $configured)) {
        return rtrim($configured, '/') . '/';
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = preg_replace('/[^a-z0-9.:\-\[\]]/i', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    // Scripts in subfolders (api/) still belong to the site root one level up.
    if (preg_match('#/(api|tools|admin|admin/includes)$#', $dir)) $dir = preg_replace('#/(api|tools|admin|admin/includes)$#', '', $dir);
    return ($https ? 'https' : 'http') . '://' . $host . ($dir === '' || $dir === '.' ? '' : $dir) . '/';
}

// Absolute URL for a path relative to the site root ('' = home).
function seo_url(string $path = ''): string
{
    return seo_base_url() . ltrim($path, '/');
}

// The current page's clean path relative to the site root, without the query string.
function seo_current_path(): string
{
    $path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $root = (string)parse_url(seo_base_url(), PHP_URL_PATH);
    $local = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';
    foreach ([$local, $root] as $prefix) {
        if ($prefix !== '/' && str_starts_with($path, $prefix)) return preg_replace('#(^|/)index\.php$#', '', substr($path, strlen($prefix)));
    }
    return ltrim(preg_replace('#(^|/)index\.php$#', '', $path), '/');
}

// JSON-LD <script> for one schema.org object.
function seo_jsonld(array $data): string
{
    return '<script type="application/ld+json">' . json_encode(['@context' => 'https://schema.org'] + $data,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . "</script>\n";
}

// Rento as an AutoRental business (a schema.org LocalBusiness), from admin -> Contact info and Site & SEO.
function seo_local_business(PDO $dbh): array
{
    $info = $dbh->query("SELECT Address, EmailId, ContactNo FROM tblcontactusinfo LIMIT 1")->fetch(PDO::FETCH_OBJ) ?: null;
    $logo = content_image('site_logo');
    $sameAs = array_values(array_filter(array_map(fn($k) => trim(content_raw($k)), ['social_facebook', 'social_instagram', 'social_twitter', 'social_youtube', 'social_linkedin']),
        fn($u) => preg_match('#^https?://#', $u)));
    $data = [
        '@type' => 'AutoRental',
        '@id' => seo_url() . '#business',
        'name' => content_raw('site_name'),
        'url' => seo_url(),
        'description' => content_raw('seo_default_description'),
        'image' => seo_url(content_image('seo_share_image')),
        'priceRange' => 'Rs',
        'currenciesAccepted' => 'NPR',
        'areaServed' => ['@type' => 'Country', 'name' => 'Nepal'],
    ];
    if ($logo !== '') $data['logo'] = seo_url($logo);
    if ($info && trim((string)$info->Address) !== '') {
        $data['address'] = ['@type' => 'PostalAddress', 'streetAddress' => trim((string)$info->Address), 'addressCountry' => 'NP'];
    }
    if ($info && trim((string)$info->EmailId) !== '') $data['email'] = trim((string)$info->EmailId);
    if ($info && trim((string)$info->ContactNo) !== '') $data['telephone'] = trim((string)$info->ContactNo);
    if ($sameAs) $data['sameAs'] = $sameAs;
    return $data;
}

// Breadcrumb trail: [[name, path], ...] from the home page.
function seo_breadcrumbs(array $trail): array
{
    $items = [];
    foreach (array_merge([[content_raw('site_name'), '']], $trail) as $i => [$name, $path]) {
        $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => seo_url($path)];
    }
    return ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
}

// All <head> tags for search and sharing.
function seo_head_tags(PDO $dbh, string $title, string $description, array $page): string
{
    $canonical = seo_url($page['canonical'] ?? seo_current_path());
    $image = seo_url($page['image'] ?? content_image('seo_share_image'));
    $site = content_raw('site_name');
    $title = $title . ' | ' . $site; // as in <title>
    $out = '';
    if (!empty($page['noindex'])) {
        $out .= '<meta name="robots" content="noindex, follow">' . "\n";
    }
    $out .= '<link rel="canonical" href="' . htmlentities($canonical) . '">' . "\n";
    $og = [
        'og:site_name' => $site, 'og:type' => $page['ogType'] ?? 'website', 'og:title' => $title, 'og:description' => $description,
        'og:url' => $canonical, 'og:image' => $image, 'og:image:alt' => $title, 'og:locale' => 'en_US',
    ];
    if (($page['image'] ?? null) === null) { $og['og:image:width'] = '1200'; $og['og:image:height'] = '630'; }
    foreach ($og as $property => $value) {
        $out .= '<meta property="' . $property . '" content="' . htmlentities($value) . '">' . "\n";
    }
    $out .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
    $handle = trim(content_raw('seo_twitter'));
    if (preg_match('/^@?[A-Za-z0-9_]{1,15}$/', $handle)) {
        $out .= '<meta name="twitter:site" content="@' . htmlentities(ltrim($handle, '@')) . '">' . "\n";
    }
    foreach (['twitter:title' => $title, 'twitter:description' => $description, 'twitter:image' => $image] as $name => $value) {
        $out .= '<meta name="' . $name . '" content="' . htmlentities($value) . '">' . "\n";
    }
    foreach ($page['jsonld'] ?? [] as $data) {
        $out .= seo_jsonld($data);
    }
    return $out;
}
