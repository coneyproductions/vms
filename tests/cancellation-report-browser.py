"""Read-only synthetic report QA. Generate fixtures with the PHP completeness test first."""
import json
import sys
from pathlib import Path
from playwright.sync_api import sync_playwright

root = Path(sys.argv[1])
results = []
with sync_playwright() as playwright:
    browser = playwright.chromium.launch(headless=True)
    page = browser.new_page()
    page.route('**/*', lambda route: route.abort())
    for name in ('preflight', 'partial'):
        for width in (1440, 390):
            page.set_viewport_size({'width': width, 'height': 1100})
            page.set_content((root / (name + '.html')).read_text())
            assert page.get_by_text('Six Corona preorders / Express Bar / preorders / 6', exact=True).count() == 1
            assert page.get_by_text('Back to Event Plan', exact=True).count() == 1
            assert page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1')
            if name == 'preflight':
                assert page.locator('textarea').evaluate_all('(els) => els.every(el => el.form?.id === "bvm-cancel-accept")')
                assert page.get_by_text('Planned', exact=True).count() >= 1
                assert page.get_by_text('Failure', exact=True).count() == 0
            else:
                assert page.get_by_role('heading', name='Attention required', exact=True).count() == 1
                assert 'Unexplained residual: USD 36.00' in page.inner_text('body')
            page.screenshot(path=str(root / (name + '-' + str(width) + '.png')), full_page=True)
            results.append({'fixture': name, 'width': width, 'passed': True})
    browser.close()
(root / 'browser-results.json').write_text(json.dumps(results, indent=2) + '\n')
print(json.dumps(results))
