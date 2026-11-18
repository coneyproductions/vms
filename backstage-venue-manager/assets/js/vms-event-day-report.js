(function () {
    'use strict';

    var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-vms-edr-tab]'));
    var panels = Array.prototype.slice.call(document.querySelectorAll('[data-vms-edr-panel]'));
    var allowedTabs = ['guests', 'reservations', 'issues', 'information'];

    function show(name) {
        tabs.forEach(function (tab) {
            tab.setAttribute('aria-selected', tab.getAttribute('data-vms-edr-tab') === name ? 'true' : 'false');
        });
        panels.forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-vms-edr-panel') !== name;
        });
        if (window.history.replaceState) {
            window.history.replaceState(null, '', '#' + name);
        }
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            show(tab.getAttribute('data-vms-edr-tab'));
        });
    });

    if (tabs.length > 0) {
        var initial = window.location.hash.slice(1);
        show(allowedTabs.indexOf(initial) >= 0 ? initial : 'guests');
    }

    var search = document.getElementById('vms-edr-search');
    var empty = document.getElementById('vms-edr-no-search-results');
    if (search) {
        search.addEventListener('input', function () {
            var query = search.value.toLowerCase().trim();
            var shown = 0;
            document.querySelectorAll('[data-vms-edr-guest-row]').forEach(function (row) {
                var match = !query || (row.getAttribute('data-search') || '').indexOf(query) >= 0;
                row.hidden = !match;
                if (match) {
                    shown++;
                }
            });
            if (empty) {
                empty.hidden = shown > 0;
            }
        });
    }

    document.querySelectorAll('[data-vms-edr-print-now]').forEach(function (button) {
        button.addEventListener('click', function () {
            window.print();
        });
    });

    if (document.body.getAttribute('data-vms-edr-auto-print') === '1') {
        window.addEventListener('load', function () {
            window.print();
        });
    }
}());
