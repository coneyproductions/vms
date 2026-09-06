(function () {
  function closestCard(target) {
    return target.closest('[data-vmsx-weather-risk-card]');
  }

  function setMessage(target, message) {
    const scope = closestCard(target) || document;
    const node = scope.querySelector('[data-vmsx-weather-message]');
    if (node) {
      node.textContent = message || '';
    }
  }

  async function refresh(target) {
    const eventPlanId = parseInt(target.getAttribute('data-event-plan-id') || '0', 10);
    if (!eventPlanId) {
      return;
    }

    target.disabled = true;
    setMessage(target, (window.VMSXWeatherRisk && window.VMSXWeatherRisk.refreshingText) || 'Refreshing…');

    const formData = new FormData();
    formData.append('action', (window.VMSXWeatherRisk && window.VMSXWeatherRisk.action) || 'vmsx_weather_risk_refresh');
    formData.append('nonce', (window.VMSXWeatherRisk && window.VMSXWeatherRisk.nonce) || '');
    formData.append('event_plan_id', String(eventPlanId));

    try {
      const response = await fetch((window.VMSXWeatherRisk && window.VMSXWeatherRisk.ajaxUrl) || window.ajaxurl, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
      });
      const json = await response.json();
      if (!json || !json.success) {
        throw new Error((json && json.data && json.data.message) || ((window.VMSXWeatherRisk && window.VMSXWeatherRisk.errorText) || 'Refresh failed.'));
      }

      if (target.getAttribute('data-vmsx-weather-refresh-page') === 'details') {
        window.location.reload();
        return;
      }

      const card = closestCard(target);
      if (card && json.data && json.data.card_html) {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = json.data.card_html;
        const replacement = wrapper.firstElementChild;
        if (replacement) {
          card.replaceWith(replacement);
        }
      }
    } catch (error) {
      setMessage(target, (error && error.message) || ((window.VMSXWeatherRisk && window.VMSXWeatherRisk.errorText) || 'Refresh failed.'));
      target.disabled = false;
      return;
    }

    target.disabled = false;
  }

  document.addEventListener('click', function (event) {
    const target = event.target.closest('[data-vmsx-weather-refresh]');
    if (!target) {
      return;
    }
    event.preventDefault();
    refresh(target);
  });
})();
