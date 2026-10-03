document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.vms-express-bar').forEach((shell) => {
    const categories = Array.from(shell.querySelectorAll('[data-category]'));
    const rows = Array.from(shell.querySelectorAll('[data-item-row]'));
    const payloadField = shell.querySelector('[data-vmseb-payload]');
    const targetField = shell.querySelector('[data-vmseb-target]');
    const selectedCountEl = shell.querySelector('[data-vmseb-selected-count]');
    const regularSubtotalEl = shell.querySelector('[data-vmseb-regular-subtotal]');
    const summaryWrapper = shell.querySelector('[data-vmseb-summary-wrapper]');
    const pickupInput = shell.querySelector('input[name="vmseb_pickup_name"]');
    const mobileAction = shell.querySelector('[data-vmseb-mobile-action]');
    const mobileReviewBtn = shell.querySelector('[data-vmseb-mobile-review]');
    const cartCount = mobileAction ? (parseInt(mobileAction.getAttribute('data-vmseb-cart-count') || '0', 10) || 0) : 0;
    const cartUrl = mobileAction ? (mobileAction.getAttribute('data-vmseb-cart-url') || '') : '';
    const orderingOpen = !mobileAction || mobileAction.getAttribute('data-vmseb-ordering-open') === '1';
    const adminBar = document.getElementById('wpadminbar');
    let pendingAccordionScrollFrame = 0;
    let pendingAccordionScrollTimeout = 0;

    const ageGateEnabled = shell.getAttribute('data-age-gate-enabled') === '1';
    const ageGateMinAge = parseInt(shell.getAttribute('data-age-gate-min-age') || '21', 10) || 21;
    const eventPlanId = shell.getAttribute('data-event-plan-id') || '0';
    const ageGateStorageKey = `vmseb_age_gate_${eventPlanId}`;
    const ageGateModal = shell.querySelector('[data-vmseb-age-gate]');
    const ageGateDate = shell.querySelector('[data-vmseb-age-gate-date]');
    const ageGateStatus = shell.querySelector('[data-vmseb-age-gate-status]');
    const ageGateConfirmBtn = shell.querySelector('[data-vmseb-age-gate-confirm]');
    const ageGateConfirmedField = shell.querySelector('[data-vmseb-age-gate-confirmed]');
    let pendingAgeGateAction = null;

    const eventSelector = shell.querySelector('[data-vmseb-event-selector]');
    const eventSelectorToggle = shell.querySelector('[data-vmseb-event-selector-toggle]');
    const eventSelectorClose = shell.querySelector('[data-vmseb-event-selector-close]');
    const admissionWarning = shell.querySelector('[data-vmseb-admission-warning]');
    const admissionAck = shell.querySelector('[data-vmseb-admission-ack]');
    const admissionStorageKey = `vmseb_admission_ack_${eventPlanId}`;

    const setEventSelectorOpen = (open) => {
      if (!eventSelector) return;
      eventSelector.hidden = !open;
      if (eventSelectorToggle) eventSelectorToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    if (eventSelectorToggle) {
      eventSelectorToggle.addEventListener('click', () => setEventSelectorOpen(eventSelector ? eventSelector.hidden : false));
    }
    if (eventSelectorClose) {
      eventSelectorClose.addEventListener('click', () => setEventSelectorOpen(false));
    }

    shell.querySelectorAll('[data-vmseb-event-choice]').forEach((choice) => {
      choice.addEventListener('click', () => {
        const targetId = choice.getAttribute('data-event-plan-id') || '0';
        const targetUrl = choice.getAttribute('data-event-url') || '';
        if (targetId === eventPlanId) {
          setEventSelectorOpen(false);
          return;
        }
        const selectedCount = parseInt(selectedCountEl ? selectedCountEl.textContent || '0' : '0', 10) || 0;
        if (selectedCount > 0) {
          const confirmed = window.confirm(`You have ${selectedCount} selected Express Bar item${selectedCount === 1 ? '' : 's'} that have not been added to your cart. Switching events will discard those selections. Continue?`);
          if (!confirmed) return;
        }
        if (targetUrl) window.location.assign(targetUrl);
      });
    });

    if (admissionWarning) {
      try {
        if (window.sessionStorage && window.sessionStorage.getItem(admissionStorageKey) === '1') {
          admissionWarning.remove();
        }
      } catch (error) {}
    }
    if (admissionAck) {
      admissionAck.addEventListener('click', () => {
        try {
          if (window.sessionStorage) window.sessionStorage.setItem(admissionStorageKey, '1');
        } catch (error) {}
        if (admissionWarning) admissionWarning.remove();
      });
    }

    const setAgeGateConfirmed = (confirmed) => {
      if (ageGateConfirmedField) {
        ageGateConfirmedField.value = confirmed ? '1' : '0';
      }
    };

    const ageGateAlreadyPassed = () => {
      if (!ageGateEnabled) return true;
      const saved = window.sessionStorage ? window.sessionStorage.getItem(ageGateStorageKey) : null;
      const passed = saved === '1';
      setAgeGateConfirmed(passed);
      return passed;
    };

    const setAgeGateStatus = (message, isError = false) => {
      if (!ageGateStatus) return;
      ageGateStatus.textContent = message || '';
      ageGateStatus.classList.toggle('is-error', !!isError);
      ageGateStatus.classList.toggle('is-success', !!message && !isError);
    };

    const closeAgeGate = () => {
      if (!ageGateModal) return;
      ageGateModal.hidden = true;
      pendingAgeGateAction = null;
    };

    const openAgeGate = (afterConfirm) => {
      if (!ageGateEnabled || ageGateAlreadyPassed() || !ageGateModal) {
        if (typeof afterConfirm === 'function') afterConfirm();
        return;
      }
      pendingAgeGateAction = afterConfirm;
      ageGateModal.hidden = false;
      if (ageGateDate) {
        if (!ageGateDate.value) ageGateDate.value = '';
        ageGateDate.focus();
      }
      setAgeGateStatus('');
    };

    const calculateAge = (value) => {
      if (!value) return null;
      const dob = new Date(`${value}T00:00:00`);
      if (Number.isNaN(dob.getTime())) return null;
      const today = new Date();
      let age = today.getFullYear() - dob.getFullYear();
      const monthDiff = today.getMonth() - dob.getMonth();
      if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
        age -= 1;
      }
      return age;
    };

    if (ageGateModal) {
      ageGateModal.querySelectorAll('[data-vmseb-age-gate-close]').forEach((el) => {
        el.addEventListener('click', () => closeAgeGate());
      });
      if (ageGateConfirmBtn) {
        ageGateConfirmBtn.addEventListener('click', () => {
          const age = calculateAge(ageGateDate ? ageGateDate.value : '');
          if (age === null) {
            setAgeGateStatus('Enter your birthday to continue.', true);
            return;
          }
          if (age < ageGateMinAge) {
            setAgeGateConfirmed(false);
            setAgeGateStatus(`You must be at least ${ageGateMinAge} to place this alcohol order online.`, true);
            return;
          }
          if (window.sessionStorage) {
            window.sessionStorage.setItem(ageGateStorageKey, '1');
          }
          setAgeGateConfirmed(true);
          setAgeGateStatus(`Thanks. ID is still required at pickup.`, false);
          const action = pendingAgeGateAction;
          window.setTimeout(() => {
            closeAgeGate();
            if (typeof action === 'function') action();
          }, 150);
        });
      }
    }

    ageGateAlreadyPassed();

    const openCategory = (current) => {
      categories.forEach((section) => {
        const isCurrent = section === current;
        section.classList.toggle('is-open', isCurrent);
        const toggle = section.querySelector('[data-category-toggle]');
        if (toggle) toggle.setAttribute('aria-expanded', isCurrent ? 'true' : 'false');
      });
    };

    const cancelPendingAccordionScroll = () => {
      if (pendingAccordionScrollFrame) {
        window.cancelAnimationFrame(pendingAccordionScrollFrame);
        pendingAccordionScrollFrame = 0;
      }
      if (pendingAccordionScrollTimeout) {
        window.clearTimeout(pendingAccordionScrollTimeout);
        pendingAccordionScrollTimeout = 0;
      }
    };

    const getShellMetric = (name, fallback = 0) => {
      const styles = window.getComputedStyle(shell);
      const rawValue = styles.getPropertyValue(name);
      const parsed = parseFloat(rawValue || '');
      return Number.isFinite(parsed) ? parsed : fallback;
    };

    const getAccordionScrollOffset = () => {
      syncLayoutMetrics();
      const adminOffset = getShellMetric('--vmseb-admin-bar-offset', 0);
      const stickyGap = getShellMetric('--vmseb-sticky-gap', 12);
      const categoryGap = getShellMetric('--vmseb-category-scroll-gap', 12);
      return Math.max(0, Math.ceil(adminOffset + stickyGap + categoryGap));
    };

    const alignOpenedCategory = (section) => {
      if (!section || !section.classList.contains('is-open')) return;
      const toggle = section.querySelector('[data-category-toggle]');
      if (!toggle) return;

      const rect = toggle.getBoundingClientRect();
      const viewportOffset = getAccordionScrollOffset();
      const currentScrollTop = window.scrollY || window.pageYOffset || 0;
      const targetTop = Math.max(0, currentScrollTop + rect.top - viewportOffset);
      const maxScrollTop = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
      const nextScrollTop = Math.min(targetTop, maxScrollTop);
      const prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      const behavior = prefersReducedMotion ? 'auto' : 'smooth';

      if (Math.abs(currentScrollTop - nextScrollTop) < 2) return;
      window.scrollTo({ top: nextScrollTop, behavior });
    };

    const scheduleAccordionAlignment = (section) => {
      cancelPendingAccordionScroll();
      pendingAccordionScrollTimeout = window.setTimeout(() => {
        pendingAccordionScrollTimeout = 0;
        let framesRemaining = 2;
        const run = () => {
          if (framesRemaining <= 0) {
            pendingAccordionScrollFrame = 0;
            alignOpenedCategory(section);
            return;
          }
          framesRemaining -= 1;
          pendingAccordionScrollFrame = window.requestAnimationFrame(run);
        };
        pendingAccordionScrollFrame = window.requestAnimationFrame(run);
      }, 0);
    };

    categories.forEach((section) => {
      const toggle = section.querySelector('[data-category-toggle]');
      if (!toggle) return;
      toggle.addEventListener('click', () => {
        const wasOpen = section.classList.contains('is-open');
        openCategory(section);
        if (!wasOpen) scheduleAccordionAlignment(section);
      });
    });

    const syncAdminBarOffset = () => {
      const hasAdminBar = document.body && document.body.classList.contains('admin-bar') && adminBar;
      const height = hasAdminBar ? Math.ceil(adminBar.getBoundingClientRect().height || adminBar.offsetHeight || 0) : 0;
      shell.style.setProperty('--vmseb-admin-bar-offset', `${height}px`);
    };

    const syncSummaryMetrics = () => {
      if (!summaryWrapper) return;
      const height = Math.ceil(summaryWrapper.getBoundingClientRect().height || 0);
      shell.style.setProperty('--vmseb-summary-height', `${height}px`);
    };

    const syncLayoutMetrics = () => {
      syncAdminBarOffset();
      syncSummaryMetrics();
    };

    const refreshSummary = () => {
      let totalCount = 0;
      let regularSubtotal = 0;
      const payload = {};

      rows.forEach((row) => {
        const qtyInput = row.querySelector('[data-qty]');
        if (!qtyInput) return;
        const qty = parseInt(qtyInput.value || '0', 10) || 0;
        const price = parseFloat(row.getAttribute('data-price') || '0') || 0;
        const token = row.getAttribute('data-token') || '';
        if (qty > 0 && token !== '') payload[token] = qty;
        totalCount += qty;
        regularSubtotal += (qty * price);
      });

      if (payloadField) payloadField.value = JSON.stringify(payload);
      if (selectedCountEl) selectedCountEl.textContent = String(totalCount);
      if (regularSubtotalEl) {
        regularSubtotalEl.textContent = new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(regularSubtotal);
      }
      if (pickupInput) pickupInput.required = totalCount > 0;

      if (mobileReviewBtn) {
        const enabled = cartCount > 0 || (orderingOpen && totalCount > 0);
        mobileReviewBtn.disabled = !enabled;
        mobileReviewBtn.setAttribute('aria-disabled', enabled ? 'false' : 'true');
        if (mobileAction) mobileAction.classList.toggle('is-disabled', !enabled);
      }

      categories.forEach((section) => {
        let catCount = 0;
        section.querySelectorAll('[data-item-row]').forEach((row) => {
          const qtyInput = row.querySelector('[data-qty]');
          if (!qtyInput) return;
          catCount += parseInt(qtyInput.value || '0', 10) || 0;
        });
        const label = section.querySelector('[data-category-count]');
        if (label) label.textContent = String(catCount);
      });

      syncLayoutMetrics();
    };

    rows.forEach((row) => {
      const qtyInput = row.querySelector('[data-qty]');
      if (!qtyInput) return;
      row.querySelectorAll('[data-step]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const direction = btn.getAttribute('data-step');
          const current = parseInt(qtyInput.value || '0', 10) || 0;
          const nextValue = Math.max(0, current + (direction === 'up' ? 1 : -1));
          const ageGate = row.getAttribute('data-age-gate') === '1';
          const applyChange = () => {
            qtyInput.value = String(nextValue);
            refreshSummary();
          };
          if (direction === 'up' && ageGate && !ageGateAlreadyPassed()) {
            openAgeGate(applyChange);
            return;
          }
          applyChange();
        });
      });
    });

    shell.querySelectorAll('[data-vmseb-submit]').forEach((btn) => {
      btn.addEventListener('click', () => {
        if (targetField) targetField.value = btn.getAttribute('data-vmseb-submit') || 'cart';
      });
    });

    const form = shell.querySelector('.vmseb-builder');
    if (form) {
      form.addEventListener('submit', (event) => {
        refreshSummary();
        if (!payloadField || payloadField.value === '{}' || payloadField.value === '') {
          event.preventDefault();
          if (cartCount > 0 && cartUrl) {
            window.location.assign(cartUrl);
            return;
          }
          window.alert('Add at least one item before continuing.');
          return;
        }
        if (pickupInput && !pickupInput.value.trim()) {
          event.preventDefault();
          pickupInput.focus();
          window.alert('Enter a pickup name before continuing.');
          return;
        }
        const selectedAgeGated = rows.some((row) => {
          const qtyInput = row.querySelector('[data-qty]');
          if (!qtyInput) return false;
          const qty = parseInt(qtyInput.value || '0', 10) || 0;
          return qty > 0 && row.getAttribute('data-age-gate') === '1';
        });
        if (selectedAgeGated && ageGateEnabled && !ageGateAlreadyPassed()) {
          event.preventDefault();
          openAgeGate(() => form.requestSubmit());
        }
      });
    }

    if (summaryWrapper) {
      window.addEventListener('resize', syncLayoutMetrics);
      window.addEventListener('orientationchange', syncLayoutMetrics);
      if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', syncLayoutMetrics);
      }
      if (window.ResizeObserver && adminBar) {
        const adminBarObserver = new ResizeObserver(syncLayoutMetrics);
        adminBarObserver.observe(adminBar);
      }
      window.requestAnimationFrame(syncLayoutMetrics);
    }

    if (mobileAction) {
      const setPurchaseBarVisibility = (visible) => {
        mobileAction.classList.toggle('is-outside-shell', !visible);
      };
      if (window.IntersectionObserver) {
        const shellObserver = new IntersectionObserver((entries) => {
          entries.forEach((entry) => setPurchaseBarVisibility(entry.isIntersecting));
        });
        shellObserver.observe(shell);
      }
    }

    refreshSummary();
  });
});
