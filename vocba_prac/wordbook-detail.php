<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'Word Book';
$activeNav = 'wordbank';
require_once __DIR__ . '/includes/header.php';

$slug = isset($_GET['id']) ? trim((string)$_GET['id']) : 'daily';
if ($slug === '') $slug = 'daily';

// 词书信息 + 词数
$stmt = db()->prepare("
  SELECT
    wb.id,
    wb.slug,
    wb.title,
    wb.description,
    COUNT(DISTINCT wbw.word_id) AS word_count
  FROM word_books wb
  LEFT JOIN word_book_words wbw ON wbw.word_book_id = wb.id
  WHERE wb.slug = ?
  GROUP BY wb.id, wb.slug, wb.title, wb.description
  LIMIT 1
");
$stmt->execute([$slug]);
$book = $stmt->fetch();

if (!$book) {
  http_response_code(404);
  ?>
  <p style="margin-bottom:12px"><a href="./wordbank.php" class="linkBtn" style="display:inline-flex;align-items:center;gap:6px">← Word Bank</a></p>
  <section class="card" aria-label="Not found">
    <div class="card__head">
      <div>
        <div class="card__title">Word book not found</div>
        <div class="card__sub">The requested book does not exist.</div>
      </div>
    </div>
  </section>
  <?php
  require_once __DIR__ . '/includes/footer.php';
  exit;
}

$bookId = (int)$book['id'];
$bookTitle = (string)$book['title'];
$bookDesc = (string)($book['description'] ?? '');
$wordCount = (int)($book['word_count'] ?? 0);

// 单词列表
$wstmt = db()->prepare("
  SELECT
    w.word,
    w.phonetic,
    w.meaning
  FROM word_book_words wbw
  JOIN words w ON w.id = wbw.word_id
  WHERE wbw.word_book_id = ?
  ORDER BY wbw.sort_order ASC, w.word ASC
");
$wstmt->execute([$bookId]);
$words = $wstmt->fetchAll();

?>

        <p style="margin-bottom:12px"><a href="./wordbank.php" class="linkBtn" style="display:inline-flex;align-items:center;gap:6px">← Word Bank</a></p>

        <div class="card" id="detailCard" data-slug="<?php echo htmlspecialchars($slug); ?>">
          <div class="card__head">
            <div>
              <div class="card__title"><?php echo htmlspecialchars($bookTitle); ?></div>
              <div class="card__sub"><span id="detailCount"><?php echo $wordCount; ?></span> words</div>
            </div>
            <div class="friendRow__actions">
              <button class="primary" type="button" id="btnToggleSelect">Add to my selection</button>
            </div>
          </div>
          <div class="divider"></div>
          <?php if ($bookDesc !== ''): ?>
            <div class="detailIntro"><?php echo nl2br(htmlspecialchars($bookDesc)); ?></div>
          <?php else: ?>
            <div class="detailIntro" style="color:var(--muted)">No description.</div>
          <?php endif; ?>
        </div>

        <section class="card detailWordsCard" aria-label="Words in this book" style="margin-top:24px">
          <div class="card__head">
            <div>
              <div class="card__title">Words in this book</div>
              <div class="card__sub"><span id="detailWordCount"><?php echo count($words); ?></span> words</div>
            </div>
          </div>
          <div class="detailWords" id="detailWordsList" role="list">
            <?php foreach ($words as $i => $w): ?>
              <div class="detailWord" role="listitem">
                <span class="detailWord__index"><?php echo $i + 1; ?></span>
                <div class="detailWord__main">
                  <span class="detailWord__word"><?php echo htmlspecialchars((string)$w['word']); ?></span>
                  <span class="detailWord__phonetic"><?php echo htmlspecialchars((string)($w['phonetic'] ?? '')); ?></span>
                </div>
                <div class="detailWord__meaning"><?php echo htmlspecialchars((string)($w['meaning'] ?? '')); ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </section>

        <script>
          (function() {
            var STORAGE_KEY = 'vocab_wordbook_selection';
            var slug = (document.getElementById('detailCard') && document.getElementById('detailCard').dataset.slug) || 'daily';
            var btn = document.getElementById('btnToggleSelect');
            function getSelection() {
              try {
                var raw = localStorage.getItem(STORAGE_KEY);
                return raw ? JSON.parse(raw) : ['daily'];
              } catch (e) { return ['daily']; }
            }
            function setSelection(ids) {
              localStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
            }
            function isSelected(id) { return getSelection().indexOf(id) >= 0; }
            function updateBtn() {
              btn.textContent = isSelected(slug) ? 'Remove from my selection' : 'Add to my selection';
              btn.classList.remove('primary', 'ghost');
              btn.classList.add(isSelected(slug) ? 'ghost' : 'primary');
            }
            updateBtn();
            btn.addEventListener('click', function() {
              var sel = getSelection();
              var i = sel.indexOf(slug);
              if (i >= 0) sel.splice(i, 1); else sel.push(slug);
              setSelection(sel);
              updateBtn();
            });
          })();
        </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

