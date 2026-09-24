const pageSelector = '[data-requisition-page]';
let busy = false;
let uncertain = false;
let pollIndex = 0;
let pollController;

function notify(message) {
    const feedback = document.querySelector('[data-requisition-feedback]');
    feedback.querySelector('[data-action-message]').textContent = message;
    feedback.classList.remove('hidden');
}

function lock(page) {
    page.setAttribute('aria-busy', 'true');
    page.querySelectorAll('form[method="POST"] button[type="submit"]').forEach(button => {
        button.disabled = true;
    });
}

function renderPage(next, submittedForm) {
    const current = document.querySelector(pageSelector);
    // Measure just before rendering, so scrolling during a slow request is respected.
    const position = { x: window.scrollX, y: window.scrollY };
    const horizontal = current.querySelector('[data-requisition-table]')?.scrollLeft ?? 0;
    const headerBottom = document.querySelector('header')?.getBoundingClientRect().bottom ?? 0;
    const anchors = [...current.querySelectorAll('[data-requisition-row]')]
        .map(row => ({ id: row.id, top: row.getBoundingClientRect().top, bottom: row.getBoundingClientRect().bottom }))
        .filter(row => row.bottom > headerBottom);
    const focusId = document.activeElement?.id;
    const focusedRowId = document.activeElement?.closest('[data-requisition-row]')?.id
        ?? (document.activeElement === document.body ? submittedForm?.closest('[data-requisition-row]')?.id : null);
    const submittedPayment = submittedForm?.closest('[data-payment-poll]');
    const drafts = [...current.querySelectorAll('input[name="otp"]')]
        .filter(input => !submittedPayment?.contains(input))
        .map(input => ({ id: input.id, value: input.value, reference: input.form.elements.reference.value }));

    // Keep the live region mounted so assistive technology announces the result.
    const feedback = current.querySelector('[data-requisition-feedback]');
    const nextFeedback = next.querySelector('[data-requisition-feedback]');
    current.querySelector('[data-requisition-content]').replaceWith(next.querySelector('[data-requisition-content]'));
    feedback.querySelector('[data-action-message]').textContent = nextFeedback.querySelector('[data-action-message]').textContent;
    feedback.classList.toggle('hidden', nextFeedback.classList.contains('hidden'));
    for (const draft of drafts) {
        const input = document.getElementById(draft.id);
        if (input?.form.elements.reference.value === draft.reference) input.value = draft.value;
    }
    const table = current.querySelector('[data-requisition-table]');
    if (table) table.scrollLeft = horizontal;
    const focusTarget = (focusId && document.getElementById(focusId))
        || (focusedRowId && document.getElementById(focusedRowId)?.querySelector('input:not([type="hidden"]):not(:disabled), button:not(:disabled), a[href]'));
    if (focusTarget) focusTarget.focus({ preventScroll: true });
    else if (focusedRowId && !feedback.classList.contains('hidden')) feedback.focus({ preventScroll: true });
    const anchor = anchors.find(row => document.getElementById(row.id));
    window.scrollTo({ left: position.x, top: anchor
        ? position.y + document.getElementById(anchor.id).getBoundingClientRect().top - anchor.top
        : position.y, behavior: 'instant' });
}

async function updatePage(form = null) {
    if (busy || (form && uncertain)) return;
    const page = document.querySelector(pageSelector);
    if (!page) return;
    const body = form ? new FormData(form) : undefined;
    busy = true;
    pollController?.abort();
    lock(page);
    notify(form ? 'Saving. Please wait.' : 'Refreshing status.');
    try {
        const response = await fetch(form?.action ?? window.location.href, {
            method: form ? 'POST' : 'GET',
            body,
            headers: { Accept: 'text/html' },
            credentials: 'same-origin',
            cache: 'no-store',
            signal: AbortSignal.timeout(90000),
        });
        if ((response.redirected && new URL(response.url).pathname !== window.location.pathname) || [401, 419].includes(response.status)) {
            throw new Error('Your session may have expired. Sign in again, then check payment status before retrying.');
        }
        const html = new DOMParser().parseFromString(await response.text(), 'text/html');
        const next = html.querySelector(pageSelector);
        if (!response.ok || !next) throw new Error('The result could not be confirmed. Refresh status before trying again.');
        renderPage(next, form);
        uncertain = false;
    } catch (error) {
        // Never retry a POST automatically: the server may already have processed it.
        uncertain = true;
        notify(error.name === 'Error' ? error.message : 'The result could not be confirmed. Refresh status before trying again.');
    } finally {
        busy = false;
        document.querySelector(pageSelector)?.removeAttribute('aria-busy');
    }
}

document.addEventListener('submit', event => {
    const form = event.target;
    if (event.defaultPrevented || !form.closest(pageSelector) || form.method.toLowerCase() !== 'post') return;
    event.preventDefault();
    void updatePage(form);
});

document.addEventListener('click', event => {
    if (event.target.closest('[data-requisition-refresh]')) void updatePage();
    if (event.target.closest('[data-dismiss-feedback]')) {
        document.querySelector('[data-requisition-feedback]')?.classList.add('hidden');
    }
});

async function poll() {
    const page = document.querySelector(pageSelector);
    if (!page) return;
    try {
        const payments = [...page.querySelectorAll('[data-payment-poll]')];
        const editing = [...page.querySelectorAll('input[name="otp"]')]
            .some(input => input.value !== '' || document.activeElement === input);
        if (busy || uncertain || editing || !payments.length) return;
        const payment = payments[pollIndex++ % payments.length];
        pollController = new AbortController();
        const timeout = window.setTimeout(() => pollController?.abort(), 65000);
        try {
            const response = await fetch(payment.dataset.url, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': payment.querySelector('[name="_token"]').value },
                signal: pollController.signal,
            });
            if (response.ok && payment.isConnected && !busy) {
                const state = await response.json();
                if (JSON.stringify(state) !== JSON.stringify(JSON.parse(payment.dataset.state))) {
                    const banner = document.getElementById('payment-update-banner');
                    banner?.classList.remove('hidden');
                    banner?.classList.add('flex');
                }
            }
        } finally {
            window.clearTimeout(timeout);
        }
    } catch (_) {
        // An unavailable status check must not submit another payment.
    } finally {
        window.setTimeout(poll, 10000);
    }
}

if (document.querySelector(pageSelector)) window.setTimeout(poll, 10000);
