// Shared, page-independent UI behaviour (2026-10-06 UI pass):
// toasts, mobile stacked-table labels, and double-submit protection.

const TOAST_STYLES = {
    success: { ring: 'border-brand-300', bar: 'bg-brand-600', icon: '<circle cx="12" cy="12" r="9"/><path d="m9 12 2 2 4-4"/>', label: 'Success' },
    error: { ring: 'border-danger/40', bar: 'bg-danger', icon: '<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/>', label: 'Error' },
    warning: { ring: 'border-gold-300', bar: 'bg-gold-500', icon: '<path d="M12 9v4M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/>', label: 'Warning' },
    info: { ring: 'border-info/30', bar: 'bg-info', icon: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>', label: 'Information' },
};
const ICON_COLOR = { success: 'text-brand-700', error: 'text-danger', warning: 'text-gold-600', info: 'text-info' };
// Errors and warnings stay up longer - they usually need reading, not just noticing.
const TIMEOUTS = { success: 5000, info: 6000, warning: 9000, error: 12000 };

function toastRegion() {
    let region = document.getElementById('toast-region');
    if (!region) {
        region = document.createElement('div');
        region.id = 'toast-region';
        document.body.appendChild(region);
    }
    region.className = 'fixed z-[60] top-[4.75rem] lg:top-[7.75rem] inset-x-4 sm:inset-x-auto sm:right-6 sm:w-96 flex flex-col gap-2 pointer-events-none';
    region.setAttribute('aria-live', 'polite');
    region.setAttribute('aria-relevant', 'additions');
    return region;
}

export function toast(type, message, { timeout } = {}) {
    if (!message) return;
    const style = TOAST_STYLES[type] ?? TOAST_STYLES.info;
    const el = document.createElement('div');
    el.className = `toast-enter pointer-events-auto relative overflow-hidden rounded-xl border ${style.ring} bg-white shadow-lg shadow-ink/10 pl-4 pr-10 py-3 text-sm text-ink flex items-start gap-3`;
    // Errors interrupt; everything else is announced politely.
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');
    el.innerHTML = `
        <span class="absolute inset-y-0 left-0 w-1 ${style.bar}"></span>
        <svg class="shrink-0 mt-0.5 ${ICON_COLOR[type] ?? ICON_COLOR.info}" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${style.icon}</svg>
        <p class="min-w-0 break-words"><span class="sr-only">${style.label}: </span></p>
        <button type="button" class="absolute top-2 right-2 p-1.5 rounded-md text-ink-faint hover:text-ink hover:bg-panel-muted" aria-label="Dismiss notification">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>`;
    el.querySelector('p').append(document.createTextNode(message));

    const remove = () => {
        el.style.transition = 'opacity .18s, transform .18s';
        el.style.opacity = '0';
        el.style.transform = 'translateY(-4px)';
        setTimeout(() => el.remove(), 180);
    };
    el.querySelector('button').addEventListener('click', remove);

    // Pause the countdown while the pointer or keyboard focus is on it.
    let timer;
    const start = () => { timer = setTimeout(remove, timeout ?? TIMEOUTS[type] ?? 6000); };
    const stop = () => clearTimeout(timer);
    el.addEventListener('mouseenter', stop);
    el.addEventListener('mouseleave', start);
    el.addEventListener('focusin', stop);
    el.addEventListener('focusout', start);
    start();

    toastRegion().appendChild(el);
    return el;
}
window.toast = toast;

function showInitialToasts() {
    const data = document.getElementById('flash-toasts');
    if (!data) return;
    try {
        for (const [type, message] of JSON.parse(data.textContent)) toast(type, message);
    } catch (_) {
        // A malformed payload must never break the page.
    }
}

// Copy each column's header text onto its cells so the mobile stacked
// layout (CSS: table[data-stack]) can label every value.
function labelStackedTables(root = document) {
    root.querySelectorAll('table[data-stack]').forEach(table => {
        const headers = [...table.querySelectorAll('thead th')].map(th => th.dataset.label ?? th.textContent.trim());
        table.querySelectorAll('tbody tr').forEach(row => {
            let column = 0;
            [...row.children].forEach(cell => {
                if (!cell.hasAttribute('data-label') && !cell.hasAttribute('colspan')) cell.setAttribute('data-label', headers[column] ?? '');
                column += Number(cell.getAttribute('colspan') ?? 1);
            });
        });
        // Only now may CSS hide the real headings and stack the rows -
        // without this (no JS, or labels not added yet) the table stays a
        // normal table inside its own horizontal scroller.
        table.setAttribute('data-stack-ready', '');
    });
}

// Prevent double submissions on ordinary POST forms: show a spinner on the
// clicked button and disable the form's submit buttons until the next page
// loads. The Requisitions page manages its own submit lifecycle (live
// updates, payment safety) and is deliberately left alone.
document.addEventListener('submit', event => {
    const form = event.target;
    if (event.defaultPrevented || form.method.toLowerCase() !== 'post') return;
    if (form.closest('[data-requisition-page]') || form.hasAttribute('target') || form.hasAttribute('data-no-loading')) return;
    const submitter = event.submitter;
    // Disable after the browser has captured the form data, so the
    // clicked button's own name/value is still sent.
    setTimeout(() => {
        form.querySelectorAll('button[type="submit"], button:not([type])').forEach(button => { button.disabled = true; });
        if (submitter?.classList.contains('btn')) submitter.setAttribute('data-loading', '');
        form.setAttribute('aria-busy', 'true');
    }, 0);
});

// Coming back via the browser's back button restores a frozen page -
// re-enable anything the handler above disabled.
window.addEventListener('pageshow', event => {
    if (!event.persisted) return;
    document.querySelectorAll('form[aria-busy="true"]').forEach(form => {
        form.removeAttribute('aria-busy');
        form.querySelectorAll('button:disabled').forEach(button => { button.disabled = false; });
        form.querySelectorAll('[data-loading]').forEach(button => button.removeAttribute('data-loading'));
    });
});

function init() {
    showInitialToasts();
    labelStackedTables();
    // Pages that swap their own content (e.g. Requisitions' live updates)
    // get their new rows labelled too.
    new MutationObserver(mutations => {
        if (mutations.some(m => [...m.addedNodes].some(n => n.nodeType === 1 && (n.matches?.('table, tr') || n.querySelector?.('table[data-stack]'))))) labelStackedTables();
    }).observe(document.body, { childList: true, subtree: true });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
else init();
