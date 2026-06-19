(function () {
  function setActiveLinkFromHash() {
    var hash = window.location.hash || '';

    var nav = document.querySelector('.vms-continuity-binder .vms-cb-nav');
    if (!nav) return;

    var links = nav.querySelectorAll('.vms-cb-nav-link');
    if (!links || !links.length) return;

    // Clear
    for (var i = 0; i < links.length; i++) {
      links[i].classList.remove('is-active');
    }

    if (!hash) return;

    for (var j = 0; j < links.length; j++) {
      var href = links[j].getAttribute('href') || '';
      // Match on the fragment portion only
      if (href.indexOf(hash) !== -1) {
        links[j].classList.add('is-active');
        break;
      }
    }
  }

  function wireNavClicks() {
    var nav = document.querySelector('.vms-continuity-binder .vms-cb-nav');
    if (!nav) return;

    nav.addEventListener('click', function (e) {
      var target = e.target;
      if (!target) return;

      // Support clicks on nested elements inside the link
      while (target && target !== nav && !target.classList.contains('vms-cb-nav-link')) {
        target = target.parentElement;
      }
      if (!target || !target.classList || !target.classList.contains('vms-cb-nav-link')) return;

      // The hash will update after the browser navigates; set now to feel instant.
      var href = target.getAttribute('href') || '';
      var hashIndex = href.indexOf('#');
      if (hashIndex !== -1) {
        window.location.hash = href.substring(hashIndex);
      }

      setActiveLinkFromHash();
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    wireNavClicks();
    setActiveLinkFromHash();

    window.addEventListener('hashchange', function () {
      setActiveLinkFromHash();
    });
  });
})();
