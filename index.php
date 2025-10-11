<?php
/**
 * index.php
 * Public-facing site template. Renders content from the JSON-backed
 * content store (`data/content.json`). This file is intentionally
 * lightweight and uses small helper functions to keep templates
 * easy to read.
 *
 * Contract (high level):
 *  - Inputs: reads `data/content.json` (no user-supplied inputs).
 *  - Outputs: HTML page (status 200) rendering site sections.
 *  - Side effects: none (read-only). Any write operations happen in
 *    admin endpoints which update the JSON file.
 *
 * Important notes for developers:
 *  - The template expects `data/content.json` to be valid JSON. If
 *    absent, an empty content array is used.
 *  - For security, user-provided values are escaped with
 *    `htmlspecialchars()` before rendering.
 *  - This file should avoid performing write operations. Admin
 *    pages handle persistence and validation.
 */

// Load the site content (stored as JSON in data/content.json).
// This file is the single source of truth for editable content (hero text, menu, images, etc.).
$contentFile = 'data/content.json';
$content = file_exists($contentFile) ? json_decode(file_get_contents($contentFile), true) : [];

// Developer debug code was used during diagnosis and has been removed.

// Client-side dev diagnostics: when ?dev=1, also emit a small script that
// parses the embedded per-section JSON blobs (if present), logs them to the
// console, and renders a tiny overlay showing a few items and whether their
// descriptions are present. This helps confirm whether the browser received
// the same content the server rendered.
// client-side dev overlay removed

/**
 * Helper: getContent
 * Fetches a nested key from the $content array using dot notation.
 * Returns $fallback when the key path is missing. This centralizes access
 * so templates don't need to repeatedly check isset() everywhere.
 *
 * Inputs:
 *  - $content: decoded content array
 *  - $key: dot-delimited path like 'hero.title'
 *  - $fallback: value to return if key not found
 */
function getContent($content, $key, $fallback = '') {
    $keys = explode('.', $key);
    $value = $content;
    foreach ($keys as $k) {
        if (isset($value[$k])) {
            $value = $value[$k];
        } else {
            return $fallback;
        }
    }
    return $value;
}
?>

<?php
// Cache-busting version string for static assets during development
$cssPath = 'assets/css/styles.css';
$cssVersion = file_exists($cssPath) ? filemtime($cssPath) : time();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thunder Road Bar and Grill | Midway, NC</title>
    
    <!-- External CSS File (auto cache-busted using file modification time) -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars($cssPath . '?v=' . $cssVersion, ENT_QUOTES, 'UTF-8'); ?>">
    
    <!-- Favicons and platform icons (using uploaded favicon-set) -->
    <link rel="icon" type="image/png" sizes="32x32" href="uploads/images/favicon-set/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="uploads/images/favicon-set/favicon-16x16.png">
    <link rel="shortcut icon" href="uploads/images/favicon-set/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="uploads/images/favicon-set/apple-touch-icon.png">
    <!-- Web app manifest (placed at site root) -->
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#dc2626">
    <!-- Android / Chrome icons -->
    <link rel="icon" type="image/png" sizes="192x192" href="uploads/images/favicon-set/android-chrome-192x192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="uploads/images/favicon-set/android-chrome-512x512.png">
    <!-- Microsoft tile -->
    <meta name="msapplication-TileColor" content="#ffffff">
    <meta name="msapplication-TileImage" content="uploads/images/favicon-set/mstile-150x150.png">
    <meta name="description" content="Thunder Road Bar and Grill - Your go-to spot for great food and drinks in Midway, NC. Enjoy a welcoming atmosphere with friends and family.">
    <meta name="keywords" content="bar and grill, Midway NC, food, drinks, family-friendly">
    <meta name="author" content="Thunder Road Bar and Grill">
    
    <!-- Open Graph Meta Tags for Social Sharing -->
    <meta property="og:title" content="Thunder Road Bar and Grill">
    <meta property="og:description" content="Your go-to spot for great food and drinks in Midway, NC.">
    <meta property="og:image" content="assets/images/og-image.jpg">
    <meta property="og:url" content="https://yourwebsite.com">
    
    <!-- Preload Critical Resources -->
    <link rel="preload" href="<?php echo htmlspecialchars($cssPath . '?v=' . $cssVersion, ENT_QUOTES, 'UTF-8'); ?>" as="style">
</head>
<body>
    <!-- ==============================================
         HEADER COMPONENT
         Sticky navigation with logo and menu items
         ============================================== -->
    <header class="header">
        <div class="container">
            <nav class="navbar flex justify-between align-center">
                <?php
                    $logoFile = $content['images']['logo'] ?? '';
                    $logoUrl = '';
                    if ($logoFile) {
                        $logoUrl = preg_match('#^https?://#i', $logoFile) ? $logoFile : 'uploads/images/'.ltrim($logoFile, '/');
                    }
                ?>
                <a href="/" class="logo" aria-label="Home">
                    <?php if ($logoUrl): ?>
                        <span class="logo-badge" aria-hidden="true">
                            <img class="site-logo-img" src="<?php echo htmlspecialchars($logoUrl); ?>" alt="<?php echo htmlspecialchars($content['business_info']['name'] ?? 'Thunder Road Bar and Grill'); ?>">
                        </span>
                    <?php else: ?>
                        <?php echo htmlspecialchars($content['business_info']['name'] ?? 'Thunder Road Bar and Grill'); ?>
                    <?php endif; ?>
                </a>
                <ul class="nav-menu flex gap-4">
                    <li><a href="#menu" class="nav-link">Menu</a></li>
                    <li><a href="#reservation" class="nav-link">Reservations</a></li>
                    <li><a href="#about" class="nav-link">About</a></li>
                    <li><a href="#job-application" class="nav-link">Careers</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- ==============================================
         HERO SECTION COMPONENT
         Main landing area with primary CTA
         ============================================== -->
        <section class="hero" id="home">
        <div class="container">
            <h1 class="hero-title">
                <?php echo htmlspecialchars((string)getContent($content, 'hero.title', 'Welcome to Our Business'), ENT_QUOTES, 'UTF-8'); ?>
            </h1>
            <p class="hero-subtitle">
                <?php echo htmlspecialchars((string)getContent($content, 'hero.subtitle', 'Your success is our priority'), ENT_QUOTES, 'UTF-8'); ?>
            </p>
            <div class="flex justify-center gap-4">
                <?php
                    // Primary CTA: prefer hero.btn_text / hero.btn_link, fall back to older keys
                    $primaryText = getContent($content, 'hero.btn_text', '') ?: getContent($content, 'hero.cta_text', 'View Menu');
                    $primaryLink = getContent($content, 'hero.btn_link', '') ?: getContent($content, 'hero.cta_url', '#menu');
                    $secondaryText = getContent($content, 'hero.btn2_text', 'Order Online');
                    $secondaryLink = getContent($content, 'hero.btn2_link', '#order');
                ?>
                <a href="<?php echo htmlspecialchars($primaryLink); ?>" class="btn btn-primary" role="button">
                    <?php echo htmlspecialchars($primaryText); ?>
                </a>
                <a href="<?php echo htmlspecialchars($secondaryLink); ?>" class="btn btn-secondary" role="button" aria-label="<?php echo htmlspecialchars($secondaryText); ?>">
                    <?php echo htmlspecialchars($secondaryText); ?>
                </a>
            </div>
        </div>
    </section>

    

                <style>
                /* Hero slideshow layers with controls */
                .hero { position: relative; overflow: hidden; }
                .hero .hero-slide { position:absolute; inset:0; background-size:cover; background-position:center; opacity:0; transition:opacity 1s ease-in-out; }
                .hero .hero-slide.visible { opacity:1 }
                .hero .hero-overlay { position:relative; z-index:5 }
                .hero .hero-controls { position:absolute; left:0; right:0; top:50%; transform:translateY(-50%); display:flex; justify-content:space-between; gap:1rem; padding:0 1rem; z-index:10 }
                .hero .hero-controls button { background:rgba(0,0,0,0.45); color:var(--text-inverse); border:none; padding:.5rem .7rem; border-radius:6px; cursor:pointer }
                .hero .hero-controls button:focus { outline:2px solid rgba(255,255,255,0.7) }
                </style>

                <script>
                (function(){
                    var heroImages = [];
                    <?php
                            $imgs = [];
                            // Prefer new `images` array; fall back to legacy `image` key for older content
                            if (!empty($content['hero']['images']) && is_array($content['hero']['images'])) {
                                foreach ($content['hero']['images'] as $i) { if ($i) $imgs[] = $i; }
                            } elseif (!empty($content['hero']['image'])) {
                                $imgs[] = $content['hero']['image'];
                            }
                        $out = [];
                        foreach ($imgs as $fn) {
                            if (preg_match('#^https?://#i', $fn)) $out[] = $fn; else $out[] = 'uploads/images/'.ltrim($fn, '/');
                        }
                        echo 'heroImages = ' . json_encode($out) . ";\n";
                    ?>

                    if (!heroImages || !heroImages.length) return;
                    var hero = document.getElementById('home'); if (!hero) return;

                    heroImages.forEach(function(url, idx){
                        var div = document.createElement('div'); div.className = 'hero-slide'; div.style.backgroundImage = 'url("'+url.replace(/"/g,'\\"')+'")';
                        if (idx===0) div.classList.add('visible');
                        hero.insertBefore(div, hero.firstChild);
                    });

                    // controls container
                    var controls = document.createElement('div'); controls.className = 'hero-controls';
                    var prev = document.createElement('button'); prev.type='button'; prev.setAttribute('aria-label','Previous slide'); prev.textContent='←';
                    var next = document.createElement('button'); next.type='button'; next.setAttribute('aria-label','Next slide'); next.textContent='→';
                    controls.appendChild(prev); controls.appendChild(next);
                    hero.appendChild(controls);

                    var slides = hero.querySelectorAll('.hero-slide');
                    var current = 0; var interval = null; var paused = false; var delay = 5000;

                    function show(idx){ if (idx === current) return; slides[current].classList.remove('visible'); slides[idx].classList.add('visible'); current = idx; }
                    function start(){ if (interval) return; interval = setInterval(()=>{ if (!paused){ var n=(current+1)%slides.length; show(n); } }, delay); }
                    function stop(){ if (interval){ clearInterval(interval); interval=null; } }

                    prev.addEventListener('click', function(){ var n = (current-1+slides.length)%slides.length; show(n); });
                    next.addEventListener('click', function(){ var n = (current+1)%slides.length; show(n); });

                    hero.addEventListener('mouseenter', function(){ paused=true; });
                    hero.addEventListener('mouseleave', function(){ paused=false; });

                    document.addEventListener('keydown', function(e){ if (e.key === 'ArrowLeft') prev.click(); if (e.key === 'ArrowRight') next.click(); });

                    start();
                })();
                </script>


    <!-- ==============================================
         MENU SECTION (moved to top)
         ============================================== -->
    <section class="section" id="menu">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Our Menu</h2>
                <p class="section-subtitle">
                    All of our burgers are cooked to the required minimum temperature. Upon request we will cook to your specifications || Consuming raw or undercooked meat, poultry, eggs, or seafood poses a risk to everyone.
                </p>
            </div>
            
            <div class="menu-grid">
                            <?php
                                $menu = $content['menu'] ?? null;
                                // If menu is an array of sections (each with 'items'), render grouped sections
                                $is_sections = is_array($menu) && count($menu) && isset($menu[0]) && (is_array($menu[0]) && array_key_exists('items', $menu[0]));
                                if ($is_sections) {
                                    foreach ($menu as $sidx => $section) {
                                            $sectionTitle = htmlspecialchars($section['title'] ?? ($section['id'] ?? 'Section'));
                                            $sectionId = $section['id'] ?? '';
                                        $items = is_array($section['items']) ? $section['items'] : [];
                                        // determine a representative image for the section
                                        $firstImg = '';
                                        $firstTitle = '';
                                        // Prefer explicit section-level image (set in admin). Fallback to first item image.
                                        $sectionImg = '';
                                        if (!empty($section['image'])) {
                                            $sectionImg = $section['image'];
                                            $firstImg = preg_match('#^https?://#i', $sectionImg) ? $sectionImg : 'uploads/images/'.ltrim($sectionImg,'/');
                                        } else if (count($items)) {
                                            $first = $items[0];
                                            $firstTitle = htmlspecialchars($first['title'] ?? '');
                                            $img = $first['image'] ?? '';
                                            if ($img) { $firstImg = preg_match('#^https?://#i', $img) ? $img : 'uploads/images/'.ltrim($img,'/'); }
                                        }
                                        echo '<div class="card menu-card section-card" data-section-idx="'.$sidx.'" data-section-id="'.htmlspecialchars($sectionId).'">';
                                        // If a section-level image is present, render it as a background block
                                        if ($firstImg) {
                                            // If the admin provided alt text for the section image, render
                                            // a semantic <img> with that alt text for accessibility. Otherwise
                                            // render as a decorative background (aria-hidden).
                                            $alt = isset($section['image_alt']) ? trim((string)$section['image_alt']) : '';
                                            if ($alt !== '') {
                                                echo '<div class="menu-img" aria-hidden="false">';
                                                echo '<img src="'.htmlspecialchars($firstImg).'" alt="'.htmlspecialchars($alt).'">';
                                                echo '</div>';
                                            } else {
                                                echo '<div class="menu-img menu-img-bg" aria-hidden="true" style="background-image:url(' . htmlspecialchars($firstImg) . ');"></div>';
                                            }
                                        }
                                        // collapsed card: only show section title and the expand button
                                        echo '<div class="menu-body">';
                                        // decorative overlay to improve legibility on image-backed cards
                                        echo '<div class="menu-overlay" aria-hidden="true"></div>';
                                        echo '<div class="menu-title">'.$sectionTitle.'</div>';
                                        // expand button includes an icon and accessible label
                                        $chev = '<svg class="expand-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><polyline points="6 9 12 15 18 9" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                                        echo '<div class="menu-actions"><button type="button" class="expand-btn" aria-expanded="false">' . $chev . '<span class="expand-label">View All</span></button></div>';
                                        echo '</div>'; // body
                                        // details: list each item in this section
                                        // details header (shown inside expanded area) + items
                                        echo '<div class="menu-details"><div class="menu-details-header">';
                                        if (!empty($section['details'])) {
                                            if (is_array($section['details'])) {
                                                foreach ($section['details'] as $dline) {
                                                    echo '<div class="menu-section-details small muted-text">'.htmlspecialchars($dline).'</div>';
                                                }
                                            } else {
                                                echo '<div class="menu-section-details small muted-text">'.htmlspecialchars($section['details']).'</div>';
                                            }
                                        }
                                        echo '</div><div class="section-items">';
                                                            if (count($items)) {
                                                                foreach ($items as $it) {
                                                                        $t = htmlspecialchars($it['title'] ?? '');
                                                                        $s = htmlspecialchars($it['short'] ?? '');
                                                                        $d = htmlspecialchars($it['description'] ?? '');
                                                                        $img = $it['image'] ?? '';
                                                                        $imgUrl = '';
                                                                        if ($img) { $imgUrl = preg_match('#^https?://#i', $img) ? $img : 'uploads/images/'.ltrim($img,'/'); }
                                                                        // debug comment removed
                                                                        echo '<div class="card section-item">';
                                                                        if ($imgUrl) echo '<img src="'.htmlspecialchars($imgUrl).'" alt="'.htmlspecialchars($t).'">';
                                                                        echo '<div class="preview-meta">';
                                                                        // title row (title left, representative price/placeholder right)
                                                                        echo '<div class="preview-row"><div style="font-weight:700">'.$t.'</div>';
                                                                        // (debug flag removed)
                                                                        // Prefer to show per-quantity pricing when available
                                                                        if ($sectionId !== 'current-ice-cream-flavors' && isset($it['quantities']) && is_array($it['quantities']) && count($it['quantities'])) {
                                                                            // If multiple quantity-price pairs are present, show both prices
                                                                            // in the summary for quicker comparison (e.g. "$8.50 / $11.50").
                                                                            $rp = '';
                                                                            $qarr = $it['quantities'];
                                                                            if (count($qarr) >= 2) {
                                                                                // Build segments that include the quantity label/value and the price
                                                                                $segParts = [];
                                                                                for ($qi = 0; $qi < 2; $qi++) {
                                                                                    $qopt = $qarr[$qi];
                                                                                    $label = trim((string)($qopt['label'] ?? ''));
                                                                                    $val = isset($qopt['value']) ? $qopt['value'] : '';
                                                                                    $price = isset($qopt['price']) ? trim((string)$qopt['price']) : '';
                                                                                    $lead = $label !== '' ? $label : ($val !== '' ? (string)$val : '');
                                                                                    if ($price !== '') {
                                                                                            // For Wings & Tenders prefer the compact "<value> for $<price>" style
                                                                                            if ($sectionId === 'wings-tenders') {
                                                                                                // use the numeric value when present, otherwise fall back to label
                                                                                                $num = $val !== '' ? htmlspecialchars((string)$val) : htmlspecialchars($lead);
                                                                                                if ($num !== '') $segParts[] = $num . ' for $' . htmlspecialchars($price);
                                                                                                else $segParts[] = htmlspecialchars($lead) . ' for $' . htmlspecialchars($price);
                                                                                            } else {
                                                                                                if ($lead !== '') $segParts[] = htmlspecialchars($lead) . ' $' . htmlspecialchars($price);
                                                                                                else $segParts[] = '$' . htmlspecialchars($price);
                                                                                            }
                                                                                        }
                                                                                }
                                                                                if (count($segParts)) $rp = implode(' / ', $segParts);
                                                                                elseif (isset($it['price']) && $it['price'] !== '') $rp = '$'.htmlspecialchars($it['price']);
                                                                            } else {
                                                                                $firstQ = $qarr[0];
                                                                                $lead = trim((string)($firstQ['label'] ?? '')) ?: (isset($firstQ['value']) ? (string)$firstQ['value'] : '');
                                                                                if (isset($firstQ['price']) && $firstQ['price'] !== '') {
                                                                                    $rp = ($lead !== '' ? htmlspecialchars($lead) . ' $' : '$') . htmlspecialchars($firstQ['price']);
                                                                                } elseif (isset($it['price']) && $it['price'] !== '') $rp = '$'.htmlspecialchars($it['price']);
                                                                            }
                                                                            if ($rp) echo '<div class="menu-price"><span class="price-badge">'.$rp.'</span></div>';
                                                                        } else {
                                                                            // fallback to item-level price
                                                                            $price = '';
                                                                            if ($sectionId !== 'current-ice-cream-flavors') {
                                                                                $price = isset($it['price']) && $it['price'] !== '' ? '$'.htmlspecialchars($it['price']) : '';
                                                                            }
                                                                            if ($price) echo '<div class="menu-price"><span class="price-badge">'.$price.'</span></div>';
                                                                        }
                                                                        echo '</div>';
                                                                        if ($s) echo '<div class="small muted-text">'.htmlspecialchars($s, ENT_QUOTES, "UTF-8").'</div>';
                                                                        // Render per-quantity options (list) when present
                                                                        if (isset($it['quantities']) && is_array($it['quantities']) && count($it['quantities'])) {
                                                                            // Render quantity labels only in the options list. Prices should
                                                                            // be shown exclusively in the price badge above. This keeps the
                                                                            // visual layout consistent and prevents duplicate price text.
                                                                            echo '<div class="menu-qty-options small" style="margin-top:.4rem">';
                                                                            $parts = [];
                                                                            foreach ($it['quantities'] as $qopt) {
                                                                                $ql = trim($qopt['label'] ?? '');
                                                                                $qv = isset($qopt['value']) ? $qopt['value'] : '';
                                                                                $seg = '';
                                                                                if ($ql !== '') $seg = htmlspecialchars($ql);
                                                                                elseif ($qv !== '') $seg = htmlspecialchars((string)$qv);
                                                                                if ($seg !== '') $parts[] = $seg;
                                                                            }
                                                                            if (count($parts)) echo 'Options: '.implode(' | ', $parts);
                                                                            echo '</div>';
                                                                        } else {
                                                                            // fallback: legacy quantity display when no explicit quantities are present
                                                                            if (!empty($it['quantity']) && in_array($sectionId, ['wings-tenders', 'sides'])) {
                                                                                echo '<div class="menu-quantity small">Qty: '.htmlspecialchars((string)$it['quantity']).'</div>';
                                                                            }
                                                                        }
                                                                        // Always render item description (placed after quantity/options for clarity)
                                                                        if ($d) echo '<div class="item-desc" style="margin-top:.4rem">'.nl2br($d).'</div>';
                                                                        echo '</div></div>';
                                                                }
                                                            } else {
                                            echo '<div class="small">No items in this section yet.</div>';
                                        }
                                        echo '</div></div>'; // section-items + menu-details
                                        // Embed a JSON blob for this section so client-side overlay can render it reliably
                                        // include the numeric section index so we can always find it
                                        $jsonRaw = json_encode($section, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                                        echo '<script type="application/json" class="menu-section-data" data-section-idx="' . intval($sidx) . '" data-section-id="' . htmlspecialchars($sectionId) . '">' . $jsonRaw . '</script>';
                                        
                                        echo '</div>';
                                    }
                                } else {
                                                                        // fallback to the requested ordered sections
                                                                        echo '<div class="card"><div class="card-header"><h3 class="card-title">Burgers & Sandwiches</h3><p class="card-description">Handcrafted burgers and sandwiches</p></div><p>Try our classic and specialty burgers and sandwiches, cooked to order and served with fresh toppings.</p></div>';
                                                                        echo '<div class="card"><div class="card-header"><h3 class="card-title">Wings & Tenders</h3><p class="card-description">Crispy wings and juicy tenders</p></div><p>Our wings and tenders are available in multiple sauces and heat levels.</p></div>';
                                                                        echo '<div class="card"><div class="card-header"><h3 class="card-title">Salads</h3><p class="card-description">Fresh and healthy options</p></div><p>Our salads are made with the freshest ingredients and are perfect for a light start or a side dish.</p></div>';
                                                                        echo '<div class="card"><div class="card-header"><h3 class="card-title">Flatbread Pizza</h3><p class="card-description">Thin-crust flatbreads</p></div><p>Delicious flatbread pizzas with a crispy crust and flavorful toppings.</p></div>';
                                                                        echo '<div class="card"><div class="card-header"><h3 class="card-title">Sides</h3><p class="card-description">Tasty side dishes</p></div><p>Choose from fries, onion rings, and other crowd-pleasing sides.</p></div>';
                                                                        echo '<div class="card"><div class="card-header"><h3 class="card-title">Additions & Dressings</h3><p class="card-description">Extras to customize your meal</p></div><p>Extra toppings, sauces, and dressings to make your meal perfect.</p></div>';
                                                                        echo '<div class="card"><div class="card-header"><h3 class="card-title">Hershey\'s Ice Cream</h3><p class="card-description">Classic ice cream favorites</p></div><p>Enjoy Hershey\'s ice cream scoops and sundaes.</p></div>';
                                                                        echo '<div class="card"><div class="card-header"><h3 class="card-title">Current Ice Cream Flavors</h3><p class="card-description">Rotating ice cream flavors</p></div><p>See what\'s currently available\'s currently available\'s currently available\'s currently available</p></div>';
                                }
              ?>
            </div>
        </div>
    </section>


    <!-- ==============================================
         RESERVATION SECTION (moved after Menu)
         ============================================== -->
    <section class="section" id="reservation">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Make a Reservation</h2>
                <p class="section-subtitle">Request a reservation and we'll confirm availability.</p>
            </div>
            <div class="container-narrow">
                <form id="reservation-form" class="card" method="post" action="admin/reserve.php" data-no-ajax="1">
                    <div class="grid grid-2">
                        <div>
                            <label for="res-name" class="form-label">Name *</label>
                            <input type="text" id="res-name" name="name" required class="form-input">
                        </div>
                        <div>
                            <label for="res-phone" class="form-label">Phone *</label>
                            <input type="tel" id="res-phone" name="phone" required class="form-input">
                        </div>
                    </div>
                    <div class="grid grid-2">
                        <div>
                            <label for="res-date" class="form-label">Date *</label>
                            <input type="date" id="res-date" name="date" required class="form-input">
                        </div>
                        <div>
                            <label for="res-time" class="form-label">Time *</label>
                            <input type="time" id="res-time" name="time" required class="form-input">
                        </div>
                    </div>
                    <div>
                        <label for="res-event" class="form-label">Event type (optional)</label>
                        <input type="text" id="res-event" name="event_type" class="form-input" placeholder="Birthday, meeting, etc.">
                    </div>
                    <div>
                        <label for="res-guests" class="form-label">Number of Guests *</label>
                        <div style="display:flex;align-items:center;gap:.5rem">
                            <div class="stepper" aria-label="Number of guests" role="group">
                                <button type="button" class="stepper-btn" data-step="down" aria-label="Decrease guests"> <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19 13H5v-2h14v2z"/></svg> </button>
                                <input type="number" id="res-guests" name="guests" required class="form-input" min="1" value="1" aria-describedby="res-guests-note res-guests-error">
                                <button type="button" class="stepper-btn" data-step="up" aria-label="Increase guests">+</button>
                            </div>
                        </div>
                        <?php $phone = htmlspecialchars($content['business_info']['phone'] ?? ''); ?>
                        <div id="res-guests-note" class="form-note small text-muted" style="display:none;margin-top:.35rem">For parties of 8 or more, please call us at <?php echo $phone ?: 'the restaurant'; ?> to arrange seating.</div>
                        <div id="res-guests-error" class="form-error small" style="display:none;margin-top:.35rem">Please call us for very large parties (over 50 guests).</div>
                    </div>
                    <div id="reservation-confirm" style="margin-top:1rem; display:none">
                        <div class="card form-success" id="reservation-confirm-msg" tabindex="-1">
                            <button id="reservation-confirm-close" type="button" aria-label="Dismiss confirmation" style="float:right;background:none;border:none;font-weight:bold;font-size:1.1rem;cursor:pointer">✕</button>
                            <div id="reservation-confirm-text"></div>
                        </div>
                    </div>
                    <div style="margin-top:1rem">
                        <button type="submit" class="btn btn-primary">Request Reservation</button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- ==============================================
         ABOUT SECTION COMPONENT
         Two-column layout with content and image placeholder
         ============================================== -->
    <section class="section" id="about">
        <div class="container">
            <div class="grid grid-2">
                <div>
                    <?php
                        $aboutHeading = getContent($content, 'about.heading', getContent($content, 'about.title', 'About Our Restaurant'));
                        $aboutBody = getContent($content, 'about.body', getContent($content, 'about.content', ''));
                    ?>
                    <h2 class="section-title text-left"><?php echo htmlspecialchars($aboutHeading); ?></h2>
                    <p class="mb-4"><?php echo nl2br(htmlspecialchars($aboutBody)); ?></p>
                    <a href="#" class="btn btn-primary open-contact" data-contact-message="Hi — I'm interested in learning more about your restaurant and services.">Contact Us</a>
                </div>
                <div class="card">
                    <div class="image-placeholder" style="height:300px;">
                                                <?php
                                                    // Embed a Google Map for the business address when available.
                                                    // We prefer a simple embed using the public `q=` + `output=embed`
                                                    // pattern which does not require an API key. If address is
                                                    // missing fall back to the static image.
                                                    // NOTE: this template's content is stored in $content (not $siteContent)
                                                    $bizAddress = $content['business_info']['address'] ?? '';
                          $mapLinksHtml = '';
                          if ($bizAddress) {
                              $q = rawurlencode($bizAddress);
                              // set a sane default zoom for the embedded map (15 = neighborhood level)
                              $zoom = 15;
                              $mapSrc = "https://www.google.com/maps?q={$q}&z={$zoom}&output=embed";
                              echo '<iframe class="about-map" src="' . htmlspecialchars($mapSrc) . '" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Map showing our location"></iframe>';
                              $gmUrl = 'https://www.google.com/maps?q=' . $q;
                              $dirUrl = 'https://www.google.com/maps/dir/?api=1&destination=' . $q;
                              // Use business name when available to make aria-labels descriptive
                              $bizName = htmlspecialchars($content['business_info']['name'] ?? 'Thunder Road Bar and Grill');
                              $pinSvg = '<svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"/></svg>';
                              $dirSvg = '<svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 0-.9-3.8L12 18l3.8-8.1c-.9.4-1.9.6-2.8.6C8.7 10.5 6 7.8 6 4.5 6 3.7 6.1 3 6.2 2.2 7.9 3 9.7 3.5 11.5 3.5c6 0 9.5 3.6 9.5 8z"/></svg>';
                              $mapLinksHtml = '<div class="map-links"><a class="btn btn-secondary" href="' . htmlspecialchars($gmUrl) . '" target="_blank" rel="noopener noreferrer" title="Open ' . $bizName . ' in Google Maps" aria-label="Open ' . $bizName . ' in Google Maps" aria-pressed="false">' . $pinSvg . '<span class="label">Open map</span></a><a class="btn btn-outline" href="' . htmlspecialchars($dirUrl) . '" target="_blank" rel="noopener noreferrer" title="Get directions to ' . $bizName . '" aria-label="Get directions to ' . $bizName . '" aria-pressed="false">' . $dirSvg . '<span class="label">Directions</span></a></div>';
                          } else {
                              echo '<img src="assets/images/about-image.jpg" alt="About our restaurant" class="responsive-image">';
                              $pinSvg = '<svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"/></svg>';
                              $dirSvg = '<svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 0-.9-3.8L12 18l3.8-8.1c-.9.4-1.9.6-2.8.6C8.7 10.5 6 7.8 6 4.5 6 3.7 6.1 3 6.2 2.2 7.9 3 9.7 3.5 11.5 3.5c6 0 9.5 3.6 9.5 8z"/></svg>';
                              $bizName = htmlspecialchars($content['business_info']['name'] ?? 'Thunder Road Bar and Grill');
                              $mapLinksHtml = '<div class="map-links"><a class="btn btn-secondary" href="https://www.google.com/maps" target="_blank" rel="noopener noreferrer" title="Open ' . $bizName . ' in Google Maps" aria-label="Open ' . $bizName . ' in Google Maps" aria-pressed="false">' . $pinSvg . '<span class="label">Open map</span></a><a class="btn btn-outline" href="https://www.google.com/maps" target="_blank" rel="noopener noreferrer" title="Get directions to ' . $bizName . '" aria-label="Get directions to ' . $bizName . '" aria-pressed="false">' . $dirSvg . '<span class="label">Directions</span></a></div>';
                          }
                        ?>
                    </div>
                    <?php
                        // Render the map action buttons below the map container so they don't overlap the iframe
                        echo $mapLinksHtml ?? '';
                    ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
    // Reservation confirmation helper: if the page is loaded with
    // ?success=1&guests=N in the hash/query, show a confirmation message.
    (function(){
        try {
            var params = new URLSearchParams(window.location.hash.replace(/^#/, '?'));
            if (!params || params.get('success') !== '1') return;
            var guests = params.get('guests') || '';
            var wrap = document.getElementById('reservation-confirm');
            var outer = document.getElementById('reservation-confirm-msg');
            var text = document.getElementById('reservation-confirm-text');
            if (!wrap || !outer || !text) return;

            // Respect user dismissal stored in localStorage
            var dismissKey = 'reservation_confirm_dismissed';
            if (localStorage.getItem(dismissKey) === '1') return;

            text.textContent = 'Thank you! Your reservation request has been received' + (guests ? (' for ' + guests + ' guest' + (guests === '1' ? '' : 's')) : '') + '. We will contact you to confirm.';
            wrap.style.display = 'block';
            outer.focus({preventScroll:true});

            // Wire dismiss button to hide and remember dismissal
            var close = document.getElementById('reservation-confirm-close');
            if (close) {
                close.addEventListener('click', function(){
                    wrap.style.display = 'none';
                    try { localStorage.setItem(dismissKey, '1'); } catch(e){}
                });
            }

            // clear hash so refreshing doesn't re-show
            try { history.replaceState(null, '', window.location.pathname + window.location.search + '#reservation'); } catch(e){}
        } catch(e) { /* non-fatal */ }
    })();
    </script>

        <script>
            // Menu card expand -> full-width overlay panel. Replaces inline expansion
            (function(){
                // Create overlay element and append to body (so it's available)
                var overlay = document.createElement('div'); overlay.className = 'section-overlay';
                overlay.innerHTML = '<div class="panel" role="dialog" aria-modal="true"><div class="panel-header"><h3 id="overlay-title">Section</h3><button class="close-btn" aria-label="Close">Close</button></div><div class="panel-body" id="overlay-body"></div></div>';
                document.body.appendChild(overlay);

                var openBtnLabel = function(btn, open){ var label = btn.querySelector('.expand-label'); if (label) label.textContent = open ? 'Less' : 'View All'; };

                function openOverlayForButton(btn){
                    var card = btn.closest('.menu-card'); if (!card) return;
                    var title = card.querySelector('.menu-title');
                    var body = overlay.querySelector('#overlay-body');
                    var titleEl = overlay.querySelector('#overlay-title');
                    // Find the embedded JSON for this section (prioritize data-section-idx)
                    var sectionIdx = card.getAttribute('data-section-idx');
                    var sectionId = card.getAttribute('data-section-id') || card.querySelector('.menu-title') && card.querySelector('.menu-title').textContent;
                    var jsonEl = document.querySelector('.menu-section-data[data-section-idx="' + sectionIdx + '"]') || document.querySelector('.menu-section-data[data-section-id="' + sectionId + '"]');
                    body.innerHTML = '';
                    if (jsonEl) {
                        try {
                            var data = JSON.parse(jsonEl.textContent || jsonEl.innerText || '{}');
                            renderSectionIntoOverlay(data, body);
                        } catch(e) {
                            body.textContent = 'Error loading section.';
                        }
                    } else {
                        // fallback: clone existing HTML details if JSON not found
                        var details = card.querySelector('.menu-details');
                        if (details) body.appendChild(details.cloneNode(true));
                    }
                    titleEl.textContent = title ? title.textContent : 'Section';

                    // animate open by adding the show class
                    overlay.classList.add('show');
                    document.body.classList.add('overlay-open');
                    // set aria
                    btn.setAttribute('aria-expanded','true');
                    openBtnLabel(btn, true);
                    // remember which button opened the overlay so close can restore state
                    overlay._openedBy = btn;
                }

                function closeOverlay(){
                    var btn = overlay._openedBy;
                    // remove show to run the fade/scale out animation
                    overlay.classList.remove('show');
                    document.body.classList.remove('overlay-open');
                    if (btn) { btn.setAttribute('aria-expanded','false'); openBtnLabel(btn, false); }
                    overlay._openedBy = null;
                }

                // Render section data into the overlay body element
                function renderSectionIntoOverlay(section, container) {
                    // title/details
                    if (section.details) {
                        var hd = document.createElement('div'); hd.className = 'menu-details-header';
                        if (Array.isArray(section.details)) section.details.forEach(function(d){ var p = document.createElement('div'); p.className = 'menu-section-details small'; p.textContent = d; hd.appendChild(p); });
                        else { var p = document.createElement('div'); p.className = 'menu-section-details small'; p.textContent = section.details; hd.appendChild(p); }
                        container.appendChild(hd);
                    }
                    var itemsWrap = document.createElement('div'); itemsWrap.className = 'section-items';
                    (section.items || []).forEach(function(it){
                        var card = document.createElement('div'); card.className = 'card section-item';
                        if (it.image) { var img = document.createElement('img'); img.src = it.image.match(/^https?:\/\//) ? it.image : ('uploads/images/' + it.image.replace(/^\//,'') ); img.alt = it.title || ''; card.appendChild(img); }
                        var meta = document.createElement('div'); meta.className = 'preview-meta';
                        var row = document.createElement('div'); row.className = 'preview-row';
                        var left = document.createElement('div'); left.style.fontWeight = '700'; left.textContent = it.title || '';
                        row.appendChild(left);
                        var rp = '';
                        if (Array.isArray(it.quantities) && it.quantities.length) {
                            if (it.quantities.length >= 2) {
                                var segs = [];
                                for (var qi = 0; qi < 2; qi++) {
                                    var qopt = it.quantities[qi] || {};
                                    var label = qopt.label ? qopt.label : (qopt.value !== undefined ? String(qopt.value) : '');
                                    var price = qopt.price ? qopt.price : '';
                                    if (price) {
                                            if (section.id === 'wings-tenders') {
                                                // prefer numeric value (e.g., 3) when available; fall back to label
                                                var num = (qopt.value !== undefined && String(qopt.value) !== '') ? String(qopt.value) : label;
                                                if (num) segs.push(num + ' for $' + price);
                                                else if (label) segs.push(label + ' for $' + price);
                                                else segs.push('$' + price);
                                            } else {
                                                if (label) segs.push(label + ' $' + price);
                                                else segs.push('$' + price);
                                            }
                                        }
                                }
                                if (segs.length) rp = segs.join(' / ');
                                else if (it.price) rp = '$' + it.price;
                            } else {
                                var firstQ = it.quantities[0] || {};
                                var lead = firstQ.label ? firstQ.label : (firstQ.value !== undefined ? String(firstQ.value) : '');
                                if (firstQ.price) rp = (lead ? lead + ' $' : '$') + firstQ.price;
                                else if (it.price) rp = '$' + it.price;
                            }
                        } else if (it.price) rp = '$' + it.price;
                        if (rp) { var priceDiv = document.createElement('div'); priceDiv.className = 'menu-price'; priceDiv.innerHTML = '<span class="price-badge">' + rp + '</span>'; row.appendChild(priceDiv); }
                        meta.appendChild(row);
                        if (it.short) { var s = document.createElement('div'); s.className = 'small muted-text'; s.textContent = it.short; meta.appendChild(s); }
                        if (Array.isArray(it.quantities) && it.quantities.length) {
                            var opts = document.createElement('div'); opts.className = 'menu-qty-options small'; opts.style.marginTop = '.4rem'; var parts = [];
                            it.quantities.forEach(function(q){ var seg=''; if (q.label) seg = q.label; else if (q.value) seg = String(q.value); if (seg) parts.push(seg); });
                            if (parts.length) opts.textContent = 'Options: ' + parts.join(' | ');
                            meta.appendChild(opts);
                        }

                        // Always render the item description when present. Previously this
                        // was only rendered when quantities were absent; that hid descriptions
                        // for items that have quantity-price pairs. Show description after
                        // the quantity options for clarity.
                        if (it.description) {
                            var d = document.createElement('div');
                            d.style.marginTop = '.4rem';
                            d.innerHTML = escapeHtml(it.description).replace(/\n/g,'<br>');
                            meta.appendChild(d);
                        }
                        card.appendChild(meta);
                        itemsWrap.appendChild(card);
                    });
                    container.appendChild(itemsWrap);
                }

                function escapeHtml(str){ if (!str) return ''; return String(str).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[c]; }); }

                document.addEventListener('click', function(e){
                    var btn = e.target.closest && e.target.closest('.expand-btn');
                    if (btn) { e.preventDefault(); openOverlayForButton(btn); return; }

                    var close = e.target.closest && e.target.closest('.close-btn');
                    if (close) { closeOverlay(); return; }

                    // click outside panel should close overlay
                    if (overlay.classList.contains('show') && !e.target.closest('.panel')) { closeOverlay(); }
                });

                // keyboard support: close on Escape
                document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && overlay.classList.contains('show')) { closeOverlay(); } });
            })();
        </script>

    <!-- Footer will be rendered at the end of the page to ensure it stays below main content -->

    <!-- ==============================================
         CAREERS / JOB APPLICATION SECTION (moved into main body)
         ============================================== -->
    <section class="section" id="job-application">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Join Our Team</h2>
            <p class="section-subtitle">Apply for a position at our restaurant and become part of our family!</p>
        </div>
        <div class="container-narrow">
            <form class="card" id="job-application-form" action="/contact.php" method="post">
          <!-- Honeypot field to deter bots (hidden from users) -->
          <input type="text" name="hp_field" id="hp-field" autocomplete="off" tabindex="-1"
              style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;border:0;">
                <!-- Personal Information -->
                <h3 style="margin-bottom: 1rem; color: var(--primary-color);">Personal Information</h3>
                <div class="grid grid-2">
                    <div>
                        <label for="applicant-first-name" class="form-label">First Name *</label>
                        <input type="text" id="applicant-first-name" name="first_name" required class="form-input">
                    </div>
                    <div>
                        <label for="applicant-last-name" class="form-label">Last Name *</label>
                        <input type="text" id="applicant-last-name" name="last_name" required class="form-input">
                    </div>
                </div>
                
                <div class="grid grid-2">
                    <div>
                        <label for="applicant-email" class="form-label">Email Address *</label>
                        <input type="email" id="applicant-email" name="email" required class="form-input">
                    </div>
                    <div>
                        <label for="applicant-phone" class="form-label">Phone Number *</label>
                        <input type="tel" id="applicant-phone" name="phone" required class="form-input">
                    </div>
                </div>
                
                <div class="grid grid-2">
                    <label for="address" class="form-label">Address *</label>
                    <textarea id="address" name="address" rows="2" required placeholder="Street address, city, state, zip" class="form-input"></textarea>
                </div>
                
                <div class="grid grid-2">
                    <div>
                        <label for="age" class="form-label">Age *</label>
                        <div class="stepper" role="group" aria-label="Age">
                                <button type="button" class="stepper-btn" data-step="down" aria-label="Decrease age"> <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19 13H5v-2h14v2z"/></svg> </button>
                            <input type="number" id="age" name="age" min="16" max="100" required class="form-input">
                            <button type="button" class="stepper-btn" data-step="up" aria-label="Increase age"> <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19 13H13v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg> </button>
                        </div>
                        <div id="age-note" class="form-note small" style="margin-top:.35rem">Minimum age to apply is 16 years.</div>
                    </div>
                    <div>
                        <label for="eligible-to-work" class="form-label">Eligible to work in US? *</label>
                        <select id="eligible-to-work" name="eligible_to_work" required class="form-input">
                            <option value="">Select</option>
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                </div>
                
                <!-- Position Information -->
                <h3 style="margin: 2rem 0 1rem 0; color: var(--primary-color);">Position Information</h3>
                <div class="grid grid-2">
                    <div>
                        <label for="position-desired" class="form-label">Position Desired *</label>
                        <select id="position-desired" name="position_desired" required class="form-input">
                            <option value="">Select Position</option>
                            <option value="server">Server</option>
                            <option value="host">Host/Hostess</option>
                            <option value="bartender">Bartender</option>
                            <option value="cook">Line Cook</option>
                            <option value="prep-cook">Prep Cook</option>
                            <option value="dishwasher">Dishwasher</option>
                            <option value="manager">Manager</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label for="employment-type" class="form-label">Employment Type *</label>
                        <select id="employment-type" name="employment_type" required class="form-input">
                            <option value="">Select Type</option>
                            <option value="full-time">Full Time</option>
                            <option value="part-time">Part Time</option>
                            <option value="seasonal">Seasonal</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-2">
                    <div>
                        <label for="desired-salary" class="form-label">Desired Salary/Hourly Rate</label>
                        <input type="text" id="desired-salary" name="desired_salary" class="form-input" placeholder="e.g., $15/hour">
                    </div>
                    <div>
                        <label for="start-date" class="form-label">Available Start Date</label>
                        <input type="date" id="start-date" name="start_date" class="form-input">
                    </div>
                </div>
                
                <!-- Availability -->
                <h3 style="margin: 2rem 0 1rem 0; color: var(--primary-color);">Availability</h3>
                <p style="margin-bottom: 1rem; color: var(--text-secondary);">Check all days you are available to work:</p>
                <div class="grid grid-2">
                    <div>
                        <label class="form-checkbox">
                            <input type="checkbox" name="availability[]" value="monday">
                            <span>Monday</span>
                        </label>
                        <label class="form-checkbox">
                            <input type="checkbox" name="availability[]" value="tuesday">
                            <span>Tuesday</span>
                        </label>
                        <label class="form-checkbox">
                            <input type="checkbox" name="availability[]" value="wednesday">
                            <span>Wednesday</span>
                        </label>
                        <label class="form-checkbox">
                            <input type="checkbox" name="availability[]" value="thursday">
                            <span>Thursday</span>
                        </label>
                    </div>
                    <div>
                        <label class="form-checkbox">
                            <input type="checkbox" name="availability[]" value="friday">
                            <span>Friday</span>
                        </label>
                        <label class="form-checkbox">
                            <input type="checkbox" name="availability[]" value="saturday">
                            <span>Saturday</span>
                        </label>
                        <label class="form-checkbox">
                            <input type="checkbox" name="availability[]" value="sunday">
                            <span>Sunday</span>
                        </label>
                        <label class="form-checkbox">
                            <input type="checkbox" name="availability[]" value="holidays">
                            <span>Holidays</span>
                        </label>
                    </div>
                </div>
                
                <div class="grid grid-2">
                    <div>
                        <label for="shift-preference" class="form-label">Shift Preference</label>
                        <select id="shift-preference" name="shift_preference" class="form-input">
                            <option value="">No Preference</option>
                            <option value="morning">Morning (8am-4pm)</option>
                            <option value="evening">Evening (4pm-close)</option>
                            <option value="night">Night (close)</option>
                        </select>
                    </div>
                    <div>
                        <label for="hours-per-week" class="form-label">Preferred Hours per Week</label>
                        <div class="stepper" role="group" aria-label="Preferred hours per week">
                            <button type="button" class="stepper-btn" data-step="down" aria-label="Decrease hours"> <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19 13H5v-2h14v2z"/></svg> </button>
                            <input type="number" id="hours-per-week" name="hours_per_week" min="1" max="60" class="form-input">
                            <button type="button" class="stepper-btn" data-step="up" aria-label="Increase hours"> <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19 13H13v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg> </button>
                        </div>
                        <div class="form-note small" style="margin-top:.35rem">Typical availability: 20–40 hours per week.</div>
                    </div>
                </div>
                
                <!-- Experience -->
                <h3 style="margin: 2rem 0 1rem 0; color: var(--primary-color);">Experience</h3>
                <div>
                    <label for="restaurant-experience" class="form-label">Restaurant Experience</label>
                    <textarea id="restaurant-experience" name="restaurant_experience" rows="4" placeholder="Describe your previous restaurant or food service experience..." class="form-input"></textarea>
                </div>
                
                <div>
                    <label for="other-experience" class="form-label">Other Relevant Experience</label>
                    <textarea id="other-experience" name="other_experience" rows="3" placeholder="Any other work experience that might be relevant..." class="form-input"></textarea>
                </div>
                
                <div>
                    <label for="why-work-here" class="form-label">Why do you want to work here? *</label>
                    <textarea id="why-work-here" name="why_work_here" rows="3" required placeholder="Tell us why you're interested in joining our team..." class="form-input"></textarea>
                </div>
                
                <!-- References -->
                <div>
                    <label for="references" class="form-label">References</label>
                    <textarea id="references" name="references" rows="3" placeholder="Please provide 2-3 professional references (name, relationship, phone number)" class="form-input"></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary form-submit-btn">Submit Application</button>
            </form>
        </div>
    </div>
</section>
    <footer class="footer" id="contact">
        <div class="container">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
                <div class="small footer-left">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($content['business_info']['name'] ?? 'Thunder Road Bar and Grill'); ?>. All rights reserved.</div>
                <div class="small footer-center center">
                    <?php $biz = $content['business_info'] ?? []; ?>
                    <div class="biz-name" style="font-weight:700"><?php echo htmlspecialchars($biz['name'] ?? 'Thunder Road Bar and Grill'); ?></div>
                    <div class="small biz-address"><?php echo htmlspecialchars($biz['address'] ?? ''); ?></div>
                    <div class="small biz-contact"><a href="tel:<?php echo preg_replace('/[^0-9+]/','', $biz['phone'] ?? ''); ?>"><?php echo htmlspecialchars($biz['phone'] ?? ''); ?></a> &nbsp;|&nbsp; <a href="mailto:<?php echo htmlspecialchars($biz['email'] ?? ''); ?>"><?php echo htmlspecialchars($biz['email'] ?? ''); ?></a></div>
                    <div class="small" style="margin-top:.25rem"><a href="#" id="footer-hours-link">Hours</a></div>
                </div>
                <div class="small footer-right right">
                    <nav class="footer-links" aria-label="Footer navigation">
                        <a href="#menu">Menu</a>
                        <a href="#reservation">Reservations</a>
                        <a href="#about">About</a>
                        <a href="#job-application">Careers</a>
                        <a href="#" id="footer-contact-link">Contact</a>
                    </nav>
                </div>
                <!-- footer preferences removed: theme now follows system preference only -->
            </div>
        </div>
    </footer>

    <!-- External JavaScript File -->
    <script>
    // Accessibility helpers:
    // 1) Mark the current nav link with aria-current when the user scrolls to a section.
    // 2) Briefly toggle aria-pressed on map action buttons when clicked to provide
    //    assistive feedback; they remain links (open in new tab) so we don't change default behavior.
    (function(){
        try {
            // aria-current for nav links based on scroll position
            var navLinks = document.querySelectorAll('.nav-link');
            var sections = Array.from(navLinks).map(function(a){
                var href = a.getAttribute('href') || ''; if (!href.startsWith('#')) return null; return document.querySelector(href);
            });
            function updateCurrent(){
                var top = window.scrollY + 96; // header offset
                for (var i=0;i<navLinks.length;i++){
                    var a = navLinks[i]; var sec = sections[i];
                    if (!sec) { a.removeAttribute('aria-current'); continue; }
                    var rect = sec.getBoundingClientRect();
                    var inView = (rect.top + window.scrollY) <= top && (rect.bottom + window.scrollY) > top;
                    if (inView) a.setAttribute('aria-current', 'true'); else a.removeAttribute('aria-current');
                }
            }
            window.addEventListener('scroll', updateCurrent, {passive:true});
            window.addEventListener('resize', updateCurrent);
            updateCurrent();

            // aria-pressed toggling for map buttons
            var mapBtns = document.querySelectorAll('.map-links a[aria-pressed]');
            mapBtns.forEach(function(b){
                b.addEventListener('click', function(){
                    try { b.setAttribute('aria-pressed','true'); } catch(e){}
                    // revert after a short delay so assistive tech notices the change
                    setTimeout(function(){ try { b.setAttribute('aria-pressed','false'); } catch(e){} }, 1200);
                });
            });

            // Reveal map action buttons with a subtle animation once the iframe has loaded
            var mapLinks = document.querySelector('.map-links');
            if (mapLinks) {
                var iframe = document.querySelector('.about-map');
                var reveal = function(){ mapLinks.classList.add('visible'); };
                if (iframe) {
                    // If the iframe is same-origin the load event will fire normally; otherwise it still fires on most browsers
                    iframe.addEventListener('load', reveal);
                    // fallback: reveal after short timeout if load hasn't fired
                    setTimeout(reveal, 600);
                } else {
                    // no iframe (fallback image) — reveal immediately after a tiny delay
                    setTimeout(reveal, 120);
                }
            }
        } catch(e) { /* non-fatal */ }
    })();
    </script>
    <script src="assets/js/main.js"></script>
    <script>
    // Footer contact modal: create markup dynamically to avoid altering server-side template flow
    (function(){
        var footerLink = document.getElementById('footer-contact-link');
        if (!footerLink) return;

        // Modal markup
        var modalHtml = ''+
        '<div id="contact-modal" class="modal" role="dialog" aria-hidden="true" aria-labelledby="contact-modal-title">'+
          '<div class="modal-backdrop" id="contact-modal-backdrop"></div>'+
          '<div class="modal-panel" role="document">'+
            '<button type="button" class="modal-close" aria-label="Close contact form">✕</button>'+
            '<h2 id="contact-modal-title">Contact Us</h2>'+
            '<form id="footer-contact-form" class="card">'+
              '<input type="text" name="hp_field" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;border:0;" tabindex="-1" autocomplete="off">'+
              '<div class="grid grid-2"><div><label class="form-label">First Name *</label><input type="text" name="first_name" required class="form-input"></div><div><label class="form-label">Last Name *</label><input type="text" name="last_name" required class="form-input"></div></div>'+
              '<div class="grid grid-2"><div><label class="form-label">Email *</label><input type="email" name="email" required class="form-input"></div><div><label class="form-label">Phone *</label><input type="tel" name="phone" required class="form-input"></div></div>'+
              '<div><label class="form-label">Message *</label><textarea name="message" rows="4" required class="form-input" placeholder="How can we help?"></textarea></div>'+
              '<div style="margin-top:1rem"><button type="submit" class="btn btn-primary">Send Message</button></div>'+
            '</form>'+
          '</div>'+
        '</div>';

        var wrapper = document.createElement('div'); wrapper.innerHTML = modalHtml;
        document.body.appendChild(wrapper.firstChild);

        var modal = document.getElementById('contact-modal');
        var backdrop = document.getElementById('contact-modal-backdrop');
        var closeBtn = modal.querySelector('.modal-close');
        var form = document.getElementById('footer-contact-form');

    var removeFocusTrap = null;
    function openModal(e){
        if (e && e.preventDefault) e.preventDefault();
        // determine opener element (either event target or element passed directly)
        var opener = (e && e.currentTarget) ? e.currentTarget : (e && e.target) ? e.target : null;
        if (e && e.dataset && e.dataset.contactMessage) opener = e;
        modal.setAttribute('aria-hidden','false'); modal.classList.add('open'); document.body.style.overflow='hidden';
        // prefill message if opener carries a data-contact-message
        try {
            var msgNode = form.querySelector('[name="message"]');
            if (opener && opener.dataset && opener.dataset.contactMessage && msgNode) {
                msgNode.value = opener.dataset.contactMessage;
                msgNode.focus();
            } else {
                var first = form.querySelector('[name="first_name"]'); if (first) first.focus();
            }
        } catch(e) { var first = form.querySelector('[name="first_name"]'); if (first) first.focus(); }
        removeFocusTrap = trapFocus(modal);
    }
    function closeModal(){ modal.setAttribute('aria-hidden','true'); modal.classList.remove('open'); document.body.style.overflow=''; if (typeof removeFocusTrap === 'function') { removeFocusTrap(); removeFocusTrap = null; } }

    footerLink.addEventListener('click', openModal);
    // Also attach to any elements that should open the contact modal (e.g., Learn More button)
    var extraOpeners = document.querySelectorAll('.open-contact');
    extraOpeners.forEach(function(el){ if (el !== footerLink) el.addEventListener('click', openModal); });
        backdrop.addEventListener('click', closeModal);
        closeBtn.addEventListener('click', closeModal);

        // When the FormHandler shows success message, close the modal automatically
        var observer = new MutationObserver(function(m){
            m.forEach(function(rec){
                if (rec.addedNodes && rec.addedNodes.length) {
                    rec.addedNodes.forEach(function(n){
                        if (n.classList && n.classList.contains('form-success')) {
                            // close the modal after a short delay so user sees the message
                            setTimeout(closeModal, 900);
                        }
                    });
                }
            });
        });
        observer.observe(form.parentNode, { childList: true });

        // Ensure modal form uses the same submission endpoint as other forms
        form.setAttribute('action', '/contact.php');
        // Let FormHandler intercept submission (no data-no-ajax attribute)

        // Move modal styles into assets/css/styles.css; implement focus trap for accessibility
        function trapFocus(modalEl) {
            var focusableSelectors = 'a[href], area[href], input:not([disabled]):not([type=hidden]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), [tabindex]:not([tabindex="-1"])';
            var focusable = Array.from(modalEl.querySelectorAll(focusableSelectors)).filter(function(el){ return el.offsetParent !== null; });
            if (!focusable.length) return function(){};
            var first = focusable[0]; var last = focusable[focusable.length - 1];
            function keyHandler(e) {
                if (e.key === 'Tab') {
                    if (e.shiftKey) { // shift + tab
                        if (document.activeElement === first) { e.preventDefault(); last.focus(); }
                    } else { // tab
                        if (document.activeElement === last) { e.preventDefault(); first.focus(); }
                    }
                }
                if (e.key === 'Escape') { closeModal(); }
            }
            document.addEventListener('keydown', keyHandler);
            return function remove() { document.removeEventListener('keydown', keyHandler); };
        }
    })();
        // Hours modal: show business hours from content.json
        (function(){
                var hoursLink = document.getElementById('footer-hours-link');
                if (!hoursLink) return;
                // Build markup using server-side hours injected into JS
                var hoursData = <?php echo json_encode($content['hours'] ?? new stdClass()); ?>;
                var html = ''+
                '<div id="hours-modal" class="modal" role="dialog" aria-hidden="true" aria-labelledby="hours-modal-title">'+
                    '<div class="modal-backdrop" id="hours-modal-backdrop"></div>'+
                    '<div class="modal-panel" role="document">'+
                        '<button type="button" class="modal-close" aria-label="Close hours">✕</button>'+
                        '<h2 id="hours-modal-title">Hours</h2>'+
                        '<div class="card">'+
                            '<div class="hours-list">';
                // Determine today's key in the same format as the hours object
                var daysMap = {0:'sunday',1:'monday',2:'tuesday',3:'wednesday',4:'thursday',5:'friday',6:'saturday'};
                var todayKey = daysMap[new Date().getDay()];
                for (var d in hoursData) {
                    if (!hoursData.hasOwnProperty(d)) continue;
                    var isToday = (d.toLowerCase() === todayKey);
                    var cls = isToday ? 'hours-today' : '';
                    var badge = isToday ? ' <span class="today-badge">Today</span>' : '';
                    html += '<div class="'+cls+'" style="display:flex;justify-content:space-between;padding:.25rem 0"><div style="text-transform:capitalize">'+d.replace(/_/g,' ') + badge +'</div><div>'+hoursData[d]+'</div></div>';
                }
                html +=      '</div>'+
                        '</div>'+
                    '</div>'+
                '</div>';
                var wrap = document.createElement('div'); wrap.innerHTML = html;
                document.body.appendChild(wrap.firstChild);
                var modal = document.getElementById('hours-modal');
                var backdrop = document.getElementById('hours-modal-backdrop');
                var closeBtn = modal.querySelector('.modal-close');
                function open(){ modal.setAttribute('aria-hidden','false'); modal.classList.add('open'); document.body.style.overflow='hidden'; trapFocus(modal); }
                function close(){ modal.setAttribute('aria-hidden','true'); modal.classList.remove('open'); document.body.style.overflow=''; }
                hoursLink.addEventListener('click', function(e){ e.preventDefault(); open(); });
                backdrop.addEventListener('click', close);
                closeBtn.addEventListener('click', close);
        })();
        // Toggle fixed footer only when its content fits the configured height
        (function(){
            function updateFooterFixed() {
                var footer = document.querySelector('.footer');
                if (!footer) return;
                // compute required height for footer content
                footer.classList.remove('footer--fixed');
                document.body.classList.remove('footer-has-fixed-footer');
                var required = footer.scrollHeight;
                // read CSS var --footer-height (fallback to 96)
                var cssVal = getComputedStyle(document.documentElement).getPropertyValue('--footer-height') || '96px';
                var footerHeight = parseInt(cssVal, 10) || 96;
                if (required <= footerHeight) {
                    footer.classList.add('footer--fixed');
                    document.body.classList.add('footer-has-fixed-footer');
                } else {
                    footer.classList.remove('footer--fixed');
                    document.body.classList.remove('footer-has-fixed-footer');
                }
            }
            window.addEventListener('load', updateFooterFixed);
            window.addEventListener('resize', function(){ setTimeout(updateFooterFixed, 120); });
            // also attempt after fonts/images load
            window.addEventListener('DOMContentLoaded', function(){ setTimeout(updateFooterFixed, 200); });
        })();
        // Reservation guests advisory: show note for large parties and prevent very large parties
        (function(){
            var guests = document.getElementById('res-guests');
            var note = document.getElementById('res-guests-note');
            var err = document.getElementById('res-guests-error');
            var form = document.getElementById('reservation-form');
            if (!guests || !form) return;
            function update(){
                var v = parseInt(guests.value, 10) || 0;
                if (v >= 8 && v <= 50) { note.style.display = 'block'; err.style.display = 'none'; }
                else if (v > 50) { note.style.display = 'none'; err.style.display = 'block'; }
                else { note.style.display = 'none'; err.style.display = 'none'; }
            }
            guests.addEventListener('input', update);
            form.addEventListener('submit', function(e){
                var v = parseInt(guests.value, 10) || 0;
                if (v > 50) {
                    e.preventDefault();
                    guests.focus();
                    update();
                    return false;
                }
            });
        })();
    </script>
</body>
</html>