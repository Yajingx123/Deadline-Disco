<?php
if (!isset($pageTitle)) $pageTitle = 'Vocabulary';
if (!isset($activeNav)) $activeNav = '';
$hideGlobalHomeNav = $hideGlobalHomeNav ?? false;
$fullTitle = $pageTitle . ' · ' . ($siteName ?? 'Vocabulary Practice');
$base = $baseHref ?? '';
$authUser = vocab_current_user();
$userInitial = strtoupper(substr((string)($authUser['username'] ?? 'U'), 0, 2));
$homeVocabularyUrl = APP_HOME_URL . '?module=Lexis';
$logoutUrl = $base . 'logout.php';
$acadbeatMain = ACADBEAT_MAIN_ORIGIN;
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo htmlspecialchars($fullTitle); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet" />
    <?php if (!$hideGlobalHomeNav): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($acadbeatMain); ?>/shared-nav.css" />
    <script src="<?php echo htmlspecialchars($acadbeatMain); ?>/shared/acadbeat-local-config.js"></script>
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo $base; ?>frontend/styles.css" />
  </head>
  <body<?php echo !$hideGlobalHomeNav ? ' class="with-acadbeat-shared-nav"' : ''; ?>>
    <a class="skip" href="#main">Skip to content</a>
    <div class="page">
      <?php if (!$hideGlobalHomeNav): ?>
      <div id="acadbeatNav" aria-label="AcadBeat site navigation"></div>
      <?php endif; ?>
      <main class="page__main" id="main">
