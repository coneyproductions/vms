(() => {
  const ROOT_SELECTOR = '.vms-public-cal';
  const ENTRY_SELECTOR = '.vms-cal-entry';
  const POP_SELECTOR = '.vms-cal-pop';
  const OPEN_CLASS = 'is-pop-open';
  const ABOVE_CLASS = 'is-above';
  const BELOW_CLASS = 'is-below';
  const GAP = 8;
  const VIEWPORT_MARGIN = 16;
  const CLOSE_DELAY = 110;
  const REPOSITION_DELAYS = [0, 120, 260];
  const MOBILE_LIST_QUERY = '(max-width: 1180px), (hover: none) and (pointer: coarse)';

  function getPopover(entry) {
    return entry ? entry.querySelector(POP_SELECTOR) : null;
  }

  function clearCloseTimer(entry) {
    if (entry && entry.__vmsPopCloseTimer) {
      window.clearTimeout(entry.__vmsPopCloseTimer);
      entry.__vmsPopCloseTimer = null;
    }
  }

  function setCellOpen(entry, open) {
    if (!entry || typeof entry.closest !== 'function') {
      return;
    }
    const cell = entry.closest('td, .vms-cal-cell');
    if (!cell) {
      return;
    }
    cell.classList.toggle('vms-pop-cell-open', !!open);
  }

  function getClientSize() {
    const doc = document.documentElement;
    const vv = window.visualViewport;
    if (vv) {
      return {
        width: vv.width || window.innerWidth || doc.clientWidth || 0,
        height: vv.height || window.innerHeight || doc.clientHeight || 0,
        offsetLeft: vv.offsetLeft || 0,
        offsetTop: vv.offsetTop || 0,
      };
    }
    return {
      width: window.innerWidth || doc.clientWidth || 0,
      height: window.innerHeight || doc.clientHeight || 0,
      offsetLeft: 0,
      offsetTop: 0,
    };
  }

  function getTopBoundary(entry) {
    if (!entry || typeof entry.closest !== 'function') {
      return VIEWPORT_MARGIN;
    }
    const details = entry.closest('.vms-av-month details');
    if (!details) {
      return VIEWPORT_MARGIN;
    }
    const summary = Array.from(details.children || []).find((child) => child && child.tagName === 'SUMMARY');
    if (!summary) {
      return VIEWPORT_MARGIN;
    }
    const rect = summary.getBoundingClientRect();
    return Math.max(VIEWPORT_MARGIN, rect.bottom + GAP);
  }

  function usesFixedPopover(entry) {
    return !!(entry && typeof entry.closest === 'function' && entry.closest('.vms-av-grid'));
  }

  function placePopover(entry) {
    const pop = getPopover(entry);
    if (!pop) {
      return;
    }

    entry.classList.add(OPEN_CLASS);
    const fixedPopover = usesFixedPopover(entry);

    pop.classList.remove(ABOVE_CLASS, BELOW_CLASS);
    pop.style.position = fixedPopover ? 'fixed' : 'absolute';
    pop.style.left = fixedPopover ? `${entry.getBoundingClientRect().left}px` : '0px';
    pop.style.top = fixedPopover ? `${entry.getBoundingClientRect().bottom + GAP}px` : `${entry.offsetHeight + GAP}px`;
    pop.style.right = 'auto';
    pop.style.bottom = 'auto';

    const entryRect = entry.getBoundingClientRect();
    const popRect = pop.getBoundingClientRect();
    const viewport = getClientSize();

    if (!entryRect.width || !popRect.width || !viewport.width || !viewport.height) {
      return;
    }

    const viewportLeft = viewport.offsetLeft || 0;
    const viewportTop = viewport.offsetTop || 0;
    const viewportRight = viewportLeft + viewport.width;
    const viewportBottom = viewportTop + viewport.height;
    const topBoundary = Math.max(getTopBoundary(entry), viewportTop + VIEWPORT_MARGIN);

    const belowTop = entryRect.bottom + GAP;
    const aboveTop = entryRect.top - popRect.height - GAP;
    const fitsBelow = belowTop + popRect.height <= (viewportBottom - VIEWPORT_MARGIN);
    const fitsAbove = aboveTop >= topBoundary;

    let targetTop = belowTop;
    let placeAboveFlag = false;

    if (!fitsBelow && fitsAbove) {
      targetTop = aboveTop;
      placeAboveFlag = true;
    } else if (!fitsBelow && !fitsAbove) {
      const roomBelow = (viewportBottom - VIEWPORT_MARGIN) - belowTop;
      const roomAbove = entryRect.top - GAP - topBoundary;
      if (roomAbove > roomBelow) {
        targetTop = Math.max(topBoundary, aboveTop);
        placeAboveFlag = true;
      } else {
        targetTop = Math.max(topBoundary, Math.min(belowTop, viewportBottom - VIEWPORT_MARGIN - popRect.height));
      }
    }

    const maxHeight = Math.max(180, viewportBottom - targetTop - VIEWPORT_MARGIN);
    pop.style.maxHeight = `${Math.round(maxHeight)}px`;
    pop.style.overflowY = 'auto';

    let targetLeft = entryRect.left;
    if (targetLeft + popRect.width > viewportRight - VIEWPORT_MARGIN) {
      targetLeft = viewportRight - VIEWPORT_MARGIN - popRect.width;
    }
    if (targetLeft < viewportLeft + VIEWPORT_MARGIN) {
      targetLeft = viewportLeft + VIEWPORT_MARGIN;
    }

    if (fixedPopover) {
      pop.style.left = `${Math.round(targetLeft)}px`;
      pop.style.top = `${Math.round(targetTop)}px`;
    } else {
      const relativeLeft = targetLeft - entryRect.left;
      const relativeTop = targetTop - entryRect.top;
      pop.style.left = `${Math.round(relativeLeft)}px`;
      pop.style.top = `${Math.round(relativeTop)}px`;
    }
    pop.classList.toggle(ABOVE_CLASS, placeAboveFlag);
    pop.classList.toggle(BELOW_CLASS, !placeAboveFlag);
  }

  function scheduleReposition(entry) {
    if (!entry) {
      return;
    }
    if (!Array.isArray(entry.__vmsRepositionTimers)) {
      entry.__vmsRepositionTimers = [];
    }
    entry.__vmsRepositionTimers.forEach((timerId) => window.clearTimeout(timerId));
    entry.__vmsRepositionTimers = [];
    REPOSITION_DELAYS.forEach((delay) => {
      const timerId = window.setTimeout(() => {
        if (entry.classList.contains(OPEN_CLASS)) {
          placePopover(entry);
        }
      }, delay);
      entry.__vmsRepositionTimers.push(timerId);
    });
  }

  function bindPopoverMedia(entry) {
    const pop = getPopover(entry);
    if (!pop) {
      return;
    }
    pop.querySelectorAll('img').forEach((img) => {
      if (img.__vmsCalendarBound) {
        return;
      }
      img.__vmsCalendarBound = true;
      const refresh = () => {
        if (entry.classList.contains(OPEN_CLASS)) {
          scheduleRefresh();
        }
      };
      if (img.complete) {
        refresh();
      } else {
        img.addEventListener('load', refresh, { passive: true });
        img.addEventListener('error', refresh, { passive: true });
      }
    });
  }

  function openEntry(entry) {
    if (!entry) {
      return;
    }
    clearCloseTimer(entry);
    entry.classList.add(OPEN_CLASS);
    setCellOpen(entry, true);
    bindPopoverMedia(entry);
    placePopover(entry);
    scheduleReposition(entry);
  }

  function scheduleClose(entry, event) {
    if (!entry) {
      return;
    }
    const nextTarget = event && event.relatedTarget ? event.relatedTarget : null;
    if (nextTarget && entry.contains(nextTarget)) {
      return;
    }
    clearCloseTimer(entry);
    entry.__vmsPopCloseTimer = window.setTimeout(() => {
      entry.classList.remove(OPEN_CLASS);
      setCellOpen(entry, false);
    }, CLOSE_DELAY);
  }

  function bindEntry(entry) {
    if (!entry || entry.__vmsCalendarBound) {
      return;
    }
    entry.__vmsCalendarBound = true;

    const pop = getPopover(entry);

    entry.addEventListener('mouseenter', () => openEntry(entry));
    entry.addEventListener('mouseleave', (event) => scheduleClose(entry, event));
    entry.addEventListener('focusin', () => openEntry(entry));
    entry.addEventListener('focusout', (event) => scheduleClose(entry, event));

    if (pop) {
      pop.addEventListener('mouseenter', () => openEntry(entry));
      pop.addEventListener('mouseleave', (event) => scheduleClose(entry, event));
      pop.addEventListener('focusin', () => openEntry(entry));
      pop.addEventListener('focusout', (event) => scheduleClose(entry, event));
    }
  }

  function bindAll(root = document) {
    root.querySelectorAll(`${ROOT_SELECTOR} ${ENTRY_SELECTOR}`).forEach(bindEntry);
  }


  function isResponsiveListViewport() {
    return !!(window.matchMedia && window.matchMedia(MOBILE_LIST_QUERY).matches);
  }

  function forceResponsiveListControls(root) {
    if (!root || typeof root.querySelector !== 'function' || !isResponsiveListViewport()) {
      return;
    }

    const viewSelect = root.querySelector('.vms-public-cal-view');
    if (viewSelect && viewSelect.value !== 'list') {
      viewSelect.value = 'list';
    }

    const form = root.querySelector('.vms-public-cal-filters');
    if (form && !form.__vmsResponsiveListBound) {
      form.__vmsResponsiveListBound = true;
      form.addEventListener('submit', () => {
        if (!isResponsiveListViewport()) {
          return;
        }
        const select = form.querySelector('.vms-public-cal-view');
        if (select) {
          select.value = 'list';
          return;
        }
        let hidden = form.querySelector('input[name="view"]');
        if (!hidden) {
          hidden = document.createElement('input');
          hidden.type = 'hidden';
          hidden.name = 'view';
          form.appendChild(hidden);
        }
        hidden.value = 'list';
      });
    }

    // Defensive recovery for cached Month HTML produced before this responsive-list fix.
    const hasMonthGrid = !!root.querySelector('.vms-cal-grid');
    const hasList = !!root.querySelector('.vms-public-cal-list');
    if (hasMonthGrid && !hasList) {
      const url = new URL(window.location.href);
      if (url.searchParams.get('view') !== 'list') {
        url.searchParams.set('view', 'list');
        window.location.replace(url.toString());
      }
    }
  }

  function enforceResponsiveListView() {
    document.querySelectorAll(ROOT_SELECTOR).forEach(forceResponsiveListControls);
  }

  function refreshOpenPopovers() {
    document.querySelectorAll(`${ROOT_SELECTOR} ${ENTRY_SELECTOR}.${OPEN_CLASS}`).forEach(placePopover);
  }

  let refreshScheduled = false;
  function scheduleRefresh() {
    if (refreshScheduled) {
      return;
    }
    refreshScheduled = true;
    window.requestAnimationFrame(() => {
      refreshScheduled = false;
      refreshOpenPopovers();
    });
  }

  function init() {
    bindAll(document);
    enforceResponsiveListView();
    window.addEventListener('resize', () => {
      scheduleRefresh();
      enforceResponsiveListView();
    }, { passive: true });
    window.addEventListener('scroll', scheduleRefresh, { passive: true });
    document.addEventListener('scroll', scheduleRefresh, true);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
