// Verbatim readiness predicate exercised by Issue 124 native Staging acceptance.
const nativeCartReady = want=>{const store=window.wp?.data?.select('wc/store/cart');if(!store?.getCartData)return false;const c=store.getCartData();if(!c||!Array.isArray(c.items))return false;const actual={};for(const i of c.items)actual[i.id]=(actual[i.id]||0)+i.quantity;const same=Object.keys(actual).length===Object.keys(want).length&&Object.entries(want).every(([id,n])=>actual[id]===n);if(!same||document.querySelector('.wc-block-cart-items__row[aria-label*="Loading"]'))return false;if(c.items.length&&!document.querySelector('.wc-block-cart-item__quantity input'))return false;return !document.querySelector('.wc-block-cart-items.is-loading,.wc-block-cart-items [aria-busy="true"]');};

async function waitForNativeCart(page, expectedTotals, timeout = 90000) {
  await page.waitForFunction(nativeCartReady, expectedTotals, { timeout });
}

function rowsForAdmission(page, admissionName) {
  return page.locator('.wc-block-cart-items__row').filter({
    has: page.locator('.wc-block-components-product-name', { hasText: admissionName }),
  });
}

module.exports = { waitForNativeCart, rowsForAdmission };
