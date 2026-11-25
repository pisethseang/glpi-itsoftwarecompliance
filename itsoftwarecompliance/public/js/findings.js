/* IT Software Compliance v0.4.3 -- lightweight, read-only Findings inspector. */
(function () {
    'use strict';
    function init() {
        var root = document.querySelector('.itc-findings-v43');
        var panel = document.getElementById('itc-f43-inspector');
        var backdrop = document.getElementById('itc-f43-backdrop');
        var density = document.getElementById('itc-f43-density');
        if (!root || !panel || !backdrop) return;

        var previousFocus = null;
        var selectedRow = null;
        var closeButton = document.getElementById('itc-f43-inspector-close');
        var doneButton = document.getElementById('itc-f43-detail-done');
        var openLink = document.getElementById('itc-f43-detail-open');
        var entries = {
            hostname: document.getElementById('itc-f43-detail-hostname'),
            serial: document.getElementById('itc-f43-detail-serial'),
            user: document.getElementById('itc-f43-detail-user'),
            department: document.getElementById('itc-f43-detail-department'),
            inventory: document.getElementById('itc-f43-detail-inventory'),
            confidence: document.getElementById('itc-f43-detail-confidence'),
            finding: document.getElementById('itc-f43-detail-finding'),
            next: document.getElementById('itc-f43-detail-next'),
            rule: document.getElementById('itc-f43-detail-rule'),
            pattern: document.getElementById('itc-f43-detail-pattern'),
            version: document.getElementById('itc-f43-detail-version')
        };
        function setText(element, value) {
            if (element) element.textContent = value || '—';
        }
        function openInspector(link, event) {
            if (event) event.preventDefault();
            var data = link.dataset;
            previousFocus = link;
            if (selectedRow) selectedRow.classList.remove('itc-f43-row-selected');
            selectedRow = link.closest('tr');
            if (selectedRow) selectedRow.classList.add('itc-f43-row-selected');
            Object.keys(entries).forEach(function (key) {
                var field = key === 'next' ? 'nextStep' : key;
                var value = data[field] || '';
                if (key === 'confidence') value += (data.confidenceDetail ? ' · ' + data.confidenceDetail : '');
                setText(entries[key], value);
            });
            if (openLink) openLink.href = link.href;
            panel.hidden = false;
            backdrop.hidden = false;
            document.body.classList.add('itc-f43-modal-open');
            if (closeButton) closeButton.focus();
        }
        function closeInspector() {
            if (panel.hidden) return;
            panel.hidden = true;
            backdrop.hidden = true;
            document.body.classList.remove('itc-f43-modal-open');
            if (selectedRow) selectedRow.classList.remove('itc-f43-row-selected');
            selectedRow = null;
            if (previousFocus && document.contains(previousFocus)) previousFocus.focus();
        }
        root.querySelectorAll('[data-itc-inspect]').forEach(function (link) {
            link.addEventListener('click', function (event) { openInspector(link, event); });
        });
        if (closeButton) closeButton.addEventListener('click', closeInspector);
        if (doneButton) doneButton.addEventListener('click', closeInspector);
        backdrop.addEventListener('click', closeInspector);
        document.addEventListener('keydown', function (event) {
            if (panel.hidden) return;
            if (event.key === 'Escape') { event.preventDefault(); closeInspector(); return; }
            if (event.key !== 'Tab') return;
            var focusable = Array.prototype.slice.call(panel.querySelectorAll('a[href], button:not([disabled])')).filter(function (el) { return !el.hidden; });
            if (!focusable.length) return;
            var first = focusable[0], last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        });
        if (density) {
            density.addEventListener('click', function () {
                var compact = root.classList.toggle('itc-f43-is-compact');
                density.setAttribute('aria-pressed', compact ? 'true' : 'false');
                density.innerHTML = compact ? '<i class="ti ti-layout-list" aria-hidden="true"></i> Comfortable rows' : '<i class="ti ti-list-details" aria-hidden="true"></i> Compact rows';
            });
        }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
}());
