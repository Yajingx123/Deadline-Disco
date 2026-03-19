// exam.js

// 考试配置
const EXAM_CONFIG = {
  questionCount: 30, // 每次随机抽取的题目数量
  timeLimitSeconds: 20 * 60 // 考试时间（与题量匹配）
};

let currentQuestions = [];
let userAnswers = {}; // 记录用户答案：{ 题目序号: 答案 }
let timeLeft = EXAM_CONFIG.timeLimitSeconds;
let timerInterval;

function getSelectedLevel() {
  const select = document.getElementById('level-select');
  const level = select ? select.value : 'advanced';
  return level || 'advanced';
}

function getQuestionBankForLevel(level) {
  if (typeof QUESTION_BANKS === 'undefined' || !QUESTION_BANKS) return [];
  return QUESTION_BANKS[level] || QUESTION_BANKS.advanced || [];
}

function resetRuntimeState() {
  currentQuestions = [];
  userAnswers = {};
  timeLeft = EXAM_CONFIG.timeLimitSeconds;
  clearInterval(timerInterval);
}

// 初始化考试
function initExam() {
  resetRuntimeState();

  // 1. 从题库中随机抽取指定数量的题目 (洗牌算法思想)
  const level = getSelectedLevel();
  const questionBank = getQuestionBankForLevel(level);
  const shuffled = [...questionBank].sort(() => 0.5 - Math.random());
  currentQuestions = shuffled.slice(0, Math.min(EXAM_CONFIG.questionCount, shuffled.length));

  // 2. 更新头部信息
  document.getElementById('exam-info-chip').innerText = `${EXAM_CONFIG.timeLimitSeconds / 60} Minutes | ${currentQuestions.length} Questions`;
  document.getElementById('answered-count').innerText = `0 / ${currentQuestions.length}`;

  // 3. 渲染题目和答题卡
  renderQuestions();
  renderNavGrid();

  // 4. 绑定提交按钮
  const submitBtn = document.getElementById('submit-btn');
  submitBtn.disabled = false;
  submitBtn.innerText = "Submit Exam";
  submitBtn.onclick = submitExam;

  const retakeBtn = document.getElementById('retake-btn');
  retakeBtn.style.display = "none";
  retakeBtn.onclick = retakeExam;

  // 5. 启动计时器
  startTimer();
}

// 渲染题目列表
function renderQuestions() {
  const container = document.getElementById('question-container');
  container.innerHTML = ''; // 清空容器

  currentQuestions.forEach((q, index) => {
    const qNumber = index + 1;
    let inputHtml = '';

    // 根据题型渲染不同的输入结构
    if (q.type === 'choice') {
      let optionsHtml = '';
      for (const [key, value] of Object.entries(q.options)) {
        optionsHtml += `
          <label class="option-row rich">
            <input type="radio" name="q${qNumber}" value="${key}" onchange="markAnswered(${qNumber}, '${key}')">
            <span>${key.toUpperCase()}) ${value}</span>
          </label>
        `;
      }
      inputHtml = `<div class="options-list">${optionsHtml}</div>`;
    } else if (q.type === 'fill') {
      inputHtml = `
        <input type="text" class="fill-input" name="q${qNumber}" 
               placeholder="Type your answer here..." 
               oninput="markAnswered(${qNumber}, this.value)">
      `;
    }

    const qHtml = `
      <div class="question-box card" id="box-q${qNumber}">
        <div class="question-meta">
          <span class="chip">Question ${qNumber}</span>
        </div>
        <div class="question-render">
          <h3 class="question-title">${q.question}</h3>
          ${inputHtml}
        </div>
      </div>
    `;
    container.innerHTML += qHtml;
  });
}

// 渲染右侧答题导航卡
function renderNavGrid() {
  const grid = document.getElementById('nav-grid');
  grid.innerHTML = '';
  currentQuestions.forEach((_, index) => {
    const qNumber = index + 1;
    // 点击答题卡按钮，页面平滑滚动到对应题目
    grid.innerHTML += `<button id="nav-btn-${qNumber}" onclick="document.getElementById('box-q${qNumber}').scrollIntoView({behavior: 'smooth', block: 'center'})">${qNumber}</button>`;
  });
}

// 记录用户答题状态并更新 UI
function markAnswered(qNumber, value) {
  if (value.trim() !== "") {
    userAnswers[qNumber] = value;
    document.getElementById(`nav-btn-${qNumber}`).classList.add('answered');
  } else {
    delete userAnswers[qNumber];
    document.getElementById(`nav-btn-${qNumber}`).classList.remove('answered');
  }

  // 更新答题进度统计
  const answeredCount = Object.keys(userAnswers).length;
  document.getElementById('answered-count').innerText = `${answeredCount} / ${currentQuestions.length}`;
}

// 计时器核心逻辑
function startTimer() {
  const display = document.getElementById('time-display');

  // 初始化一次显示
  let initMinutes = Math.floor(timeLeft / 60);
  let initSeconds = timeLeft % 60;
  initMinutes = initMinutes < 10 ? '0' + initMinutes : initMinutes;
  initSeconds = initSeconds < 10 ? '0' + initSeconds : initSeconds;
  display.innerText = `${initMinutes}:${initSeconds}`;

  timerInterval = setInterval(() => {
    timeLeft--;

    let minutes = Math.floor(timeLeft / 60);
    let seconds = timeLeft % 60;

    // 补零格式化
    minutes = minutes < 10 ? '0' + minutes : minutes;
    seconds = seconds < 10 ? '0' + seconds : seconds;

    display.innerText = `${minutes}:${seconds}`;

    // 倒计时结束，自动交卷
    if (timeLeft <= 0) {
      clearInterval(timerInterval);
      alert("Time is up! Auto-submitting your exam.");
      submitExam();
    }
  }, 1000); // 每 1000 毫秒 (1秒) 执行一次
}

// 提交与评分逻辑
function submitExam() {
  clearInterval(timerInterval); // 停止计分
  const submitBtn = document.getElementById('submit-btn');
  submitBtn.disabled = true;
  submitBtn.innerText = "Submitted";

  let score = 0;

  // 冻结输入并比对答案
  currentQuestions.forEach((q, index) => {
    const qNumber = index + 1;
    const userAnswer = userAnswers[qNumber] ? userAnswers[qNumber].toLowerCase().trim() : null;
    const correctAnswer = q.answer.toLowerCase();

    // 禁用该题目的输入
    const inputs = document.getElementsByName(`q${qNumber}`);
    inputs.forEach(input => input.disabled = true);

    const questionBox = document.getElementById(`box-q${qNumber}`);

    // 评分比对
    if (userAnswer === correctAnswer) {
      score++;
      questionBox.style.borderLeft = "6px solid var(--green)"; // 答对标记绿色
    } else {
      questionBox.style.borderLeft = "6px solid var(--red)";   // 答错标记红色
      // 在题目下方追加正确答案提示
      const answerNote = document.createElement('div');
      answerNote.className = "answer-note";
      answerNote.style.marginTop = "10px";
      answerNote.style.color = "var(--red)";
      answerNote.style.fontWeight = "bold";
      answerNote.innerText = `Correct Answer: ${q.answer}`;
      questionBox.querySelector('.question-render').appendChild(answerNote);
    }
  });

  // 给出最终分数反馈
  const finalPercentage = Math.round((score / currentQuestions.length) * 100);
  setTimeout(() => {
    alert(`Exam submitted!\nYour Score: ${score} / ${currentQuestions.length} (${finalPercentage}%)`);
  }, 300);

  const retakeBtn = document.getElementById('retake-btn');
  retakeBtn.style.display = "block";
}

function retakeExam() {
  initExam();
  const top = document.querySelector('.content-body');
  if (top) top.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// 页面加载完成后立即初始化
window.onload = () => {
  const select = document.getElementById('level-select');
  if (select) {
    select.onchange = () => initExam();
  }
  initExam();
};