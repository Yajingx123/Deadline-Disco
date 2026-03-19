<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'Friends & Groups';
$activeNav = 'friends';
require_once __DIR__ . '/includes/header.php';
?>

        <h1 class="hero__title" style="margin-top:0">Friends & Groups</h1>
        <p class="hero__subtitle" style="margin-bottom:18px">Add friends, group chat, and optionally view progress or rankings.</p>

        <section class="card" style="margin-bottom:18px" aria-label="Add friend">
          <div class="card__head">
            <div>
              <div class="card__title">Add friend</div>
              <div class="card__sub">UI placeholder.</div>
            </div>
          </div>
          <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <input type="text" class="wordbankSearchInput" style="max-width:320px" placeholder="User ID or nickname" />
            <button class="primary" type="button">Send request</button>
          </div>
        </section>

        <section class="card" aria-label="Friends">
          <div class="card__head">
            <div>
              <div class="card__title">Friends</div>
              <div class="card__sub">UI placeholder.</div>
            </div>
          </div>
          <div class="emptyHint">This section will be connected to user data later.</div>
        </section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

