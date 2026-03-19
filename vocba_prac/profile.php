<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'Profile';
$activeNav = 'profile';
require_once __DIR__ . '/includes/header.php';
?>
        <h1 class="hero__title" style="margin-top:0">Profile</h1>
        <p class="hero__subtitle" style="margin-bottom:18px">Your basic info and learning goals.</p>
        <div class="grid" style="max-width:720px">
          <section class="card progressCard" aria-label="Student profile">
            <div class="progressCard__top">
              <div>
                <div class="progressCard__label">Student</div>
                <div class="progressCard__value" style="font-size:22px" id="profileName">Lenovo</div>
              </div>
              <span class="tag" style="height:32px;padding:0 12px">Pre-college</span>
            </div>
            <div class="miniStats" role="list">
              <div class="miniStat" role="listitem">
                <div class="miniStat__k">Goal</div>
                <div class="miniStat__v" id="statGoal" style="font-size:14px">Build college-ready vocab</div>
              </div>
              <div class="miniStat" role="listitem">
                <div class="miniStat__k">Major interest</div>
                <div class="miniStat__v" id="statMajor">Undeclared</div>
              </div>
              <div class="miniStat" role="listitem">
                <div class="miniStat__k">Streak</div>
                <div class="miniStat__v" id="statStreak">3 days</div>
              </div>
            </div>
            <div class="progressCard__actions">
              <button class="secondary" type="button" id="btnEditProfile">Edit profile</button>
              <button class="secondary" type="button" id="btnAddGoal">Set a goal</button>
            </div>
          </section>
        </div>
        <script>
          document.getElementById('btnEditProfile')?.addEventListener('click', () => alert('Edit profile (UI)'));
          document.getElementById('btnAddGoal')?.addEventListener('click', () => alert('Set a goal (UI)'));
        </script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
