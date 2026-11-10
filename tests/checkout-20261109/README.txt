These tests use synthetic HTML, mocked transport and synthetic customer addresses. They do not bootstrap WordPress, access a customer database or invoke a payment gateway. Install/provide Playwright externally; set BVM_PLAYWRIGHT_MODULE to its module path when needed. Provide a read-only extracted BVM directory as the first argument. Set BVM_CHECKOUT_EVIDENCE for screenshots outside this repository.

node tests/checkout-20261109/cart-recovery.cjs /path/to/BVM
node tests/checkout-20261109/status-browser.cjs /path/to/BVM
node tests/checkout-20261109/admission-workflows.cjs /path/to/BVM

Original hashes and path-only adaptations are recorded in docs/checkout-20261109/test-adaptations.json. Earlier passing results apply to the retained original harnesses. Any rerun of these portable copies must be reported separately. Raw native fixtures, remote helpers and payment harnesses remain in the private evidence archive to prevent accidental live execution or exposure of credentials/private data.

The quantity-lifecycle test also needs BVM_EVENT_TICKETS_ROOT and BVM_WORDPRESS_ROOT pointing to read-only native assets. It uses those files in synthetic HTML, never a running WordPress instance. Run it only with the intended .2 BVM source. All network requests are intercepted at the synthetic quantity.test origin.

Issue #124 physical-device correction: device-checkout.cjs uses native TEC scripts
and both local Chromium (Chrome channel) and WebKit, with fully intercepted local
transport. It accepts --baseline to reproduce the late native ready race in .2.
cart-summary.php is a pure local stub test of the display-only WooCommerce snapshot.
No WordPress bootstrap, remote connection, provider request or payment is allowed.
