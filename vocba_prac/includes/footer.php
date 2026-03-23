
      </main>
    </div>
    <div class="appModal" id="appModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="appModalTitle">
      <div class="appModal__card">
        <h3 class="appModal__title" id="appModalTitle">Notice</h3>
        <p class="appModal__body" id="appModalBody"></p>
        <div class="appModal__actions">
          <button class="primary" type="button" id="appModalConfirm">OK</button>
        </div>
      </div>
    </div>
    <script>
      (function() {
        var modal = document.getElementById('appModal');
        var titleEl = document.getElementById('appModalTitle');
        var bodyEl = document.getElementById('appModalBody');
        var confirmBtn = document.getElementById('appModalConfirm');
        if (!modal || !titleEl || !bodyEl || !confirmBtn) return;

        function closeModal() {
          modal.classList.remove('isOpen');
          modal.setAttribute('aria-hidden', 'true');
        }

        window.vocabOpenModal = function(options) {
          var opts = options || {};
          titleEl.textContent = opts.title || 'Notice';
          bodyEl.textContent = opts.message || '';
          confirmBtn.textContent = opts.confirmText || 'OK';
          confirmBtn.onclick = function() {
            closeModal();
            if (typeof opts.onConfirm === 'function') opts.onConfirm();
          };
          modal.classList.add('isOpen');
          modal.setAttribute('aria-hidden', 'false');
        };

        modal.addEventListener('click', function(e) {
          if (e.target === modal) closeModal();
        });
        document.addEventListener('keydown', function(e) {
          if (e.key === 'Escape' && modal.classList.contains('isOpen')) closeModal();
        });
      })();
    </script>
  </body>
</html>
