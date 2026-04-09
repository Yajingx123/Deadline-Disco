<?php
require_once __DIR__ . '/config.php';
vocab_require_auth();
$pageTitle = 'Word Books';
$activeNav = 'wordbank';
$hideGlobalHomeNav = true;

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
$totalBooks = count($books);
$initialSelection = vocab_selected_book_slugs(vocab_current_user_id());
$wordbankCoverImages = [
  'daily' => './vocabulary/1.png',
  'cs' => './vocabulary/2.png',
  'mech' => './vocabulary/3.png',
  'civil' => './vocabulary/4.png',
  'traffic' => './vocabulary/5.png',
  'math' => './vocabulary/6.png',
];
$practiceUrl = vocab_build_url('./practice.php');
$pageBodyClass = 'wordbankTempTheme';
$pageInlineStyles = <<<'CSS'
.wordbankTempTheme{
  background: url("./vocabulary/bg.png") center top / cover fixed no-repeat !important;
}
.wordbankTempTheme .page__main{
  background: transparent !important;
}
.wordbankTempTheme .card,
.wordbankTempTheme .wordbankSimpleSummary,
.wordbankTempTheme .wordbankSimpleGridCard{
  background: transparent !important;
  border: none !important;
  box-shadow: none !important;
}
.wordbankTempTheme .wordbankSelection{
  background: transparent !important;
  border: none !important;
  border-radius: 0 !important;
  padding: 0 !important;
}
.wordbankTempTheme .wordbankSelection__count,
.wordbankTempTheme .wordbankSelection__count strong,
.wordbankTempTheme .wordbankSelection__empty{
  color: rgba(31,41,55,.92) !important;
}
.wordbankTempTheme .wordbankSelection__pillLink{
  background: transparent !important;
  border: none !important;
  border-radius: 0 !important;
  padding: 0 !important;
  color: rgba(31,41,55,.86) !important;
  font-weight: 700 !important;
  text-decoration: underline !important;
  text-underline-offset: 2px;
}
CSS;

$titleMap = [];
$detailUrlMap = [];
foreach ($books as $b) {
  $slug = (string)($b['slug'] ?? '');
  $meta = vocab_book_meta($slug, (string)($b['title'] ?? $slug), (string)($b['description'] ?? ''));
  $title = (string)$meta['title'];
  if ($slug !== '') {
    $titleMap[$slug] = $title;
    $detailUrlMap[$slug] = vocab_build_url('./wordbook-detail.php', ['id' => $slug]);
  }
}

require_once __DIR__ . '/includes/header.php';
?>

        <section class="vocabSubpageHeader wordbankSimpleHeader">
          <div class="vocabSubpageHeader__copy">
            <h1 class="hero__title" style="margin-top:0">My word books</h1>
            <p class="hero__subtitle" style="margin-bottom:24px">Choose books by tapping the cover. Keep this page simple: just books, selection, and direct word access.</p>
          </div>
          <a href="<?php echo htmlspecialchars($practiceUrl, ENT_QUOTES, 'UTF-8'); ?>" class="secondary wordbankSimpleHeader__back" style="display:inline-flex;align-items:center;text-decoration:none">Back to Practice</a>
        </section>

        <section class="card wordbankSimpleSummary" style="margin-bottom:20px" aria-label="Selected books">
          <div class="card__head">
            <div>
              <div class="card__title">My selected books</div>
              <div class="card__sub">Select books here, then open a book directly to view words.</div>
            </div>
          </div>
          <div class="wordbankSelection" id="wordbankSelection">
            <span class="wordbankSelection__count" id="selectionCount">—</span>
            <span class="wordbankSelection__list" id="currentSelectionSummary">
              <span class="wordbankSelection__empty">Loading…</span>
            </span>
          </div>
        </section>

        <section class="card wordbankSimpleGridCard" aria-label="All word books">
          <div class="card__head">
            <div>
              <div class="card__title">All word books (<span id="wordbookTotalCount"><?php echo (int)$totalBooks; ?></span>)</div>
              <div class="card__sub">Tap cover to select. Use "View words" to open that book directly.</div>
            </div>
          </div>
          <div class="wordbooks wordbooks--bookOnly" id="wordbooksList" role="list">
            <?php foreach ($books as $b): ?>
              <?php
                $slug = (string)($b['slug'] ?? '');
                $meta = vocab_book_meta($slug, (string)($b['title'] ?? $slug), (string)($b['description'] ?? ''));
                $title = (string)$meta['title'];
                $coverUrl = $wordbankCoverImages[$slug] ?? vocab_book_cover_url($slug);
                $count = (int)($b['word_count'] ?? 0);
              ?>
              <div class="wordbook wordbook--bookOnly" data-id="<?php echo htmlspecialchars($slug); ?>" id="wb-<?php echo htmlspecialchars($slug); ?>" role="listitem">
                <input type="checkbox" name="wordbook" value="<?php echo htmlspecialchars($slug); ?>" id="cb-<?php echo htmlspecialchars($slug); ?>" aria-label="Select <?php echo htmlspecialchars($title); ?>" />
                <div class="wordbook__check" aria-hidden="true">✓</div>
                <div class="wordbook__cover" aria-hidden="true">
                  <img src="<?php echo htmlspecialchars($coverUrl); ?>" alt="" loading="lazy" />
                </div>
                <div class="wordbook__body">
                  <div class="wordbook__title"><?php echo htmlspecialchars($title); ?></div>
                  <div class="wordbook__count"><?php echo $count; ?> words</div>
                  <a href="<?php echo htmlspecialchars(vocab_build_url('./wordbook-detail.php', ['id' => $slug]), ENT_QUOTES, 'UTF-8'); ?>" class="secondary wordbook__openBtn">View words</a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </section>

        <script>
          var STORAGE_KEY = 'vocab_wordbook_selection';
          var API_SELECTION_URL = './api/selection.php';
          var TOTAL_BOOKS = <?php echo (int)$totalBooks; ?>;
          var TITLES = <?php echo json_encode($titleMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
          var DETAIL_URLS = <?php echo json_encode($detailUrlMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
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
          function renderSummary() {
            var sel = getSelection();
            var countEl = document.getElementById('selectionCount');
            var listEl = document.getElementById('currentSelectionSummary');
            if (countEl) countEl.innerHTML = '<strong>' + sel.length + '</strong> of ' + TOTAL_BOOKS + ' books selected';
            if (!listEl) return;
            if (sel.length === 0) {
              listEl.innerHTML = '<span class="wordbankSelection__empty">No book selected yet.</span>';
              return;
            }
            listEl.innerHTML = sel.map(function(id) {
              var href = DETAIL_URLS[id] || '#';
              return '<a class="wordbankSelection__pillLink" href="' + href + '">' + (TITLES[id] || id) + '</a>';
            }).join('');
          }
          function applySelectionToCheckboxes() {
            var sel = getSelection();
            document.querySelectorAll('.wordbook input[name="wordbook"]').forEach(function(cb) {
              cb.checked = sel.indexOf(cb.value) >= 0;
              var row = cb.closest('.wordbook');
              if (row) row.classList.toggle('isSelected', cb.checked);
            });
          }
          setSelection(INITIAL_SELECTION);
          applySelectionToCheckboxes();
          renderSummary();

          (function initSearch() {
            var searchEl = document.getElementById('wordbookSearch');
            var listEl = document.getElementById('wordbooksList');
            var noResultsEl = document.getElementById('wordbankNoResults');
            var hintEl = document.getElementById('wordbookSearchHint');
            var totalCountEl = document.getElementById('wordbookTotalCount');
            var totalCount = listEl ? listEl.querySelectorAll('.wordbook').length : 0;
            if (totalCountEl) totalCountEl.textContent = totalCount;

            function getSearchText(wordbookEl) {
              var body = wordbookEl.querySelector('.wordbook__body');
              if (!body) return '';
              return (body.textContent || '').toLowerCase();
            }
            function runSearch() {
              var q = (searchEl && searchEl.value) ? searchEl.value.trim().toLowerCase() : '';
              if (!listEl) return;
              var cards = listEl.querySelectorAll('.wordbook');
              var visible = 0;
              cards.forEach(function(card) {
                var match = !q || getSearchText(card).indexOf(q) !== -1;
                card.classList.toggle('wordbook--hidden', !match);
                if (match) visible++;
              });
              if (noResultsEl) noResultsEl.style.display = visible === 0 && q ? 'block' : 'none';
              if (hintEl && q) {
                hintEl.style.display = 'inline';
                hintEl.textContent = visible + ' of ' + totalCount + ' matching';
              } else if (hintEl) hintEl.style.display = 'none';
            }
            if (searchEl) {
              searchEl.addEventListener('input', runSearch);
              searchEl.addEventListener('search', runSearch);
            }
          })();

          document.querySelectorAll('.wordbook').forEach(function(row) {
            var cb = row.querySelector('input[name="wordbook"]');
            var id = row.dataset.id || cb.value;
            async function syncSelection(checked) {
              row.classList.toggle('isSelected', checked);
              var sel = getSelection();
              if (checked && sel.indexOf(id) < 0) sel.push(id);
              if (!checked) sel = sel.filter(function(x) { return x !== id; });
              sel = await saveSelection(sel);
              cb.checked = sel.indexOf(id) >= 0;
              row.classList.toggle('isSelected', cb.checked);
              renderSummary();
            }
            row.addEventListener('click', async function(e) {
              if (e.target.closest('a')) return;
              cb.checked = !cb.checked;
              await syncSelection(cb.checked);
            });
            cb.addEventListener('change', async function(e) {
              e.stopPropagation();
              await syncSelection(cb.checked);
            });
          });
        </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
