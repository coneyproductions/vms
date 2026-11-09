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
 async function fixture({cart={},remaining=1,assigned=0,loggedIn=1,eligible=1,source=false,layout='v2',grant=1,admission='Police, Fire Fighter, EMT Admission',denyGuest=false}={}){
  const page=await browser.newPage({viewport:{width:1100,height:900}});let requests=[];let errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  await page.route('**/*',async route=>{requests.push({url:route.request().url(),data:route.request().postData()});const denied=denyGuest&&route.request().url().endsWith('/validate');await route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({success:!denied,data:{ok:!denied,cart_url:'http://ticket-test.local/cart/',eligible:!denyGuest,reason_code:denyGuest?'unapproved':'ok',message:denyGuest?'This guest is not approved for this admission.':'',assignee_email:'guest@example.test'}})});});
  await page.goto('http://ticket-test.local/'); requests=[];
  await page.setContent(`<form id="tribe-tickets__tickets-form">${row(101,'General Admission')}${row(102,'Veteran Admission')}<footer class="tribe-tickets__tickets-footer"><button type="submit" id="tribe-tickets__tickets-submit">Get Tickets</button></footer></form>${source?`<div id="vms-reserved-addons" class="vms-entitlements-block" data-vms-render-mode="${source==='legacy'?'':'server_controls'}" data-vms-cart-ga-qty="2" data-vms-qualifying-ticket-product-ids="101,102"><div class="vms-entitlements-list"></div></div>`:''}`);
  await page.evaluate(cfg=>window.BVMGR_TICKETING_FRONT=cfg,{uiLayout:layout,uiProgressive:layout==='progressive'?'1':'0',isLoggedIn:loggedIn,tecEventId:1,eventPlanId:2,currentUserEmail:'buyer@example.test',cartTicketQuantities:cart,cartUrl:'http://ticket-test.local/cart/',checkoutUrl:'http://ticket-test.local/checkout/',atomicAddUrl:'http://ticket-test.local/atomic',atomicAddNonce:'fixture',claimsValidateUrl:'http://ticket-test.local/validate',claimsValidateNonce:'fixture',ticketAccessMap:{101:access(101,false),102:access(102,true,{current_user_claim_remaining_qty:remaining,current_user_is_eligible:eligible,current_user_assigned_cart_qty:assigned,current_user_claims_per_assignee:grant,label:admission,display_label:admission})},ticketPriceMap:{101:20,102:0}});
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
 for(const admission of ['Police, Fire Fighter, EMT Admission','Registered Member Admission']){
  let f=await fixture({admission});await f.qty(102,1);await f.check(!(await f.context()).showPanel,admission+': empty cart honors one approved ticket');await f.qty(102,2);await f.check((await f.context()).assigneeQty===1,admission+': a true extra seat requires one guest');
  await f.page.locator('.vms-claim-seat-input').fill('guest@example.test');await f.page.locator('.vms-claim-seat-validate').click();await f.page.waitForFunction(()=>Object.values(window.BVMGR_TICKETING_FRONT_BUNDLE.state.ticketClaimsByProduct['102'].rowStates).some(r=>r.statusKind==='valid'));await f.page.locator('#tribe-tickets__tickets-submit').click();await f.page.waitForURL('**/cart/');let payload=JSON.parse(f.requests.find(r=>r.url.endsWith('/atomic')).data);await f.check(payload.ticket_lines[0].qty===2&&payload.ticket_lines[0].claim_assignments.length===1&&payload.ticket_lines[0].claim_assignments[0].seat===2,admission+': self plus approved guest sends two tickets and explicit guest seat 2; server assigns self');await f.page.close();
  f=await fixture({admission,cart:{102:1},remaining:0,assigned:1,denyGuest:true});await f.qty(102,2);await f.page.locator('.vms-claim-seat-input').fill('unapproved@example.test');await f.page.locator('.vms-claim-seat-validate').click();await f.page.waitForTimeout(180);await f.page.locator('#tribe-tickets__tickets-submit').click();await f.check(!f.requests.some(r=>r.url.endsWith('/atomic')),admission+': denied extra guest cannot add a ticket');await f.page.close();
 }
 let f=await fixture({cart:{101:1,102:1},remaining:0,assigned:1});await f.qty(101,1);await f.qty(102,1);await f.page.setViewportSize({width:320,height:740});await f.page.addStyleTag({content:'html{font-size:20px}'});await f.check(await f.page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'mixed cart actions fit a 320px viewport with larger text');await f.page.screenshot({path:path.join(evidenceDir, 'admission-workflows-mobile.png'),fullPage:true});await f.page.close();
 f=await fixture({remaining:1});await f.qty(102,1);await f.check(!(await f.context()).showPanel,'fresh cart snapshot after removal restores self allowance');await f.page.close();
 f=await fixture({eligible:0,remaining:0});await f.qty(102,1);await f.check((await f.context()).selfEligibleQty===0,'fresh unrelated-account snapshot cannot inherit prior buyer approval');await f.page.close();
 console.log(JSON.stringify({passes,network:'mocked',full_theme:false}));
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
