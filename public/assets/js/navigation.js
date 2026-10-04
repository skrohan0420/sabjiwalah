(function () {
  function handleHistoryBack(event) {
    const trigger = event.target.closest('[data-history-back]');

    if (!trigger) {
      return;
    }

    if (window.history.length <= 1) {
      return;
    }

    event.preventDefault();
    window.history.back();
  }

  document.addEventListener('click', handleHistoryBack);
})();
