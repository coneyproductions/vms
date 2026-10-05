#!/usr/bin/env python3
"""Focused browser regression for guided-tour launch, dismissal, and fallback state."""

from __future__ import annotations

import json
import pathlib
import sys
from typing import Any

from playwright.sync_api import Page, sync_playwright


ROOT = pathlib.Path(__file__).resolve().parents[1]
RUNTIMES = (
    ("mirror", ROOT / "assets/js/vms-tours-runtime.js", "BVMGR_TOURS_PAYLOAD"),
    ("live", ROOT.parent.parent / "vms/assets/js/vms-tours-runtime.js", "VMS_TOURS_PAYLOAD"),
)


def check(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)


def payload(tour_count: int = 2) -> dict[str, Any]:
    tours = [
        {
            "id": "tour.one",
            "title": "Tour one",
            "version": "1.0.0",
            "auto_run": False,
            "steps": [
                {"id": "one", "selector": "#step-one", "title": "Tour one / step one"},
                {"id": "two", "selector": "#step-two", "title": "Tour one / step two"},
            ],
        }
    ]
    if tour_count > 1:
        tours.extend(
            [
                {
                    "id": "tour.two",
                    "title": "Tour two",
                    "version": "1.0.0",
                    "auto_run": False,
                    "steps": [
                        {"id": "three", "selector": "#step-three", "title": "Tour two / only step"}
                    ],
                },
                {
                    "id": "tour.missing",
                    "title": "Missing-step tour",
                    "version": "1.0.0",
                    "auto_run": False,
                    "steps": [
                        {"id": "missing", "selector": "#not-present", "title": "Missing"}
                    ],
                },
            ]
        )

    return {
        "settings": {
            "debug_log_enabled": True,
            "global_enabled": False,
            "help_button_enabled": True,
            "max_auto_run_per_page_load": 1,
        },
        "user": {
            "canRunTours": True,
            "prefs": {"auto_run_enabled": False, "dismissed_tours": {}},
            "state": {},
        },
        "tours": tours,
        "ajaxUrl": "/tour-test-ajax",
        "nonce": "synthetic",
        "screenKey": "admin:tour-test",
        "context": {"isAdminScreen": True},
    }


HTML = """<!doctype html>
<html><body>
  <button id="start-one" type="button" data-vms-tour-start="tour.one">Start one</button>
  <button id="start-two" type="button" data-vms-tour-start="tour.two">Start two</button>
  <button id="start-missing" type="button" data-vms-tour-start="tour.missing">Start missing</button>
  <div id="step-one">One</div><div id="step-two">Two</div><div id="step-three">Three</div>
  <div id="fallback-step">Fallback</div>
</body></html>"""


FAKE_DRIVER = r"""
({ mode }) => {
  window.__tourTest = {
    mode,
    factoryCalls: 0,
    driveCalls: 0,
    destroys: 0,
    fetchBodies: [],
    errors: []
  };
  window.fetch = function (_url, options) {
    window.__tourTest.fetchBodies.push(String((options && options.body) || ''));
    return Promise.resolve({
      ok: true,
      json: function () { return Promise.resolve({ success: true, data: {} }); }
    });
  };

  if (mode === 'missing_driver') {
    window.driver = undefined;
    return;
  }

  window.driver = {
    js: function (config) {
      window.__tourTest.factoryCalls += 1;
      var factoryCall = window.__tourTest.factoryCalls;
      if (mode === 'factory_throw_once' && factoryCall === 1) {
        throw new Error('synthetic factory failure');
      }

      var activeIndex = 0;
      var destroyed = false;
      var focusBefore = null;
      var escapeHandler = null;

      function removeChrome() {
        var chrome = document.querySelector('.driver-popover');
        if (chrome) {
          chrome.remove();
        }
      }

      function destroy() {
        if (destroyed) {
          return;
        }
        destroyed = true;
        window.__tourTest.destroys += 1;
        removeChrome();
        if (escapeHandler) {
          window.removeEventListener('keyup', escapeHandler);
        }
        if (mode !== 'destroy_without_callback' && typeof config.onDestroyed === 'function') {
          config.onDestroyed();
        }
        if (focusBefore && typeof focusBefore.focus === 'function') {
          focusBefore.focus();
        }
      }

      var api = {
        drive: function (index) {
          window.__tourTest.driveCalls += 1;
          if (mode === 'drive_throw_once' && factoryCall === 1) {
            throw new Error('synthetic drive failure');
          }
          focusBefore = document.activeElement;
          activeIndex = Number.isInteger(index) ? index : 0;
          show();
        },
        destroy,
        hasNextStep: function () {
          return activeIndex < config.steps.length - 1;
        }
      };

      function requestDestroy() {
        if (typeof config.onDestroyStarted === 'function') {
          config.onDestroyStarted();
        } else {
          destroy();
        }
      }

      function show() {
        removeChrome();
        if (typeof config.onHighlightStarted === 'function') {
          config.onHighlightStarted(null, null, { state: { activeIndex } });
        }

        var chrome = document.createElement('div');
        chrome.className = 'driver-popover';
        chrome.innerHTML =
          '<h2 class="driver-popover-title"></h2>' +
          '<button type="button" class="driver-popover-close-btn">Close</button>' +
          '<div class="driver-popover-footer"><span class="driver-popover-navigation-btns">' +
          '<button type="button" class="driver-popover-prev-btn">Back</button>' +
          '<button type="button" class="driver-popover-next-btn">Next</button>' +
          '</span></div>';
        chrome.querySelector('.driver-popover-title').textContent =
          String(config.steps[activeIndex].popover.title || '');
        document.body.appendChild(chrome);

        if (typeof config.onPopoverRender === 'function') {
          config.onPopoverRender({
            footerButtons: chrome.querySelector('.driver-popover-navigation-btns')
          });
        }

        chrome.addEventListener('click', function (event) {
          if (event.target.closest('.driver-popover-close-btn')) {
            requestDestroy();
            return;
          }
          if (event.target.closest('.driver-popover-next-btn')) {
            if (api.hasNextStep()) {
              activeIndex += 1;
              show();
            } else {
              requestDestroy();
            }
          }
        });

        escapeHandler = function (event) {
          if (event.key === 'Escape') {
            requestDestroy();
          }
        };
        window.addEventListener('keyup', escapeHandler);
      }

      return api;
    }
  };
}
"""


def setup_page(
    page: Page,
    runtime: pathlib.Path,
    payload_name: str,
    *,
    tours: int = 2,
    mode: str = "normal",
    auto_run: bool = False,
) -> None:
    page.set_content(HTML)
    fallback = {
        "tourId": "fallback.tour",
        "steps": [
            {"id": "fallback", "selector": "#fallback-step", "title": "Ad hoc fallback"}
        ],
    }
    page.eval_on_selector(
        "#start-one",
        "(node, value) => node.setAttribute('data-vms-tour-fallback', value)",
        json.dumps(fallback),
    )
    page.eval_on_selector(
        "#start-missing",
        "(node, value) => node.setAttribute('data-vms-tour-fallback', value)",
        json.dumps(fallback),
    )
    runtime_payload = payload(tours)
    if auto_run:
        runtime_payload["settings"]["global_enabled"] = True
        runtime_payload["settings"]["max_auto_run_per_page_load"] = 2
        runtime_payload["settings"]["auto_run_delay_ms"] = 0
        runtime_payload["user"]["prefs"]["auto_run_enabled"] = True
        for tour in runtime_payload["tours"][:2]:
            tour["auto_run"] = True
            tour["auto_run_delay_ms"] = 0
    page.evaluate("([name, value]) => { window[name] = value; }", [payload_name, runtime_payload])
    page.evaluate(FAKE_DRIVER, {"mode": mode})
    page.add_script_tag(path=str(runtime))
    page.wait_for_selector("#vms-help-tour-button")


def popover_title(page: Page) -> str:
    return page.locator(".driver-popover-title").inner_text()


def assert_stays_dismissed(page: Page, label: str) -> None:
    page.wait_for_timeout(1300)
    check(page.locator(".driver-popover").count() == 0, f"{label}: tour reopened after fallback deadlines")
    panel = page.locator("#vms-help-tour-panel")
    check(panel.count() == 0 or panel.is_hidden(), f"{label}: fallback help panel opened after dismissal")


def run_runtime(browser: Any, label: str, runtime: pathlib.Path, payload_name: str) -> list[str]:
    results: list[str] = []

    def scenario(name: str, *, tours: int = 2, mode: str = "normal", auto_run: bool = False) -> Page:
        page = browser.new_page(viewport={"width": 1280, "height": 900})
        setup_page(page, runtime, payload_name, tours=tours, mode=mode, auto_run=auto_run)
        results.append(name)
        return page

    page = scenario("close-immediate")
    page.locator("#start-one").focus()
    page.locator("#start-one").click()
    page.locator(".driver-popover-close-btn").click()
    assert_stays_dismissed(page, "close-immediate")
    check(page.locator("#start-one").evaluate("el => el === document.activeElement"), "Close did not restore focus")
    check(len(page.evaluate("window.__tourTest.fetchBodies")) == 1, "Dismissal should record seen exactly once")
    page.close()

    page = scenario("close-after-navigation")
    page.locator("#start-one").click()
    page.locator(".driver-popover-next-btn").click()
    check("step two" in popover_title(page).lower(), "Next did not navigate to the second step")
    page.locator(".driver-popover-close-btn").click()
    assert_stays_dismissed(page, "close-after-navigation")
    page.close()

    page = scenario("skip-immediate")
    page.locator("#start-one").click()
    page.locator(".vms-tour-skip-btn").click()
    assert_stays_dismissed(page, "skip-immediate")
    page.close()

    page = scenario("skip-after-navigation")
    page.locator("#start-one").click()
    page.locator(".driver-popover-next-btn").click()
    page.locator(".vms-tour-skip-btn").click()
    assert_stays_dismissed(page, "skip-after-navigation")
    page.close()

    page = scenario("escape-dismissal")
    page.locator("#start-one").click()
    page.keyboard.press("Escape")
    assert_stays_dismissed(page, "escape-dismissal")
    page.close()

    page = scenario("escape-after-navigation")
    page.locator("#start-one").click()
    page.locator(".driver-popover-next-btn").click()
    page.keyboard.press("Escape")
    assert_stays_dismissed(page, "escape-after-navigation")
    page.close()

    page = scenario("explicit-restart")
    page.locator("#start-one").click()
    page.locator(".driver-popover-close-btn").click()
    page.wait_for_timeout(800)
    page.locator("#start-one").click()
    check("Tour one" in popover_title(page), "Explicit restart did not launch the tour")
    page.locator(".driver-popover-close-btn").click()
    page.close()

    page = scenario("single-tour-dismissal", tours=1)
    page.locator("#start-one").click()
    page.locator(".driver-popover-close-btn").click()
    assert_stays_dismissed(page, "single-tour-dismissal")
    page.close()

    page = scenario("driver-early-destroy-without-onDestroyed", mode="destroy_without_callback")
    page.locator("#start-one").click()
    page.locator(".driver-popover-close-btn").click()
    assert_stays_dismissed(page, "driver-early-destroy-without-onDestroyed")
    check(len(page.evaluate("window.__tourTest.fetchBodies")) == 1, "Early Driver dismissal did not record seen")
    page.close()

    page = scenario("ad-hoc-fallback-dismissal")
    page.locator("#start-missing").click()
    page.wait_for_selector(".driver-popover")
    check("Ad hoc fallback" in popover_title(page), "Missing steps did not start the ad-hoc fallback")
    page.locator(".driver-popover-close-btn").click()
    assert_stays_dismissed(page, "ad-hoc-fallback-dismissal")
    page.close()

    page = scenario("missing-ad-hoc-steps-fallback")
    page.eval_on_selector(
        "#start-missing",
        "node => node.setAttribute('data-vms-tour-fallback', JSON.stringify({tourId:'missing.fallback',steps:[{selector:'#also-not-present',title:'Missing fallback'}]}))",
    )
    page.locator("#start-missing").click()
    page.wait_for_timeout(100)
    check(
        not page.eval_on_selector("#vms-help-tour-panel", "node => node.hidden"),
        "Missing ad-hoc steps did not open the genuine help-panel fallback",
    )
    page.close()

    page = scenario("missing-driver-fallback", mode="missing_driver")
    page.locator("#start-one").click()
    page.wait_for_timeout(900)
    check(page.locator("#vms-help-tour-panel").is_visible(), "Missing driver did not open the genuine fallback")
    page.close()

    page = scenario("factory-error-fallback", mode="factory_throw_once")
    page.locator("#start-one").click()
    page.wait_for_selector(".driver-popover")
    check("Ad hoc fallback" in popover_title(page), "Thrown factory error did not start fallback")
    page.locator(".driver-popover-close-btn").click()
    assert_stays_dismissed(page, "factory-error-fallback")
    page.close()

    page = scenario("drive-error-fallback", mode="drive_throw_once")
    page.locator("#start-one").click()
    page.wait_for_selector(".driver-popover")
    check("Ad hoc fallback" in popover_title(page), "Thrown drive error did not start fallback")
    page.locator(".driver-popover-close-btn").click()
    assert_stays_dismissed(page, "drive-error-fallback")
    page.close()

    page = scenario("rapid-launch-stale-callback")
    page.locator("#start-one").click()
    page.locator("#start-two").click()
    check("Tour two" in popover_title(page), "Newer rapid launch did not replace the older tour")
    page.wait_for_timeout(1300)
    check("Tour two" in popover_title(page), "Older callback or watchdog replaced the newer tour")
    check(page.evaluate("window.__tourTest.factoryCalls") == 2, "Rapid launch invoked an unexpected fallback")
    check(page.evaluate("window.__tourTest.destroys") >= 1, "Rapid launch did not destroy the older tour")
    page.locator(".driver-popover-close-btn").click()
    page.close()

    page = scenario("auto-run-sequencing", auto_run=True)
    page.wait_for_selector(".driver-popover")
    check("Tour one" in popover_title(page), "Auto-run did not start with the first eligible tour")
    page.locator(".driver-popover-close-btn").click()
    page.wait_for_function(
        "document.querySelector('.driver-popover-title')?.textContent.includes('Tour two')"
    )
    check("Tour two" in popover_title(page), "Auto-run did not continue to the second eligible tour")
    page.locator(".driver-popover-close-btn").click()
    page.close()

    page = scenario("newer-manual-launch-cancels-auto-run-queue", auto_run=True)
    page.wait_for_selector(".driver-popover")
    page.locator("#start-two").click()
    check("Tour two" in popover_title(page), "Newer manual launch did not replace the auto-run tour")
    page.wait_for_timeout(700)
    check(
        page.evaluate("window.__tourTest.factoryCalls") == 2,
        "A stale auto-run callback replaced the newer manual tour",
    )
    page.locator(".driver-popover-close-btn").click()
    page.close()

    page = scenario("completion-semantics")
    page.locator("#start-one").click()
    page.locator(".driver-popover-next-btn").click()
    page.locator(".driver-popover-next-btn").click()
    page.wait_for_timeout(50)
    bodies = page.evaluate("window.__tourTest.fetchBodies")
    check(len(bodies) == 2, "Completion should record seen and completed exactly once each")
    check(any("mode=seen" in body for body in bodies), "Completion did not record seen")
    check(any("mode=complete" in body for body in bodies), "Completion did not record completed")
    page.close()

    return results


def main() -> int:
    missing = [str(path) for _, path, _ in RUNTIMES if not path.is_file()]
    if missing:
        raise RuntimeError("Missing runtime file(s): " + ", ".join(missing))

    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(
            executable_path="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome",
            headless=True,
        )
        try:
            for label, runtime, payload_name in RUNTIMES:
                completed = run_runtime(browser, label, runtime, payload_name)
                print(f"PASS {label}: {len(completed)} scenarios ({', '.join(completed)})")
        finally:
            browser.close()

    print("PASS guided-tour dismissal and fallback browser regression")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as exc:  # pragma: no cover - preserves actionable CLI failure output.
        print(f"FAIL: {exc}", file=sys.stderr)
        raise
