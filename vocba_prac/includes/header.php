<?php
if (!isset($pageTitle)) $pageTitle = 'Vocabulary';
if (!isset($activeNav)) $activeNav = '';
$fullTitle = $pageTitle . ' · ' . ($siteName ?? 'Vocabulary Practice');
$base = $baseHref ?? '';

// "Back to main page" button:
// Keep the URL you came from when you first entered this vocab module,
// then always use it even after navigating within vocab pages.
session_start();
$defaultBackUrl = '../home.html';
$ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
if ($ref !== '' && strpos($ref, 'home.html') !== false) {
  $_SESSION['vocab_return_url'] = $ref;
}
$backUrl = $_SESSION['vocab_return_url'] ?? $defaultBackUrl;
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo htmlspecialchars($fullTitle); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="<?php echo $base; ?>frontend/styles.css" />
  </head>
  <body>
    <a class="skip" href="#main">Skip to content</a>
    <div class="page">
      <header class="topNav" aria-label="Vocabulary navigation">
        <div class="topNav__brand">Vocabulary</div>
        <nav class="topNav__links" aria-label="Main">
          <a class="topNav__link<?php echo $activeNav === 'wordbank' ? ' isActive' : ''; ?>" href="wordbank.php">Word Bank</a>
          <a class="topNav__link<?php echo $activeNav === 'practice' ? ' isActive' : ''; ?>" href="practice.php">Practice</a>
          <a class="topNav__link<?php echo $activeNav === 'progress' ? ' isActive' : ''; ?>" href="progress.php">Progress</a>
        </nav>
        <div class="topNav__actions">
          <div class="avatar" title="User"><span aria-hidden="true">L</span></div>
        </div>
      </header>
      <main class="page__main" id="main">
        <a class="backToMainBtn" href="<?php echo htmlspecialchars($backUrl); ?>" aria-label="Back to main page">
          Back to Main
        </a>
