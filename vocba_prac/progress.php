<?php
require_once __DIR__ . '/config.php';
vocab_require_auth();
$pageTitle = 'Progress';
$activeNav = 'progress';
require_once __DIR__ . '/includes/header.php';

$activeBooks = [
  ['title' => 'Daily life & campus', 'learned' => 9, 'total' => 15, 'status' => 'In rotation'],
  ['title' => 'CS core vocabulary', 'learned' => 6, 'total' => 15, 'status' => 'In rotation'],
  ['title' => 'Daily + CS full review', 'learned' => 15, 'total' => 30, 'status' => 'Building'],
];

$todayTasks = [
  [
    'title' => '1-minute reset',
    'meta' => '5 target words, 1 quick round each',
    'done' => 1,
    'total' => 1,
    'status' => 'Done',
  ],
  [
    'title' => '3-5 minute core review',
    'meta' => '10 words with image, audio, spelling, and sentence fill',
    'done' => 4,
    'total' => 6,
    'status' => 'In progress',
  ],
  [
    'title' => '10-minute full mix',
    'meta' => '15 words with sentence choose + sentence fill',
    'done' => 0,
    'total' => 1,
    'status' => 'Not started',
  ],
];

function pct($done, $total): int {
  $t = max(1, (int)$total);
  $d = max(0, (int)$done);
  return (int)max(0, min(100, round(($d / $t) * 100)));
}
?>

        <h1 class="hero__title" style="margin-top:0">Progress</h1>
        <p class="hero__subtitle" style="margin-bottom:18px">Track the current 30-word Daily life + CS practice pack and today’s short review sessions.</p>

        <div class="progressDash">
          <section class="card progressSection" aria-label="Current word books progress">
            <div class="card__head">
              <div>
                <div class="card__title">Current word books</div>
                <div class="card__sub">Temporary dashboard copy aligned to the 15-word Daily set, the 15-word CS set, and the combined 30-word review pack.</div>
              </div>
              <span class="tag"><?php echo count($activeBooks); ?> active</span>
            </div>

            <div class="bookRows" role="list">
              <?php foreach ($activeBooks as $b): ?>
                <?php
                  $learned = (int)$b['learned'];
                  $total = (int)$b['total'];
                  $p = pct($learned, $total);
                ?>
                <div class="bookRow" role="listitem">
                  <div class="bookRow__head">
                    <div class="bookRow__title"><?php echo htmlspecialchars((string)$b['title']); ?></div>
                    <div class="bookRow__right">
                      <span class="pill2"><?php echo htmlspecialchars((string)$b['status']); ?></span>
                      <span class="bookRow__pct"><?php echo $p; ?>%</span>
                    </div>
                  </div>
                  <div class="bookRow__meta"><?php echo $learned; ?> / <?php echo $total; ?> words learned</div>
                  <div class="bar">
                    <div class="bar__fill" style="width:<?php echo $p; ?>%"></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </section>

          <section class="card progressSection" aria-label="Today tasks">
            <div class="card__head">
              <div>
                <div class="card__title">Today</div>
                <div class="card__sub">Short-session practice goals for the new 1 minute / 3-5 minutes / 10 minutes flow.</div>
              </div>
              <span class="tag">Daily tasks</span>
            </div>

            <div class="taskRows" role="list">
              <?php foreach ($todayTasks as $t): ?>
                <?php
                  $done = (int)$t['done'];
                  $total = (int)$t['total'];
                  $p = pct($done, $total);
                ?>
                <div class="taskRow" role="listitem">
                  <div class="taskRow__head">
                    <div class="taskRow__title"><?php echo htmlspecialchars((string)$t['title']); ?></div>
                    <div class="taskRow__right">
                      <span class="pill2"><?php echo htmlspecialchars((string)$t['status']); ?></span>
                      <span class="taskRow__pct"><?php echo $p; ?>%</span>
                    </div>
                  </div>
                  <div class="taskRow__meta"><?php echo htmlspecialchars((string)$t['meta']); ?></div>
                  <div class="taskRow__submeta"><?php echo $done; ?> / <?php echo $total; ?> done</div>
                  <div class="bar">
                    <div class="bar__fill" style="width:<?php echo $p; ?>%"></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </section>
        </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
