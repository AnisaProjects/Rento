<?php
// Editable site content: every public text and image that isn't a car, a CMS page (Manage
// Pages) or the contact details (Contact Info). Edited in admin -> Site content.
//
// SITE_CONTENT is the registry: group => [label, lead, items]; item key => [label, type, default, hint].
// The defaults are the texts the site shipped with. tblsitecontent only stores what the
// admin changed; an empty value falls back to the default.
//   content('home_hero_1_title')            text, already HTML-escaped
//   content_raw('social_facebook')          plain value (for URLs and attributes)
//   content_image('home_hero_1_image')      site-relative image path (e.g. assets/images/banner-image-1.jpg)
//   site_picture(content_image(...), alt)   <picture> with its WebP sizes
// Types: text, textarea, url, image.

const SITE_CONTENT_IMAGE_DIR = 'assets/images/content/';

const SITE_CONTENT = [
    'home' => ['Home page', 'The hero slides and every section heading on the home page.', [
        'home_hero_1_image' => ['Slide 1: image', 'image', 'assets/images/banner-image-1.jpg', 'Wide photo, at least 1920 × 820 px.'],
        'home_hero_1_eyebrow' => ['Slide 1: small label', 'text', 'Car rental across Nepal'],
        'home_hero_1_title' => ['Slide 1: title', 'text', 'The right car for every road in Nepal', 'The first slide title is the page heading (H1).'],
        'home_hero_1_text' => ['Slide 1: text', 'textarea', 'Hatchbacks for the city, SUVs for the hills. Choose a car, pick your dates and book online.'],
        'home_hero_1_button' => ['Slide 1: button', 'text', 'Browse all cars'],
        'home_hero_1_link' => ['Slide 1: button link', 'url', 'car-listing'],
        'home_hero_2_image' => ['Slide 2: image', 'image', 'assets/images/banner-image-2.jpg'],
        'home_hero_2_eyebrow' => ['Slide 2: small label', 'text', 'Comfort for long drives'],
        'home_hero_2_title' => ['Slide 2: title', 'text', 'Sedans for highways and family trips'],
        'home_hero_2_text' => ['Slide 2: text', 'textarea', 'Quiet, comfortable cars for the Kathmandu–Pokhara highway and every trip in between.'],
        'home_hero_2_button' => ['Slide 2: button', 'text', 'See sedans'],
        'home_hero_2_link' => ['Slide 2: button link', 'url', 'car-listing?type=Sedan'],
        'home_hero_3_image' => ['Slide 3: image', 'image', 'assets/images/banner-image-3.jpg'],
        'home_hero_3_eyebrow' => ['Slide 3: small label', 'text', 'Not sure what to pick?'],
        'home_hero_3_title' => ['Slide 3: title', 'text', 'Tell us your trip, we match the car'],
        'home_hero_3_text' => ['Slide 3: text', 'textarea', 'Share your budget and the kind of car you like, and get suggestions that fit.'],
        'home_hero_3_button' => ['Slide 3: button', 'text', 'Find my car'],
        'home_hero_3_link' => ['Slide 3: button link', 'url', 'car-recommendation'],
        'home_trust_1' => ['Under the search: point 1', 'text', 'Cars checked before every trip'],
        'home_trust_2' => ['Under the search: point 2', 'text', 'Daily prices in rupees'],
        'home_trust_3' => ['Under the search: point 3', 'text', 'Local support when you need it'],
        'home_type_hatchback' => ['Car type card: Hatchback', 'text', 'Easy to park and light on fuel. Great for city trips.'],
        'home_type_sedan' => ['Car type card: Sedan', 'text', 'Comfortable and quiet for highways and family trips.'],
        'home_type_suv' => ['Car type card: SUV', 'text', 'Ground clearance and space for hills and rough roads.'],
        'home_fleet_eyebrow' => ['Newest cars: small label', 'text', 'The fleet'],
        'home_fleet_title' => ['Newest cars: heading', 'text', 'Recently added cars'],
        'home_fleet_lead' => ['Newest cars: text', 'text', 'Every car is listed with its real photos, specs and daily price.'],
        'home_fleet_button' => ['Newest cars: button', 'text', 'View all cars'],
        'home_packages_eyebrow' => ['Packages: small label', 'text', 'Packages & services'],
        'home_packages_title' => ['Packages: heading', 'text', 'Tours, transfers and cars with a driver'],
        'home_packages_lead' => ['Packages: text', 'text', 'Ready-made trips and services, planned with local drivers who know the roads.'],
        'home_packages_button' => ['Packages: button', 'text', 'See all packages'],
        'home_steps_eyebrow' => ['How it works: small label', 'text', 'How it works'],
        'home_steps_title' => ['How it works: heading', 'text', 'On the road in three steps'],
        'home_step_1_title' => ['Step 1: title', 'text', 'Choose a car'],
        'home_step_1_text' => ['Step 1: text', 'text', 'Filter by type, brand and budget, or let us suggest a car for your trip.'],
        'home_step_2_title' => ['Step 2: title', 'text', 'Pick your dates'],
        'home_step_2_text' => ['Step 2: text', 'text', 'Choose pickup and return dates and send your booking request in a minute.'],
        'home_step_3_title' => ['Step 3: title', 'text', 'Drive away'],
        'home_step_3_text' => ['Step 3: text', 'text', 'We confirm your booking and the car is ready when you arrive.'],
        'home_why_eyebrow' => ['Why Rento: small label', 'text', 'Why Rento'],
        'home_why_title' => ['Why Rento: heading', 'text', 'Renting a car should be the easy part'],
        'home_why_1_title' => ['Reason 1: title', 'text', 'Checked cars'],
        'home_why_1_text' => ['Reason 1: text', 'text', 'Cars are inspected and cleaned before every rental.'],
        'home_why_2_title' => ['Reason 2: title', 'text', 'Clear pricing'],
        'home_why_2_text' => ['Reason 2: text', 'text', 'One daily price in rupees, shown before you book.'],
        'home_why_3_title' => ['Reason 3: title', 'text', 'Flexible dates'],
        'home_why_3_text' => ['Reason 3: text', 'text', 'Rent for a day, a weekend or longer trips.'],
        'home_why_4_title' => ['Reason 4: title', 'text', 'Local support'],
        'home_why_4_text' => ['Reason 4: text', 'text', 'Questions before or during your trip? Call or message us.'],
        'home_findcar_title' => ['Find My Car box: heading', 'text', 'Not sure which car fits your trip?'],
        'home_findcar_text' => ['Find My Car box: text', 'text', "Tell us your budget and the kind of car you like, and we'll suggest the best matches."],
        'home_findcar_button' => ['Find My Car box: button', 'text', 'Find my car'],
        'home_testimonials_eyebrow' => ['Testimonials: small label', 'text', 'Testimonials', 'The testimonials themselves are approved under Testimonials.'],
        'home_testimonials_title' => ['Testimonials: heading', 'text', 'What renters say'],
        'home_brands_eyebrow' => ['Brands: small label', 'text', 'Brands', 'Brand logos are uploaded under Brands.'],
        'home_brands_title' => ['Brands: heading', 'text', 'Browse by brand'],
        'cta_eyebrow' => ['Booking banner: small label', 'text', 'Ready when you are', 'This banner is above the footer on the home and About pages.'],
        'cta_title' => ['Booking banner: heading', 'text', 'Book your car in Nepal'],
        'cta_text' => ['Booking banner: text', 'textarea', 'From city runs in Kathmandu to mountain roads beyond: pick a car, pick your dates and go, with clear daily prices in rupees.'],
        'cta_button_1' => ['Booking banner: first button', 'text', 'Browse cars'],
        'cta_button_2' => ['Booking banner: second button', 'text', 'Help me choose'],
    ]],
    'about' => ['About page', 'Everything on the About page except the main story text, which is under Manage Pages → About Us.', [
        'about_lead' => ['Header: text', 'textarea', 'Est. 2022 in Kathmandu. Cars for city runs and mountain roads, with prices you can see before you book.'],
        'about_hero_image' => ['Header: image', 'image', 'assets/images/aboutus-page-header-img.jpg', 'Wide photo; it is shown darkened behind the title.'],
        'about_story_eyebrow' => ['Story: small label', 'text', 'Our story'],
        'about_story_title' => ['Story: heading', 'text', 'Kathmandu traffic taught us what renters actually need.'],
        'about_stat_cars' => ['Numbers: cars label', 'text', 'Cars in the fleet', 'The numbers are counted from the fleet automatically.'],
        'about_stat_brands' => ['Numbers: brands label', 'text', 'Brands'],
        'about_stat_types' => ['Numbers: car types label', 'text', 'Car types'],
        'about_stat_price' => ['Numbers: price label', 'text', 'Daily prices from'],
        'about_mission_label' => ['Box 1: label', 'text', 'Our mission'],
        'about_mission_text' => ['Box 1: text', 'textarea', 'Pair every renter in Nepal with the right car, at a fair price, with nothing hidden.'],
        'about_vision_label' => ['Box 2: label', 'text', 'Our vision'],
        'about_vision_text' => ['Box 2: text', 'textarea', 'A country where booking a car takes less time than hailing one on the street.'],
        'about_work_label' => ['Box 3: label', 'text', 'How we work'],
        'about_work_text' => ['Box 3: text', 'textarea', 'Checked cars, clear daily prices in rupees, and a real person to call.'],
        'about_band_image' => ['Photo band: image', 'image', 'assets/images/fun-facts-bg.jpg'],
        'about_band_eyebrow' => ['Photo band: small label', 'text', 'On the road'],
        'about_band_quote' => ['Photo band: quote', 'textarea', 'From the Ring Road to the Prithvi Highway, we keep cars ready for the way Nepal drives.'],
        'about_fleet_eyebrow' => ['Fleet photos: small label', 'text', 'The fleet'],
        'about_fleet_title' => ['Fleet photos: heading', 'text', 'Meet some of our cars'],
        'about_fleet_button' => ['Fleet photos: button', 'text', 'See every car'],
    ]],
    'pages' => ['Other pages', 'Page headers and fixed text on the cars, Find My Car, packages, contact, help and 404 pages.', [
        'cars_lead' => ['Cars: header text', 'text', 'Describe what you need, or use the filters. Every price is per day, in rupees.'],
        'findcar_eyebrow' => ['Find My Car: small label', 'text', 'Smart match'],
        'findcar_title' => ['Find My Car: heading', 'text', "Describe your trip. We'll find the car."],
        'findcar_lead' => ['Find My Car: text', 'textarea', "Say where you're going, how many of you there are and your budget.", 'The page adds "We match it against N cars" after this.'],
        'findcar_example_1' => ['Find My Car: example 1', 'text', 'Family trip to Pokhara under Rs 8,000', 'Example prompts visitors can click. Leave one empty to use its default.'],
        'findcar_example_2' => ['Find My Car: example 2', 'text', 'Mountain roads to Mustang, 6 people'],
        'findcar_example_3' => ['Find My Car: example 3', 'text', 'Daily city commute in Kathmandu, cheap'],
        'findcar_example_4' => ['Find My Car: example 4', 'text', 'Airport pickup for a business meeting'],
        'packages_title' => ['Packages: heading', 'text', 'Packages & Services'],
        'packages_lead' => ['Packages: header text', 'textarea', 'Tours, airport transfers, cars with a driver and more. Send a request and we confirm the price and details with you.'],
        'contact_title' => ['Contact: heading', 'text', 'Contact us', 'The address, phone and email are under Contact Info.'],
        'contact_lead' => ['Contact: header text', 'textarea', 'Questions about a car, a booking or a trip? Send us a message and we will get back to you.'],
        'contact_form_title' => ['Contact: form heading', 'text', 'Send us a message'],
        'faqs_lead' => ['FAQs: header text', 'text', 'Answers to the questions renters ask us most.', 'The questions are under Manage Pages → FAQs.'],
        'terms_lead' => ['Terms: header text', 'text', 'The terms that apply when you rent a car with Rento.'],
        'privacy_lead' => ['Privacy: header text', 'text', 'How we collect, use and protect your personal information.'],
        'help_title' => ['Help box (FAQs, Terms, Privacy): heading', 'text', 'Still have a question?'],
        'help_text' => ['Help box: text', 'text', "We're happy to help with bookings, cars and trips."],
        'help_button' => ['Help box: button', 'text', 'Contact us'],
        'notfound_title' => ['404 page: heading', 'text', "This road doesn't go anywhere"],
        'notfound_lead' => ['404 page: text', 'text', "The page you were looking for doesn't exist, or the car is no longer listed."],
    ]],
    'footer' => ['Footer & social', 'Footer text and social links. The address, phone and email come from Contact Info.', [
        'footer_about' => ['About text', 'textarea', 'Verified cars and clear daily prices in rupees, across Nepal. Booking a car should be the easy part of the trip.'],
        'footer_newsletter_hint' => ['Newsletter note', 'text', 'Deals and new cars, about once a month.'],
        'footer_copyright' => ['Copyright line', 'text', 'Rento. All rights reserved.', 'Shown after "© ' . '{year}".'],
        'footer_tagline' => ['Bottom line', 'text', 'Car rental in Nepal · prices in NPR'],
        'social_facebook' => ['Facebook page URL', 'url', '', 'Leave empty to hide the icon.'],
        'social_instagram' => ['Instagram URL', 'url', ''],
        'social_tiktok' => ['TikTok URL', 'url', ''],
        'social_youtube' => ['YouTube URL', 'url', ''],
        'social_x' => ['X (Twitter) URL', 'url', ''],
        'social_linkedin' => ['LinkedIn URL', 'url', ''],
        'social_whatsapp' => ['WhatsApp link', 'url', '', 'For example https://wa.me/9779800000000'],
    ]],
    'seo' => ['Site & SEO', 'Site name, logo, and the title and description search engines show for each page.', [
        'site_name' => ['Site name', 'text', 'Rento', 'Used in page titles ("… | Rento") and the text logo (its last letter is orange).'],
        'site_logo' => ['Logo image', 'image', '', 'Optional. Without an image the site shows the site name as a text logo.'],
        'site_url' => ['Site address', 'url', '', 'Full address of the live site, e.g. https://www.rento.com.np/. Used for canonical links, the sitemap, robots.txt and share previews. Leave empty while testing: the address the page was opened at is used.'],
        'seo_share_image' => ['Share image', 'image', 'assets/images/share-default.jpg', 'Shown when a page is shared on Facebook, WhatsApp, X and others (car and package pages use their own photo). 1200 × 630 px.'],
        'seo_twitter' => ['X (Twitter) handle', 'text', '', 'Optional, e.g. @rento_np. Added to X share cards.'],
        'seo_default_description' => ['Default description', 'textarea', 'Rento: rent a verified car anywhere in Nepal. Browse the fleet, compare daily prices in rupees and book online.', 'Used on pages without their own description.'],
        'seo_home_title' => ['Home: title', 'text', 'Car Rental in Nepal'],
        'seo_home_description' => ['Home: description', 'textarea', 'Rento: rent a verified car anywhere in Nepal. Browse hatchbacks, sedans and SUVs, compare daily prices in rupees and book online.'],
        'seo_cars_title' => ['Cars: title', 'text', 'Car Listing', 'Filtered lists use their own title, e.g. "SUV cars".'],
        'seo_cars_description' => ['Cars: description', 'textarea', 'Browse every car available to rent with Rento: filter by type, brand, fuel and budget, with daily prices in rupees.'],
        'seo_findcar_title' => ['Find My Car: title', 'text', 'Find My Car'],
        'seo_findcar_description' => ['Find My Car: description', 'textarea', 'Describe your trip and Rento suggests the cars that fit your budget and the roads you will drive.'],
        'seo_packages_title' => ['Packages: title', 'text', 'Packages & Services'],
        'seo_packages_description' => ['Packages: description', 'textarea', 'Tours, airport transfers, hourly hire and cars with a driver in Nepal. Send Rento a booking request and we confirm the details.'],
        'seo_contact_title' => ['Contact: title', 'text', 'Contact Us'],
        'seo_contact_description' => ['Contact: description', 'textarea', 'Contact Rento: address, email, phone number and a form to send us your question.'],
        'seo_aboutus_title' => ['About: title', 'text', 'About Us'],
        'seo_aboutus_description' => ['About: description', 'textarea', 'Who is behind Rento, how we choose and look after our cars, and why renting a car in Nepal with us is simple and fairly priced.'],
        'seo_faqs_title' => ['FAQs: title', 'text', 'FAQs'],
        'seo_faqs_description' => ['FAQs: description', 'textarea', 'Answers about booking a Rento car in Nepal: how prices are worked out, payment, documents, changing or cancelling a booking and choosing a car.'],
        'seo_terms_title' => ['Terms: title', 'text', 'Terms and Conditions'],
        'seo_terms_description' => ['Terms: description', 'textarea', 'The terms for renting a Rento car in Nepal, self-drive or with a driver, and for packages: bookings, prices, deposits, fuel, damage and cancellations.'],
        'seo_privacy_title' => ['Privacy: title', 'text', 'Privacy Policy'],
        'seo_privacy_description' => ['Privacy: description', 'textarea', 'How Rento collects, uses, stores and protects your personal data when you create an account, book a car or send an enquiry, and your choices.'],
        'seo_notfound_title' => ['404: title', 'text', 'Page Not Found'],
        'seo_notfound_description' => ['404: description', 'textarea', 'The page you were looking for could not be found. Browse the Rento fleet or search for a car.'],
    ]],
];

// [label, default, type, hint] for a key, or null.
function site_content_item(string $key): ?array
{
    static $index = null;
    if ($index === null) {
        $index = [];
        foreach (SITE_CONTENT as $group => [, , $items]) {
            foreach ($items as $itemKey => $item) {
                $index[$itemKey] = ['label' => $item[0], 'type' => $item[1], 'default' => $item[2], 'hint' => $item[3] ?? '', 'group' => $group];
            }
        }
    }
    return $index[$key] ?? null;
}

// Values the admin has saved (loaded once per request).
function site_content_saved(?PDO $dbh = null, bool $reload = false): array
{
    static $saved = null;
    if ($saved === null || $reload) {
        $saved = [];
        $dbh = $dbh ?? ($GLOBALS['dbh'] ?? null);
        if ($dbh instanceof PDO) {
            try {
                foreach ($dbh->query("SELECT ContentKey, ContentValue FROM tblsitecontent") as $row) {
                    $saved[$row['ContentKey']] = (string)$row['ContentValue'];
                }
            } catch (PDOException $e) {
                // Table not created yet (migration 005): use the defaults.
                $saved = [];
            }
        }
    }
    return $saved;
}

// The current value (saved, or the default when nothing or an empty value is saved).
function content_raw(string $key): string
{
    $saved = site_content_saved();
    if (isset($saved[$key]) && trim($saved[$key]) !== '') {
        return $saved[$key];
    }
    return (string)(site_content_item($key)['default'] ?? '');
}

// The current value, HTML-escaped for output.
function content(string $key): string
{
    return htmlentities(content_raw($key), ENT_QUOTES);
}

// Image path relative to the site root, or '' when there is none.
function content_image(string $key): string
{
    $path = content_raw($key);
    return ($path !== '' && is_file(__DIR__ . '/../' . $path)) ? $path : (string)(site_content_item($key)['default'] ?? '');
}

// Site name as the text logo: "Rent<span>o</span>".
function site_wordmark(): string
{
    $name = content_raw('site_name');
    $last = mb_substr($name, -1);
    return htmlentities(mb_substr($name, 0, -1)) . '<span>' . htmlentities($last) . '</span>';
}

// <picture> for a site image (e.g. assets/images/banner-image-1.jpg) with the WebP files
// next to it: <base>.webp (full size) and <base>-960.webp (smaller screens), when present.
//   $opts: 'class', 'imgClass', 'sizes', 'width', 'height', 'priority' (true = eager + high priority)
function site_picture(string $path, string $alt = '', array $opts = []): string
{
    if ($path === '') {
        return '';
    }
    $root = __DIR__ . '/../';
    $base = preg_replace('/\.[a-z0-9]+$/i', '', $path);
    $srcset = [];
    if (is_file($root . $base . '-960.webp')) {
        $srcset[] = $base . '-960.webp 960w';
    }
    if (is_file($root . $base . '.webp')) {
        $size = @getimagesize($root . $base . '.webp');
        $srcset[] = $base . '.webp' . ($srcset ? ' ' . ($size[0] ?? 1920) . 'w' : '');
    }
    $html = '<picture' . (!empty($opts['class']) ? ' class="' . htmlentities($opts['class']) . '"' : '') . '>';
    if ($srcset) {
        $html .= '<source type="image/webp" srcset="' . htmlentities(implode(', ', $srcset)) . '"'
            . (count($srcset) > 1 ? ' sizes="' . htmlentities($opts['sizes'] ?? '100vw') . '"' : '') . '>';
    }
    $html .= '<img src="' . htmlentities($path) . '" alt="' . htmlentities($alt) . '"';
    if (!empty($opts['imgClass'])) {
        $html .= ' class="' . htmlentities($opts['imgClass']) . '"';
    }
    if (!empty($opts['width']) && !empty($opts['height'])) {
        $html .= ' width="' . (int)$opts['width'] . '" height="' . (int)$opts['height'] . '"';
    } elseif ($size = @getimagesize($root . $path)) {
        $html .= ' width="' . $size[0] . '" height="' . $size[1] . '"';
    }
    $html .= !empty($opts['priority']) ? ' fetchpriority="high"' : ' loading="lazy"';
    return $html . '></picture>';
}

// Social links that have a URL (empty ones are not shown).
function site_social_links(): array
{
    $links = [];
    foreach (['facebook', 'instagram', 'tiktok', 'youtube', 'x', 'linkedin', 'whatsapp'] as $network) {
        $url = trim(content_raw('social_' . $network));
        if ($url !== '' && preg_match('#^https?://#i', $url)) {
            $links[$network] = $url;
        }
    }
    return $links;
}
