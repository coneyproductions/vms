'use strict';
// Representative TEC/Woo ticket markup and BVM server-controls data. All identities and endpoints are test-only.
exports.page = function (mode = 'guest', layout = 'progressive') {
  const names = ['General Admission', "Child's Admission (12 & under)", 'Veteran Admission', 'Police, Fire Fighter, EMT Admission'];
  const access = {};
  names.forEach((label, i) => {
    const id = 6996 + i;
    access[id] = {
      ticket_key: ['ga', 'child', 'veteran', 'first_responder'][i], label, display_label: label,
      description: i > 1 ? 'Requires registration' : '', visibility_mode: i > 1 ? 'verified' : 'public',
      allowed_programs: i > 1 ? [i === 2 ? 'veteran' : 'first_responder'] : [],
      verified_program: i > 1 ? (i === 2 ? 'veteran' : 'first_responder') : '',
      counts_toward_unlock: 1, ratio_rule_enabled: i === 1 ? 1 : 0, ratio_rule_max_per_qualifying: 3,
      ratio_rule_group: i === 1 ? 'youth' : '', require_assignee_email: 1, claims_per_assignee: 1,
      current_user_is_eligible: mode === 'verified' && i > 1 ? 1 : 0,
      current_user_claim_remaining_qty: mode === 'verified' && i > 1 ? 1 : 0,
      woo_product_id: id, tec_ticket_id: id, sale_active: 0
    };
  });
  const cfg = {
    tecEventId: 6995, eventPlanId: 6994, isLoggedIn: mode === 'guest' ? 0 : 1,
    isAdminUser: mode === 'admin' ? 1 : 0,
    currentUserEmail: mode === 'guest' ? '' : 'buyer@example.test',
    uiLayout: layout, uiProgressive: layout === 'progressive' ? 1 : 0, buildStamp: 'test', myActiveTicketCount: -1,
    ticketAccessMap: access, ticketPriceMap: {6996: 20, 6997: 0, 6998: 0, 6999: 0},
    ticketRemainingMap: {}, disabledTicketProductIds: [],
    ticketHelpText: '<p>Select tickets for everyone in your party.</p>', addonHelpText: '',
    addonSectionHeading: 'Amenities', addonSectionSubtext: 'Make your night more comfortable.',
    loginUrl: '/login/?return_to=event', registerUrl: '/register/?return_to=event',
    verificationUrl: '/my-account/?vms_verification=1&vms_return_to=%2Fevent%2F#vms-verification-panel',
    myBenefitsUrl: '/my-account/?vms_benefits=1#vms-benefits-panel',
    atomicAddUrl: '/api/atomic-add', atomicAddNonce: 'test-only', cartUrl: '/cart/', checkoutUrl: '/checkout/',
    postCartOffer: {}, cartContextUrl: '/api/cart-context', cartContextNonce: 'test-only',
    claimsValidateUrl: '/api/validate-assignee', claimsValidateNonce: 'test-only',
    verificationProgramLabels: {veteran:'Veteran',first_responder:'First Responder'}
  };
  const rows = names.map((name, i) => `<div class="tribe-tickets__tickets-item" id="tribe-block-tickets-item-${6996+i}" data-ticket-id="${6996+i}">
    <div class="tribe-tickets__tickets-item-content"><div class="tribe-tickets__tickets-item-content-title-container"><h3 class="tribe-tickets__tickets-item-content-title">${name}</h3></div></div>
    <div class="tribe-tickets__tickets-item-extra"><div class="tribe-tickets__tickets-item-extra-price">$${i ? '0.00' : '20.00'}</div></div>
    <div class="tribe-tickets__tickets-item-quantity">
      <button type="button" class="tribe-tickets__tickets-item-quantity-remove" aria-label="Decrease ${name}">−</button>
      <input type="number" class="tribe-tickets__tickets-item-quantity-number-input" id="tribe-tickets__tickets-item-quantity-number--${6996+i}" name="tickets[${6996+i}]" min="0" max="20" value="0">
      <button type="button" class="tribe-tickets__tickets-item-quantity-add" aria-label="Increase ${name}">+</button>
    </div></div>`).join('');
  function addon(id, label, price, checkbox, min) {
    return `<li class="vms-entitlements-item vms-ent-row" data-vms-product-id="${id}" data-vms-selector-mode="${checkbox?'checkbox':'stepper'}">
      <div class="vms-ent-main"><strong class="vms-ent-title">${label}</strong><details class="vms-ent-more"><summary>More info</summary>Reserved space.</details></div>
      <div class="vms-ent-side"><div class="vms-ent-price">$${price}.00</div><div class="vms-ent-qty">
      <div class="vms-addon-controls" data-vms-server-stepper="1" data-vms-product-id="${id}" data-vms-selector-mode="${checkbox?'checkbox':'stepper'}" data-vms-can-add="1" data-vms-max-qty="4" data-vms-pool-min-ga="${min}">
      ${checkbox?'<label class="vms-addon-checkbox-wrap">':''}<input class="vms-addon-input" type="${checkbox?'checkbox':'number'}" value="${checkbox?1:0}" min="0" aria-label="Reserve ${label}">${checkbox?'<span>Reserve</span></label>':''}
      <button type="button" class="vms-addon-minus" ${checkbox?'hidden':''}>−</button><button type="button" class="vms-addon-plus" ${checkbox?'hidden':''}>+</button></div></div>
      <div class="vms-ent-note"></div><div class="vms-rw-addon__status"></div></div></li>`;
  }
  return `<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/assets/css/vms-ticketing-front.css"><link rel="stylesheet" href="/assets/css/vms-entitlements-public.css"><style>[hidden]{display:none!important}body{font:16px sans-serif;margin:20px}.tribe-tickets__tickets-item{padding:12px;border-bottom:1px solid #ccc}input{max-width:180px}button,summary{min-height:32px;cursor:pointer}.vms-ticket-ui-stickybar{position:sticky;bottom:0;background:white}.vms-ticket-progressive-title{display:block}</style></head><body class="single-tribe_events">
    <section data-test-sponsorship><h2>Sponsorship</h2></section>
    <div id="tribe-tickets"><form id="tribe-tickets__tickets-form" class="tribe-tickets__tickets-form">${rows}<div id="vms-addon-mount" class="vms-addon-mount vms-addon-mount--server"><section class="bvmgr-enhance-night"><div id="vms-reserved-addons" class="vms-entitlements-block vms-entitlements--compact" data-vms-render-mode="server_controls" data-vms-ga-product-id="6996" data-vms-qualifying-ticket-product-ids="6996,6997,6998,6999" data-vms-prior-qualifying-qty="0" data-vms-cart-ga-qty="0" data-vms-cart-pool-qty="{}" data-vms-prior-pool-qty="{}"><ul class="vms-entitlements-list">${addon(7000,'Table',20,true,2)}${addon(7006,'Pool',10,false,0)}</ul></div>
    <section class="bvm-rentals-public bvm-rentals-public--embedded vms-entitlements-block vms-entitlements--compact" data-bvmgr-purchase-extension="bvm-rentals" data-event-plan-id="6994"><ul class="vms-entitlements-list"><li class="vms-entitlements-item vms-ent-row vms-ent-row--no-image bvm-rentals-public__card" data-bvm-rental-card data-product-id="6014"><div class="vms-entitlements-main vms-ent-main"><strong class="vms-entitlements-label vms-ent-title">Rental fan</strong><details class="vms-entitlements-more vms-ent-more"><summary>Rental terms</summary><div class="vms-entitlements-more-body vms-ent-more-body">Return after the show.</div></details></div><div class="vms-ent-side bvm-rentals-public__side"><div class="vms-entitlements-price vms-ent-price bvm-rentals-public__price">$7.50</div><div class="vms-entitlements-qty vms-ent-qty"><div class="vms-rw-stepper vms-addon-controls vms-addon-controls--stepper bvm-rentals-public__stepper"><button type="button" class="vms-rw-stepper__btn vms-rw-stepper__btn--minus vms-addon-minus" data-test-rental-minus data-bvm-qty-change="-1">−</button><input type="number" class="vms-rw-stepper__input vms-addon-input bvm-rental-input" data-test-extension-qty min="0" max="3" value="0"><button type="button" class="vms-rw-stepper__btn vms-rw-stepper__btn--plus vms-addon-plus" data-test-rental-plus data-bvm-qty-change="1">+</button></div></div></div></li></ul><label><input type="checkbox" data-test-extension-terms> Accept rental terms</label></section></section></div><div class="tribe-tickets__tickets-footer"><button id="tribe-tickets__tickets-submit" type="submit">Get Tickets</button></div></form></div>
    <script>window.BVMGR_TICKETING_FRONT=${JSON.stringify(cfg)};window.BVMGR_TICKETING_PURCHASE_EXTENSIONS={handlers:{test_extension:{collect:function(options){var qty=Math.max(0,Number(document.querySelector('[data-test-extension-qty]').value||0));var terms=document.querySelector('[data-test-extension-terms]');return {active:qty>0,ok:!options.validate||terms.checked,message:'Accept the extension terms.',focusEl:terms,payload:{quantity:qty,termsAccepted:!!terms.checked},quantity:qty,total:qty*7.5,contributions:[{label:'Rental fan',quantity:qty,unitPrice:7.5}]};}}}};document.addEventListener('input',function(e){if(e.target.matches('[data-test-extension-qty],[data-test-extension-terms]'))document.dispatchEvent(new CustomEvent('bvmgr:purchase-extension-change'));});</script>
    <script src="/assets/vms-ticketing-post-cart-offer.js"></script><script src="/assets/vms-ticketing-front.js"></script><script src="/assets/vms-ticketing-front.js"></script><script src="/assets/vms-ticketing-front-fallback.js"></script><script src="/assets/vms-ticketing-progressive-ui.js"></script>
    <script>document.addEventListener('click',function(e){var b=e.target.closest('.tribe-tickets__tickets-item-quantity-add,.tribe-tickets__tickets-item-quantity-remove,[data-bvm-qty-change]');if(!b||b.disabled)return;var i=b.parentNode.querySelector('input');var delta=b.hasAttribute('data-bvm-qty-change')?Number(b.getAttribute('data-bvm-qty-change')):(b.classList.contains('tribe-tickets__tickets-item-quantity-add')?1:-1);var n=Math.max(Number(i.min||0),Math.min(Number(i.max),Number(i.value)+delta));i.value=String(n);i.dispatchEvent(new Event('input',{bubbles:true}));i.dispatchEvent(new Event('change',{bubbles:true}));});</script></body></html>`;
};
