'use strict';
// All HTML, customer identities and cart transport are synthetic and local.
const {chromium,webkit}=require(process.env.BVM_PLAYWRIGHT_MODULE||'playwright');
const fs=require('fs'),path=require('path'),assert=require('assert/strict');
const root=path.resolve(process.argv[2]||'.'),baseline=process.argv.includes('--baseline');
const evidence=process.env.BVM_CHECKOUT_EVIDENCE||fs.mkdtempSync('/tmp/bvm-124-device-');fs.mkdirSync(evidence,{recursive:true});
const tec=process.env.BVM_EVENT_TICKETS_ROOT,wp=process.env.BVM_WORDPRESS_ROOT;
assert(tec&&wp,'Read-only TEC and WordPress asset paths required');
const origin='http://device-checkout.test';let passes=0;const records=[];
const check=(ok,label)=>{assert.ok(ok,label);passes++;records.push(label);console.log('PASS '+label)};
const row=(id,name,price)=>`<div class="tribe-tickets__tickets-item tribe-tickets__item" data-ticket-id="${id}" data-product-id="${id}"><h3 class="tribe-tickets__tickets-item-content-title">${name}</h3><div class="tribe-tickets__tickets-item-quantity"><button type="button" class="tribe-tickets__tickets-item-quantity-remove">−</button><div class="tribe-tickets__tickets-item-quantity-number"><input type="number" id="q${id}" class="tribe-tickets__tickets-item-quantity-number-input" min="0" max="50" step="1" value="0"></div><button type="button" class="tribe-tickets__tickets-item-quantity-add">+</button></div><span class="tribe-tickets__tickets-item-price">$${price}.00</span></div>`;
(async()=>{for(const engine of ['chromium','webkit']){
 const browser=await(engine==='webkit'?webkit:chromium).launch(engine==='webkit'?{headless:true}:{headless:true,channel:'chrome'});
 async function fixture({cart={101:1,102:2},admission=102,width=393,source=false,eligible=1,blocked=false,payment=null}={}){
  cart={...cart};const context=await browser.newContext({viewport:{width,height:900}}),page=await context.newPage();let atomic=[],nativePosts=0,errors=[],routeReads=[];
  const guest='approved_guest_with_a_very_long_synthetic_account_name_20261109@example.test';
  page.on('pageerror',e=>errors.push(e.message));
  await page.route('**/*',async route=>{const r=route.request(),u=new URL(r.url());assert.equal(u.origin,origin,'No external network allowed');routeReads.push(u.pathname);
   if(u.pathname==='/atomic'){
    const body=JSON.parse(r.postData());atomic.push(body);assert(body.ticket_lines.every(l=>l.qty>0));await new Promise(r=>setTimeout(r,160));for(const l of body.ticket_lines)cart[l.product_id]=(cart[l.product_id]||0)+l.qty;
    return route.fulfill({contentType:'application/json',body:JSON.stringify({success:true,data:{ok:true,cart_url:origin+'/cart/'}})});
   }
   if(u.pathname==='/validate'){const email=new URLSearchParams(r.postData()).get('assignee_email'),ok=email===guest;await new Promise(r=>setTimeout(r,90));return route.fulfill({contentType:'application/json',body:JSON.stringify({success:true,data:{ok,eligible:ok,reason_code:ok?'ok':'not_approved',message:ok?'Approved guest: '+email:'Guest is not approved.',assignee_email:ok?email:''}})});}
   if(r.method()!=='GET')nativePosts++;
   return route.fulfill({contentType:'text/html',body:'<!doctype html><html><body>Local destination</body></html>'});
  });
  async function visit(){
   await page.goto(origin+'/event/');
   await page.setContent(`<!doctype html><html><body class="single-tribe_events"><div class="tribe-tickets__tickets-wrapper"><form class="tribe-tickets__tickets-form" id="tribe-tickets__tickets-form" data-provider="Tribe__Tickets_Plus__Commerce__WooCommerce__Main">${row(101,'General Admission',1)}${row(admission,admission===102?'Veteran Admission':'Police, Fire Fighter, EMT Admission',0)}<footer class="tribe-tickets__tickets-footer"><button type="submit" id="tribe-tickets__tickets-submit" class="tribe-tickets__tickets-buy">Get Tickets</button></footer></form></div>${source?'<div id="vms-reserved-addons" class="vms-entitlements-block" data-vms-render-mode="server_controls"><div class="vms-entitlements-list"></div></div>':''}</body></html>`);
   await page.addStyleTag({content:'*{box-sizing:border-box}body{margin:0;padding:12px;font-family:Arial}button,input{font:inherit}'});
   await page.addStyleTag({path:path.join(root,'assets/css/vms-ticketing-front.css')});
   const access=id=>({woo_product_id:id,tec_ticket_id:id,label:id===101?'General Admission':'Veteran Admission',visibility_mode:id===101?'public':'verified',current_user_is_eligible:id===101?0:eligible,current_user_claim_remaining_qty:cart[id]?0:1,current_user_assigned_cart_qty:cart[id]?1:0,current_user_claims_per_assignee:1,require_assignee_email:id===101?0:1,counts_toward_unlock:1});
   await page.evaluate(c=>window.BVMGR_TICKETING_FRONT=c,{uiLayout:'v2',uiProgressive:'0',isLoggedIn:1,tecEventId:1,eventPlanId:2,cartTicketQuantities:{...cart},cartSummary:{total:cart[101]===2?'$2.17':'$1.08'},cartUrl:origin+'/cart/',checkoutUrl:origin+'/checkout/',atomicAddUrl:origin+'/atomic',atomicAddNonce:'synthetic',claimsValidateUrl:origin+'/validate',claimsValidateNonce:'synthetic',currentUserEmail:'buyer@example.test',hasCheckoutBlockers:blocked?1:0,ticketPriceMap:{101:1,[admission]:0},ticketAccessMap:{101:access(101),[admission]:access(admission)},eventPurchaseStatus:payment?{payment_orders:[{id:42,total:'$1.08',url:origin+'/order-pay/42/'}]}:{}});
   await page.addScriptTag({path:path.join(wp,'wp-includes/js/jquery/jquery.min.js')});
   await page.evaluate(()=>{window.wp={hooks:{applyFilters:(n,v)=>v}};window.TribeTicketOptions={availability_check_interval:0};window.TribeCurrency={formatting:JSON.stringify({Tribe__Tickets_Plus__Commerce__WooCommerce__Main:{number_of_decimals:2,thousands_sep:',',decimal_point:'.'}})};});
   for(const f of ['common/build/js/tribe-common.js','build/js/v2/tickets-utils.js','build/js/v2/tickets-block.js','build/js/v2/tickets-loader.js'])await page.addScriptTag({path:path.join(tec,f)});
   await page.waitForTimeout(60);await page.addScriptTag({path:path.join(root,'assets/vms-ticketing-front.js')});await page.waitForFunction(()=>window.BVMGR_TICKETING_FRONT_BUNDLE?.state);await page.waitForTimeout(100);
  }
  async function qty(id,n){await page.locator('#q'+id).fill(String(n));await page.locator('#q'+id).dispatchEvent('change');await page.waitForTimeout(130);}
  async function validate(email){await page.locator('.vms-claim-seat-input:visible').fill(email);await page.locator('.vms-claim-seat-validate:visible').click();await page.waitForTimeout(260);}
  const nav=()=>page.locator('#vms-ticket-cart-continue'),submit=()=>page.locator('#tribe-tickets__tickets-submit');
  await visit();return {context,page,cart,atomic,errors,guest,qty,validate,visit,nav,submit,nativePosts:()=>nativePosts,routeReads};
 }
 try{
  if(baseline){const f=await fixture();await f.page.evaluate(()=>tribe.tickets.block.ready());await f.page.waitForTimeout(180);check(await f.submit().isDisabled(),engine+': .2 deterministically reproduces late native ready disabling Continue to checkout');await f.context.close();continue;}
  for(const source of [false,true]){
   const f=await fixture({source});check(await f.nav().isVisible()&&await f.nav().getAttribute('aria-disabled')==='false',engine+': separate existing-cart navigation available '+source);
   for(let n=0;n<3;n++){await f.page.evaluate(()=>tribe.tickets.block.ready());await f.page.waitForTimeout(100);check(await f.nav().isVisible()&&await f.nav().getAttribute('aria-disabled')==='false',engine+': late native ready cannot disable navigation '+source+'/'+n);}
   check(await f.submit().isHidden(),engine+': native purchase control hidden for navigation, no shared ownership');
   const before=f.atomic.length;await f.nav().evaluate(n=>{n.click();n.click()});await f.page.waitForURL('**/checkout/');check(f.atomic.length===before&&f.nativePosts()===0,engine+': repeated navigation makes no additions or native POST');
   await f.visit();check(await f.page.locator('#q102').inputValue()==='2'&&await f.nav().isVisible(),engine+': event return restores authoritative quantities and navigation');
   await f.page.locator('#tribe-tickets__tickets-form').evaluate(n=>n.setAttribute('aria-busy','true'));await f.page.waitForTimeout(100);check(await f.nav().getAttribute('aria-disabled')==='true'&&await f.nav().getAttribute('href')===null,engine+': loading disables navigation and removes its destination');
   await f.nav().evaluate(n=>n.click());check(new URL(f.page.url()).pathname==='/event/'&&f.atomic.length===before,engine+': loading navigation cannot activate');
   await f.page.locator('#tribe-tickets__tickets-form').evaluate(n=>n.removeAttribute('aria-busy'));await f.page.waitForTimeout(100);check(await f.nav().getAttribute('aria-disabled')==='false',engine+': loading completion reconciles navigation');
   await f.page.locator('#tribe-tickets__tickets-form').evaluate(n=>{const loader=document.createElement('div');loader.className='tribe-common-c-loader tribe-common-a11y-hidden';n.appendChild(loader);tribe.tickets.loader.show(jQuery(n));});await f.page.waitForTimeout(100);check(await f.nav().getAttribute('aria-disabled')==='true',engine+': native TEC loader retains its safety lock');await f.page.evaluate(()=>tribe.tickets.loader.hide(jQuery('#tribe-tickets__tickets-form')));await f.page.waitForTimeout(100);check(await f.nav().getAttribute('aria-disabled')==='false',engine+': native loader completion restores navigation');
   await f.page.evaluate(()=>{window.BVMGR_TICKETING_PURCHASE_EXTENSIONS={handlers:{guard:{collect:()=>({active:true,ok:false,message:'Synthetic selection blocker'})}}};document.dispatchEvent(new Event('bvmgr:purchase-extension-change'));});check(await f.nav().getAttribute('aria-disabled')==='true',engine+': failed purchase-extension validation retains disabled navigation');await f.page.evaluate(()=>{delete window.BVMGR_TICKETING_PURCHASE_EXTENSIONS;document.dispatchEvent(new Event('bvmgr:purchase-extension-change'));});
   await f.page.locator('#q101').evaluate(n=>{n.setCustomValidity('Synthetic invalid selection');n.dispatchEvent(new Event('input',{bubbles:true}));});await f.page.waitForTimeout(100);check(await f.nav().getAttribute('aria-disabled')==='true',engine+': native constraint validation is not bypassed');await f.page.locator('#q101').evaluate(n=>{n.setCustomValidity('');n.dispatchEvent(new Event('input',{bubbles:true}));});await f.page.waitForTimeout(100);
   await f.qty(101,2);check(await f.nav().isHidden()&&await f.submit().isEnabled(),engine+': real additions use guarded native purchase control');
   check(await f.page.locator('[data-vms-existing-ticket-value]').innerText()==='$1.00'&&await f.page.locator('[data-vms-subtotal-ticket]').innerText()==='$1.00'&&await f.page.locator('[data-vms-selected-ticket-value]').innerText()==='$2.00',engine+': existing $1, added $1, selected $2 are distinct');
   check(await f.page.locator('[data-vms-current-cart-total]').innerText()==='$1.08',engine+': WooCommerce total is unchanged while new selection is not yet added');
   await f.submit().evaluate(n=>{n.click();n.click()});await f.page.waitForURL('**/cart/');check(f.atomic.length===1&&f.atomic[0].ticket_lines.length===1&&f.atomic[0].ticket_lines[0].qty===1&&f.cart[101]===2,engine+': double activation submits one positive delta only');
   await f.visit();check(await f.page.locator('[data-vms-current-cart-total]').innerText()==='$2.17',engine+': fresh snapshot displays WooCommerce total, not a JS tax calculation');
   await f.qty(102,3);check(await f.submit().isDisabled()&&await f.nav().isHidden(),engine+': missing guest approval disables addition without offering unsafe continuation');
   await f.validate('unapproved@example.test');check(await f.submit().isDisabled()&&f.atomic.length===1,engine+': failed guest approval remains blocked');
   await f.validate(f.guest);check(await f.submit().isEnabled(),engine+': independently approved guest enables only the genuine extra seat');
   await f.submit().evaluate(n=>{n.click();n.click()});await f.page.waitForURL('**/cart/');const lines=f.atomic[1].ticket_lines;check(f.atomic.length===2&&lines[0].qty===1&&lines[0].claim_assignments.length===1,engine+': guest assignment is not duplicated on repeated activation');
   await f.visit();await f.qty(102,0);check(await f.page.locator('#q102').inputValue()==='3'&&await f.page.getByRole('link',{name:'Reduce or remove tickets in cart'}).count()===2,engine+': quantity floor and immediate edit-cart routes preserved');
   f.cart[102]=1;await f.visit();check(await f.page.locator('#q102').inputValue()==='1',engine+': cart reduction is reflected by refreshed server snapshot');
   delete f.cart[102];await f.visit();check(await f.page.locator('#q102').inputValue()==='0',engine+': removal does not resurrect old quantities');
   await f.page.evaluate(()=>window.BVMGR_TICKETING_FRONT.hasCheckoutBlockers=1);await f.nav().evaluate(n=>n.click());check(new URL(f.page.url()).pathname==='/event/'&&await f.nav().getAttribute('aria-disabled')==='true',engine+': explicit checkout blocker prevents navigation');
   check(f.errors.length===0,engine+': no browser exceptions across lifecycle');await f.context.close();
  }
  const f=await fixture({cart:{101:1},width:393});await f.qty(102,2);
  for(const width of [320,393,768,1100]){
   await f.page.setViewportSize({width,height:900});
   await f.page.locator('.vms-claim-seat-input:visible').fill(f.guest);
   await f.page.locator('.vms-claim-seat-input:visible').focus();
   const boxes=await f.page.locator('.vms-claim-seat-input:visible,.vms-claim-seat-validate:visible').evaluateAll(ns=>ns.map(n=>{const r=n.getBoundingClientRect();return{x:r.x,y:r.y,width:r.width,height:r.height,right:r.right,bottom:r.bottom};}));
   check(boxes[0].width>=120&&boxes[0].right<=width&&boxes[1].right<=width&&(width>759||boxes[1].y>=boxes[0].bottom),engine+': input/button readable and unobstructed at '+width);
   await f.validate(f.guest);const overflow=await f.page.evaluate(()=>({ok:document.documentElement.scrollWidth<=innerWidth,width:innerWidth,scroll:document.documentElement.scrollWidth,nodes:[...document.querySelectorAll('body *')].map(n=>({tag:n.tagName,cls:n.className,right:n.getBoundingClientRect().right,width:n.getBoundingClientRect().width})).filter(n=>n.right>innerWidth+1).slice(-10)}));if(!overflow.ok)console.log(JSON.stringify(overflow));check(overflow.ok,engine+': long approved email/status wraps at '+width);
   check(await f.page.locator('.vms-ticketing-cart-details .vms-ticketing-subtotal__line').first().evaluate(n=>getComputedStyle(n).display==='grid'),engine+': cart summary labels and values retain separate columns at '+width);
   await f.page.screenshot({path:path.join(evidence,engine+'-guest-'+width+'.png'),fullPage:true});
  }
  await f.page.setViewportSize({width:320,height:900});await f.page.addStyleTag({content:'.vms-claim-seat-label,.vms-claim-seat-status{font-size:22px!important}'});check(await f.page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),engine+': enlarged label/status text wraps on narrow screen');
  await f.context.close();
const responder=await fixture({cart:{103:1},admission:103});await responder.qty(103,2);check(await responder.submit().isDisabled(),engine+': first-responder extra admission requires an approved guest');await responder.validate(responder.guest);await responder.submit().click();await responder.page.waitForURL('**/cart/');check(responder.atomic[0].ticket_lines[0].product_id===103&&responder.atomic[0].ticket_lines[0].qty===1,engine+': other approved admission adds one authorized difference');await responder.context.close();
  const unknown=await fixture({cart:{},eligible:0});await unknown.qty(102,1);check(await unknown.submit().isDisabled()&&unknown.atomic.length===0,engine+': unapproved buyer receives no self-admission bypass');await unknown.context.close();
  const stale=await fixture();await stale.qty(101,2);await stale.submit().evaluate(n=>{n.click();jQuery(document).trigger('updated_wc_div');});await stale.page.waitForTimeout(250);check(stale.atomic.length===0&&new URL(stale.page.url()).pathname==='/event/',engine+': external cart change aborts queued stale delta before transport');await stale.context.close();
  const keyboard=await fixture();await keyboard.nav().focus();await keyboard.nav().press('Enter');await keyboard.page.waitForURL('**/checkout/');check(keyboard.atomic.length===0,engine+': keyboard activation navigates without adding tickets');await keyboard.context.close();
  for(const event of ['wc-blocks_removed_from_cart','classic']){
  const c=await fixture();const reload=c.page.waitForRequest(r=>r.isNavigationRequest());await c.page.evaluate(e=>e==='classic'?jQuery(document).trigger('updated_wc_div'):document.dispatchEvent(new Event(e)),event);await reload;check(c.atomic.length===0,engine+': '+event+' invalidates snapshot through safe GET reload');await c.context.close();}
  const c=await fixture();const reload=c.page.waitForRequest(r=>r.isNavigationRequest());await c.page.evaluate(()=>window.dispatchEvent(new PageTransitionEvent('pageshow',{persisted:true})));await reload;check(c.atomic.length===0,engine+': persisted Safari lifecycle re-reads cart without submitting');await c.context.close();
  const manual=await fixture({cart:{},payment:true});check(await manual.nav().innerText()==='Complete Payment',engine+': unpaid-order navigation stays distinct');await manual.nav().click();await manual.page.waitForURL('**/order-pay/42/');check(manual.atomic.length===0&&manual.nativePosts()===0,engine+': payment route never authorizes a transaction');await manual.context.close();
 }finally{await browser.close();}
 }
 fs.writeFileSync(path.join(evidence,baseline?'baseline-device-results.json':'device-results.json'),JSON.stringify({passes,baseline,records,scope:'local synthetic cart; native TEC JS; Chromium and WebKit; no WP/gateway/security writes'},null,2)+'\n');console.log(JSON.stringify({passes,baseline}));
})().catch(e=>{console.error(e);process.exit(1)});
