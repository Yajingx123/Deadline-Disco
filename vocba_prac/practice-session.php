<?php
require_once __DIR__ . '/config.php';
vocab_require_auth();
$hideGlobalHomeNav = true;
$pageTitle = 'Practice session';
$activeNav = 'practice';

$mode = isset($_GET['mode']) ? (int)$_GET['mode'] : 1;
if (!in_array($mode, [1, 5, 10], true)) $mode = 1;

$booksParam = isset($_GET['books']) ? (string)$_GET['books'] : implode(',', vocab_selected_book_slugs(vocab_current_user_id()));
$bookSlugs = array_values(array_filter(array_map('trim', explode(',', $booksParam))));

$booksTable = vocab_table('books');
$wordsTable = vocab_table('words');
$bookWordsTable = vocab_table('book_words');

function sample_words_in_selection_order(array $rows, array $selectedSlugs, int $targetCount): array {
  $grouped = [];
  foreach ($selectedSlugs as $slug) {
    $grouped[$slug] = [];
  }

  foreach ($rows as $row) {
    $slug = (string)($row['bookSlug'] ?? '');
    $wordId = (int)($row['id'] ?? 0);
    if ($slug === '' || $wordId <= 0) {
      continue;
    }
    if (!isset($grouped[$slug])) {
      $grouped[$slug] = [];
    }
    if (!isset($grouped[$slug][$wordId])) {
      $grouped[$slug][$wordId] = $row;
    }
  }

  $selected = [];
  $selectedIds = [];
  foreach ($selectedSlugs as $slug) {
    foreach ($grouped[$slug] ?? [] as $item) {
      if (count($selected) >= $targetCount) {
        break 2;
      }
      $wordId = (int)$item['id'];
      if (isset($selectedIds[$wordId])) {
        continue;
      }
      $selected[] = $item;
      $selectedIds[$wordId] = true;
    }
  }
  return array_values($selected);
}

if (!$bookSlugs) {
  header('Location: ./wordbank.php', true, 302);
  exit;
}

// 只允许数据库里存在的 slug，并保留用户选择顺序
$placeholders = implode(',', array_fill(0, count($bookSlugs), '?'));
$validStmt = db()->prepare("SELECT slug FROM {$booksTable} WHERE slug IN ($placeholders)");
$validStmt->execute($bookSlugs);
$validSet = array_values(array_unique(array_map('strval', $validStmt->fetchAll(PDO::FETCH_COLUMN))));
$valid = [];
foreach ($bookSlugs as $slug) {
  if (in_array($slug, $validSet, true) && !in_array($slug, $valid, true)) {
    $valid[] = $slug;
  }
}
if (!$valid) {
  header('Location: ./wordbank.php', true, 302);
  exit;
}

// 根据词书 slug 拉取去重后的单词（支持词书重叠）
$placeholders = implode(',', array_fill(0, count($valid), '?'));
$wordStmt = db()->prepare("
  SELECT
    w.word_id AS id,
    w.word,
    w.phonetic,
    w.meaning_en AS meaning,
    w.meaning_zh AS meaningZh,
    w.sentence,
    w.image_url AS imageUrl,
    w.audio_url AS audioUrl,
    wb.slug AS bookSlug
  FROM {$wordsTable} w
  JOIN {$bookWordsTable} wbw ON wbw.word_id = w.word_id
  JOIN {$booksTable} wb ON wb.word_book_id = wbw.word_book_id
  WHERE wb.slug IN ($placeholders)
  ORDER BY wb.word_book_id ASC, wbw.sort_order ASC, w.word ASC
");
$wordStmt->execute($valid);
$allWords = $wordStmt->fetchAll();

if (!$allWords) {
  header('Location: ./wordbank.php', true, 302);
  exit;
}

$targetWordCount = $mode === 1 ? 5 : ($mode === 5 ? 10 : 15);
$words = sample_words_in_selection_order($allWords, $valid, $targetWordCount);

foreach ($words as &$word) {
  $wordText = (string)($word['word'] ?? '');
  $word['imageUrl'] = vocab_media_url((string)($word['imageUrl'] ?? ''), $wordText);
  $word['audioUrl'] = vocab_media_url((string)($word['audioUrl'] ?? ''), null);
}
unset($word);

// 顶栏需要显示 mode
require_once __DIR__ . '/includes/header.php';
?>

        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px">
          <div style="color:var(--muted);font-size:13px">
            Session: <strong><?php echo htmlspecialchars($mode === 1 ? '1 minute' : ($mode === 5 ? '3-5 minutes' : '10 minutes')); ?></strong>
          </div>
          <a href="./practice.php" class="sessionExitBtn">Exit</a>
        </div>

        <div class="sessionProgress" id="sessionProgress">
          <div class="sessionProgress__bar"><div class="sessionProgress__fill" id="progressFill" style="width:0%"></div></div>
          <div class="sessionProgress__text" id="progressText">0 / 0</div>
        </div>
        <div id="sessionContent"></div>

        <script>
          (function() {
            var WORDS = <?php echo json_encode($words, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            var mode = <?php echo (int)$mode; ?>;
            var selectedBooks = <?php echo json_encode($valid, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            var STATUS_API_URL = './api/word-status.php';
            var COMPLETE_SESSION_API_URL = './api/complete-session.php';
            var sessionStartedAt = new Date().toISOString().slice(0, 19).replace('T', ' ');
            var labels = { 1: '1 minute', 5: '3-5 minutes', 10: '10 minutes' };
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

            function getMediaUrl(url) {
              return String(url || '').trim();
            }

            var activeAudio = null;
            function stopAudio() {
              if (!activeAudio) return;
              activeAudio.pause();
              activeAudio.currentTime = 0;
              activeAudio = null;
            }

            function speak(text) {
              if (!window.speechSynthesis || !text) return;
              window.speechSynthesis.cancel();
              var utterance = new window.SpeechSynthesisUtterance(text);
              utterance.lang = 'en-US';
              utterance.rate = 0.9;
              window.speechSynthesis.speak(utterance);
            }

            function playAudioForWord(wordObj, statusEl) {
              stopAudio();
              var src = getMediaUrl(wordObj && wordObj.audioUrl);
              if (!src) {
                if (statusEl) statusEl.textContent = 'No recording found. Playing browser fallback.';
                speak(wordObj && wordObj.word);
                return;
              }
              var audio = new Audio(src);
              activeAudio = audio;
              if (statusEl) statusEl.textContent = 'Loading pronunciation...';
              audio.oncanplay = function() {
                if (statusEl) statusEl.textContent = 'Playing pronunciation';
              };
              audio.onended = function() {
                if (statusEl) statusEl.textContent = 'Ready to replay';
              };
              audio.onerror = function() {
                if (statusEl) statusEl.textContent = 'Recording failed. Using browser fallback.';
                activeAudio = null;
                speak(wordObj && wordObj.word);
              };
              var playPromise = audio.play();
              if (playPromise && typeof playPromise.catch === 'function') {
                playPromise.catch(function() {
                  if (statusEl) statusEl.textContent = 'Click again to play pronunciation.';
                  activeAudio = null;
                });
              }
            }

            var steps = [];
            var words = shuffle(WORDS);
            var sessionWordStatus = {};
            var exerciseTypes =
              mode === 1
                ? ['image', 'audio', 'fill']
                : mode === 5
                  ? ['image', 'audio', 'fill', 'sentence_fill']
                  : ['image', 'audio', 'fill', 'sentence_fill', 'sentence_pick'];

            words.forEach(function(w) {
              sessionWordStatus[w.id] = w.masteryStatus || 'new';
              steps.push({ type: 'learn', word: w });
            });

            var exercisesPerWord = mode === 1 ? 1 : 2;
            words.forEach(function(w, i) {
              for (var e = 0; e < exercisesPerWord; e++) {
                var kind = exerciseTypes[(i + e) % 3];
                // Diversify for 3-5 / 10-minute sessions with sentence-based practice.
                if (mode !== 1) {
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

                steps.push({
                  type: kind,
                  word: w,
                  options: options,
                  promptData: null,
                  responses: [],
                  attemptCount: 0,
                  firstAttemptCorrect: null
                });
              }
            });

            var stepIndex = 0;
            var correctCount = 0;
            var answered = false;
            var contentEl = document.getElementById('sessionContent');
            var progressFill = document.getElementById('progressFill');
            var progressText = document.getElementById('progressText');
            var sessionSavePromise = null;

            function renderProgress() {
              var p = steps.length ? (stepIndex / steps.length) * 100 : 0;
              progressFill.style.width = p + '%';
              progressText.textContent = stepIndex + ' / ' + steps.length;
            }

            function makeRetryStep(step) {
              if (!step || step.type === 'learn') return null;
              var opts =
                step.type === 'image' || step.type === 'audio'
                  ? pickOptions(step.word.word, WORDS, 4)
                  : step.type === 'sentence_pick'
                    ? (function() {
                        var sentenceWords = WORDS.filter(hasSentence);
                        if (sentenceWords.length < 2) return pickOptions(step.word.word, WORDS, 4);
                        return pickOptions(step.word.word, sentenceWords, 4);
                      })()
                    : null;
              return { type: step.type, word: step.word, options: opts };
            }

            function escapeHtml(s) { return (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\"/g, '&quot;'); }

            function lockOptionsAndRevealAnswer(container, chosenBtn, chosenWord, correctWord) {
              container.querySelectorAll('.sessionOption').forEach(function(btn) {
                btn.disabled = true;
                if (btn.textContent === correctWord) {
                  btn.classList.add('sessionOption--correct');
                }
                if (btn === chosenBtn && chosenWord !== correctWord) {
                  btn.classList.add('sessionOption--wrong');
                }
              });
            }

            function showResult(ok, correctWord, meaning) {
              answered = true;
              var currentStep = steps[stepIndex];
              if (ok && currentStep && currentStep.type !== 'learn' && (currentStep.responses || []).length === 1) {
                correctCount++;
              } else {
                if (!ok) {
                  steps.push(makeRetryStep(steps[stepIndex]));
                  renderProgress();
                }
              }
              if (currentStep && currentStep.type !== 'learn') {
                if (currentStep.firstAttemptCorrect === null) {
                  currentStep.firstAttemptCorrect = ok;
                }
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
              stopAudio();
              stepIndex++;
              if (stepIndex >= steps.length) {
                renderEnd();
                return;
              }
              answered = false;
              renderStep();
            }

            async function saveWordStatus(wordId, status, triggerBtn) {
              sessionWordStatus[wordId] = status;
              if (triggerBtn) {
                triggerBtn.closest('.sessionStatusActions').querySelectorAll('.sessionStatusBtn').forEach(function(btn) {
                  btn.classList.toggle('isSelected', btn === triggerBtn);
                });
              }
              var label = document.getElementById('sessionStatusValue');
              if (label) {
                label.textContent =
                  status === 'mastered' ? 'Marked as learned. This word will stay out of new sessions.' :
                  status === 'forgot' ? 'Marked as forgot. Keep showing this word in future review.' :
                  status === 'learning' ? 'Marked as still learning.' :
                  'No status selected yet.';
              }
              try {
                await fetch(STATUS_API_URL, {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  credentials: 'include',
                  body: JSON.stringify({ wordId: wordId, status: status })
                });
              } catch (e) {}
            }

            function buildSessionPayload() {
              return {
                mode: mode,
                selectedBooks: selectedBooks,
                correctFirstTry: correctCount,
                startedAt: sessionStartedAt,
                completedAt: new Date().toISOString().slice(0, 19).replace('T', ' '),
                steps: steps.filter(function(step) {
                  return step.type !== 'learn';
                }).map(function(step, idx) {
                  return {
                    wordId: Number(step.word && step.word.id || 0),
                    type: step.type,
                    promptData: step.promptData || {},
                    options: step.options || null,
                    correctAnswer: step.word && step.word.word ? step.word.word : '',
                    firstAttemptCorrect: step.firstAttemptCorrect,
                    responses: step.responses || [],
                    stepOrder: idx + 1
                  };
                })
              };
            }

            async function persistCompletedSession() {
              if (sessionSavePromise) return sessionSavePromise;
              sessionSavePromise = fetch(COMPLETE_SESSION_API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify(buildSessionPayload())
              }).then(function(res) { return res.json(); }).catch(function() {
                return { ok: false };
              });
              return sessionSavePromise;
            }

            function renderEnd() {
              progressFill.style.width = '100%';
              progressText.textContent = 'Done';
              var totalAnswered = stepIndex;
              persistCompletedSession();
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
                var url = getMediaUrl(wordObj && wordObj.imageUrl);
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
                  '<button class="ghost sessionToggleBtn" type="button" id="btnToggleMeaning">' + (w.meaningZh ? 'Show Chinese meaning' : 'Chinese meaning unavailable') + '</button>' +
                  '<div class="sessionMeaningBlock" id="meaningBlock" hidden>' +
                  '<div class="sessionMeaningZh">' + escapeHtml(w.meaningZh || '') + '</div>' +
                  '</div>' +
                  (w.sentence ? '<div class="sessionSentence">' + escapeHtml(w.sentence) + '</div>' : '') +
                  '<div class="sessionStatusPanel">' +
                    '<div class="sessionStatusTitle">How is this word for you?</div>' +
                    '<div class="sessionStatusActions">' +
                      '<button class="ghost sessionStatusBtn' + ((sessionWordStatus[w.id] || "new") === 'mastered' ? ' isSelected' : '') + '" type="button" data-status="mastered">Learned</button>' +
                      '<button class="ghost sessionStatusBtn' + ((sessionWordStatus[w.id] || "new") === 'learning' ? ' isSelected' : '') + '" type="button" data-status="learning">Still learning</button>' +
                      '<button class="ghost sessionStatusBtn' + ((sessionWordStatus[w.id] || "new") === 'forgot' ? ' isSelected' : '') + '" type="button" data-status="forgot">Forgot</button>' +
                    '</div>' +
                    '<div class="sessionStatusValue" id="sessionStatusValue">' +
                      ((sessionWordStatus[w.id] || "new") === 'mastered' ? 'Marked as learned. This word will stay out of new sessions.' :
                      (sessionWordStatus[w.id] || "new") === 'learning' ? 'Marked as still learning.' :
                      (sessionWordStatus[w.id] || "new") === 'forgot' ? 'Marked as forgot. Keep showing this word in future review.' :
                      'No status selected yet.') +
                    '</div>' +
                  '</div>' +
                  '<div class="sessionAudioHint" id="audioStatus">Use the stored pronunciation audio for this word.</div>' +
                  '<div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap">' +
                  '<button class="secondary" type="button" id="btnPlayWord">Play word</button>' +
                  '<button class="primary" type="button" id="btnNextStep">Next</button>' +
                  '</div></div>';
                var toggleBtn = document.getElementById('btnToggleMeaning');
                if (!w.meaningZh) {
                  toggleBtn.disabled = true;
                }
                toggleBtn.onclick = function() {
                  if (!w.meaningZh) return;
                  var block = document.getElementById('meaningBlock');
                  if (!block) return;
                  var willShow = block.hasAttribute('hidden');
                  if (willShow) block.removeAttribute('hidden');
                  else block.setAttribute('hidden', 'hidden');
                  this.textContent = willShow ? 'Hide Chinese meaning' : 'Show Chinese meaning';
                };
                document.querySelectorAll('.sessionStatusBtn').forEach(function(btn) {
                  btn.addEventListener('click', function() {
                    saveWordStatus(w.id, btn.getAttribute('data-status') || 'learning', btn);
                  });
                });
                document.getElementById('btnPlayWord').onclick = function() { playAudioForWord(w, document.getElementById('audioStatus')); };
                document.getElementById('btnNextStep').onclick = async function() {
                  if (!sessionWordStatus[w.id] || sessionWordStatus[w.id] === 'new') {
                    await saveWordStatus(w.id, 'learning', null);
                  }
                  nextStep();
                };
                return;
              }

              if (step.type === 'image') {
                step.promptData = { meaning_en: w.meaning || '' };
                contentEl.innerHTML =
                  '<div class="sessionCard">' +
                  '<div class="sessionCard__label">Match the image and the English meaning to the correct word</div>' +
                  imgHtml(w) +
                  '<div class="sessionPromptMeaning">' + escapeHtml(w.meaning || '') + '</div>' +
                  '<div id="optionsContainer"></div></div>';
                var container = document.getElementById('optionsContainer');
                step.options.forEach(function(opt) {
                  var btn = document.createElement('button');
                  btn.type = 'button';
                  btn.className = 'sessionOption';
                  btn.textContent = opt;
                  btn.onclick = function() {
                    if (answered) return;
                    step.attemptCount = (step.attemptCount || 0) + 1;
                    step.responses = step.responses || [];
                    step.responses.push({
                      responseText: opt,
                      isCorrect: opt === w.word,
                      answeredAt: new Date().toISOString().slice(0, 19).replace('T', ' ')
                    });
                    lockOptionsAndRevealAnswer(container, btn, opt, w.word);
                    showResult(opt === w.word, w.word, w.meaning);
                  };
                  container.appendChild(btn);
                });
                return;
              }

              if (step.type === 'audio') {
                step.promptData = { audio: true };
                contentEl.innerHTML =
                  '<div class="sessionCard">' +
                  '<div class="sessionCard__label">Listen and choose the word</div>' +
                  '<div class="sessionAudioHint" id="audioStatus">Play the stored pronunciation and choose the matching word.</div>' +
                  '<button class="primary" type="button" id="btnPlay" style="margin-bottom:14px">Play again</button>' +
                  '<div id="optionsContainer"></div></div>';
                document.getElementById('btnPlay').onclick = function() { playAudioForWord(w, document.getElementById('audioStatus')); };
                var container = document.getElementById('optionsContainer');
                step.options.forEach(function(opt) {
                  var btn = document.createElement('button');
                  btn.type = 'button';
                  btn.className = 'sessionOption';
                  btn.textContent = opt;
                  btn.onclick = function() {
                    if (answered) return;
                    step.attemptCount = (step.attemptCount || 0) + 1;
                    step.responses = step.responses || [];
                    step.responses.push({
                      responseText: opt,
                      isCorrect: opt === w.word,
                      answeredAt: new Date().toISOString().slice(0, 19).replace('T', ' ')
                    });
                    lockOptionsAndRevealAnswer(container, btn, opt, w.word);
                    showResult(opt === w.word, w.word, w.meaning);
                  };
                  container.appendChild(btn);
                });
                setTimeout(function() { playAudioForWord(w, document.getElementById('audioStatus')); }, 300);
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
                  step.promptData = { meaning_en: w.meaning || '', masked_word: expectedDisplay };
                  // Fallback for very short words (no real "blank" in this logic).
                  contentEl.innerHTML =
                    '<div class="sessionCard">' +
                    '<div class="sessionCard__label">Use the English meaning to complete the word</div>' +
                    '<div class="sessionPromptMeaning">' + escapeHtml(w.meaning || '') + '</div>' +
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
                    step.attemptCount = (step.attemptCount || 0) + 1;
                    step.responses = step.responses || [];
                    step.responses.push({
                      responseText: ans,
                      isCorrect: ok,
                      answeredAt: new Date().toISOString().slice(0, 19).replace('T', ' ')
                    });
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
                  '<div class="sessionCard__label">Use the English meaning to complete the word</div>' +
                  '<div class="sessionPromptMeaning">' + escapeHtml(w.meaning || '') + '</div>' +
                  '<div class="sessionFillRow">' +
                    '<span class="fillPrefix">' + prefix + '</span>' +
                    '<span class="fillGap" id="fillGap"></span>' +
                    '<span class="fillSuffix">' + suffix + '</span>' +
                  '</div>' +
                  '<input type="text" class="sessionFillInput" id="fillInput" placeholder="Type the missing letters" maxlength="' + gapLen + '" style="letter-spacing:.05em">' +
                  '<button class="primary" type="button" id="btnSubmitFill" style="margin-top:14px">Check</button></div>';
                step.promptData = { meaning_en: w.meaning || '', masked_word: prefix + '_'.repeat(gapLen) + suffix };

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
                  step.attemptCount = (step.attemptCount || 0) + 1;
                  step.responses = step.responses || [];
                  step.responses.push({
                    responseText: typed,
                    isCorrect: ok,
                    answeredAt: new Date().toISOString().slice(0, 19).replace('T', ' ')
                  });

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
                step.promptData = { sentence: sentence, blanked: blanked.prompt };

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
                  step.attemptCount = (step.attemptCount || 0) + 1;
                  step.responses = step.responses || [];
                  step.responses.push({
                    responseText: ans,
                    isCorrect: ok,
                    answeredAt: new Date().toISOString().slice(0, 19).replace('T', ' ')
                  });
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
                step.promptData = { sentence: sentence, blanked: blanked.prompt };
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
                    step.attemptCount = (step.attemptCount || 0) + 1;
                    step.responses = step.responses || [];
                    step.responses.push({
                      responseText: opt,
                      isCorrect: opt === w.word,
                      answeredAt: new Date().toISOString().slice(0, 19).replace('T', ' ')
                    });
                    lockOptionsAndRevealAnswer(container, btn, opt, w.word);
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
