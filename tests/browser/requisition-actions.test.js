import assert from 'node:assert/strict';
import { startFixture } from './requisition-actions-fixture.js';

const server = startFixture();
const url = `${server.url}admin/requisitions?status=pending&page=2`;
async function browser(...args) {
    // The first Windows daemon can inherit stdout. Do not pipe the startup command.
    const startup = args[0] === 'open';
    const process = Bun.spawn(['agent-browser', '--session', 'westport-scroll-tests', ...args], { stdout: startup ? 'ignore' : 'pipe', stderr: startup ? 'inherit' : 'pipe' });
    if (startup) { assert.equal(await process.exited, 0); return ''; }
    const [output, error, code] = await Promise.all([new Response(process.stdout).text(), new Response(process.stderr).text(), process.exited]);
    assert.equal(code, 0, error || output);
    return output.trim();
}
async function evaluate(code) { return JSON.parse(JSON.parse(await browser('eval', `JSON.stringify(${code})`))); }
async function idle() {
    await browser('eval', `new Promise((resolve,reject)=>{const start=Date.now();const check=()=>{if(!document.querySelector('[aria-busy]'))return resolve(true);if(Date.now()-start>5000)return reject(new Error('Action timed out'));setTimeout(check,20)};check()})`);
}
try {
    await browser('open', url);
    await browser('wait', '--load', 'networkidle');
    const snapshot = await browser('snapshot', '-i');
    assert.match(snapshot, /Approve/);
    assert.equal(await evaluate(`document.body.innerText.trim().length > 0`), true);
    await browser('eval', `window.testPageIdentity = 'same-document'; window.testFeedback = document.querySelector('[data-requisition-feedback]'); document.querySelector('[data-requisition-table]').scrollLeft=300; window.scrollTo(0,3000)`);
    const before = await evaluate(`({y:scrollY,x:document.querySelector('[data-requisition-table]').scrollLeft})`);
    // Preserve a separate payment's partially entered OTP in memory only.
    await browser('eval', `document.getElementById('otp-21').value='123'; document.querySelector('#requisition-20 button').focus({preventScroll:true}); document.querySelector('#requisition-20 form').requestSubmit(); document.querySelector('#requisition-20 form').requestSubmit()`);
    await idle();
    assert.equal(await evaluate(`window.testPageIdentity`), 'same-document');
    assert.equal(await evaluate(`window.testFeedback === document.querySelector('[data-requisition-feedback]')`), true);
    assert.equal(await evaluate(`document.activeElement.closest('[data-requisition-row]')?.id`), 'requisition-20');
    assert.equal(await evaluate(`location.href`), url);
    assert.equal(await evaluate(`scrollY`), before.y);
    assert.equal(await evaluate(`document.querySelector('[data-requisition-table]').scrollLeft`), before.x);
    assert.equal(await evaluate(`document.getElementById('otp-21').value`), '123');
    assert.equal((await (await fetch(`${server.url}test-state`)).json()).length, 1);
    await browser('eval', `document.querySelector('#requisition-20 form').requestSubmit()`);
    await idle();
    assert.equal(await evaluate(`!!document.getElementById('otp-20')`), true);
    assert.equal(await evaluate(`scrollY`), before.y);
    await browser('eval', `document.getElementById('otp-20').value='123456'; document.getElementById('otp-20').form.requestSubmit()`);
    await idle();
    assert.match(await evaluate(`document.getElementById('requisition-20').innerText`), /Paid/);
    assert.equal(await evaluate(`window.testPageIdentity`), 'same-document');
    // Validation errors stay visible at the current scroll position.
    await browser('eval', `document.querySelector('#requisition-20 form[action$="/invalid"]').requestSubmit()`);
    await idle();
    assert.match(await evaluate(`document.querySelector('[data-action-message]').textContent`), /invalid/);
    // An unknown POST result must not trigger another POST or enable blind retries.
    await browser('eval', `document.querySelector('#requisition-20 form[action$="/unavailable"]').requestSubmit()`);
    await idle();
    assert.equal(await evaluate(`document.querySelector('#requisition-20 button[type="submit"]').disabled`), true);
    assert.match(await evaluate(`document.querySelector('[data-action-message]').textContent`), /could not be confirmed/);
    const count = (await (await fetch(`${server.url}test-state`)).json()).length;
    await browser('eval', `document.querySelector('#requisition-20 form').requestSubmit()`);
    assert.equal((await (await fetch(`${server.url}test-state`)).json()).length, count);
    await browser('eval', `document.querySelector('[data-requisition-refresh]').click()`);
    await idle();
    assert.equal(await evaluate(`document.querySelector('#requisition-20 button[type="submit"]').disabled`), false);
    assert.equal(await evaluate(`scrollY`), before.y);
    // Respect a cancelled inline confirmation.
    await browser('eval', `const f=document.querySelector('#requisition-20 form'); f.onsubmit=()=>false; f.requestSubmit()`);
    assert.equal((await (await fetch(`${server.url}test-state`)).json()).length, count);
    // If the acted-on row leaves the filtered list, keep its next neighbour in place.
    const neighbour = await evaluate(`(()=>{const rows=[...document.querySelectorAll('[data-requisition-row]')]; const index=rows.findIndex(row=>row.getBoundingClientRect().bottom>60); const row=rows[index], next=rows[index+1]; return {removed:row.id,next:next.id,top:next.getBoundingClientRect().top}})()`);
    await browser('eval', `document.querySelector('#${neighbour.removed} form[action$="/remove"] button').focus({preventScroll:true}); document.querySelector('#${neighbour.removed} form[action$="/remove"]').requestSubmit()`);
    await idle();
    assert.equal(await evaluate(`!!document.getElementById('${neighbour.removed}')`), false);
    assert.equal(await evaluate(`document.activeElement === window.testFeedback`), true);
    assert.equal(await evaluate(`document.getElementById('${neighbour.next}').getBoundingClientRect().top`), neighbour.top);
    assert.equal(await evaluate(`window.testPageIdentity`), 'same-document');
    assert.equal(await browser('errors'), '');
    console.log('PASS: approve, pay, OTP, scroll, horizontal position, filters, drafts, duplicate submission, validation, failure recovery and cancelled confirmation.');
} finally {
    await browser('close');
    server.stop(true);
}
