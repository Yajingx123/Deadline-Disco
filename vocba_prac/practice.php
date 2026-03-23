<?php
require_once __DIR__ . '/config.php';
vocab_require_auth();
$pageTitle = 'Practice';
$activeNav = 'practice';

$booksTable = vocab_table('books');
$bookWordsTable = vocab_table('book_words');

$stmt = db()->query("
  SELECT
    wb.word_book_id AS id,
    wb.slug,
    wb.title,
    wb.description,
    COUNT(DISTINCT wbw.word_id) AS word_count
  FROM {$booksTable} wb
  LEFT JOIN {$bookWordsTable} wbw ON wbw.word_book_id = wb.word_book_id
  GROUP BY wb.word_book_id, wb.slug, wb.title, wb.description
  ORDER BY wb.word_book_id ASC
");
$books = $stmt->fetchAll();
$initialSelection = vocab_selected_book_slugs(vocab_current_user_id());
$titleMap = [];
$bookMap = [];
foreach ($books as $b) {
  $slug = (string)($b['slug'] ?? '');
  $meta = vocab_book_meta($slug, (string)($b['title'] ?? $slug), (string)($b['description'] ?? ''));
  $title = (string)$meta['title'];
  if ($slug !== '') {
    $titleMap[$slug] = $title;
    $bookMap[$slug] = [
      'title' => $title,
      'description' => (string)$meta['description'],
      'count' => (int)($b['word_count'] ?? 0),
      'coverUrl' => vocab_book_cover_url($slug),
    ];
  }
}

require_once __DIR__ . '/includes/header.php';
?>

        <h1 class="hero__title" style="margin-top:0">Vocabulary practice</h1>
        <p class="hero__subtitle" style="margin-bottom:12px">Choose a short session first, then practise with the books you already picked. Study time decides whether you review 5, 10, or 15 words, and words are taken in the exact order of your selected books.</p>

        <section class="card" style="margin-bottom:18px">
          <div class="card__head">
            <div>
              <div class="card__title">Study time</div>
              <div class="card__sub">1 minute, 3-5 minutes, or 10 minutes.</div>
            </div>
          </div>
          <div class="modeGrid" id="modes">
            <button class="modeCard" type="button" data-minutes="1" id="mode1">
              <div class="modeCard__top">
                <div class="modeCard__title">1 minute</div>
                <span class="tag">Fast reset</span>
              </div>
              <div class="modeCard__desc">5 words. One quick recognition round with image, audio, or spelling.</div>
              <div class="modeCard__meta">
                <span class="pill2">Image ↔ word</span>
                <span class="pill2">Audio ↔ word</span>
                <span class="pill2">Word completion</span>
              </div>
            </button>
            <button class="modeCard" type="button" data-minutes="5" id="mode5">
              <div class="modeCard__top">
                <div class="modeCard__title">3-5 minutes</div>
                <span class="tag">Core review</span>
              </div>
              <div class="modeCard__desc">10 words. Balanced review with image, audio, spelling, and short sentence clues.</div>
              <div class="modeCard__meta">
                <span class="pill2">Sentence fill</span>
                <span class="pill2">Audio ↔ word</span>
                <span class="pill2">Word completion</span>
              </div>
            </button>
            <button class="modeCard" type="button" data-minutes="10" id="mode10">
              <div class="modeCard__top">
                <div class="modeCard__title">10 minutes</div>
                <span class="tag">Full mix</span>
              </div>
              <div class="modeCard__desc">15 words. Deeper review with repeated retrieval and sentence choice/fill prompts.</div>
              <div class="modeCard__meta">
                <span class="pill2">Sentence choose</span>
                <span class="pill2">Sentence fill</span>
                <span class="pill2">More target words</span>
              </div>
            </button>
          </div>
          <div style="margin-top:16px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
            <button class="primary" type="button" id="btnStartSession">Start <span id="selectedMinLabel">1 minute</span> session →</button>
            <span id="sessionInlineError" style="display:none;color:#c25757;font-size:13px;font-weight:700;">Please choose at least one word book first.</span>
          </div>
        </section>

        <div class="card dailyPlan" style="margin-bottom:18px" id="practiceSelectionBar" aria-label="Word books used for this practice">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
            <div>
              <div style="font-weight:700;margin-bottom:4px">Your study books</div>
              <div style="color:var(--muted);font-size:13px">These are the actual word books that will feed your practice session.</div>
            </div>
            <a href="./wordbank.php" class="secondary" style="display:inline-flex;align-items:center;text-decoration:none">Choose word books</a>
          </div>
          <div class="selectedBooksGrid" id="practiceBookList" style="margin-top:16px">—</div>
        </div>

        <script>
          var STORAGE_KEY = 'vocab_wordbook_selection';
          var API_SELECTION_URL = './api/selection.php';
          var TITLES = <?php
            echo json_encode($titleMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
          ?>;
          var BOOKS = <?php echo json_encode($bookMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
          var INITIAL_SELECTION = <?php echo json_encode($initialSelection, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

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
          function renderPracticeBar() {
            var sel = getSelection();
            var listEl = document.getElementById('practiceBookList');
            if (!listEl) return;
            if (sel.length === 0) {
              listEl.innerHTML = '<div class="selectedBookCard selectedBookCard--empty"><div class="selectedBookCard__emptyTitle">No word book selected</div><div class="selectedBookCard__emptyText">Open `Choose word books` and pick at least one book before starting practice.</div></div>';
              return;
            }
            listEl.innerHTML = sel.map(function(id) {
              var book = BOOKS[id];
              if (!book) return '';
              return '' +
                '<article class="selectedBookCard">' +
                  '<div class="selectedBookCard__cover"><img src="' + book.coverUrl + '" alt=""></div>' +
                  '<div class="selectedBookCard__body">' +
                    '<div class="selectedBookCard__title">' + book.title + '</div>' +
                    '<div class="selectedBookCard__meta">Selected for practice</div>' +
                    '<div class="selectedBookCard__count">' + book.count + ' words</div>' +
                  '</div>' +
                '</article>';
            }).join('');
          }
          setSelection(INITIAL_SELECTION);
          renderPracticeBar();

          var selected = 1;
          var modeLabels = { 1: '1 minute', 5: '3-5 minutes', 10: '10 minutes' };
          document.querySelectorAll('.modeCard').forEach(function(card) {
            card.addEventListener('click', function() {
              selected = Number(card.dataset.minutes) || 1;
              document.querySelectorAll('.modeCard').forEach(function(c) { c.classList.remove('isSelected'); });
              card.classList.add('isSelected');
              var el = document.getElementById('selectedMinLabel');
              if (el) el.textContent = modeLabels[selected] || (selected + ' minutes');
            });
          });
          document.getElementById('mode1').classList.add('isSelected');

          document.getElementById('btnStartSession').addEventListener('click', function() {
            var books = getSelection();
            var errorEl = document.getElementById('sessionInlineError');
            if (books.length === 0) {
              if (errorEl) errorEl.style.display = 'inline';
              return;
            }
            if (errorEl) errorEl.style.display = 'none';
            var qs = new URLSearchParams();
            qs.set('mode', String(selected));
            qs.set('books', books.join(','));
            window.location.href = './practice-session.php?' + qs.toString();
          });

          window.addEventListener('storage', function(e) {
            if (e.key === STORAGE_KEY) renderPracticeBar();
          });
        </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
