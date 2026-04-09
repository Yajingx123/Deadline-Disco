(function () {
  const GUIDE_CLASS = 'acadbeat-guide-focus';

  function toUserKey(userId) {
    const value = String(userId || '').trim();
    return value || 'guest';
  }

  function doneKey(guideKey, userId) {
    return `acadbeat:guide:done:${guideKey}:u:${toUserKey(userId)}`;
  }

  function markSeen(guideKey, userId) {
    try {
      window.localStorage.setItem(doneKey(guideKey, userId), '1');
    } catch (_err) {}
  }

  function hasSeen(guideKey, userId) {
    try {
      return window.localStorage.getItem(doneKey(guideKey, userId)) === '1';
    } catch (_err) {
      return false;
    }
  }

  function resolveTarget(step) {
    if (!step) return null;
    if (typeof step.target === 'function') {
      return step.target() || null;
    }
    if (step.target && typeof step.target === 'string') {
      return document.querySelector(step.target);
    }
    return null;
  }

  function waitForElement(step, timeoutMs) {
    const timeout = Number(timeoutMs || 0) > 0 ? Number(timeoutMs) : 6000;
    const started = Date.now();
    return new Promise((resolve) => {
      function check() {
        const el = resolveTarget(step);
        if (el) {
          resolve(el);
          return;
        }
        if (Date.now() - started >= timeout) {
          resolve(null);
          return;
        }
        window.setTimeout(check, 120);
      }
      check();
    });
  }

  class GuideRunner {
    constructor(options) {
      this.options = options || {};
      this.steps = Array.isArray(this.options.steps) ? this.options.steps : [];
      this.index = 0;
      this.overlayEl = null;
      this.cardEl = null;
      this.focusEl = null;
      this.clickCleanup = null;
      this.active = false;
    }

    start(startIndex) {
      if (!this.steps.length) return;
      this.stop(false);
      this.active = true;
      this.index = Number(startIndex || 0);
      this.mount();
      this.showCurrent();
    }

    stop(markCompleted) {
      if (this.clickCleanup) {
        this.clickCleanup();
        this.clickCleanup = null;
      }
      if (this.focusEl) {
        this.focusEl.classList.remove(GUIDE_CLASS);
        this.focusEl = null;
      }
      if (this.overlayEl && this.overlayEl.parentNode) {
        this.overlayEl.parentNode.removeChild(this.overlayEl);
      }
      if (this.cardEl && this.cardEl.parentNode) {
        this.cardEl.parentNode.removeChild(this.cardEl);
      }
      this.overlayEl = null;
      this.cardEl = null;
      const completed = Boolean(markCompleted);
      this.active = false;
      if (completed && this.options.guideKey) {
        markSeen(this.options.guideKey, this.options.userId);
      }
      if (typeof this.options.onFinish === 'function') {
        this.options.onFinish(completed);
      }
    }

    next() {
      if (!this.active) return;
      const nextIndex = this.index + 1;
      if (nextIndex >= this.steps.length) {
        this.stop(true);
        return;
      }
      this.index = nextIndex;
      this.showCurrent();
    }

    prev() {
      if (!this.active) return;
      this.index = Math.max(0, this.index - 1);
      this.showCurrent();
    }

    mount() {
      this.overlayEl = document.createElement('div');
      this.overlayEl.className = 'acadbeat-guide-overlay';
      // 确保遮罩层不会阻止点击事件
      this.overlayEl.style.pointerEvents = 'none';
      document.body.appendChild(this.overlayEl);

      this.cardEl = document.createElement('div');
      this.cardEl.className = 'acadbeat-guide-card';
      // 确保卡片可以接收点击事件
      this.cardEl.style.pointerEvents = 'auto';
      document.body.appendChild(this.cardEl);
    }

    async showCurrent() {
      if (!this.active) return;
      const step = this.steps[this.index];
      if (!step) {
        this.stop(true);
        return;
      }

      if (typeof step.beforeEnter === 'function') {
        try {
          await Promise.resolve(step.beforeEnter());
        } catch (_err) {}
      }

      if (this.clickCleanup) {
        this.clickCleanup();
        this.clickCleanup = null;
      }
      if (this.focusEl) {
        this.focusEl.classList.remove(GUIDE_CLASS);
        this.focusEl = null;
      }

      const targetEl = await waitForElement(step, step.waitTimeout || 6000);
      this.focusEl = targetEl;
      if (targetEl) {
        targetEl.classList.add(GUIDE_CLASS);
        try {
          targetEl.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
        } catch (_err) {}
      }

      this.renderCard(step, targetEl);
      this.bindStepClickIfNeeded(step, targetEl);
    }

    bindStepClickIfNeeded(step, targetEl) {
      if (!step.requireClick || !targetEl) return;
      const handler = (e) => {
        // 不阻止事件默认行为，确保按钮的点击事件能够正常触发
        // 只是在点击后进入下一步指南
        window.setTimeout(() => this.next(), 140);
      };
      targetEl.addEventListener('click', handler, { once: true });
      this.clickCleanup = () => targetEl.removeEventListener('click', handler);
    }

    renderCard(step, targetEl) {
      if (!this.cardEl) return;
      const total = this.steps.length;
      const current = this.index + 1;
      const nextDisabled = Boolean(step.requireClick && targetEl);
      const clickTip = step.requireClick
        ? '<div class="acadbeat-guide-card__meta">This step will continue after you click the highlighted area.</div>'
        : '';
      this.cardEl.innerHTML = `
        <h4>${step.title || 'Guide'}</h4>
        <p>${step.content || ''}</p>
        ${clickTip}
        <div class="acadbeat-guide-card__meta">Step ${current} / ${total}</div>
        <div class="acadbeat-guide-card__actions">
          <button type="button" class="acadbeat-guide-btn" data-guide-action="skip">Skip</button>
          <button type="button" class="acadbeat-guide-btn" data-guide-action="prev" ${this.index === 0 ? 'disabled' : ''}>Prev</button>
          <button type="button" class="acadbeat-guide-btn acadbeat-guide-btn--primary" data-guide-action="next" ${nextDisabled ? 'disabled' : ''}>${current === total ? 'Finish' : 'Next'}</button>
        </div>
      `;

      const btnSkip = this.cardEl.querySelector('[data-guide-action="skip"]');
      const btnPrev = this.cardEl.querySelector('[data-guide-action="prev"]');
      const btnNext = this.cardEl.querySelector('[data-guide-action="next"]');
      btnSkip?.addEventListener('click', () => this.stop(false));
      btnPrev?.addEventListener('click', () => this.prev());
      btnNext?.addEventListener('click', () => this.next());

      const cardRect = this.cardEl.getBoundingClientRect();
      const vw = window.innerWidth;
      const vh = window.innerHeight;

      let top = Math.round((vh - cardRect.height) / 2);
      let left = Math.round((vw - cardRect.width) / 2);

      if (targetEl) {
        const rect = targetEl.getBoundingClientRect();
        const gap = 14;
        const place = String(step.placement || 'bottom');
        if (place === 'top') {
          top = rect.top - cardRect.height - gap;
          left = rect.left;
        } else if (place === 'left') {
          top = rect.top;
          left = rect.left - cardRect.width - gap;
        } else if (place === 'right') {
          top = rect.top;
          left = rect.right + gap;
        } else {
          top = rect.bottom + gap;
          left = rect.left;
        }

        // If this step requires clicking, avoid covering the target area.
        if (step.requireClick) {
          const targetCenterX = rect.left + (rect.width / 2);
          const targetCenterY = rect.top + (rect.height / 2);
          const fitsTop = rect.top >= (cardRect.height + gap + 10);
          const fitsBottom = (vh - rect.bottom) >= (cardRect.height + gap + 10);
          const fitsLeft = rect.left >= (cardRect.width + gap + 10);
          const fitsRight = (vw - rect.right) >= (cardRect.width + gap + 10);

          if (fitsTop) {
            top = rect.top - cardRect.height - gap;
            left = targetCenterX - (cardRect.width / 2);
          } else if (fitsBottom) {
            top = rect.bottom + gap;
            left = targetCenterX - (cardRect.width / 2);
          } else if (fitsRight) {
            top = targetCenterY - (cardRect.height / 2);
            left = rect.right + gap;
          } else if (fitsLeft) {
            top = targetCenterY - (cardRect.height / 2);
            left = rect.left - cardRect.width - gap;
          }
        }
      }

      top = Math.max(10, Math.min(vh - cardRect.height - 10, top));
      left = Math.max(10, Math.min(vw - cardRect.width - 10, left));
      this.cardEl.style.top = `${top}px`;
      this.cardEl.style.left = `${left}px`;
    }
  }

  window.AcadBeatGuide = {
    create(options) {
      return new GuideRunner(options);
    },
    hasSeen,
    markSeen,
    doneKey,
  };
})();
