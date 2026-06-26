/**
 * HSNM Bulk Edit Controller
 *
 * How it works:
 *  1. Tick checkboxes → floating bar appears.
 *  2. "Edit Selected" opens the first item's edit modal.
 *  3. Edit fields, then click Next in the floating bar.
 *     → If there are unsaved changes (isFormDirty=true):
 *        auto-clicks the form's submit button to save,
 *        stores the remaining bulk session in sessionStorage,
 *        page reloads (normal POST), then auto-resumes.
 *     → If form is clean: navigates without saving.
 *  4. After page reload, restoreBulkSession() reopens the next item.
 */
(function () {
    'use strict';

    var _items        = [];   // collected JSON objects for selected rows
    var _idx          = -1;   // current position in _items
    var _inBulk       = false;

    // ── SessionStorage key ─────────────────────────────────────────────────
    var SESSION_KEY = 'hsnm_bulk_edit';

    // ── Save / Restore session across page reloads ─────────────────────────
    function saveSession(nextIdx) {
        try {
            sessionStorage.setItem(SESSION_KEY, JSON.stringify({
                items : _items,
                index : nextIdx
            }));
        } catch (e) {}
    }

    function restoreSession() {
        try {
            var raw = sessionStorage.getItem(SESSION_KEY);
            if (!raw) return;
            sessionStorage.removeItem(SESSION_KEY);
            var data = JSON.parse(raw);
            if (!data || !Array.isArray(data.items) || data.items.length === 0) return;

            _items   = data.items;
            _idx     = typeof data.index === 'number' ? data.index : 0;
            _inBulk  = true;

            // Show the floating bar (checkboxes may have been reset by reload)
            setTimeout(function () {
                var bar   = document.getElementById('bulk-edit-bar');
                var count = document.getElementById('bulk-bar-count');
                if (bar)   bar.style.display = 'flex';
                if (count) count.textContent  = _items.length;
                setNavVisible(true);

                if (_idx < _items.length) {
                    showItem(_idx);
                } else {
                    // All items done
                    endBulk();
                }
            }, 450);
        } catch (e) {}
    }

    // ── Floating action bar ────────────────────────────────────────────────
    function injectBar() {
        if (document.getElementById('bulk-edit-bar')) return;
        var bar = document.createElement('div');
        bar.id  = 'bulk-edit-bar';
        bar.innerHTML = [
            '<div class="flex items-center gap-2">',
                '<span class="material-symbols-outlined text-primary text-[20px]">checklist</span>',
                '<span id="bulk-bar-count" class="text-sm font-bold text-slate-900 dark:text-white"></span>',
                '<span class="text-slate-400 text-xs">selected</span>',
            '</div>',
            '<div id="bulk-nav-controls" class="hidden items-center gap-2">',
                '<button id="bulk-prev-btn" type="button"',
                    'class="flex items-center gap-1 px-3 py-2 text-xs font-bold rounded-xl',
                    ' bg-slate-100 dark:bg-[#232b3d] text-slate-600 dark:text-slate-300',
                    ' hover:bg-slate-200 dark:hover:bg-[#2d3748] transition-colors">',
                    '<span class="material-symbols-outlined text-[16px]">arrow_back</span> Prev',
                '</button>',
                '<span id="bulk-counter" class="text-xs text-slate-400 min-w-[44px] text-center font-mono"></span>',
                '<button id="bulk-next-btn" type="button"',
                    'class="flex items-center gap-1 px-3 py-2 text-xs font-bold rounded-xl',
                    ' bg-slate-100 dark:bg-[#232b3d] text-slate-600 dark:text-slate-300',
                    ' hover:bg-slate-200 dark:hover:bg-[#2d3748] transition-colors">',
                    'Next <span class="material-symbols-outlined text-[16px]">arrow_forward</span>',
                '</button>',
            '</div>',
            '<div class="flex items-center gap-2">',
                '<button id="bulk-edit-btn" type="button"',
                    'class="flex items-center gap-2 px-4 py-2 bg-primary text-white text-xs font-bold',
                    ' rounded-xl hover:bg-primary/90 transition-colors shadow-lg shadow-primary/30">',
                    '<span class="material-symbols-outlined text-[16px]">edit_note</span> Edit Selected',
                '</button>',
                '<button id="bulk-deselect-btn" type="button"',
                    'class="flex items-center gap-2 px-3 py-2 bg-slate-200 dark:bg-[#232b3d]',
                    ' text-slate-600 dark:text-slate-300 text-xs font-bold rounded-xl',
                    ' hover:bg-slate-300 dark:hover:bg-[#2d3748] transition-colors">',
                    '<span class="material-symbols-outlined text-[16px]">close</span> Deselect All',
                '</button>',
            '</div>'
        ].join('');

        bar.style.cssText = [
            'display:none;',
            'position:fixed;',
            'bottom:24px;',
            'left:50%;',
            'transform:translateX(-50%);',
            'z-index:9999;',
            'border:1px solid rgba(99,102,241,.25);',
            'border-radius:16px;',
            'padding:12px 20px;',
            'box-shadow:0 8px 32px rgba(0,0,0,.22),0 0 0 2px rgba(99,102,241,.12);',
            'align-items:center;',
            'gap:16px;',
            'min-width:320px;',
            'backdrop-filter:blur(12px);',
            'background:rgba(255,255,255,.97);'
        ].join('');

        if (document.documentElement.classList.contains('dark')) {
            bar.style.background = 'rgba(26,33,48,.97)';
        }
        document.body.appendChild(bar);

        document.getElementById('bulk-edit-btn').addEventListener('click', startBulk);
        document.getElementById('bulk-deselect-btn').addEventListener('click', deselectAll);
        document.getElementById('bulk-prev-btn').addEventListener('click', function (e) {
            e.stopPropagation();
            navigate(-1);
        });
        document.getElementById('bulk-next-btn').addEventListener('click', function (e) {
            e.stopPropagation();
            navigate(1);
        });
    }

    // ── Collect JSON from checked rows ─────────────────────────────────────
    function collect() {
        _items = [];
        document.querySelectorAll('.item-checkbox:checked').forEach(function (cb) {
            var tr  = cb.closest('tr');
            var raw = tr ? tr.getAttribute('data-item') : null;
            if (raw) { try { _items.push(JSON.parse(raw)); } catch (e) {} }
        });
        return _items;
    }

    // ── Start bulk session ─────────────────────────────────────────────────
    function startBulk() {
        collect();
        if (_items.length === 0) {
            alert('No items selected, or rows are missing data-item attributes.');
            return;
        }
        _idx     = 0;
        _inBulk  = true;
        setNavVisible(true);
        showItem(_idx);
    }

    // ── Show the item at _idx ──────────────────────────────────────────────
    function showItem(index) {
        var item = _items[index];
        if (!item) return;

        // Clear dirty flag so toggleModal won't prompt
        if (typeof window.resetDirtyState === 'function') window.resetDirtyState();

        // Patch toggleModal: if modal already open, keep it open (don't toggle-close)
        var visibleModal = getVisibleModal();
        var orig         = window.toggleModal;
        if (visibleModal) {
            window.toggleModal = function (id) {
                var el = document.getElementById(id);
                if (el) el.classList.remove('hidden');
                window.toggleModal = orig;   // restore after one call
            };
        }

        if (typeof window.openEditModal === 'function') {
            window.openEditModal(item);
        }

        // Safety: restore if openEditModal didn't call toggleModal
        if (window.toggleModal !== orig) window.toggleModal = orig;

        setTimeout(function () {
            // Ensure modal visible
            var m = visibleModal || getVisibleModal();
            if (m) {
                m.classList.remove('hidden');
                if (m.style.display === 'none') m.style.display = '';
            }
            updateCounter();
        }, 50);
    }

    // ── Navigate Prev / Next ───────────────────────────────────────────────
    function navigate(dir) {
        var next = _idx + dir;
        if (next < 0 || next >= _items.length) return;

        // If form has unsaved changes → auto-save first, then continue after reload
        if (window.isFormDirty) {
            var form = getOpenForm();
            if (form) {
                // Save session so we can resume on the NEXT item after reload
                saveSession(next);
                // Reset dirty flag to avoid confirm() from toggleModal
                if (typeof window.resetDirtyState === 'function') window.resetDirtyState();
                // Click the submit button — triggers HTML5 validation + submit event
                var btn = form.querySelector('button[type=submit], input[type=submit]');
                if (btn) { btn.click(); } else { form.submit(); }
                return;  // page will reload; restoreSession() handles the rest
            }
        }

        _idx = next;
        showItem(_idx);
    }

    // ── End bulk mode ──────────────────────────────────────────────────────
    function endBulk() {
        _inBulk = false;
        setNavVisible(false);
        var bar = document.getElementById('bulk-edit-bar');
        if (bar) bar.style.display = 'none';
    }

    // ── Helpers ────────────────────────────────────────────────────────────
    function getVisibleModal() {
        var els = document.querySelectorAll('[id*="edit"][id*="odal"],[id*="Edit"][id*="odal"]');
        for (var i = 0; i < els.length; i++) {
            var el = els[i];
            if (!el.classList.contains('hidden') &&
                getComputedStyle(el).display !== 'none') {
                return el;
            }
        }
        return null;
    }

    function getOpenForm() {
        var m = getVisibleModal();
        return m ? m.querySelector('form') : null;
    }

    function setNavVisible(show) {
        var ctrl = document.getElementById('bulk-nav-controls');
        if (!ctrl) return;
        ctrl.classList.toggle('hidden', !show);
        ctrl.classList.toggle('flex', show);
    }

    function updateCounter() {
        var counter = document.getElementById('bulk-counter');
        var prev    = document.getElementById('bulk-prev-btn');
        var next    = document.getElementById('bulk-next-btn');
        if (counter) counter.textContent = (_idx + 1) + ' / ' + _items.length;
        if (prev) { prev.disabled = (_idx === 0);                      prev.style.opacity = prev.disabled ? '0.35' : '1'; }
        if (next) { next.disabled = (_idx >= _items.length - 1);       next.style.opacity = next.disabled ? '0.35' : '1'; }
    }

    // ── Checkbox state → floating bar ─────────────────────────────────────
    function onCheck() {
        var checked = document.querySelectorAll('.item-checkbox:checked');
        var bar     = document.getElementById('bulk-edit-bar');
        var count   = document.getElementById('bulk-bar-count');
        if (checked.length > 0) {
            if (bar)   bar.style.display = 'flex';
            if (count) count.textContent  = checked.length;
            if (!_inBulk) setNavVisible(false);
        } else {
            if (bar) bar.style.display = 'none';
            _inBulk = false;
            setNavVisible(false);
        }
    }

    function deselectAll() {
        document.querySelectorAll('.item-checkbox').forEach(function (cb) { cb.checked = false; });
        var h = document.querySelector('thead input[type=checkbox]');
        if (h) h.checked = false;
        _inBulk = false;
        setNavVisible(false);
        onCheck();
    }

    // ── Also handle manual "Update" clicks (user saves then clicks Next later) ──
    // If user clicks the submit button manually (not via Next), save the session
    // pointing to the NEXT item so the floating bar resumes correctly after reload.
    function attachListeners() {
        // Checkbox changes
        document.addEventListener('change', function (e) {
            if (e.target.classList.contains('item-checkbox')) onCheck();
            if (e.target.closest && e.target.closest('thead') && e.target.type === 'checkbox') {
                setTimeout(onCheck, 15);
            }
        });

        // Manual form submit while in bulk mode → save session to resume on next item
        document.addEventListener('submit', function (e) {
            if (!_inBulk) return;
            var form = e.target;
            if (!form.closest || !form.closest('.fixed.inset-0')) return;

            if (typeof window.resetDirtyState === 'function') window.resetDirtyState();

            var nextIdx = _idx + 1;
            if (nextIdx < _items.length) {
                // More items remain — save session so we can continue after reload
                saveSession(nextIdx);
            }
            // If nextIdx >= _items.length, no session saved → bulk ends after this save
        }, true);
    }

    // ── Watch tbody for AJAX-replaced rows ────────────────────────────────
    function observeTable() {
        var tbody = document.querySelector('tbody');
        if (!tbody) return;
        new MutationObserver(function () { onCheck(); })
            .observe(tbody, { childList: true, subtree: true });
    }

    // ── Boot ──────────────────────────────────────────────────────────────
    function init() {
        injectBar();
        attachListeners();
        observeTable();
        restoreSession();   // ← resumes bulk session after page reload
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
