<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'Practice session';
$activeNav = 'practice';

$mode = isset($_GET['mode']) ? (int)$_GET['mode'] : 10;
if (!in_array($mode, [10, 30, 60], true)) $mode = 10;

$booksParam = isset($_GET['books']) ? (string)$_GET['books'] : 'daily';
$bookSlugs = array_values(array_filter(array_map('trim', explode(',', $booksParam))));
if (!$bookSlugs) $bookSlugs = ['daily'];

// 只允许数据库里存在的 slug
$placeholders = implode(',', array_fill(0, count($bookSlugs), '?'));
$validStmt = db()->prepare("SELECT slug FROM word_books WHERE slug IN ($placeholders)");
$validStmt->execute($bookSlugs);
$valid = $validStmt->fetchAll(PDO::FETCH_COLUMN);
$valid = array_values(array_unique(array_map('strval', $valid)));
if (!$valid) $valid = ['daily'];

// 根据词书 slug 拉取去重后的单词（支持词书重叠）
$placeholders = implode(',', array_fill(0, count($valid), '?'));
$wordStmt = db()->prepare("
  SELECT DISTINCT
    w.id,
    w.word,
    w.phonetic,
    w.meaning,
    w.sentence,
    w.image_url AS imageUrl,
    w.audio_url AS audioUrl
  FROM words w
  JOIN word_book_words wbw ON wbw.word_id = w.id
  JOIN word_books wb ON wb.id = wbw.word_book_id
  WHERE wb.slug IN ($placeholders)
  ORDER BY w.word ASC
");
$wordStmt->execute($valid);
$words = $wordStmt->fetchAll();

// 若没有数据，兜底：取 daily
if (!$words) {
  $wordStmt = db()->prepare("
    SELECT DISTINCT
      w.id,
      w.word,
      w.phonetic,
      w.meaning,
      w.sentence,
      w.image_url AS imageUrl,
      w.audio_url AS audioUrl
    FROM words w
    JOIN word_book_words wbw ON wbw.word_id = w.id
    JOIN word_books wb ON wb.id = wbw.word_book_id
    WHERE wb.slug = 'daily'
    ORDER BY w.word ASC
  ");
  $wordStmt->execute();
  $words = $wordStmt->fetchAll();
}

// 顶栏需要显示 mode
require_once __DIR__ . '/includes/header.php';
?>

        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px">
          <div style="color:var(--muted);font-size:13px">
            Session: <strong><?php echo htmlspecialchars($mode); ?> min</strong>
          </div>
          <a href="./practice.php" class="ghost" style="display:inline-flex;align-items:center;padding:6px 14px;text-decoration:none;font-size:14px">Exit</a>
        </div>

        <div class="sessionProgress" id="sessionProgress">
          <div class="sessionProgress__bar"><div class="sessionProgress__fill" id="progressFill" style="width:0%"></div></div>
          <div class="sessionProgress__text" id="progressText">0 / 0</div>
        </div>
        <div id="sessionContent"></div>

        <!-- Debug only: allow skipping to next step without answering -->
        <button
          id="btnTestNext"
          type="button"
          class="debugNextBtn"
          aria-label="Next (test)"
        >
          Next (test)
        </button>

        <script>
          (function() {
            var WORDS = <?php echo json_encode($words, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            var mode = <?php echo (int)$mode; ?>;
            var labels = { 10: '10 min', 30: '30 min', 60: '60 min' };
            // 顶栏的 sessionModeLabel 在旧 HTML 里有，这里不依赖它；若存在则更新
            var modeLabelEl = document.getElementById('sessionModeLabel');
            if (modeLabelEl) modeLabelEl.textContent = labels[mode] || (mode + ' min');

            function shuffle(arr) {
              var a = arr.slice();
              for (var i = a.length - 1; i > 0; i--) {
                var j = Math.floor(Math.random() * (i + 1));
                var t = a[i]; a[i] = a[j]; a[j] = t;
              }
              return a;
            }

            function pickOptions(correctWord, wordList, count) {
              var others = wordList.filter(function(w) { return w.word !== correctWord; });
              others = shuffle(others).slice(0, count - 1);
              var opts = [correctWord].concat(others.map(function(w) { return w.word; }));
              return shuffle(opts);
            }

            function safeText(s) {
              return String(s || "").trim();
            }

            function hasSentence(wordObj) {
              return Boolean(safeText(wordObj && wordObj.sentence));
            }

            function makeSentenceBlank(sentence, answerWord) {
              // Replace the first occurrence of the answer word with underscores.
              // If not found, fall back to a generic blank at the end.
              var s = safeText(sentence);
              var w = safeText(answerWord);
              if (!s || !w) return { prompt: s, found: false };

              // Try word-boundary-ish replacement first (case-insensitive).
              var re = new RegExp("\\b" + w.replace(/[.*+?^${}()|[\]\\]/g, "\\$&") + "\\b", "i");
              if (re.test(s)) {
                var blank = "_".repeat(Math.max(3, Math.min(10, w.length)));
                return { prompt: s.replace(re, blank), found: true };
              }
              return { prompt: s + "  ____", found: false };
            }

            function makeBlankParts(word) {
              // Return parts to render "prefix + missing letters + suffix"
              if (!word || word.length <= 3) {
                return { idx: 0, gapLen: 0, prefix: word || "", suffix: "", expectedMissingLower: "" };
              }
              var idx = Math.floor(word.length * 0.4);
              var len = Math.min(3, word.length - idx - 1);
              var prefix = word.slice(0, idx);
              var suffix = word.slice(idx + len);
              var expectedMissingLower = word.slice(idx, idx + len).toLowerCase();
              return { idx: idx, gapLen: len, prefix: prefix, suffix: suffix, expectedMissingLower: expectedMissingLower };
            }

            var steps = [];
            var words = shuffle(WORDS);
            // 10-min: quick warm-up (1 exercise per word).
            // 30-min: add sentence-based practice.
            // 60-min: add even more sentence-based practice.
            var exerciseTypes =
              mode === 10
                ? ['image', 'audio', 'fill']
                : mode === 30
                  ? ['image', 'audio', 'fill', 'sentence_fill']
                  : ['image', 'audio', 'fill', 'sentence_fill', 'sentence_pick'];

            // Learn card（每个单词先过一遍）
            words.forEach(function(w) { steps.push({ type: 'learn', word: w }); });

            var exercisesPerWord = mode === 10 ? 1 : mode === 30 ? 2 : 3;
            words.forEach(function(w, i) {
              for (var e = 0; e < exercisesPerWord; e++) {
                var kind = exerciseTypes[(i + e) % 3];
                // Diversify for 30/60: allow sentence-based kinds as well.
                if (mode !== 10) {
                  kind = exerciseTypes[(i + e) % exerciseTypes.length];
                }

                // If sentence is missing, fall back to the classic types.
                if ((kind === 'sentence_fill' || kind === 'sentence_pick') && !hasSentence(w)) {
                  kind = ['image', 'audio', 'fill'][(i + e) % 3];
                }

                var options =
                  kind === 'image' || kind === 'audio'
                    ? pickOptions(w.word, WORDS, 4)
                    : kind === 'sentence_pick'
                      ? (function() {
                          var sentenceWords = WORDS.filter(hasSentence);
                          if (sentenceWords.length < 2) return pickOptions(w.word, WORDS, 4);
                          return pickOptions(w.word, sentenceWords, 4);
                        })()
                      : null;

                steps.push({ type: kind, word: w, options: options });
              }
            });

            var stepIndex = 0;
            var correctCount = 0;
            var answered = false;
            var contentEl = document.getElementById('sessionContent');
            var progressFill = document.getElementById('progressFill');
            var progressText = document.getElementById('progressText');
            var synth = window.speechSynthesis;

            function speak(text) {
              if (!synth || !text) return;
              synth.cancel();
              var u = new window.SpeechSynthesisUtterance(text);
              u.lang = 'en-US';
              u.rate = 0.9;
              synth.speak(u);
            }

            function renderProgress() {
              var p = steps.length ? (stepIndex / steps.length) * 100 : 0;
              progressFill.style.width = p + '%';
              progressText.textContent = stepIndex + ' / ' + steps.length;
            }

            function makeRetryStep(step) {
              if (!step || step.type === 'learn') return null;
              var opts = (step.type === 'image' || step.type === 'audio') ? pickOptions(step.word.word, WORDS, 4) : null;
              return { type: step.type, word: step.word, options: opts };
            }

            function escapeHtml(s) { return (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\"/g, '&quot;'); }

            function showResult(ok, correctWord, meaning) {
              answered = true;
              if (ok) {
                correctCount++;
              } else {
                steps.push(makeRetryStep(steps[stepIndex]));
                renderProgress();
              }
              var resultClass = ok ? 'sessionResult--ok' : 'sessionResult--bad';
              var resultTitle = ok ? 'Correct' : 'Incorrect';
              var resultBody = ok
                ? 'Well done.'
                : 'Correct answer: <strong>' + escapeHtml(correctWord) + '</strong>' +
                  (meaning ? '<br><span class="sessionResult__meaning">' + escapeHtml(meaning) + '</span>' : '') +
                  '<br><span class="sessionResult__hint">You will see this word again in this session until you get it right.</span>';
              var html = '<div class="sessionResult ' + resultClass + '">' +
                '<div class="sessionResult__title">' + resultTitle + '</div>' +
                '<div class="sessionResult__body">' + resultBody + '</div>' +
                '</div>' +
                '<button class="primary" type="button" id="btnNextStep" style="margin-top:16px">Continue</button>';
              contentEl.insertAdjacentHTML('beforeend', html);
              document.getElementById('btnNextStep').onclick = nextStep;
            }

            function nextStep() {
              stepIndex++;
              if (stepIndex >= steps.length) {
                renderEnd();
                return;
              }
              answered = false;
              renderStep();
            }

            // Enable "Next (test)" across all practice modes (10/30/60).
            // This is for testing only: it skips without requiring an answer.
            var btnTestNext = document.getElementById('btnTestNext');
            if (btnTestNext) {
              btnTestNext.addEventListener('click', function() {
                if (typeof nextStep === 'function') nextStep();
              });
            }

            function renderEnd() {
              progressFill.style.width = '100%';
              progressText.textContent = 'Done';
              var totalAnswered = stepIndex;
              contentEl.innerHTML =
                '<div class="sessionCard sessionEnd">' +
                '<div class="sessionEnd__score">' + correctCount + ' correct on first try</div>' +
                '<div class="sessionEnd__detail">' + totalAnswered + ' questions in this session. Wrong answers were added back until you got them right.</div>' +
                '<a href="./practice.php" class="primary" style="display:inline-flex;align-items:center;text-decoration:none;padding:12px 18px">Back to Practice</a>' +
                '</div>';
            }

            function renderStep() {
              renderProgress();
              var step = steps[stepIndex];
              var w = step.word;

              function imgHtml(wordObj) {
                var url = wordObj.imageUrl ? '/' + wordObj.imageUrl : '';
                return '<div class="sessionImgWrap" id="imgWrap">' +
                  (url
                    ? '<img src="' + url + '" alt="" onerror="var w=document.getElementById(\'imgWrap\');if(w){w.innerHTML=\'<span style=&quot;color:var(--muted2);font-weight:600&quot;>Image unavailable</span>\';}">'
                    : '<span style="color:var(--muted2);font-weight:600">Image unavailable</span>') +
                '</div>';
              }

              if (step.type === 'learn') {
                contentEl.innerHTML =
                  '<div class="sessionCard">' +
                  '<div class="sessionCard__label">Learn</div>' +
                  imgHtml(w) +
                  '<div class="wordCard__word">' + escapeHtml(w.word) + '</div>' +
                  '<div class="wordCard__phonetic" style="margin-top:4px">' + escapeHtml(w.phonetic || '') + '</div>' +
                  '<div class="divider"></div>' +
                  '<div style="font-weight:600">' + escapeHtml(w.meaning || '') + '</div>' +
                  (w.sentence ? '<div style="margin-top:8px;color:var(--muted)">' + escapeHtml(w.sentence) + '</div>' : '') +
                  '<div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap">' +
                  '<button class="secondary" type="button" id="btnPlayWord">Play word</button>' +
                  '<button class="primary" type="button" id="btnNextStep">Next</button>' +
                  '</div></div>';
                document.getElementById('btnPlayWord').onclick = function() { speak(w.word); };
                document.getElementById('btnNextStep').onclick = nextStep;
                return;
              }

              if (step.type === 'image') {
                contentEl.innerHTML =
                  '<div class="sessionCard">' +
                  '<div class="sessionCard__label">Which word matches the image?</div>' +
                  imgHtml(w) +
                  '<div id="optionsContainer"></div></div>';
                var container = document.getElementById('optionsContainer');
                step.options.forEach(function(opt) {
                  var btn = document.createElement('button');
                  btn.type = 'button';
                  btn.className = 'sessionOption';
                  btn.textContent = opt;
                  btn.onclick = function() {
                    if (answered) return;
                    btn.classList.add(opt === w.word ? 'sessionOption--correct' : 'sessionOption--wrong');
                    container.querySelectorAll('.sessionOption').forEach(function(b) { b.disabled = true; });
                    showResult(opt === w.word, w.word, w.meaning);
                  };
                  container.appendChild(btn);
                });
                return;
              }

              if (step.type === 'audio') {
                contentEl.innerHTML =
                  '<div class="sessionCard">' +
                  '<div class="sessionCard__label">Listen and choose the word</div>' +
                  '<button class="primary" type="button" id="btnPlay" style="margin-bottom:14px">Play again</button>' +
                  '<div id="optionsContainer"></div></div>';
                document.getElementById('btnPlay').onclick = function() { speak(w.word); };
                var container = document.getElementById('optionsContainer');
                step.options.forEach(function(opt) {
                  var btn = document.createElement('button');
                  btn.type = 'button';
                  btn.className = 'sessionOption';
                  btn.textContent = opt;
                  btn.onclick = function() {
                    if (answered) return;
                    btn.classList.add(opt === w.word ? 'sessionOption--correct' : 'sessionOption--wrong');
                    container.querySelectorAll('.sessionOption').forEach(function(b) { b.disabled = true; });
                    showResult(opt === w.word, w.word, w.meaning);
                  };
                  container.appendChild(btn);
                });
                setTimeout(function() { speak(w.word); }, 300);
                return;
              }

              if (step.type === 'fill') {
                var expectedDisplay = String(w.word || "");
                var parts = makeBlankParts(expectedDisplay);
                var prefix = parts.prefix;
                var suffix = parts.suffix;
                var gapLen = parts.gapLen;
                var expectedMissingLower = parts.expectedMissingLower;

                if (gapLen <= 0) {
                  // Fallback for very short words (no real "blank" in this logic).
                  contentEl.innerHTML =
                    '<div class="sessionCard">' +
                    '<div class="sessionCard__label">Fill in the missing letters</div>' +
                    '<div class="sessionFillRow">' + expectedDisplay + '</div>' +
                    '<input type="text" class="sessionFillInput" id="fillInput" placeholder="Type the word" maxlength="20" style="letter-spacing:.05em">' +
                    '<button class="primary" type="button" id="btnSubmitFill" style="margin-top:14px">Check</button></div>';

                  var fillInput = document.getElementById("fillInput");
                  var btnSubmit = document.getElementById("btnSubmitFill");
                  fillInput.focus();

                  btnSubmit.onclick = function() {
                    if (answered) return;
                    var ans = (fillInput.value || "").trim().toLowerCase();
                    var ok = ans === expectedDisplay.toLowerCase();
                    fillInput.disabled = true;
                    btnSubmit.disabled = true;
                    showResult(ok, w.word, w.meaning);
                  };
                  fillInput.onkeydown = function(e) {
                    if (e.key === "Enter") btnSubmit.click();
                  };
                  return;
                }

                // Display the gap: typed letters appear in-place, blanks stay as underscores.
                function renderGap() {
                  var raw = (fillInput.value || "").trim().toLowerCase();
                  var typed = raw.slice(0, gapLen);

                  var expectedMissingDisplay = expectedDisplay.slice(parts.idx, parts.idx + gapLen);
                  var gapEl = document.getElementById("fillGap");
                  if (!gapEl) return;

                  var html = "";
                  for (var i = 0; i < gapLen; i++) {
                    var expectedLower = expectedMissingLower[i] || "";
                    var expectedChar = expectedMissingDisplay[i] || "_";
                    var typedChar = typed[i] || "";

                    if (typedChar) {
                      var isCorrect = typedChar === expectedLower;
                      html +=
                        '<span class="fillChar ' +
                        (isCorrect ? "fillChar--typedCorrect" : "fillChar--typedWrong") +
                        '">' +
                        expectedChar +
                        "</span>";
                    } else {
                      // Blank placeholder (no answer hint).
                      html += '<span class="fillChar fillChar--blank">_</span>';
                    }
                  }

                  gapEl.innerHTML = html;
                }

                contentEl.innerHTML =
                  '<div class="sessionCard">' +
                  '<div class="sessionCard__label">Fill in the missing letters</div>' +
                  '<div class="sessionFillRow">' +
                    '<span class="fillPrefix">' + prefix + '</span>' +
                    '<span class="fillGap" id="fillGap"></span>' +
                    '<span class="fillSuffix">' + suffix + '</span>' +
                  '</div>' +
                  '<input type="text" class="sessionFillInput" id="fillInput" placeholder="Type the missing letters" maxlength="' + gapLen + '" style="letter-spacing:.05em">' +
                  '<button class="primary" type="button" id="btnSubmitFill" style="margin-top:14px">Check</button></div>';

                var fillInput = document.getElementById("fillInput");
                var btnSubmit = document.getElementById('btnSubmitFill');
                fillInput.focus();

                renderGap();
                fillInput.addEventListener("input", function() {
                  if (answered) return;
                  renderGap();
                });

                btnSubmit.onclick = function() {
                  if (answered) return;

                  var raw = (fillInput.value || "").trim().toLowerCase();
                  var typed = raw.slice(0, gapLen);
                  var ok = typed.length > 0 && typed === expectedMissingLower.slice(0, typed.length);

                  fillInput.disabled = true;
                  btnSubmit.disabled = true;
                  showResult(ok, w.word, w.meaning);
                };
                fillInput.onkeydown = function(e) { if (e.key === 'Enter') btnSubmit.click(); };
                return;
              }

              if (step.type === 'sentence_fill') {
                var sentence = safeText(w.sentence);
                var blanked = makeSentenceBlank(sentence, w.word);

                contentEl.innerHTML =
                  '<div class="sessionCard">' +
                  '<div class="sessionCard__label">Complete the sentence (type the missing word)</div>' +
                  '<div class="sentencePrompt">' + escapeHtml(blanked.prompt) + '</div>' +
                  '<input type="text" class="sessionFillInput" id="sentenceFillInput" placeholder="Type the word" maxlength="24" style="letter-spacing:.02em">' +
                  '<button class="primary" type="button" id="btnSubmitSentence" style="margin-top:14px">Check</button></div>';

                var input = document.getElementById('sentenceFillInput');
                var btn = document.getElementById('btnSubmitSentence');
                input.focus();
                btn.onclick = function() {
                  if (answered) return;
                  var ans = (input.value || '').trim().toLowerCase();
                  var ok = ans === (w.word || '').toLowerCase();
                  input.disabled = true;
                  btn.disabled = true;
                  showResult(ok, w.word, w.meaning);
                };
                input.onkeydown = function(e) { if (e.key === 'Enter') btn.click(); };
                return;
              }

              if (step.type === 'sentence_pick') {
                var sentence = safeText(w.sentence);
                var blanked = makeSentenceBlank(sentence, w.word);
                contentEl.innerHTML =
                  '<div class="sessionCard">' +
                  '<div class="sessionCard__label">Choose the word that fits the sentence</div>' +
                  '<div class="sentencePrompt">' + escapeHtml(blanked.prompt) + '</div>' +
                  '<div id="optionsContainer"></div></div>';

                var container = document.getElementById('optionsContainer');
                (step.options || []).forEach(function(opt) {
                  var btn = document.createElement('button');
                  btn.type = 'button';
                  btn.className = 'sessionOption';
                  btn.textContent = opt;
                  btn.onclick = function() {
                    if (answered) return;
                    btn.classList.add(opt === w.word ? 'sessionOption--correct' : 'sessionOption--wrong');
                    container.querySelectorAll('.sessionOption').forEach(function(b) { b.disabled = true; });
                    showResult(opt === w.word, w.word, w.meaning);
                  };
                  container.appendChild(btn);
                });
                return;
              }
            }

            renderStep();
          })();
        </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

