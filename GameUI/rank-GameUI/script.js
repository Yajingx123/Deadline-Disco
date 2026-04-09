(function rankGameUiAdapter() {
  function remapRelativeUrl(raw) {
    if (typeof raw !== 'string') return raw;
    if (raw.startsWith('../Auth/backend/api/')) {
      return '/Auth/backend/api/' + raw.slice('../Auth/backend/api/'.length);
    }
    if (raw.startsWith('../challenge/api/')) {
      return '/challenge/api/' + raw.slice('../challenge/api/'.length);
    }
    return raw;
  }

  // Keep canonical rank logic, but remap API paths for GameUI mount location.
  var nativeFetch = window.fetch.bind(window);
  window.fetch = function patchedFetch(input, init) {
    if (typeof input === 'string') {
      return nativeFetch(remapRelativeUrl(input), init);
    }
    if (input && typeof input.url === 'string') {
      var nextUrl = remapRelativeUrl(input.url);
      if (nextUrl !== input.url) {
        return nativeFetch(nextUrl, init);
      }
    }
    return nativeFetch(input, init);
  };

  function normalizeLinks() {
    var map = {
      '../home.html?login=1': '/home.html?login=1',
      '../home.html?challenge=1': '/home.html?challenge=1',
      '../gates/competition-gate.html': '/gates/competition-gate.html',
    };
    Object.keys(map).forEach(function (from) {
      document.querySelectorAll('a[href="' + from + '"]').forEach(function (a) {
        a.setAttribute('href', map[from]);
      });
    });
  }

  var observer = new MutationObserver(normalizeLinks);
  var startObserver = function () {
    normalizeLinks();
    if (document.body) {
      observer.observe(document.body, { childList: true, subtree: true });
    }
  };

  var script = document.createElement('script');
  script.src = '/rank/script.js';
  script.onload = function () {
    if (document.readyState !== 'loading') {
      document.dispatchEvent(new Event('DOMContentLoaded'));
    }
    startObserver();
  };
  script.onerror = function () {
    startObserver();
  };
  document.head.appendChild(script);
})();
