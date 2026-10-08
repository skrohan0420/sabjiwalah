(function () {
  const base = new URL(document.currentScript.dataset.baseUrl);

  // Accept app-relative paths and already-prefixed local paths. External
  // product images keep their original URL.
  window.Sabjiwalah = {
    url(path = '') {
      const value = String(path);
      if (/^(?:[a-z][a-z0-9+.-]*:|\/\/)/i.test(value)) return value;
      if (base.pathname !== '/' && value.startsWith(base.pathname)) {
        return new URL(value, base.origin).href;
      }
      return new URL(value.replace(/^\/+/, ''), base).href;
    },
  };
})();
