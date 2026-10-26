</main>
</div> <!-- End flex h-screen -->
<script src="<?= BASE_URL ?>/includes/bulk_edit.js?v=<?= filemtime(__DIR__ . '/bulk_edit.js') ?>"></script>

<script>
/* ─── Global MAC Address Auto-Formatter ─────────────────────────────────────
   Replaces colons ( : ) with dashes ( - ) as the user types in any
   input[name="mac_address"] field, anywhere on the page.
   Works for inputs already in the DOM and those added later (modals, AJAX).
─────────────────────────────────────────────────────────────────────────── */
(function () {
    function formatMac(input) {
        const pos = input.selectionStart;
        const old = input.value;
        // Replace colons with dashes, uppercase everything
        const updated = old.replace(/:/g, '-').toUpperCase();
        if (updated !== old) {
            input.value = updated;
            // Restore cursor position, adjusting for any replaced characters
            try { input.setSelectionRange(pos, pos); } catch(e) {}
        }
    }

    // Attach formatter to a single input element
    function attachMacFormatter(input) {
        if (input._macFormatterAttached) return;
        input._macFormatterAttached = true;
        input.addEventListener('input', function () { formatMac(this); });
        input.addEventListener('change', function () { formatMac(this); });
        // Also handle paste (fires after value is set)
        input.addEventListener('paste', function () {
            setTimeout(() => formatMac(this), 0);
        });
    }

    // Attach to all current MAC inputs
    function attachAll() {
        document.querySelectorAll('input[name="mac_address"]').forEach(attachMacFormatter);
    }

    // Re-attach whenever new nodes are added to the DOM (modals, dynamic forms)
    const observer = new MutationObserver(function (mutations) {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (node.nodeType !== 1) continue;
                if (node.matches && node.matches('input[name="mac_address"]')) {
                    attachMacFormatter(node);
                }
                // Also scan children of added containers
                node.querySelectorAll && node.querySelectorAll('input[name="mac_address"]').forEach(attachMacFormatter);
            }
        }
    });
    observer.observe(document.body, { childList: true, subtree: true });

    // Initial pass
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attachAll);
    } else {
        attachAll();
    }
})();
</script>

</body>

</html>