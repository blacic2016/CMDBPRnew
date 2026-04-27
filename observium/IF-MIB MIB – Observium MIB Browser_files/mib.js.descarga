/**
 * MIB detail page JS — Bootstrap 5 popovers, AJAX badge tooltips,
 * permalink highlight, expandable descriptions. No jQuery.
 */
(function () {
    'use strict';

    const BASE = document.body.dataset.basePath || '/v2';

    // ── 1. Static popovers on OID name links (content is pre-rendered in HTML) ──
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => {
        new bootstrap.Popover(el, {
            trigger:   'hover focus',
            html:      true,
            sanitize:  false,
            placement: 'right',
            container: 'body',
            delay:     { show: 200, hide: 100 },
        });
    });

    // ── 2. AJAX tooltips on badge spans (index & syntax badges) ───────────────
    //
    // Badge spans carry:
    //   data-ajax-tip="mib"  data-tip-name="IF-MIB"
    //   data-ajax-tip="oid"  data-tip-name="ifIndex"  data-tip-mib="IF-MIB"
    //
    // We initialise a Bootstrap Popover with loading placeholder, then
    // replace the body content on `inserted.bs.popover` via fetch.

    const tipCache = {};

    document.querySelectorAll('[data-ajax-tip]').forEach(el => {
        const type        = el.dataset.ajaxTip;    // 'mib' | 'oid'
        const name        = el.dataset.tipName;
        const mib         = el.dataset.tipMib        || '';
        const contextMib  = el.dataset.tipContextMib || '';
        const contextDir  = el.dataset.tipContextDir || '';
        const key  = type + ':' + name + ':' + mib + ':' + contextMib;

        new bootstrap.Popover(el, {
            trigger:   'hover focus',
            html:      true,
            sanitize:  false,
            placement: 'auto',
            container: 'body',
            content:   '<span class="text-muted" style="font-size:11px">Loading…</span>',
            delay:     { show: 300, hide: 100 },
        });

        el.addEventListener('inserted.bs.popover', function () {
            // If already fetched, update immediately and return
            if (tipCache[key]) {
                setTipBody(el, tipCache[key]);
                return;
            }

            const url = type === 'mib'
                ? BASE + '/api/mibinfo.php?name=' + encodeURIComponent(name)
                : BASE + '/api/tooltip.php?syntax_name=' + encodeURIComponent(name)
                    + (mib        ? '&syntax_module=' + encodeURIComponent(mib)        : '')
                    + (contextMib ? '&context_mib='   + encodeURIComponent(contextMib) : '')
                    + (contextDir ? '&context_dir='   + encodeURIComponent(contextDir) : '');

            fetch(url)
                .then(r => r.json())
                .then(data => {
                    const html = data.content
                        || '<span class="text-danger" style="font-size:11px">'
                        +  escHtml(data.error || 'Not found') + '</span>';
                    tipCache[key] = html;
                    setTipBody(el, html);
                })
                .catch(() => setTipBody(
                    el, '<span class="text-danger" style="font-size:11px">Load failed</span>'
                ));
        });
    });

    /** Find the visible popover body for a given trigger element and update it. */
    function setTipBody(trigger, html) {
        const pop = bootstrap.Popover.getInstance(trigger);
        if (!pop) return;
        const tip = pop.tip;
        if (!tip) return;
        const body = tip.querySelector('.popover-body');
        if (body) body.innerHTML = html;
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    // ── 3. Permalink highlight ─────────────────────────────────────────────────
    if (window.location.hash) {
        const id     = window.location.hash.slice(1);
        const target = document.getElementById(id);
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            const row = target.closest('tr');
            if (row) {
                row.classList.add('permalink-highlight');
                setTimeout(() => row.classList.remove('permalink-highlight'), 3000);
            }
        }
    }

    // ── 4. Expandable descriptions ─────────────────────────────────────────────
    document.querySelectorAll('.mib-description, .notif-description').forEach(pre => {
        if (pre.scrollHeight > pre.clientHeight + 4) {
            const btn = document.createElement('button');
            btn.className   = 'btn btn-link btn-sm p-0 mt-1 d-block text-muted';
            btn.textContent = 'Show more';
            btn.addEventListener('click', () => {
                const expanded = pre.classList.toggle('expanded');
                btn.textContent = expanded ? 'Show less' : 'Show more';
            });
            pre.insertAdjacentElement('afterend', btn);
        }
    });

})();
