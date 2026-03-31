<?php
require_once __DIR__ . '/config.php';
vocab_require_auth();
$pageTitle = 'Word Books';
$activeNav = 'wordbank';
$hideGlobalHomeNav = true;
require_once __DIR__ . '/includes/header.php';

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

$titleMap = [];
foreach ($books as $b) {
  $slug = (string)($b['slug'] ?? '');
  $meta = vocab_book_meta($slug, (string)($b['title'] ?? $slug), (string)($b['description'] ?? ''));
  $title = (string)$meta['title'];
  if ($slug !== '') $titleMap[$slug] = $title;
}
?>

        <section class="vocabSubpageHeader">
          <div class="vocabSubpageHeader__copy">
            <h1 class="hero__title" style="margin-top:0">Choose word books</h1>
            <p class="hero__subtitle" style="margin-bottom:24px">Pick the books you want in practice. Selected books are highlighted with a stronger card state so you can tell at a glance what is active.</p>
          </div>
        </section>

        <section class="card" style="margin-bottom:24px" aria-label="Selected for practice">
          <div class="card__head">
            <div>
              <div class="card__title">Selected for practice</div>
              <div class="card__sub">These books will appear in your `Books in this session` area on the Practice page.</div>
            </div>
          </div>
          <div class="wordbankSelection" id="wordbankSelection">
            <span class="wordbankSelection__count" id="selectionCount">—</span>
            <span class="wordbankSelection__list" id="currentSelectionSummary">
              <span class="wordbankSelection__empty">Loading…</span>
            </span>
          </div>
        </section>

        <section class="card" aria-label="All word books">
          <div class="card__head">
            <div>
              <div class="card__title">All word books (<span id="wordbookTotalCount"><?php echo (int)$totalBooks; ?></span>)</div>
              <div class="card__sub">Click a book card to add or remove it. Selected cards show a bold outline, lifted shadow, and a check marker.</div>
            </div>
            <a href="./practice.php" class="secondary" style="display:inline-flex;align-items:center;text-decoration:none">Back to Practice</a>
          </div>
          <div class="wordbankSearchWrap">
            <input type="search" id="wordbookSearch" class="wordbankSearchInput" placeholder="Search by name or topic" autocomplete="off" aria-label="Search word books" />
            <span class="wordbankSearchHint" id="wordbookSearchHint" style="display:none;font-size:13px;color:var(--muted)"></span>
          </div>
          <div id="wordbankNoResults" class="wordbankNoResults" role="status" aria-live="polite" style="display:none">
            No word books match your search. Try different keywords.
          </div>

          <div class="wordbooks" id="wordbooksList" role="list">
            <?php foreach ($books as $b): ?>
              <?php
                $slug = (string)($b['slug'] ?? '');
                $meta = vocab_book_meta($slug, (string)($b['title'] ?? $slug), (string)($b['description'] ?? ''));
                $title = (string)$meta['title'];
                $desc = (string)$meta['description'];
                $coverUrl = vocab_book_cover_url($slug);
                $count = (int)($b['word_count'] ?? 0);
              ?>
              <div class="wordbook" data-id="<?php echo htmlspecialchars($slug); ?>" id="wb-<?php echo htmlspecialchars($slug); ?>" role="listitem">
                <input type="checkbox" name="wordbook" value="<?php echo htmlspecialchars($slug); ?>" id="cb-<?php echo htmlspecialchars($slug); ?>" aria-label="Select <?php echo htmlspecialchars($title); ?>" />
                <div class="wordbook__check" aria-hidden="true">✓ Selected</div>
                <div class="wordbook__cover" aria-hidden="true">
                  <img src="<?php echo htmlspecialchars($coverUrl); ?>" alt="" loading="lazy" />
                </div>
                <div class="wordbook__body">
                  <span class="wordbook__badge" aria-hidden="true">In use</span>
                  <div class="wordbook__title"><?php echo htmlspecialchars($title); ?></div>
                  <div class="wordbook__count"><?php echo $count; ?> words</div>
                  <?php if ($desc !== ''): ?>
                    <div class="wordbook__desc"><?php echo htmlspecialchars($desc); ?></div>
                  <?php endif; ?>
                  <a href="./wordbook-detail.php?id=<?php echo urlencode($slug); ?>" class="linkBtn wordbook__link">View details</a>
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
              listEl.innerHTML = '<span class="wordbankSelection__empty">No book selected. Tap a card below to add it.</span>';
              return;
            }
            listEl.innerHTML = sel.map(function(id) {
              return '<span class="pill2">' + (TITLES[id] || id) + '</span>';
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
