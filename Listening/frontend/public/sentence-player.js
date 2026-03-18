const style = document.createElement('style');
style.textContent = `
    sentence-player {
        display: block;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }
    .sentence-player-wrapper {
        background: #ffffff;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        border: 1px solid #e6ebf0;
    }
    .sentence-progress-bar {
        width: 100%;
        height: 6px;
        background: #e6ebf0;
        border-radius: 3px;
        position: relative;
        cursor: pointer;
        margin-bottom: 15px;
    }
    .sentence-progress-fill {
        position: absolute;
        left: 0;
        top: 0;
        height: 100%;
        background: #9BB7D4;
        border-radius: 3px;
        width: 0%;
        transition: width 0.1s ease;
    }
    .sentence-controls {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 20px;
        margin-bottom: 15px;
    }
    .sentence-control-btn {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: transparent;
        color: #9BB7D4;
        border: 2px solid #9BB7D4;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }
    .sentence-control-btn:hover {
        background: rgba(155, 183, 212, 0.08);
        transform: scale(1.05);
    }
    .sentence-control-btn.primary {
        background: #9BB7D4;
        color: white;
    }
    .sentence-info-card {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        border: 1px solid #e6ebf0;
        margin-top: 15px;
        font-size: 14px;
        color: #6c7a89;
    }
    .sentence-info-title {
        font-size: 14px;
        font-weight: 600;
        color: #3A4E6B;
        margin-bottom: 8px;
    }
    /* 只遮罩句子内容 */
    .sentence-content {
        position: relative;
        display: inline-block;
        min-width: 100px;
    }
    .sentence-mask {
        position: absolute;
        inset: 0;
        background: #f8f9fa;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 1;
        transition: opacity 0.3s ease;
        cursor: pointer;
    }
    .sentence-content:hover .sentence-mask {
        opacity: 0;
    }
`;
document.head.appendChild(style);

class SentencePlayer extends HTMLElement {
    constructor() {
        super();
        this.audio = new Audio();
        this.sentences = [];
        // 👇 改为公共属性，外部可以访问
        this.currentSentenceIndex = 0;
        this.isLoopingCurrent = false;

        this.render();
        this.progressFill = this.querySelector('.sentence-progress-fill');
        this.prevBtn = this.querySelector('.sentence-prev-btn');
        this.playBtn = this.querySelector('.sentence-play-btn');
        this.nextBtn = this.querySelector('.sentence-next-btn');
        this.sentenceInfo = this.querySelector('.sentence-info-content');

        this.bindEvents();
    }

    static get observedAttributes() {
        return ['audio-src', 'sentences'];
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (name === 'audio-src') {
            this.audio.src = newValue;
        } else if (name === 'sentences') {
            try {
                this.sentences = JSON.parse(newValue);
                this.updateSentenceInfo();
                this.updateProgress();
            } catch (e) {
                console.error('解析句子数据失败：', e);
            }
        }
    }

    render() {
        this.innerHTML = `
        <div class="sentence-player-wrapper">
            <div class="sentence-progress-bar">
                <div class="sentence-progress-fill"></div>
            </div>
            <div class="sentence-controls">
                <button class="sentence-control-btn sentence-prev-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="19 20 9 12 19 4 19 20"></polygon>
                    </svg>
                </button>
                <button class="sentence-control-btn primary sentence-play-btn">
                    <svg class="play-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="5 3 19 12 5 21 5 3"></polygon>
                    </svg>
                    <svg class="pause-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none">
                        <rect x="6" y="4" width="4" height="16"></rect>
                        <rect x="14" y="4" width="4" height="16"></rect>
                    </svg>
                </button>
                <button class="sentence-control-btn sentence-next-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="5 4 15 12 5 20 5 4"></polygon>
                    </svg>
                </button>
            </div>
            <div class="sentence-info-card">
                <div class="sentence-info-title">Sentence Information</div>
                <div class="sentence-info-content"></div>
            </div>
        </div>
        `;
    }

    bindEvents() {
        this.prevBtn.addEventListener('click', () => {
            if (this.currentSentenceIndex <= 0) return;
            this.audio.pause();
            this.isLoopingCurrent = false;
            this.currentSentenceIndex--;
            this.updateBtnState();
            this.updateSentenceInfo();
            this.updateProgress();
            
            const currentSentence = this.sentences[this.currentSentenceIndex];
            this.audio.currentTime = currentSentence.start;
            this.audio.play();
            this.isLoopingCurrent = true;

            this.updateBtnState();
        });

        this.playBtn.addEventListener('click', () => {
            if (this.sentences.length === 0) return;
            const s = this.sentences[this.currentSentenceIndex];
            if (this.audio.paused) {
                this.isLoopingCurrent = true;
                this.audio.currentTime = s.start;
                this.audio.play();
            } else {
                this.isLoopingCurrent = false;
                this.audio.pause();
            }
            this.updateBtnState();
        });

        this.nextBtn.addEventListener('click', () => {
            if (this.currentSentenceIndex >= this.sentences.length - 1) return;
            this.audio.pause();
            this.isLoopingCurrent = false;
            this.currentSentenceIndex++;
            this.updateBtnState();
            this.updateSentenceInfo();
            this.updateProgress();
            
            const currentSentence = this.sentences[this.currentSentenceIndex];
            this.audio.currentTime = currentSentence.start;
            this.audio.play();
            this.isLoopingCurrent = true;

            this.updateBtnState();
        });

        this.audio.addEventListener('timeupdate', () => {
            if (!this.isLoopingCurrent) return;
            const s = this.sentences[this.currentSentenceIndex];
            if (this.audio.currentTime >= s.end) {
                this.audio.currentTime = s.start;
            }
        });

        this.audio.addEventListener('ended', () => {
            this.isLoopingCurrent = false;
            this.updateBtnState();
        });
    }

    updateBtnState() {
        const playIcon = this.querySelector('.play-icon');
        const pauseIcon = this.querySelector('.pause-icon');
        playIcon.style.display = this.audio.paused ? 'block' : 'none';
        pauseIcon.style.display = this.audio.paused ? 'none' : 'block';
    }

    updateSentenceInfo() {
        if (this.sentences.length === 0) return;
        const cur = this.currentSentenceIndex + 1;
        const total = this.sentences.length;
        const content = this.sentences[this.currentSentenceIndex].text;

        this.sentenceInfo.innerHTML = `
            Sentence ${cur} of ${total}:
            <span class="sentence-content">
                ${content}
                <span class="sentence-mask">  hidden text (touch me!)</span>
            </span>
        `;
    }

    updateProgress() {
        if (this.sentences.length === 0) return;
        this.progressFill.style.width = ((this.currentSentenceIndex + 1) / this.sentences.length * 100) + '%';
    }
}

customElements.define('sentence-player', SentencePlayer);