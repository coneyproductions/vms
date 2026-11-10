const evidenceDir=process.env.BVM_CHECKOUT_EVIDENCE || require('fs').mkdtempSync(require('path').join(require('os').tmpdir(),'bvm-checkout-124-'));
require('fs').mkdirSync(evidenceDir,{recursive:true});
const {chromium}=require(process.env.BVM_PLAYWRIGHT_MODULE || 'playwright');
const fs=require('fs'),path=require('path'),assert=require('assert/strict');
const R=evidenceDir,root=path.resolve(process.argv[2]||process.cwd());
const baseline=process.argv.includes('--baseline');
if(!process.env.BVM_EVENT_TICKETS_ROOT||!process.env.BVM_WORDPRESS_ROOT)throw new Error('Set BVM_EVENT_TICKETS_ROOT and BVM_WORDPRESS_ROOT to read-only native assets');
const prereq=path.resolve(process.env.BVM_EVENT_TICKETS_ROOT);
const jquery=path.join(process.env.BVM_WORDPRESS_ROOT,'wp-includes/js/jquery/jquery.min.js');
const origin='http://quantity.test';let passes=0;
const check=(condition,label)=>{assert.ok(condition,label);passes++;console.log('PASS '+label)};
const names={101:'General Admission',102:'Veteran Admission',103:'Police, Fire Fighter, EMT Admission',104:'Child Admission'};
const row=(pid,initial=0)=>`<div class="tribe-tickets__tickets-item tribe-tickets__item" data-ticket-id="${pid}" data-product-id="${pid}"><div class="tribe-tickets__tickets-item-content-title-container"><h3 class="tribe-tickets__tickets-item-content-title">${names[pid]}</h3></div><div class="tribe-tickets__tickets-item-quantity"><button type="button" aria-label="Decrease quantity" class="tribe-tickets__tickets-item-quantity-remove">−</button><div class="tribe-tickets__tickets-item-quantity-number"><label style="position:absolute;clip:rect(0,0,0,0)" for="q${pid}">Quantity</label><input type="number" id="q${pid}" min="0" max="20" step="1" value="${initial}" autocomplete="off" class="tribe-tickets__tickets-item-quantity-number-input"></div><button type="button" aria-label="Increase quantity" class="tribe-tickets__tickets-item-quantity-add">+</button></div><div class="tribe-tickets__tickets-item-extra"><span class="tribe-tickets__tickets-item-price tribe-tickets__tickets-item-extra-price">${pid===101?'$20.00':'$0.00'}</span></div></div>`;
(async()=>{
 const browser=await chromium.launch({channel:'chrome',headless:true});
 async function fixture({cart={101:1,102:2},source=false,prefill=false,admission=102,noAtomic=false}={}){
  cart={...cart};const requests=[],errors=[];let atomicCalls=0;
  const page=await browser.newPage({viewport:{width:1100,height:950}});
  page.on('pageerror',e=>errors.push(e.message));
  await page.route('**/*',async route=>{
   const u=new URL(route.request().url());requests.push({url:u.pathname,body:route.request().postData()});
   assert.equal(u.origin,origin,'Unexpected external request');
   if(u.pathname==='/atomic'){
    atomicCalls++;const body=JSON.parse(route.request().postData());
    // Stateful test cart only: never invokes WooCommerce or any payment provider.
    for(const line of body.ticket_lines){assert.ok(line.qty>0);cart[line.product_id]=(cart[line.product_id]||0)+line.qty;}
    await new Promise(resolve=>setTimeout(resolve,100));
    return route.fulfill({contentType:'application/json',body:JSON.stringify({success:true,data:{ok:true,cart_url:origin+'/cart/'}})});
   }
   if(u.pathname==='/validate'){
    const ok=new URLSearchParams(route.request().postData()).get('assignee_email')==='guest@example.test';
    return route.fulfill({contentType:'application/json',body:JSON.stringify({success:true,data:{ok,eligible:ok,reason_code:ok?'ok':'not_approved',message:ok?'Approved':'This guest is not approved.',assignee_email:ok?'guest@example.test':''}})});
   }
   return route.fulfill({contentType:'text/html',body:'<!doctype html><html><body>Local synthetic destination</body></html>'});
  });
  async function visit({initial=prefill,replaced=false}={}){
   await page.goto(origin+'/event/');
   const rows=[101,admission].map(pid=>row(pid,initial?(cart[pid]||0):0)).join('');
   await page.setContent(`<html><body class="single-tribe_events"><div class="tribe-tickets__tickets-wrapper"><form class="tribe-tickets__tickets-form" id="tribe-tickets__tickets-form" data-provider="Tribe__Tickets_Plus__Commerce__WooCommerce__Main">${rows}<footer class="tribe-tickets__tickets-footer"><button type="submit" id="tribe-tickets__tickets-submit">Get Tickets</button></footer></form></div>${source?`<div id="vms-reserved-addons" class="vms-entitlements-block" data-vms-render-mode="${source==='legacy'?'':'server_controls'}" data-vms-cart-ga-qty="${(cart[101]||0)+(cart[102]||0)+(cart[103]||0)}" data-vms-qualifying-ticket-product-ids="101,${admission}"><div class="vms-entitlements-list"></div></div>`:''}</body></html>`);
   await page.addStyleTag({content:'body{font-family:Arial,sans-serif;padding:16px;background:#fafbfc}form{max-width:900px;margin:auto}button,input{font:inherit}'});
   await page.addStyleTag({content:fs.readFileSync(path.join(root,'assets/css/vms-ticketing-front.css'),'utf8')});
   const access=pid=>({woo_product_id:pid,tec_ticket_id:pid,label:names[pid],display_label:names[pid],visibility_mode:pid===101?'public':'verified',current_user_is_eligible:pid===101||pid===104?0:1,current_user_claim_remaining_qty:cart[pid]?0:1,require_assignee_email:pid===101||pid===104?0:1,current_user_assigned_cart_qty:cart[pid]?1:0,current_user_claims_per_assignee:1,counts_toward_unlock:pid===104?0:1,...(pid===104?{visibility_mode:'public',ratio_rule_enabled:1,ratio_rule_max_per_qualifying:6,ratio_rule_group:'child'}:{})});
   await page.evaluate(cfg=>window.BVMGR_TICKETING_FRONT=cfg,{uiLayout:source==='legacy'?'progressive':'v2',uiProgressive:source==='legacy'?'1':'0',isLoggedIn:1,tecEventId:1,eventPlanId:2,currentUserEmail:'buyer@example.test',cartTicketQuantities:{...cart},cartUrl:origin+'/cart/',checkoutUrl:origin+'/checkout/',atomicAddUrl:noAtomic?'':origin+'/atomic',atomicAddNonce:noAtomic?'':'synthetic',claimsValidateUrl:origin+'/validate',claimsValidateNonce:'synthetic',ticketAccessMap:{101:access(101),[admission]:access(admission)},ticketPriceMap:{101:20,[admission]:0}});
   await page.addScriptTag({path:jquery});
   await page.evaluate(()=>{window.wp={hooks:{applyFilters:(name,value)=>value}};window.TribeTicketOptions={availability_check_interval:0};window.TribeCurrency={formatting:JSON.stringify({Tribe__Tickets_Plus__Commerce__WooCommerce__Main:{number_of_decimals:2,thousands_sep:',',decimal_point:'.'}})};});
   for(const f of ['common/build/js/tribe-common.js','build/js/v2/tickets-utils.js','build/js/v2/tickets-block.js'])await page.addScriptTag({path:path.join(prereq,f)});
   await page.waitForTimeout(50);
   await page.addScriptTag({path:path.join(root,'assets/vms-ticketing-front.js')});
   await page.waitForFunction(()=>window.BVMGR_TICKETING_FRONT_BUNDLE?.state);
   await page.waitForTimeout(100);
  }
  async function qty(pid,n){await page.locator('#q'+pid).fill(String(n));await page.locator('#q'+pid).dispatchEvent('change');await page.waitForTimeout(100);}
  async function context(){return page.evaluate(pid=>window.BVMGR_TICKETING_FRONT_BUNDLE.state.ticketClaimsByProduct[pid]?.context,admission);}
  async function submit(){const action=page.locator('#vms-ticket-cart-continue:visible, #tribe-tickets__tickets-submit:visible').first();if(await action.isEnabled())await action.click();await page.waitForTimeout(220);}
  const calls=()=>atomicCalls;
  await visit();return{page,requests,errors,cart,visit,qty,context,submit,calls,admission};
 }
 try{
  if(baseline){const f=await fixture();check(await f.page.locator('#q101').inputValue()==='0'&&await f.page.locator('#q102').inputValue()==='0','baseline reproduces zero fields with three cart tickets and no TEC prefill');await f.page.close();return;}
  for(const source of [false,true,'legacy']){
   const f=await fixture({source});
   check(await f.page.locator('#q101').inputValue()==='1'&&await f.page.locator('#q102').inputValue()==='2',`native zero defaults restored to server cart totals (${source||'ticket only'})`);
   check(!(await f.context()).showPanel,'existing self and approved guest require no further email');
   check((await f.page.locator('.vms-event-purchase-status').innerText()).includes('3 tickets in your cart'),'notice agrees with restored totals');
   check((await f.page.locator('[data-ticket-id="102"] .vms-ticket-total-note').innerText()).includes('2 already in cart. 0 additional selected.'),'per-admission quantities distinguish existing from additional');
   await f.submit();check(f.calls()===0&&f.page.url()===origin+'/checkout/','unchanged totals continue checkout without any addition');
   await f.visit();check(await f.page.locator('#q102').inputValue()==='2','reload/cart return restores totals without customer intervention');
   await f.page.evaluate(()=>{for(const pid of [101,102]){const i=document.querySelector('#q'+pid);i.value=String(window.BVMGR_TICKETING_FRONT.cartTicketQuantities[pid]);i.dispatchEvent(new Event('change',{bubbles:true}));}});
   check(!(await f.context()).showPanel,'late matching TEC prefill remains idempotent');
   await f.page.locator('#q102').evaluate(el=>{el.value='0';el.setAttribute('value','0');});await f.page.waitForTimeout(150);
   check(await f.page.locator('#q102').inputValue()==='2','native late zero reset cannot leave a misleading lower total');
   check(f.errors.length===0,'actual TEC quantity scripts and BVM produce no browser errors');
   await f.page.close();
  }
  let f=await fixture({prefill:true});check(await f.page.locator('#q102').inputValue()==='2'&&!(await f.context()).showPanel,'native prefilled totals are not added twice');
  await f.qty(101,2);check((await f.page.locator('[data-ticket-id="101"] .vms-ticket-total-note').innerText()).includes('1 additional selected.'),'total increase shows exactly one additional paid ticket');
  check((await f.page.locator('#vms-ticketing-subtotal').innerText()).includes('$20.00'),'additional subtotal excludes existing paid ticket');
  await f.page.locator('#tribe-tickets__tickets-submit').dblclick();await f.page.waitForTimeout(250);
  check(f.calls()===1&&f.cart[101]===2&&f.cart[102]===2,'double activation sends one request and adds only one paid ticket');
  const added=JSON.parse(f.requests.find(r=>r.url==='/atomic').body).ticket_lines;check(added.length===1&&added[0].product_id===101&&added[0].qty===1,'atomic request contains the paid delta only');
  await f.visit();await f.submit();check(f.calls()===1,'return after success cannot replay the previous addition');await f.page.close();
  f=await fixture();await f.qty(102,3);check((await f.context()).assigneeQty===1,'one genuine additional veteran requires exactly one guest');
  await f.submit();check(f.calls()===0,'blank extra guest blocks all additions');
  await f.page.locator('.vms-claim-seat-input').fill('unapproved@example.test');await f.page.locator('.vms-claim-seat-validate').click();await f.page.waitForTimeout(120);await f.submit();check(f.calls()===0,'unapproved guest cannot add extra admission');
  await f.page.locator('.vms-claim-seat-input').fill('guest@example.test');await f.page.locator('.vms-claim-seat-validate').click();await f.page.waitForFunction(()=>Object.values(window.BVMGR_TICKETING_FRONT_BUNDLE.state.ticketClaimsByProduct[102].rowStates).some(r=>r.statusKind==='valid'));
  await f.submit();const guest=JSON.parse(f.requests.find(r=>r.url==='/atomic').body).ticket_lines[0];check(f.cart[102]===3&&guest.qty===1&&guest.claim_assignments.length===1&&guest.claim_assignments[0].seat===1,'valid guest adds only one newly assigned seat');
  await f.visit();check(await f.page.locator('#q102').inputValue()==='3'&&!(await f.context()).showPanel,'new cart snapshot includes guest without another email');
  f.cart[102]=1;await f.visit();check(await f.page.locator('#q102').inputValue()==='1','cart reduction returns the current lower total');
  delete f.cart[102];await f.visit();check(await f.page.locator('#q102').inputValue()==='0','removed admission is not restored from an old snapshot');await f.qty(102,1);check(!(await f.context()).showPanel,'removed self ticket releases its allowance in a fresh snapshot');
  delete f.cart[101];await f.visit();check(await f.page.locator('.vms-event-purchase-status').count()===0&&await f.page.locator('.vms-ticket-total-note').count()===0,'empty cart removes existing-cart notices and total annotations');await f.page.close();
  f=await fixture({cart:{103:1},admission:103});check(await f.page.locator('#q103').inputValue()==='1'&&!(await f.context()).showPanel,'first responder admission uses the same restored-total contract');await f.qty(103,2);check((await f.context()).assigneeQty===1,'first responder extra ticket still requires approved guest');await f.page.close();
  for(const source of [false,true]){
   const ratio=await fixture({cart:{101:1,104:3},admission:104,source});
   check(await ratio.page.locator('#q104').inputValue()==='3'&&await ratio.page.locator('#q104').getAttribute('max')==='6','ratio-limited cart totals preserve qualifying count and remaining room ('+(source?'server controls':'ticket only')+')');
   await ratio.qty(104,7);check(await ratio.page.locator('#q104').inputValue()==='6','ratio limit still caps total children at six per qualifying ticket');
   await ratio.qty(104,4);await ratio.submit();const line=JSON.parse(ratio.requests.find(r=>r.url==='/atomic').body).ticket_lines[0];check(line.product_id===104&&line.qty===1&&ratio.cart[104]===4,'ratio-limited ticket sends only the additional quantity');
   delete ratio.cart[101];await ratio.visit();await ratio.page.waitForTimeout(300);
   check(await ratio.page.locator('#q104').inputValue()==='4'&&await ratio.page.locator('#q104').getAttribute('max')==='4','missing qualifier preserves existing cart visibility without enabling more children');
   check(await ratio.page.locator('[data-ticket-id="104"] .tribe-tickets__tickets-item-quantity-add').isDisabled()&&ratio.errors.length===0,'invalid existing ratio cannot unlock additions or enter a refresh loop');await ratio.page.close();
  }
  f=await fixture({noAtomic:true});await f.qty(101,2);await f.submit();
  check(f.calls()===0&&f.cart[101]===1&&f.page.url()===origin+'/event/','missing atomic endpoint cannot fall back to replaying native cart totals');
  check((await f.page.locator('#vms-addons-direct-add-status').innerText()).includes('Review cart'),'unavailable delta submission explains safe next action');await f.page.close();
  f=await fixture();
  await f.qty(102,0);check(await f.page.locator('#q102').inputValue()==='2','committed lower total preserves cart and points to cart removal');
  await f.page.locator('#q102').focus();await f.page.locator('#q102').press('Meta+a');await f.page.locator('#q102').press('Backspace');await f.page.waitForTimeout(150);await f.page.locator('#q102').pressSequentially('12',{delay:120});check(await f.page.locator('#q102').inputValue()==='12','multi-digit keyboard editing is not overwritten mid-entry');await f.page.locator('#q102').press('Tab');
  await f.qty(102,2);
  await f.page.locator('[data-ticket-id="102"] .tribe-tickets__tickets-item-quantity-add').click();await f.page.waitForTimeout(120);check(await f.page.locator('#q102').inputValue()==='3'&&(await f.context()).assigneeQty===1,'actual TEC plus control requests one additional guest');
  await f.page.locator('[data-ticket-id="102"] .tribe-tickets__tickets-item-quantity-remove').click();await f.page.waitForTimeout(100);await f.page.locator('[data-ticket-id="102"] .tribe-tickets__tickets-item-quantity-remove').click();await f.page.waitForTimeout(100);check(await f.page.locator('#q102').inputValue()==='2'&&!(await f.context()).showPanel,'actual TEC minus respects cart minimum and hides unnecessary email');
  check(await f.page.getByRole('spinbutton',{name:'Total Veteran Admission tickets',exact:true}).count()===1,'accessible field name identifies admission and total semantics');
  check((await f.page.locator('#q102').getAttribute('aria-describedby')).includes('q102-cart-total-help'),'quantity has accessible cart/additional explanation');
  check(await f.page.locator('label[for="q102"]').last().isVisible(),'total label is visible and associated with input');
  await f.page.locator('label[for="q102"]').last().click();check(await f.page.locator('#q102').evaluate(el=>el===document.activeElement),'visible quantity label focuses its field');
  await f.page.setViewportSize({width:390,height:844});await f.page.screenshot({path:path.join(R,'evidence/quantity-mobile.png'),fullPage:true});
  check(await f.page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'390px mobile layout has no horizontal overflow');
  await f.page.setViewportSize({width:320,height:740});await f.page.addStyleTag({content:'html{font-size:20px} .vms-ticket-total-note{font-size:20px}'});check(await f.page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'320px larger-text layout keeps labels and actions within viewport');
  await f.page.screenshot({path:path.join(R,'evidence/quantity-mobile-large-text.png'),fullPage:true});
  check(await f.page.getByRole('link',{name:'Review cart',exact:true}).first().isVisible()&&await f.page.getByRole('link',{name:'Complete checkout',exact:true}).isVisible(),'mobile cart and checkout actions remain visible');
  await f.page.setViewportSize({width:1100,height:950});await f.page.screenshot({path:path.join(R,'evidence/quantity-desktop.png'),fullPage:true});
  // Replace native quantity node as TEC may do during a form refresh.
  await f.page.locator('#q102').evaluate(el=>{const copy=el.cloneNode(true);copy.value='0';copy.setAttribute('value','0');copy.removeAttribute('aria-describedby');copy.removeAttribute('aria-label');el.replaceWith(copy);});await f.page.waitForTimeout(200);
  check(await f.page.locator('#q102').inputValue()==='2'&&await f.page.locator('[data-ticket-id="102"] .vms-ticket-total-note').count()===1,'replaced input is restored without duplicating visible help');
  check((await f.page.locator('#q102').getAttribute('aria-describedby')).includes('q102-cart-total-help'),'replaced native input retains accessible description');
  const reloadRequest=f.page.waitForRequest(r=>r.isNavigationRequest());await f.page.evaluate(()=>window.dispatchEvent(new PageTransitionEvent('pageshow',{persisted:true})));const reload=await reloadRequest;
  check(reload.url()===origin+'/event/'&&reload.isNavigationRequest()&&f.calls()===0,'history-cache return reloads authoritative cart instead of trusting stale totals');
  check(f.errors.length===0,'no browser errors across changes, mobile layout and node replacement');await f.page.close();
  console.log(JSON.stringify({passes,scope:'local stateful synthetic cart, real native TEC quantity JS, no WordPress/payment writes'}));
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
