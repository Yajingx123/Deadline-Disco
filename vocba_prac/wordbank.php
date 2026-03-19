<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'Word Bank';
$activeNav = 'wordbank';
require_once __DIR__ . '/includes/header.php';

// 词书列表（可扩展到更多词书）
$stmt = db()->query("
  SELECT
    wb.id,
    wb.slug,
    wb.title,
    wb.description,
    COUNT(DISTINCT wbw.word_id) AS word_count
  FROM word_books wb
  LEFT JOIN word_book_words wbw ON wbw.word_book_id = wb.id
  GROUP BY wb.id, wb.slug, wb.title, wb.description
  ORDER BY wb.id ASC
");
$books = $stmt->fetchAll();
$totalBooks = count($books);

$titleMap = [];
foreach ($books as $b) {
  $slug = (string)($b['slug'] ?? '');
  $title = (string)($b['title'] ?? $slug);
  if ($slug !== '') $titleMap[$slug] = $title;
}
?>

        <h1 class="hero__title" style="margin-top:0">Word Bank</h1>
        <p class="hero__subtitle" style="margin-bottom:24px">Choose which word books to use in practice. Tap a book to add or remove it. Your choice is saved automatically.</p>

        <section class="card" style="margin-bottom:24px" aria-label="Selected for practice">
          <div class="card__head">
            <div>
              <div class="card__title">Selected for practice</div>
              <div class="card__sub">These books are used when you start a practice session.</div>
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
              <div class="card__sub">Search to filter. Click a card to add or remove it from practice.</div>
            </div>
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
                $title = (string)($b['title'] ?? $slug);
                $desc = (string)($b['description'] ?? '');
                $count = (int)($b['word_count'] ?? 0);
              ?>
              <div class="wordbook" data-id="<?php echo htmlspecialchars($slug); ?>" id="wb-<?php echo htmlspecialchars($slug); ?>" role="listitem">
                <input type="checkbox" name="wordbook" value="<?php echo htmlspecialchars($slug); ?>" id="cb-<?php echo htmlspecialchars($slug); ?>" aria-label="Select <?php echo htmlspecialchars($title); ?>" />
                <div class="wordbook__cover" aria-hidden="true"><!-- optional img: <img src="..." alt="" /> --></div>
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
          var TOTAL_BOOKS = <?php echo (int)$totalBooks; ?>;
          // 标题映射：从数据库输出
          var TITLES = <?php echo json_encode($titleMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
          function getSelection() {
            try {
              var raw = localStorage.getItem(STORAGE_KEY);
              return raw ? JSON.parse(raw) : ['daily'];
            } catch (e) { return ['daily']; }
          }
          function setSelection(ids) {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
          }
          function renderSummary() {
            var sel = getSelection();
            var countEl = document.getElementById('selectionCount');
            var listEl = document.getElementById('currentSelectionSummary');
            if (countEl) countEl.innerHTML = '<strong>' + sel.length + '</strong> of ' + TOTAL_BOOKS + ' books selected';
            if (!listEl) return;
            if (sel.length === 0) {
              listEl.innerHTML = '<span class="wordbankSelection__empty">No book selected. Tap a book below to add it.</span>';
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
            row.addEventListener('click', function(e) {
              if (e.target.closest('a')) return;
              cb.checked = !cb.checked;
              row.classList.toggle('isSelected', cb.checked);
              var sel = getSelection();
              if (cb.checked && sel.indexOf(id) < 0) sel.push(id);
              if (!cb.checked) sel = sel.filter(function(x) { return x !== id; });
              setSelection(sel);
              renderSummary();
            });
            cb.addEventListener('change', function(e) {
              e.stopPropagation();
              row.classList.toggle('isSelected', cb.checked);
              var sel = getSelection();
              if (cb.checked && sel.indexOf(id) < 0) sel.push(id);
              if (!cb.checked) sel = sel.filter(function(x) { return x !== id; });
              setSelection(sel);
              renderSummary();
            });
          });
        </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

