<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'Progress';
$activeNav = 'progress';
require_once __DIR__ . '/includes/header.php';

// UI-only mock data (will be replaced by user records later).
$activeBooks = [
  ['title' => 'Daily & campus', 'learned' => 42, 'total' => 100, 'status' => 'In progress'],
  ['title' => 'CS starter pack', 'learned' => 18, 'total' => 80, 'status' => 'In progress'],
  ['title' => 'Academic essentials', 'learned' => 8, 'total' => 60, 'status' => 'New'],
];

$todayTasks = [
  [
    'title' => '10-minute quick session',
    'meta' => 'Image / Audio / Word completion',
    'done' => 7,
    'total' => 10,
    'status' => 'In progress',
  ],
  [
    'title' => 'Sentence practice (30-minute)',
    'meta' => 'Sentence fill + Sentence choose',
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
        <p class="hero__subtitle" style="margin-bottom:18px">Track what you’re learning now and what you’ve completed today.</p>

        <div class="progressDash">
          <section class="card progressSection" aria-label="Current word books progress">
            <div class="card__head">
              <div>
                <div class="card__title">Current word books</div>
                <div class="card__sub">UI mock. Later this will come from user records.</div>
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
                <div class="card__sub">Task-based progress (sessions, drills, reviews).</div>
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

