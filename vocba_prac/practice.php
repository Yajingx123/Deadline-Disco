<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'Practice';
$activeNav = 'practice';
require_once __DIR__ . '/includes/header.php';
?>

        <h1 class="hero__title" style="margin-top:0">Practice</h1>
        <p class="hero__subtitle" style="margin-bottom:12px">Choose a session length. Image, audio, and spelling exercises will be mixed.</p>

        <div class="card dailyPlan" style="margin-bottom:18px" id="practiceSelectionBar" aria-label="Word books used for this practice">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
            <div>
              <div style="font-weight:700;margin-bottom:4px">Practicing with</div>
              <div id="practiceBookList" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;min-height:28px">—</div>
            </div>
            <a href="./wordbank.php" class="secondary" style="display:inline-flex;align-items:center;text-decoration:none">Change in Word Bank</a>
          </div>
        </div>

        <section class="card">
          <div class="card__head">
            <div>
              <div class="card__title">Session mode</div>
              <div class="card__sub">10 / 30 / 60 minutes.</div>
            </div>
          </div>
          <div class="modeGrid" id="modes">
            <button class="modeCard" type="button" data-minutes="10" id="mode10">
              <div class="modeCard__top">
                <div class="modeCard__title">10 minutes</div>
                <span class="tag">Quick warm-up</span>
              </div>
              <div class="modeCard__desc">Fast review + 1 quick round per word.</div>
              <div class="modeCard__meta">
                <span class="pill2">Image ↔ word</span>
                <span class="pill2">Audio ↔ word</span>
                <span class="pill2">Word completion</span>
              </div>
            </button>
            <button class="modeCard" type="button" data-minutes="30" id="mode30">
              <div class="modeCard__top">
                <div class="modeCard__title">30 minutes</div>
                <span class="tag">Standard</span>
              </div>
              <div class="modeCard__desc">Balanced: more rounds + sentence practice.</div>
              <div class="modeCard__meta">
                <span class="pill2">Sentence fill</span>
                <span class="pill2">Audio ↔ word</span>
                <span class="pill2">Word completion</span>
              </div>
            </button>
            <button class="modeCard" type="button" data-minutes="60" id="mode60">
              <div class="modeCard__top">
                <div class="modeCard__title">60 minutes</div>
                <span class="tag">Deep focus</span>
              </div>
              <div class="modeCard__desc">Intensive: more cycles + sentence choice & fill.</div>
              <div class="modeCard__meta">
                <span class="pill2">Sentence choose</span>
                <span class="pill2">Sentence fill</span>
                <span class="pill2">More cycles</span>
              </div>
            </button>
          </div>
          <div style="margin-top:16px">
            <button class="primary" type="button" id="btnStartSession">Start <span id="selectedMin">10</span>-minute session →</button>
          </div>
        </section>

        <script>
          var STORAGE_KEY = 'vocab_wordbook_selection';
          // 标题映射：从 wordbank.php 同源思路，后续可统一从接口或 PHP 输出
          var TITLES = <?php
            $m = [];
            foreach (db()->query("SELECT slug, title FROM word_books ORDER BY id ASC")->fetchAll() as $r) {
              $m[(string)$r['slug']] = (string)$r['title'];
            }
            echo json_encode($m, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
          ?>;

          function getSelection() {
            try {
              var raw = localStorage.getItem(STORAGE_KEY);
              return raw ? JSON.parse(raw) : ['daily'];
            } catch (e) { return ['daily']; }
          }

          var sel = getSelection();
          var listEl = document.getElementById('practiceBookList');
          if (sel.length === 0) {
            listEl.innerHTML = '<span style="color:var(--danger)">No word book selected.</span> <span style="color:var(--muted);font-size:13px">Choose books in <a href="./wordbank.php" class="linkBtn">Word Bank</a> to practice.</span>';
          } else {
            listEl.innerHTML = sel.map(function(id) { return '<span class="pill2">' + (TITLES[id] || id) + '</span>'; }).join('');
          }

          var selected = 10;
          document.querySelectorAll('.modeCard').forEach(function(card) {
            card.addEventListener('click', function() {
              selected = Number(card.dataset.minutes) || 10;
              document.querySelectorAll('.modeCard').forEach(function(c) { c.classList.remove('isSelected'); });
              card.classList.add('isSelected');
              var el = document.getElementById('selectedMin');
              if (el) el.textContent = selected;
            });
          });
          document.getElementById('mode10').classList.add('isSelected');

          document.getElementById('btnStartSession').addEventListener('click', function() {
            var books = getSelection();
            if (books.length === 0) {
              alert('Select at least one word book in Word Bank first.');
              return;
            }
            var qs = new URLSearchParams();
            qs.set('mode', String(selected));
            qs.set('books', books.join(','));
            window.location.href = './practice-session.php?' + qs.toString();
          });
        </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

