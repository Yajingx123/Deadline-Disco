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
    <link rel="stylesheet" href="<?php echo $base; ?>frontend/styles.css" />
  </head>
  <body>
    <a class="skip" href="#main">Skip to content</a>
    <div class="page">
      <?php if (!$hideGlobalHomeNav): ?>
      <header class="globalHomeNav" aria-label="AcadBeat navigation">
        <a class="globalHomeNav__logo" href="<?php echo htmlspecialchars(APP_HOME_URL); ?>">Acad<span>Beat</span></a>
        <nav class="globalHomeNav__menu" aria-label="Main">
          <a class="globalHomeNav__item isActive" href="<?php echo htmlspecialchars($homeVocabularyUrl); ?>">Vocabulary</a>
          <a class="globalHomeNav__item" href="<?php echo htmlspecialchars(APP_HOME_URL . '?module=Insight'); ?>">Academic</a>
          <a class="globalHomeNav__item" href="<?php echo htmlspecialchars(APP_HOME_URL . '?module=Dialogue'); ?>">Forum</a>
          <a class="globalHomeNav__item" href="<?php echo htmlspecialchars(APP_HOME_URL . '?module=Method'); ?>">Technology</a>
        </nav>
        <div class="globalHomeNav__userGroup">
          <a class="globalHomeNav__user" href="<?php echo htmlspecialchars(APP_OWNER_URL); ?>">
            <span class="globalHomeNav__userLabel"><?php echo htmlspecialchars((string)($authUser['username'] ?? 'LOGIN')); ?></span>
            <span class="globalHomeNav__avatar" aria-hidden="true"><?php echo htmlspecialchars($userInitial); ?></span>
          </a>
          <a class="globalHomeNav__logout" href="<?php echo htmlspecialchars($logoutUrl); ?>">Log out</a>
        </div>
      </header>
      <?php endif; ?>
      <main class="page__main" id="main">
