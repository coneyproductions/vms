const evidenceDir=process.env.BVM_CHECKOUT_EVIDENCE || require('fs').mkdtempSync(require('path').join(require('os').tmpdir(),'bvm-checkout-124-'));
require('fs').mkdirSync(evidenceDir,{recursive:true});
const {chromium} = require(process.env.BVM_PLAYWRIGHT_MODULE || 'playwright');
const fs=require('fs');
const path=require('path');
const assert=require('assert/strict');
const root=path.resolve(process.argv[2] || process.cwd());
const baseline=process.argv.includes('--baseline');
const access=(pid,qualified,extra={})=>({woo_product_id:pid,tec_ticket_id:pid,label:qualified?'Veteran Admission':'General Admission',display_label:qualified?'Veteran Admission':'General Admission',visibility_mode:qualified?'verified':'public',current_user_is_eligible:qualified?1:0,current_user_claim_remaining_qty:1,require_assignee_email:qualified?1:0,current_user_assigned_cart_qty:0,counts_toward_unlock:1,...extra});
const row=(pid,label)=>`<div class="tribe-tickets__tickets-item tribe-tickets__item" data-ticket-id="${pid}" data-product-id="${pid}"><h3 class="tribe-tickets__tickets-item-content-title">${label}</h3><div class="tribe-tickets__tickets-item-quantity"><button type="button" class="tribe-tickets__tickets-item-quantity-remove">-</button><input type="number" id="q${pid}" min="0" max="20" value="0" class="tribe-tickets__tickets-item-quantity-number-input"><button type="button" class="tribe-tickets__tickets-item-quantity-add">+</button></div><span class="tribe-tickets__tickets-item-price">${pid===101?'$20.00':'$0.00'}</span></div>`;
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'chrome'});
 let passes=0;
 async function fixture({cart={},remaining=1,assigned=0,loggedIn=1,eligible=1,source=false,layout='v2',grant=1,status={},admission='Veteran Admission'}={}){
  const page=await browser.newPage({viewport:{width:1100,height:900}});let requests=[];let errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  await page.route('**/*',async route=>{requests.push({url:route.request().url(),data:route.request().postData()});await route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({success:true,data:{ok:true,cart_url:'http://ticket-test.local/cart/',eligible:true,reason_code:'ok',assignee_email:'guest@example.test'}})});});
  await page.goto('http://ticket-test.local/'); requests=[];
  await page.setContent(`<form id="tribe-tickets__tickets-form">${row(101,'General Admission')}${row(102,'Veteran Admission')}<footer class="tribe-tickets__tickets-footer"><button type="submit" id="tribe-tickets__tickets-submit">Get Tickets</button></footer></form>${source?`<div id="vms-reserved-addons" class="vms-entitlements-block" data-vms-render-mode="${source==='legacy'?'':'server_controls'}" data-vms-cart-ga-qty="2" data-vms-qualifying-ticket-product-ids="101,102"><div class="vms-entitlements-list"></div></div>`:''}`);
  await page.evaluate(cfg=>window.BVMGR_TICKETING_FRONT=cfg,{uiLayout:layout,uiProgressive:layout==='progressive'?'1':'0',isLoggedIn:loggedIn,tecEventId:1,eventPlanId:2,currentUserEmail:'buyer@example.test',cartTicketQuantities:cart,eventPurchaseStatus:status,cartUrl:'http://ticket-test.local/cart/',checkoutUrl:'http://ticket-test.local/checkout/',atomicAddUrl:'http://ticket-test.local/atomic',atomicAddNonce:'fixture',claimsValidateUrl:'http://ticket-test.local/validate',claimsValidateNonce:'fixture',ticketAccessMap:{101:access(101,false),102:access(102,true,{current_user_claim_remaining_qty:remaining,current_user_is_eligible:eligible,current_user_assigned_cart_qty:assigned,current_user_claims_per_assignee:grant,label:admission,display_label:admission})},ticketPriceMap:{101:20,102:0}});
  await page.addScriptTag({content:fs.readFileSync(path.join(root,'assets/vms-ticketing-front.js'),'utf8')});
  await page.waitForFunction(()=>window.BVMGR_TICKETING_FRONT_BUNDLE?.state);
  await page.addStyleTag({content:'body{font-family:Arial,sans-serif;padding:16px;background:#fafbfc}form{max-width:900px;margin:auto}button,input{font:inherit}'});
  await page.addStyleTag({content:fs.readFileSync(path.join(root,'assets/css/vms-ticketing-front.css'),'utf8')});
  async function qty(id,n){await page.locator('#q'+id).fill(String(n));await page.locator('#q'+id).dispatchEvent('change');await page.waitForTimeout(60);await page.evaluate(()=>document.dispatchEvent(new Event('bvmgr:purchase-extension-change')));}
  async function context(){return page.evaluate(()=>window.BVMGR_TICKETING_FRONT_BUNDLE.state.ticketClaimsByProduct['102']?.context);}
  async function check(cond,label){assert.ok(cond,label);passes++;console.log('PASS '+label);}
  return {page,requests,errors,qty,context,check};
 }
 try {
  const pending={id:7001,total:'$21.65',url:'http://ticket-test.local/order-pay/7001/?key=fixture'};
  let f=await fixture({cart:{101:1,102:1},remaining:0,assigned:1,status:{payment_orders:[pending]}});
  let text=await f.page.locator('.vms-event-purchase-status').innerText();
  await f.check(text.includes('awaiting payment')&&text.includes('purchase is not complete'),'unpaid order clearly distinguished from purchase');
  await f.check(!text.includes('issued ticket'),'unpaid order is never described as issued');
  await f.check(text.includes('in your cart')&&text.includes('have not been purchased'),'cart explicitly described as unpurchased');
  await f.check((await f.page.locator('#tribe-tickets__tickets-submit').innerText())==='Complete Payment','pending order takes priority over duplicate checkout');
  await f.check(await f.page.getByRole('link',{name:'Review cart',exact:true}).count()>=1,'review cart remains directly available');
  await f.page.locator('#tribe-tickets__tickets-submit').click();await f.page.waitForURL('**/order-pay/**');
  await f.check(!f.requests.some(r=>r.url.endsWith('/atomic')),'complete payment adds no duplicate tickets');await f.page.close();
  f=await fixture({status:{payment_orders:[pending]}});await f.check(!(await f.page.locator('#tribe-tickets__tickets-submit').isDisabled()),'payment action available with no cart');await f.page.close();
  f=await fixture({status:{issued_count:2,paid_count:2,paid_orders:[{id:30,url:'http://ticket-test.local/order/30'}],tickets_url:'http://ticket-test.local/event/tickets'}});
  text=await f.page.locator('.vms-event-purchase-status').innerText();await f.check(text.includes('2 issued tickets')&&!text.includes('awaiting payment'),'issued tickets shown independently');
  await f.check(await f.page.getByRole('link',{name:'View your tickets'}).getAttribute('href')==='http://ticket-test.local/event/tickets','issued ticket action uses ticket-view route');await f.page.close();
  f=await fixture({status:{paid_count:2,issued_count:0,paid_orders:[{id:31,url:'http://ticket-test.local/order/31'}]}});text=await f.page.locator('.vms-event-purchase-status').innerText();await f.check(text.includes('payment is complete')&&text.includes('still being prepared')&&!text.includes('issued ticket'),'paid but unissued order does not claim tickets issued');await f.page.close();
  f=await fixture({status:{confirmation_orders:[{id:32,url:'http://ticket-test.local/order/32'}]}});text=await f.page.locator('.vms-event-purchase-status').innerText();await f.check(text.includes('awaiting confirmation')&&!text.includes('Complete Payment'),'on-hold order does not offer an ineligible payment action');await f.page.close();
  f=await fixture({cart:{102:1},remaining:0,assigned:1,status:{issued_count:1,paid_count:1,paid_orders:[{id:33,url:'http://ticket-test.local/order/33'}],tickets_url:'http://ticket-test.local/event/tickets'}});text=await f.page.locator('.vms-event-purchase-status').innerText();await f.check(text.includes('1 ticket in your cart')&&text.includes('1 issued ticket'),'cart and issued ticket statuses coexist without conflation');await f.page.close();
  for(const admission of ['Police, Fire Fighter, EMT Admission','Registered Member Admission']){
    f=await fixture({cart:{102:1},remaining:0,assigned:1,admission});await f.qty(102,1);await f.check(!(await f.context()).showPanel,admission+': own cart ticket needs no guest');await f.qty(102,2);await f.check((await f.context()).assigneeQty===1,admission+': one genuine extra ticket needs a guest');await f.page.close();
  }
  f=await fixture({cart:{102:1},remaining:0,assigned:1,status:{payment_orders:[pending]}});await f.page.setViewportSize({width:390,height:844});
  await f.check(await f.page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'status actions fit narrow screens');
  const link=f.page.getByRole('link',{name:'Complete Payment — Order #7001'});await link.focus();await f.check(await link.evaluate(e=>document.activeElement===e),'complete payment action is keyboard focusable');await f.page.close();
  const nativePage=await browser.newPage();await nativePage.setContent('<form id="tribe-tickets__tickets-form"><input class="tribe-tickets__tickets-item-quantity-number-input" value="1"></form>');await nativePage.evaluate(()=>window.BVMGR_TICKETING_FRONT={uiLayout:'classic',cartTicketQuantities:{102:1},cartUrl:'https://fixture/cart',checkoutUrl:'https://fixture/checkout'});await nativePage.addScriptTag({content:fs.readFileSync(path.join(root,'assets/vms-ticketing-front.js'),'utf8')});assert.ok(await nativePage.locator('.vms-event-purchase-status').count());assert.ok(await nativePage.evaluate(()=>window.BVMGR_TICKETING_FRONT_BUNDLE.nativeAuthority));passes+=2;console.log('PASS native Safe Mode shows cart status and retains TEC control ownership');await nativePage.close();
  f=await fixture({cart:{102:1},remaining:0,assigned:1,status:{payment_orders:[pending]}});const reloadRequest=f.page.waitForRequest(r=>r.isNavigationRequest());await f.page.evaluate(()=>window.dispatchEvent(new PageTransitionEvent('pageshow',{persisted:true})));await reloadRequest;await f.check(!f.requests.some(r=>r.url.endsWith('/atomic')),'browser-history restore refreshes status without a cart or payment submission');await f.page.close();
  console.log(JSON.stringify({root,passes}));
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
