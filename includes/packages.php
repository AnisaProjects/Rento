<?php
// Packages & services helpers shared by the public pages (packages.php, package-details.php)
// and the admin (manage-packages.php, package-edit.php, package-enquiries.php).
// Packages are enquiry-only: a request is stored in tblpackageenquiries, there is no payment.

const PACKAGE_PLACEHOLDER = 'assets/images/placeholder-package.jpg';
// Enquiry status => [label, admin badge variant]
const PACKAGE_ENQUIRY_STATUSES = [
    'new' => ['New', 'primary'],
    'contacted' => ['Contacted', 'info'],
    'confirmed' => ['Confirmed', 'success'],
    'cancelled' => ['Cancelled', 'danger'],
];

// Clean address of a package: package/<title-slug>-<id>.
function package_url($id, ?string $title = null): string
{
    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower((string)$title)), '-');
    return 'package/' . ($slug === '' ? '' : $slug . '-') . (int)$id;
}

// {"Sedan": 6500, ...} as an array, cheapest first; only positive prices.
function package_prices(?object $package): array
{
    $prices = json_decode((string)($package->PricesJson ?? ''), true);
    $prices = is_array($prices) ? array_filter(array_map('intval', $prices), fn($p) => $p > 0) : [];
    asort($prices);
    return $prices;
}

// [["day" => 1, "title" => "...", "text" => "..."], ...]
function package_itinerary(?object $package): array
{
    $days = json_decode((string)($package->ItineraryJson ?? ''), true);
    if (!is_array($days)) {
        return [];
    }
    return array_values(array_filter(array_map(fn($d) => [
        'day' => (int)($d['day'] ?? 0),
        'title' => trim((string)($d['title'] ?? '')),
        'text' => trim((string)($d['text'] ?? '')),
    ], $days), fn($d) => $d['title'] !== '' || $d['text'] !== ''));
}

// A one-per-line text column as a list.
function package_lines(?string $text): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\R/', (string)$text)), fn($l) => $l !== ''));
}

// Cover image path (the placeholder when missing).
function package_cover(?object $package): string
{
    $cover = (string)($package->CoverImage ?? '');
    return ($cover !== '' && is_file(__DIR__ . '/../' . $cover)) ? $cover : PACKAGE_PLACEHOLDER;
}

// Number of active packages (the nav link and home section only show when there is one).
function packages_active_count(PDO $dbh): int
{
    static $count = null;
    if ($count === null) {
        try {
            $count = (int)$dbh->query("SELECT COUNT(*) FROM tblpackages p LEFT JOIN tblpackagecategories c ON c.id = p.CategoryId
                WHERE p.IsActive = 1 AND (c.id IS NULL OR c.IsActive = 1)")->fetchColumn();
        } catch (PDOException $e) {
            $count = 0; // migration 009 not applied yet
        }
    }
    return $count;
}

// Public card for a package (list page and home page).
function package_card(object $p, string $heading = 'h3'): string
{
    $url = package_url($p->id, $p->Title);
    ob_start(); ?>
    <article class="r-package-card">
      <a class="r-package-card__media" href="<?php echo htmlentities($url); ?>" tabindex="-1" aria-hidden="true">
        <?php echo site_picture(package_cover($p), '', ['sizes' => '(min-width: 1200px) 380px, (min-width: 768px) 45vw, 100vw']); ?>
        <?php if (!empty($p->CategoryName)) { ?><span class="r-badge r-package-card__badge"><i class="fa <?php echo htmlentities($p->Icon ?? 'fa-route'); ?>" aria-hidden="true"></i><?php echo htmlentities($p->CategoryName); ?></span><?php } ?>
      </a>
      <div class="r-package-card__body">
        <<?php echo $heading; ?> class="r-package-card__title"><a href="<?php echo htmlentities($url); ?>"><?php echo htmlentities($p->Title); ?></a></<?php echo $heading; ?>>
        <?php if (!empty($p->DurationLabel)) { ?><p class="r-package-card__meta"><i class="fa fa-clock" aria-hidden="true"></i><?php echo htmlentities($p->DurationLabel); ?></p><?php } ?>
        <?php if (!empty($p->Summary)) { ?><p class="r-package-card__text"><?php echo htmlentities($p->Summary); ?></p><?php } ?>
        <div class="r-package-card__foot">
          <span class="r-package-card__price"><small>From</small> <?php echo format_price($p->StartingPrice); ?></span>
          <a class="r-btn r-btn--sm" href="<?php echo htmlentities($url); ?>">View details<span class="visually-hidden"> of <?php echo htmlentities($p->Title); ?></span></a>
        </div>
      </div>
    </article>
    <?php
    return ob_get_clean();
}

// Festival and travel-day surcharge windows for packages that opt in (FestivalPricing), for
// the next $days: [['label', 'from', 'to', 'pct'], ...]. Same percentages and cap as the car
// pricing rules (admin -> Smart Pricing, Festivals); weekends and seasons don't apply to packages.
function package_surcharge_windows(PDO $dbh, int $days = 365): array
{
    $cap = (float)setting('pricing_max_surcharge');
    $end = date('Y-m-d', strtotime("+$days days"));
    $query = $dbh->prepare("SELECT FestivalName, StartDate, EndDate, DemandLevel, Verification, TravelFrom, TravelTo, TravelPct FROM tblfestivals
        WHERE Verification <> 'guidance' AND ((EndDate >= CURDATE() AND StartDate <= :end1) OR (TravelTo >= CURDATE() AND TravelFrom <= :end2))
        ORDER BY COALESCE(TravelFrom, StartDate)");
    $query->execute([':end1' => $end, ':end2' => $end]);
    $windows = [];
    foreach ($query->fetchAll(PDO::FETCH_OBJ) as $f) {
        $provisional = festival_is_provisional($f); // not yet on the official holiday list
        $festivalPct = (float)setting($f->DemandLevel === 'high' ? 'pricing_festival_high_pct' : 'pricing_festival_medium_pct');
        if ($f->TravelFrom && $f->TravelTo && $f->TravelTo >= date('Y-m-d')) {
            $travelPct = $f->TravelPct !== null ? (float)$f->TravelPct : (float)setting('pricing_travel_days_pct');
            $windows[] = ['label' => $f->FestivalName . ': travel days', 'from' => max($f->TravelFrom, date('Y-m-d')), 'to' => $f->TravelTo, 'pct' => min($cap, $travelPct), 'provisional' => $provisional];
        }
        // Main festival days after the travel window (a travel day never gets both).
        $mainFrom = $f->TravelTo && $f->TravelTo >= $f->StartDate ? date('Y-m-d', strtotime($f->TravelTo . ' +1 day')) : $f->StartDate;
        $mainFrom = max($mainFrom, date('Y-m-d'));
        if ($f->EndDate >= $mainFrom && $festivalPct > 0) {
            $windows[] = ['label' => $f->FestivalName, 'from' => $mainFrom, 'to' => $f->EndDate, 'pct' => min($cap, $festivalPct), 'provisional' => $provisional];
        }
    }
    usort($windows, fn($a, $b) => [$a['from'], -$a['pct']] <=> [$b['from'], -$b['pct']]);
    return array_values(array_filter($windows, fn($w) => $w['pct'] > 0));
}

// A package price with a surcharge, rounded like car prices.
function package_price_with(int $price, float $pct): int
{
    $step = max(1, (int)setting('pricing_round_to'));
    return (int)(round($price * (1 + $pct / 100) / $step) * $step);
}
