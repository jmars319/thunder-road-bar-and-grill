<?php
/**
 * order.php
 * Simple, read-only placeholder for "Order Online" page.
 * - Loads `data/content.json` for display-only content.
 * - Uses file modification time for cache-busting the main stylesheet.
 *
 * This script is intentionally minimal — it does not accept form input
 * and should be safe to serve as a static-like page.
 */

$contentFile = 'data/content.json';
$content = file_exists($contentFile) ? json_decode(file_get_contents($contentFile), true) : [];
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

// Cache-busting for styles
$cssPath = 'assets/css/styles.css';
$cssVersion = file_exists($cssPath) ? filemtime($cssPath) : time();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Order Online — Coming Soon | Thunder Road Bar and Grill</title>
  <link rel="stylesheet" href="<?php echo htmlspecialchars($cssPath . '?v=' . $cssVersion, ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
  <header class="header">
    <div class="container">
      <nav class="navbar">
        <?php
          $logoFile = $content['images']['logo'] ?? '';
          $logoUrl = '';
          if ($logoFile) { $logoUrl = preg_match('#^https?://#i', $logoFile) ? $logoFile : 'uploads/images/'.ltrim($logoFile, '/'); }
        ?>
        <a href="/" class="logo">
          <?php if ($logoUrl): ?>
            <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="<?php echo htmlspecialchars($content['business_info']['name'] ?? 'Thunder Road Bar and Grill'); ?>" style="height:40px; width:auto; display:inline-block; vertical-align:middle">
          <?php else: ?>
            Thunder Road Bar and Grill
          <?php endif; ?>
        </a>
      </nav>
    </div>
  </header>

  <main class="container" style="padding:3rem 1rem">
    <section class="card" style="max-width:720px;margin:0 auto;text-align:center;">
      <h1>Order Online — Coming Soon</h1>
      <p style="color:#555;margin-top:1rem">We're working to add online ordering. Sign up for updates or check back soon.</p>
      <div style="margin-top:1.5rem;display:flex;gap:1rem;justify-content:center">
        <a href="/" class="btn btn-ghost">Back to Home</a>
        <a href="/contact.php" class="btn btn-primary">Contact Us</a>
      </div>
    </section>
  </main>

  <footer class="footer">
    <div class="container" style="padding:2rem 0;text-align:center;color:#777">&copy; <?php echo date('Y'); ?> Thunder Road Bar and Grill</div>
  </footer>
</body>
</html>
