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
        <?php if (!empty($opts['badge']) || !empty($car->CarType)) { ?>
          <div class="r-car-card__badges">
            <?php if (!empty($opts['badge'])) { ?><span class="r-badge r-badge--primary"><?php echo htmlentities($opts['badge']); ?></span><?php } ?>
            <?php if (!empty($car->CarType)) { ?><span class="r-badge r-badge--dark"><?php echo htmlentities($car->CarType); ?></span><?php } ?>
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
function page_hero(string $title, string $lead = '', array $crumbs = []): string
{
    ob_start(); ?>
    <section class="r-page-hero">
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
?>
