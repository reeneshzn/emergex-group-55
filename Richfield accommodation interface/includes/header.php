<?php
require_once __DIR__ . '/functions.php';

/**
 * Shared page shell. Set these before including:
 *   $page_title  string  browser title
 *   $page_desc   string  meta description
 *   $is_home     bool    true only on index.php — the header is transparent
 *                        over the dark hero there and solid everywhere else.
 *                        On a light page a transparent header renders white
 *                        text on near-white and is invisible until scrolled.
 *   $needs_map   bool    load Leaflet CSS/JS
 */
$page_title = $page_title ?? 'EMERGEX Student Accommodation';
$page_desc  = $page_desc ?? 'Inspected, campus-linked student accommodation within walking distance of every Richfield campus.';
$is_home    = $is_home ?? false;
$needs_map  = $needs_map ?? false;
?>
<!DOCTYPE html>
<html lang="en-ZA">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title) ?></title>
<meta name="description" content="<?= e($page_desc) ?>">
<!-- Unapproved prototype carrying a real institution's identity. Never index it. -->
<meta name="robots" content="noindex, nofollow">

<!-- SVG favicon: one file, sharp at every size, and it carries its own
     background so it reads on a light or a dark browser theme. -->
<link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
<link rel="apple-touch-icon" href="assets/img/favicon.svg">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Lato:wght@300;400;700;900&display=swap" rel="stylesheet">
<?php if ($needs_map): ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<?php endif; ?>
<?php
/* Cache-bust on the file's own modification time. Browsers hold a
   stylesheet for a long time, and during development that means an edit
   silently does nothing until someone thinks to hard-reload - which looks
   exactly like a bug that isn't there. The timestamp changes only when the
   file does, so the cache still works normally between edits. */
$css_path = __DIR__ . '/../assets/css/style.css';
$css_ver  = is_file($css_path) ? filemtime($css_path) : '1';
?>
<link rel="stylesheet" href="assets/css/style.css?v=<?= e((string) $css_ver) ?>">
</head>
<body<?= $is_home ? '' : ' class="page--inner"' ?>>

<a class="skip-link" href="#main">Skip to content</a>

<?php if (SAMPLE_DATA): ?>
<div class="sample-banner" role="note">
  Demonstration prototype — every listing shown is sample data, not real available stock.
</div>
<?php endif; ?>

<header class="site-header<?= $is_home ? '' : ' is-solid' ?>" id="siteHeader">
  <div class="wrap site-header__inner">

    <a class="wordmark" href="index.php" aria-label="EMERGEX, home">
      <!-- Typographic wordmark. It replaces the earlier "Richfield"
           lockup, which used a brand this project has no licence to carry:
           the campuses are Richfield's, the service is not. -->
      <img class="wordmark__logo" src="assets/img/accommodation.png"
           alt="" width="512" height="512" aria-hidden="true">
      <span class="wordmark__text">
        <span class="rf-display wordmark__name">EMERGEX</span>
        <span class="rf-eyebrow wordmark__sub">Since 2026</span>
      </span>
    </a>

    <nav class="site-nav" aria-label="Primary">
      <a href="search.php">Find a room</a>
      <a href="index.php#campuses">Campuses</a>
      <a href="index.php#costs">Costs</a>
      <a href="index.php#parents">For parents</a>
      <a href="index.php#faq">FAQ</a>
    </nav>

    <div class="site-header__actions">
      <button type="button" class="btn btn--ghost shortlist-trigger" id="shortlistOpen"
              aria-label="Open shortlist, 0 saved">
        <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1L12 21l7.7-7.7 1.1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
        <span class="shortlist-trigger__label">Shortlist</span>
        <span class="shortlist-count" id="shortlistCount" aria-hidden="true">0</span>
      </button>

      <a class="btn btn--primary site-header__cta" href="apply.php">Book a viewing</a>

      <button type="button" class="btn btn--ghost menu-toggle" id="menuToggle" aria-label="Open menu" aria-expanded="false">
        <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
      </button>
    </div>
  </div>

  <nav class="mobile-nav" id="mobileNav" aria-label="Mobile" hidden>
    <a href="search.php">Find a room</a>
    <a href="index.php#campuses">Campuses</a>
    <a href="index.php#costs">Costs</a>
    <a href="index.php#parents">For parents</a>
    <a href="index.php#faq">FAQ</a>
    <a class="btn btn--primary" href="apply.php">Book a viewing</a>
  </nav>
</header>

<main id="main">
