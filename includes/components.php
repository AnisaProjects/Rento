<?php
// Reusable page pieces for the redesigned public pages (styles in assets/css/app.css).

// One car in a grid: photo, brand, name, key specs, daily price. The whole card links to the car.
//   $car needs: id, BrandName, VehiclesTitle, Vimage1, FuelType, SeatingCapacity, ModelYear, PricePerDay
//   $opts: 'badge' => text shown on the photo, 'sizes' => image sizes attribute, 'heading' => h2|h3,
//          'reasons' => short "why this car" lines shown under the specs
function car_card(object $car, array $opts = []): string
{
    $name = vehicle_display_name($car->BrandName ?? '', $car->VehiclesTitle ?? '');
    $url = vehicle_url($car->id, $car->BrandName ?? '', $car->VehiclesTitle ?? '');
    $heading = $opts['heading'] ?? 'h3';
    $specs = [];
    if (!empty($car->FuelType)) {
        $specs[] = ['fa-gas-pump', $car->FuelType];
    }
    if (!empty($car->SeatingCapacity)) {
        $specs[] = ['fa-user-group', $car->SeatingCapacity . ' seats'];
    }
    if (!empty($car->ModelYear)) {
        $specs[] = ['fa-calendar', $car->ModelYear];
    }

    ob_start(); ?>
    <article class="r-car-card">
      <div class="r-car-card__media">
        <?php echo vehicle_image($car->Vimage1 ?? '', $name, ['sizes' => $opts['sizes'] ?? '(min-width: 1200px) 400px, (min-width: 768px) 50vw, 100vw']); ?>
        <?php if (!empty($opts['badge']) || !empty($car->CarType) || !empty($car->DriverOnly)) { ?>
          <div class="r-car-card__badges">
            <?php if (!empty($opts['badge'])) { ?><span class="r-badge r-badge--primary"><?php echo htmlentities($opts['badge']); ?></span><?php } ?>
            <?php if (!empty($car->CarType)) { ?><span class="r-badge r-badge--dark"><?php echo htmlentities($car->CarType); ?></span><?php } ?>
            <?php if (!empty($car->DriverOnly)) { ?><span class="r-badge r-badge--dark"><i class="fa fa-id-card" aria-hidden="true"></i> With driver</span><?php } ?>
          </div>
        <?php } ?>
      </div>
      <div class="r-car-card__body">
        <div class="r-car-card__brand"><?php echo htmlentities($car->BrandName ?? ''); ?></div>
        <<?php echo $heading; ?> class="r-car-card__title"><a href="<?php echo htmlentities($url); ?>"><?php echo htmlentities($car->VehiclesTitle ?? ''); ?></a></<?php echo $heading; ?>>
        <?php if ($specs) { ?>
          <ul class="r-car-card__specs">
            <?php foreach ($specs as [$icon, $label]) { ?>
              <li><i class="fa <?php echo $icon; ?>" aria-hidden="true"></i><?php echo htmlentities((string)$label); ?></li>
            <?php } ?>
          </ul>
        <?php } ?>
        <?php if (!empty($opts['reasons'])) { ?>
          <ul class="r-car-card__reasons">
            <?php foreach ($opts['reasons'] as $reason) { ?>
              <li><i class="fa fa-check" aria-hidden="true"></i><?php echo htmlentities($reason); ?></li>
            <?php } ?>
          </ul>
        <?php } ?>
        <div class="r-car-card__footer">
          <div class="r-price"><?php echo format_price($car->PricePerDay ?? 0); ?><small>/day</small></div>
          <span class="r-car-card__cta" aria-hidden="true">Details <i class="fa fa-arrow-right"></i></span>
        </div>
      </div>
    </article>
    <?php
    return ob_get_clean();
}

// Dark title band at the top of inner pages.
//   $crumbs: [[label, url], ...] between Home and the current page (optional)
//   $image:  name of a photo in assets/images (WebP + JPG) shown behind the title (optional)
function page_hero(string $title, string $lead = '', array $crumbs = [], string $image = '', string $extraHtml = ''): string
{
    ob_start(); ?>
    <section class="r-page-hero<?php echo $image !== '' ? ' r-page-hero--image' : ''; ?>">
      <?php if ($image !== '' && str_contains($image, '/')) {
        // A site path such as assets/images/content/x.jpg (admin -> Site content).
        echo site_picture($image, '', ['class' => 'r-page-hero__bg', 'priority' => true]);
      } elseif ($image !== '') { ?>
        <picture class="r-page-hero__bg">
          <source type="image/webp" srcset="assets/images/<?php echo htmlentities($image); ?>.webp">
          <img src="assets/images/<?php echo htmlentities($image); ?>.jpg" alt="">
        </picture>
      <?php } ?>
      <div class="container">
        <nav aria-label="Breadcrumb">
          <ol class="r-breadcrumb">
            <li><a href="./">Home</a></li>
            <?php foreach ($crumbs as [$label, $url]) { ?>
              <li><a href="<?php echo htmlentities($url); ?>"><?php echo htmlentities($label); ?></a></li>
            <?php } ?>
            <li aria-current="page"><?php echo htmlentities($title); ?></li>
          </ol>
        </nav>
        <h1><?php echo htmlentities($title); ?></h1>
        <?php if ($lead !== '') { ?><p><?php echo htmlentities($lead); ?></p><?php } ?>
        <?php echo $extraHtml; // trusted markup built by the page ?>
      </div>
    </section>
    <?php
    return ob_get_clean();
}

// Friendly "nothing here yet" block with an optional button.
function empty_state(string $icon, string $title, string $text, string $buttonLabel = '', string $buttonUrl = ''): string
{
    $button = $buttonLabel !== ''
        ? '<a class="r-btn" href="' . htmlentities($buttonUrl) . '">' . htmlentities($buttonLabel) . '</a>'
        : '';
    return '<div class="r-empty"><span class="r-empty__icon"><i class="fa ' . htmlentities($icon) . '" aria-hidden="true"></i></span>'
        . '<h3>' . htmlentities($title) . '</h3><p>' . htmlentities($text) . '</p>' . $button . '</div>';
}

// Account area: hero, profile card and account navigation around the page content.
//   echo account_start($dbh, 'my-booking', 'My bookings'); ...content... echo account_end();
function account_start(PDO $dbh, string $active, string $title, string $lead = ''): string
{
    $userQuery = $dbh->prepare("SELECT FullName, EmailId, RegDate FROM tblusers WHERE EmailId = :email");
    $userQuery->bindParam(':email', $_SESSION['login'], PDO::PARAM_STR);
    $userQuery->execute();
    $user = $userQuery->fetch(PDO::FETCH_OBJ) ?: (object)['FullName' => '', 'EmailId' => $_SESSION['login'], 'RegDate' => null];
    $links = [
        'profile' => ['fa-user', 'Profile'],
        'my-booking' => ['fa-calendar-check', 'My bookings'],
        'my-testimonials' => ['fa-comment-dots', 'My testimonials'],
        'post-testimonial' => ['fa-pen-to-square', 'Write a testimonial'],
        'update-password' => ['fa-key', 'Change password'],
    ];
    ob_start();
    echo page_hero($title, $lead, [['My account', 'profile']]); ?>
    <section class="r-section r-section--muted r-account">
      <div class="container">
        <div class="r-account__layout">
          <aside class="r-account__aside">
            <div class="r-account__user">
              <span class="r-avatar r-avatar--lg" aria-hidden="true"><?php echo htmlentities(initials($user->FullName ?: $user->EmailId)); ?></span>
              <div>
                <p class="r-account__name"><?php echo htmlentities($user->FullName ?: 'Your account'); ?></p>
                <p class="r-account__email"><?php echo htmlentities($user->EmailId); ?></p>
                <?php if (!empty($user->RegDate)) { ?><p class="r-account__since">Member since <?php echo date('F Y', strtotime($user->RegDate)); ?></p><?php } ?>
              </div>
            </div>
            <nav aria-label="My account">
              <ul class="r-account__nav">
                <?php foreach ($links as $url => [$icon, $label]) { ?>
                  <li><a href="<?php echo $url; ?>" class="<?php echo $url === $active ? 'is-active' : ''; ?>" <?php echo $url === $active ? 'aria-current="page"' : ''; ?>><i class="fa <?php echo $icon; ?>" aria-hidden="true"></i><?php echo $label; ?></a></li>
                <?php } ?>
                <li><form method="post" action="logout"><?php echo csrf_field(); ?><button type="submit" class="r-account__signout"><i class="fa fa-arrow-right-from-bracket" aria-hidden="true"></i>Sign out</button></form></li>
              </ul>
            </nav>
          </aside>
          <div class="r-account__content">
    <?php
    return ob_get_clean();
}

function account_end(): string
{
    return '</div></div></div></section>';
}

// Status badge for a booking (tblbooking.Status: 0/NULL pending, 1 confirmed, 2 cancelled).
function booking_status_badge($status): string
{
    [$class, $label] = match ((int)$status) {
        1 => ['r-badge--success', 'Confirmed'],
        2 => ['r-badge--danger', 'Cancelled'],
        default => ['r-badge--warning', 'Awaiting confirmation'],
    };
    return '<span class="r-badge ' . $class . '">' . $label . '</span>';
}

// Splits FAQ HTML (from Manage Pages) into questions and answers: every heading, or a
// paragraph that is only bold text, starts a question; what follows is its answer.
// Returns [] when the content has no such structure (it is then shown as plain text).
function faq_items(string $html): array
{
    if (trim(strip_tags($html)) === '') {
        return [];
    }
    $doc = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div id="faq-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $root = $doc->getElementById('faq-root');

    $isQuestion = function (DOMNode $node): bool {
        if (!$node instanceof DOMElement) {
            return false;
        }
        if (in_array(strtolower($node->tagName), ['h2', 'h3', 'h4', 'h5'], true)) {
            return true;
        }
        if (in_array(strtolower($node->tagName), ['p', 'div'], true)) {
            $text = trim($node->textContent);
            $bold = '';
            foreach ($node->getElementsByTagName('strong') as $b) { $bold .= $b->textContent; }
            foreach ($node->getElementsByTagName('b') as $b) { $bold .= $b->textContent; }
            return $text !== '' && trim($bold) === $text;
        }
        return false;
    };

    $items = [];
    foreach ($root->childNodes as $node) {
        if ($isQuestion($node)) {
            $items[] = ['question' => trim($node->textContent), 'answer' => ''];
        } elseif ($items) {
            $items[count($items) - 1]['answer'] .= $doc->saveHTML($node);
        }
    }
    return array_values(array_filter($items, fn($item) => $item['question'] !== ''));
}
?>
