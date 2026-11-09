These tests use synthetic HTML, mocked transport and synthetic customer addresses. They do not bootstrap WordPress, access a customer database or invoke a payment gateway. Install/provide Playwright externally; set BVM_PLAYWRIGHT_MODULE to its module path when needed. Provide a read-only extracted BVM directory as the first argument. Set BVM_CHECKOUT_EVIDENCE for screenshots outside this repository.

node tests/checkout-20261109/cart-recovery.cjs /path/to/BVM
node tests/checkout-20261109/status-browser.cjs /path/to/BVM
node tests/checkout-20261109/admission-workflows.cjs /path/to/BVM

Original hashes and path-only adaptations are recorded in docs/checkout-20261109/test-adaptations.json. Earlier passing results apply to the retained original harnesses. Any rerun of these portable copies must be reported separately. Raw native fixtures, remote helpers and payment harnesses remain in the private evidence archive to prevent accidental live execution or exposure of credentials/private data.
