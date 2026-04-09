<?php
if (!isset($pageTitle)) $pageTitle = 'Vocabulary';
if (!isset($activeNav)) $activeNav = '';
$hideGlobalHomeNav = $hideGlobalHomeNav ?? false;
$base = $baseHref ?? '';
$extraStylesheets = $extraStylesheets ?? [];
$extraStylesheets = array_values(array_unique(array_merge([
  $base . 'temp/gameUI-global.css',
], array_map('strval', $extraStylesheets))));
$pageInlineStyles = $pageInlineStyles ?? '';
$pageBodyClass = trim((string)($pageBodyClass ?? ''));
$fullTitle = $pageTitle . ' · ' . ($siteName ?? 'Vocabulary Practice');
$authUser = vocab_current_user();
$userInitial = strtoupper(substr((string)($authUser['username'] ?? 'U'), 0, 2));
$homeVocabularyUrl = APP_HOME_URL . '?module=Lexis';
$logoutUrl = $base . 'logout.php';
$acadbeatMain = ACADBEAT_MAIN_ORIGIN;
$bodyClasses = [];
if (!$hideGlobalHomeNav) {
  $bodyClasses[] = 'with-acadbeat-shared-nav';
}
if ($pageBodyClass !== '') {
  $bodyClasses[] = $pageBodyClass;
}
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
    <link rel="stylesheet" href="<?php echo htmlspecialchars($acadbeatMain); ?>/gates/shared-nav.css" />
    <script src="<?php echo htmlspecialchars($acadbeatMain); ?>/shared/acadbeat-local-config.js"></script>
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo $base; ?>frontend/styles.css" />
    <?php foreach ($extraStylesheets as $stylesheet): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars((string)$stylesheet, ENT_QUOTES, 'UTF-8'); ?>" />
    <?php endforeach; ?>
    <?php if ($pageInlineStyles !== ''): ?>
    <style><?php echo $pageInlineStyles; ?></style>
    <?php endif; ?>
  </head>
  <body<?php echo $bodyClasses ? ' class="' . htmlspecialchars(implode(' ', $bodyClasses), ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
    <a class="skip" href="#main">Skip to content</a>
    <div class="page">
      <?php if (!$hideGlobalHomeNav): ?>
      <div id="acadbeatNav" aria-label="AcadBeat site navigation"></div>
      <?php endif; ?>
      <main class="page__main" id="main">
