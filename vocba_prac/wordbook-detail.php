<?php
require_once __DIR__ . '/config.php';
vocab_require_auth();
$pageTitle = 'Word Book';
$activeNav = 'wordbank';
require_once __DIR__ . '/includes/header.php';

$slug = isset($_GET['id']) ? trim((string)$_GET['id']) : 'daily';
if ($slug === '') $slug = 'daily';

$booksTable = vocab_table('books');
$bookWordsTable = vocab_table('book_words');
$wordsTable = vocab_table('words');

// 词书信息 + 词数
$stmt = db()->prepare("
  SELECT
    wb.word_book_id AS id,
    wb.slug,
    wb.title,
    wb.description,
    COUNT(DISTINCT wbw.word_id) AS word_count
  FROM {$booksTable} wb
  LEFT JOIN {$bookWordsTable} wbw ON wbw.word_book_id = wb.word_book_id
  WHERE wb.slug = ?
  GROUP BY wb.word_book_id, wb.slug, wb.title, wb.description
  LIMIT 1
");
$stmt->execute([$slug]);
$book = $stmt->fetch();

if (!$book) {
  http_response_code(404);
  ?>
  <p style="margin-bottom:12px"><a href="./wordbank.php" class="linkBtn" style="display:inline-flex;align-items:center;gap:6px">← All word books</a></p>
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
$displayMeta = vocab_book_meta($slug, (string)$book['title'], (string)($book['description'] ?? ''));
$bookTitle = (string)$displayMeta['title'];
$bookDesc = (string)$displayMeta['description'];
$wordCount = (int)($book['word_count'] ?? 0);
$initialSelection = vocab_selected_book_slugs(vocab_current_user_id());

// 单词列表
$wstmt = db()->prepare("
  SELECT
    w.word_id AS id,
    w.word,
    w.phonetic,
    w.meaning_en AS meaning,
    w.meaning_zh AS meaning_zh,
    COALESCE(up.mastery_status, 'new') AS mastery_status
  FROM {$bookWordsTable} wbw
  JOIN {$wordsTable} w ON w.word_id = wbw.word_id
  LEFT JOIN " . vocab_table('user_progress') . " up
    ON up.word_id = w.word_id
   AND up.user_id = ?
  WHERE wbw.word_book_id = ?
  ORDER BY wbw.sort_order ASC, w.word ASC
");
$wstmt->execute([vocab_current_user_id(), $bookId]);
$words = $wstmt->fetchAll();

?>

        <p style="margin-bottom:12px"><a href="./wordbank.php" class="linkBtn" style="display:inline-flex;align-items:center;gap:6px">← All word books</a></p>

        <div class="card" id="detailCard" data-slug="<?php echo htmlspecialchars($slug); ?>">
          <div class="card__head">
            <div>
              <div class="card__title"><?php echo htmlspecialchars($bookTitle); ?></div>
              <div class="card__sub"><span id="detailCount"><?php echo $wordCount; ?></span> words<?php echo in_array($slug, ['daily', 'cs'], true) ? ' in the current 30-word practice pack' : ''; ?></div>
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
              <div class="card__sub"><span id="detailWordCount"><?php echo count($words); ?></span> words. Click a word row to show or hide its Chinese meaning.</div>
            </div>
          </div>
          <div class="detailWords" id="detailWordsList" role="list">
            <?php foreach ($words as $i => $w): ?>
              <?php
                $status = (string)($w['mastery_status'] ?? 'new');
                $statusLabel = $status === 'mastered' ? 'Learned' : ($status === 'forgot' ? 'Forgot' : ($status === 'learning' ? 'Learning' : 'Not marked'));
              ?>
              <div class="detailWord" role="listitem" data-word-id="<?php echo (int)$w['id']; ?>" data-word-status="<?php echo htmlspecialchars($status); ?>" tabindex="0" aria-expanded="false">
                <span class="detailWord__index"><?php echo $i + 1; ?></span>
                <div class="detailWord__main">
                  <span class="detailWord__word"><?php echo htmlspecialchars((string)$w['word']); ?></span>
                  <span class="detailWord__phonetic"><?php echo htmlspecialchars((string)($w['phonetic'] ?? '')); ?></span>
                  <span class="detailWord__status detailWord__status--<?php echo htmlspecialchars($status); ?>" data-status-label><?php echo htmlspecialchars($statusLabel); ?></span>
                </div>
                <div class="detailWord__actions">
                  <button type="button" class="detailWord__actionBtn" data-status="mastered">Learned</button>
                  <button type="button" class="detailWord__actionBtn" data-status="learning">Learning</button>
                  <button type="button" class="detailWord__actionBtn" data-status="forgot">Forgot</button>
                </div>
                <div class="detailWord__meaning"><?php echo htmlspecialchars((string)($w['meaning'] ?? '')); ?></div>
                <div class="detailWord__meaningZh" hidden><?php echo htmlspecialchars((string)($w['meaning_zh'] ?? 'No Chinese meaning yet.')); ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </section>

        <script>
          (function() {
            var STORAGE_KEY = 'vocab_wordbook_selection';
            var API_SELECTION_URL = './api/selection.php';
            var STATUS_API_URL = './api/word-status.php';
            var INITIAL_SELECTION = <?php echo json_encode($initialSelection, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            var slug = (document.getElementById('detailCard') && document.getElementById('detailCard').dataset.slug) || 'daily';
            var btn = document.getElementById('btnToggleSelect');
            function getSelection() {
              try {
                var raw = localStorage.getItem(STORAGE_KEY);
                return raw ? JSON.parse(raw) : INITIAL_SELECTION;
              } catch (e) { return INITIAL_SELECTION; }
            }
            function setSelection(ids) {
              localStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
            }
            async function saveSelection(ids) {
              setSelection(ids);
              try {
                var res = await fetch(API_SELECTION_URL, {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  credentials: 'include',
                  body: JSON.stringify({ selectedBooks: ids })
                });
                var data = await res.json();
                if (data && data.ok && Array.isArray(data.selectedBooks)) {
                  setSelection(data.selectedBooks);
                  return data.selectedBooks;
                }
              } catch (e) {}
              return ids;
            }
            function isSelected(id) { return getSelection().indexOf(id) >= 0; }
            function updateBtn() {
              btn.textContent = isSelected(slug) ? 'Remove from my selection' : 'Add to my selection';
              btn.classList.remove('primary', 'ghost');
              btn.classList.add(isSelected(slug) ? 'ghost' : 'primary');
            }
            setSelection(INITIAL_SELECTION);
            updateBtn();
            btn.addEventListener('click', async function() {
              var sel = getSelection();
              var i = sel.indexOf(slug);
              if (i >= 0) sel.splice(i, 1); else sel.push(slug);
              await saveSelection(sel);
              updateBtn();
            });

            function statusLabelOf(status) {
              if (status === 'mastered') return 'Learned';
              if (status === 'learning') return 'Learning';
              if (status === 'forgot') return 'Forgot';
              return 'Not marked';
            }

            function applyRowStatus(row, status) {
              row.dataset.wordStatus = status;
              var label = row.querySelector('[data-status-label]');
              if (label) {
                label.textContent = statusLabelOf(status);
                label.className = 'detailWord__status detailWord__status--' + status;
              }
              row.querySelectorAll('.detailWord__actionBtn').forEach(function(actionBtn) {
                actionBtn.classList.toggle('isSelected', actionBtn.getAttribute('data-status') === status);
              });
            }

            async function saveWordStatus(wordId, status, row) {
              applyRowStatus(row, status);
              try {
                await fetch(STATUS_API_URL, {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  credentials: 'include',
                  body: JSON.stringify({ wordId: wordId, status: status })
                });
              } catch (e) {}
            }

            document.querySelectorAll('.detailWord').forEach(function(row) {
              var wordId = Number(row.getAttribute('data-word-id') || 0);
              applyRowStatus(row, row.getAttribute('data-word-status') || 'new');

              function toggleMeaning() {
                var zh = row.querySelector('.detailWord__meaningZh');
                if (!zh) return;
                var willShow = zh.hasAttribute('hidden');
                if (willShow) zh.removeAttribute('hidden');
                else zh.setAttribute('hidden', 'hidden');
                row.setAttribute('aria-expanded', willShow ? 'true' : 'false');
                row.classList.toggle('isExpanded', willShow);
              }

              row.addEventListener('click', function(e) {
                if (e.target.closest('.detailWord__actionBtn')) return;
                toggleMeaning();
              });
              row.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                  e.preventDefault();
                  toggleMeaning();
                }
              });

              row.querySelectorAll('.detailWord__actionBtn').forEach(function(actionBtn) {
                actionBtn.addEventListener('click', function(e) {
                  e.stopPropagation();
                  saveWordStatus(wordId, actionBtn.getAttribute('data-status') || 'learning', row);
                });
              });
            });
          })();
        </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
