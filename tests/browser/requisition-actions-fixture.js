// Local-only browser fixture. It never connects to Nawiri or moves funds.
export function startFixture() {
    const states = new Map([[21, 'otp']]);
    const requests = [];
    let message = '';
    const form = (id, action, label, extra = '') => `<form method="POST" action="/actions/${id}/${action}">
        <input name="_token" type="hidden" value="test-csrf">${extra}<button type="submit">${label}</button></form>`;
    function html() {
        const rows = Array.from({ length: 30 }, (_, i) => i + 1).filter(id => states.get(id) !== 'removed').map(id => {
            const state = states.get(id) ?? 'pending';
            let action = state === 'pending' ? form(id, 'approve', 'Approve') : form(id, 'pay', 'Pay');
            if (state === 'otp') action = `<div data-payment-poll data-url="/status/${id}" data-state='{"status":"submitted"}'>${form(id, 'authorize', 'Authorize payment', `<input type="hidden" name="reference" value="reference-${id}"><label for="otp-${id}">JamboPay OTP</label><input id="otp-${id}" name="otp" required pattern="[0-9]{6}">`)}</div>`;
            if (state === 'completed') action = 'Paid';
            return `<tr id="requisition-${id}" data-requisition-row><td>Request ${id}</td><td>${state}</td><td>${action}
                ${form(id, 'remove', 'Decline')}${form(id, 'invalid', 'Invalid submission')}${form(id, 'unavailable', 'Simulate failure')}</td></tr>`;
        }).join('');
        return `<!doctype html><html><head><title>Requisition action test</title><style>
            body {margin:0;font:16px sans-serif} header {height:60px;position:sticky;top:0;background:white}
            .hidden {display:none} [data-requisition-feedback]{position:fixed;bottom:20px;left:20px;background:white;border:1px solid;padding:12px;z-index:50}
            [data-requisition-table]{overflow-x:auto} table{width:1800px} td{height:130px;min-width:250px;border-bottom:1px solid #ccc}
            button{margin:5px} </style><script type="module" src="/actions.js"></script></head><body><header>Westport test</header>
            <div data-requisition-page><div data-requisition-feedback role="status" aria-live="polite" tabindex="-1" class="${message ? '' : 'hidden'}"><p data-action-message>${message}</p>
            <button data-requisition-refresh>Refresh status</button><button data-dismiss-feedback>Dismiss</button></div>
            <div data-requisition-content><div style="height:500px">Summary and filters</div><div data-requisition-table><table>${rows}</table></div>
            <div id="payment-update-banner" class="hidden"><button data-requisition-refresh>Refresh</button></div></div></div></body></html>`;
    }
    return Bun.serve({ hostname: '127.0.0.1', port: 0, async fetch(request) {
        const url = new URL(request.url);
        if (url.pathname === '/actions.js') return new Response(Bun.file(new URL('../../resources/js/requisition-actions.js', import.meta.url)), { headers: { 'Content-Type': 'text/javascript' } });
        if (url.pathname.startsWith('/status/')) return Response.json({ status: 'submitted' });
        if (url.pathname === '/test-state') return Response.json(requests);
        if (request.method === 'POST' && url.pathname.startsWith('/actions/')) {
            const [, , id, action] = url.pathname.split('/');
            requests.push({ id, action });
            await Bun.sleep(250);
            if (action === 'unavailable') return new Response('Unavailable', { status: 503 });
            if (action === 'approve') states.set(Number(id), 'approved');
            if (action === 'pay') states.set(Number(id), 'otp');
            if (action === 'authorize') states.set(Number(id), 'completed');
            if (action === 'remove') states.set(Number(id), 'removed');
            message = action === 'invalid' ? 'The submitted value is invalid.' : `${action} saved.`;
            return Response.redirect(new URL('/admin/requisitions?status=pending&page=2', url.origin), 303);
        }
        return new Response(html(), { headers: { 'Content-Type': 'text/html' } });
    } });
}

if (import.meta.main) {
    const server = startFixture();
    console.log(`Browser fixture: ${server.url}admin/requisitions?status=pending&page=2`);
}
