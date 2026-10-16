/* Local synthetic data only. No real account, email, or payment traffic. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const { respond } = require('./fixtures/review-data.cjs');
const base = process.env.OVEZI_REVIEW_URL || 'http://localhost:8085';
const output = path.resolve(__dirname, '../../output/lending-release');
const pageOf = data => ({ data, meta: { current_page: 1, last_page: 1 } });
(async () => {
  fs.mkdirSync(output, { recursive: true });
  const browser = await chromium.launch({ headless: true, executablePath: process.env.OVEZI_CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
  const context = await browser.newContext({ viewport: { width: 390, height: 844 }, reducedMotion: 'reduce' });
  await context.addInitScript(() => { sessionStorage.setItem('ovezi.auth-token', 'synthetic-home-account'); localStorage.setItem('ovezi.onboarding-complete', 'true'); });
  const checks = [], errors = [], writes = [], receipts = new Map();
  let failOverview = false, slowOverview = false, firstWrite = true;
  const person = { id: 11, name: 'Aishath Mariyam', is_owner: true, is_connected: false, balances: [{ currency_code: 'MVR', direction: 'lent', amount_minor: 500000, repaid_minor: 200000, outstanding_minor: 300000 }], last_currency_code: 'USD' };
  const loan = { id: 101, contact_id: 11, person_name: person.name, direction: 'lent', currency_code: 'MVR', amount_minor: 500000, repaid_minor: 200000, outstanding_minor: 300000, pending_minor: 10000, description: 'House deposit', occurred_on: '2026-10-01', due_on: '2026-10-10', sharing: 'shared', acknowledgement: 'acknowledged', voided_at: null, void_requested_by: null, is_creator: true, can_confirm: true };
  const counts = { repayments: 1, invitations: 1, overdue: 1, acknowledgements: 0, corrections: 0, disputed: 0, due_soon: 2 };
  const overview = () => ({ data: { balances: [{ currency_code: 'MVR', direction: 'lent', amount_minor: loan.amount_minor, repaid_minor: loan.repaid_minor, outstanding_minor: loan.outstanding_minor }, { currency_code: 'USD', direction: 'borrowed', amount_minor: 20000, repaid_minor: 5000, outstanding_minor: 15000 }], attention_counts: counts } });
  await context.route('**/api/v1/**', async route => {
    const request = route.request(), url = new URL(request.url()), endpoint = url.pathname.replace(/^.*\/api\/v1/, '');
    let response;
    if (endpoint === '/lending/overview') {
      if (slowOverview) await new Promise(resolve => setTimeout(resolve, 800));
      if (failOverview) return route.fulfill({ status: 503, json: { message: 'Temporarily unavailable' } });
      response = overview();
    } else if (endpoint === '/lending/attention') response = pageOf([loan]);
    else if (endpoint === '/lending/contacts/11/pin') { person.is_pinned = request.postDataJSON().pinned; response = { data: { is_pinned: person.is_pinned } }; }
    else if (endpoint === '/lending/contacts') response = pageOf([person]);
    else if (endpoint === '/lending/contacts/11') response = { data: person };
    else if (endpoint === '/lending/invitations') response = pageOf([{ id: 15, sender_name: 'Ahmed', loan_count: 2, expires_at: '2026-10-30' }]);
    else if (endpoint === '/lending/loans/duplicates') {
      const body = request.postDataJSON();
      const matches = [...receipts.values()].map(r => r.data).filter(loan => loan.amount_minor === body.amount_minor && loan.currency_code === body.currency_code && loan.description === body.description);
      response = { data: matches, total: matches.length };
    }
    else if (endpoint === '/lending/loans' && request.method() === 'POST') {
      const body = request.postDataJSON(), key = request.headers()['idempotency-key'];
      writes.push({ body, key });
      if (!receipts.has(key)) receipts.set(key, { data: { ...loan, id: 200 + receipts.size, direction: body.direction, amount_minor: body.amount_minor, outstanding_minor: body.amount_minor, repaid_minor: 0, pending_minor: 0, description: body.description, currency_code: body.currency_code, sharing: 'private' } });
      if (firstWrite) { firstWrite = false; return route.abort('failed'); }
      response = receipts.get(key);
    } else if (/\/lending\/loans\/\d+\/history$/.test(endpoint)) response = pageOf(endpoint.includes('/101/') ? [{ id: 1, event: 'repayment.reported', actor_name: person.name, created_at: '2026-10-15T10:00:00Z', details: {}, repayment: { id: 501, amount_minor: 10000, currency_code: 'MVR', occurred_on: '2026-10-15', status: counts.repayments ? 'pending' : 'confirmed', recorded_by: 2, confirmed_by: null } }] : []);
    else if (/\/lending\/loans\/\d+$/.test(endpoint)) response = Number(endpoint.split('/').pop()) === 101 ? { data: loan } : [...receipts.values()].find(r => r.data.id === Number(endpoint.split('/').pop()));
    else if (endpoint === '/lending/repayments/501/actions') { counts.repayments = 0; loan.repaid_minor += 10000; loan.outstanding_minor -= 10000; loan.pending_minor = 0; response = { message: 'Confirmed' }; }
    else response = respond(request.url());
    return route.fulfill({ json: response });
  });
  const p = await context.newPage(); p.setDefaultTimeout(15000); p.on('pageerror', e => errors.push(e.message));
  try {
    await p.goto(base + '/'); await p.getByRole('button', { name: 'Confirm repayments', exact: true }).waitFor();
    await p.getByText('MVR 3,000.00', { exact: true }).waitFor(); await p.getByText('USD 150.00', { exact: true }).waitFor();
    assert.deepEqual((await p.getByRole('tab').allTextContents()).map(s => s.replace(/[^A-Za-z ]/g, '').trim()), ['Home', 'Groups', 'Lending', 'Profile']);
    const headings = await p.getByRole('heading').allTextContents();
    assert(headings.indexOf('Group balances') < headings.indexOf('Recent group expenses'));
    assert(headings.indexOf('Recent group expenses') < headings.indexOf('Lending'));
    await p.getByText('Dinner at the harbour', { exact: true }).waitFor();
    checks.push('Home puts group balances and recent group expenses before lending');
    await p.screenshot({ path: output + '/home-light.png' }); checks.push('Home separates lending currencies and group balances with four destinations');
    await p.getByRole('button', { name: 'View all group expenses', exact: true }).click();
    await p.waitForURL(/expenseType=group/);
    await p.goto(base + '/');
    await p.getByRole('button', { name: 'Due soon', exact: true }).click(); await p.getByText('Outstanding loans due today or in the next 7 days.', { exact: true }).waitFor();
    await p.goto(base + '/'); checks.push('Due soon opens its upcoming loan list');
    await p.getByRole('button', { name: 'All activity', exact: true }).click(); await p.getByText('Ahmed Umar added an expense', { exact: true }).first().waitFor();
    await p.goto(base + '/'); await p.getByRole('button', { name: 'Connection invitations', exact: true }).click(); await p.getByRole('button', { name: 'Accept connection', exact: true }).waitFor(); checks.push('Home opens Activity and the invitation inbox directly');
    await p.goto(base + '/'); await p.getByRole('button', { name: 'Confirm repayments', exact: true }).click(); await p.getByRole('button', { name: person.name, exact: true }).click();
    await p.getByRole('button', { name: 'Confirm received', exact: true }).click(); await p.getByRole('button', { name: 'Confirm', exact: true }).click();
    await p.getByText('Received back: MVR 2,100.00', { exact: true }).waitFor();
    await p.getByRole('button', { name: 'Back', exact: true }).click(); await p.getByRole('button', { name: 'Back', exact: true }).click();
    await p.getByText('MVR 2,900.00', { exact: true }).waitFor(); assert.equal(await p.getByRole('button', { name: 'Confirm repayments', exact: true }).count(), 0); checks.push('confirmation refreshes Home balances and clears its attention item');
    await p.getByRole('button', { name: 'Record a loan', exact: true }).click(); await p.getByRole('button', { name: 'Add a new person', exact: true }).click();
    await p.getByLabel('Person’s name', { exact: true }).fill('Hassan'); await p.getByLabel('Email (optional)', { exact: true }).fill('hassan@example.test'); await p.getByRole('button', { name: 'Continue with this person', exact: true }).click();
    await p.getByLabel('Amount (MVR)', { exact: true }).fill('125.50'); await p.getByLabel('Description (optional, shared with the loan)', { exact: true }).fill('New person loan');
    const loanDateRow = () => p.getByRole('button', { name: /^Loan date:/ });
    await loanDateRow().click();
    await p.getByRole('button', { name: 'Yesterday', exact: true }).click();
    assert.notEqual(await loanDateRow().getAttribute('aria-label'), 'Loan date: Today');
    await loanDateRow().click();
    await p.getByRole('button', { name: 'Today', exact: true }).click();
    await p.getByRole('button', { name: 'Loan date: Today', exact: true }).waitFor();
    await loanDateRow().click();
    await p.getByLabel('Loan date', { exact: true }).fill('2026-09-28');
    await p.screenshot({ path: output + '/loan-date-calendar-light.png' });
    await p.getByRole('button', { name: 'Done with loan date', exact: true }).click();
    assert.match(await loanDateRow().getAttribute('aria-label'), /2026/);
    checks.push('visible loan date opens calendar and supports Today, Yesterday and a custom date');
    await p.screenshot({ path: output + '/quick-entry-light.png' });
    await p.getByRole('button', { name: 'Save private loan', exact: true }).click(); await p.getByText(/Network request failed|Failed to fetch|connection|network/i).filter({ visible: true }).first().waitFor();
    await p.reload(); await p.getByRole('button', { name: 'Resume draft', exact: true }).click();
    assert.equal(await p.getByLabel('Amount (MVR)', { exact: true }).inputValue(), '125.50');
    assert.equal(await p.getByLabel('Description (optional, shared with the loan)', { exact: true }).inputValue(), 'New person loan');
    await loanDateRow().click();
    assert.equal(await p.getByLabel('Loan date', { exact: true }).inputValue(), '2026-09-28');
    await p.emulateMedia({ colorScheme: 'dark' }); await p.setViewportSize({ width: 320, height: 740 });
    await p.screenshot({ path: output + '/loan-date-calendar-dark.png' });
    await p.getByRole('button', { name: 'Done with loan date', exact: true }).click();
    await loanDateRow().scrollIntoViewIfNeeded();
    await p.screenshot({ path: output + '/loan-date-form-dark.png' });
    await p.emulateMedia({ colorScheme: 'light' }); await p.setViewportSize({ width: 390, height: 844 });
    assert.equal(writes[0].body.occurred_on, '2026-09-28');
    checks.push('chosen loan date is submitted exactly and restored from the draft');
    checks.push('interrupted loan restores person, amount and description after a process reload');
    await p.getByRole('button', { name: 'Save private loan', exact: true }).click();
    await p.getByRole('heading', { name: 'A similar loan is already recorded', exact: true }).waitFor();
    await p.getByRole('button', { name: 'This is a separate loan — save it', exact: true }).click(); await p.getByText('New person loan', { exact: true }).filter({ visible: true }).waitFor();
    assert.equal(receipts.size, 1); assert.equal(writes[0].key, writes[1].key); assert.equal(writes[0].body.amount_minor, 12550); assert.equal(writes[0].body.person.name, 'Hassan'); assert.equal(writes[0].body.shared, false); checks.push('inline person and exact loan save together; interrupted save retains input and retries the same submission');
    await p.goto(base + '/lending'); await p.getByRole('button', { name: 'Record a loan', exact: true }).click(); await p.getByRole('button', { name: person.name, exact: true }).click();
    await p.getByLabel('Amount (USD)', { exact: true }).fill('50'); await p.getByRole('button', { name: 'Save private loan', exact: true }).click(); await p.getByText('Lent: USD 50.00', { exact: true }).waitFor();
    assert.equal(writes.at(-1).body.contact_id, 11); checks.push('quick entry selects an existing person and remembers their last currency');
    await p.goto(base + '/lending'); await p.getByRole('button', { name: 'Pin Aishath Mariyam', exact: true }).click(); await p.getByRole('button', { name: 'Unpin Aishath Mariyam', exact: true }).waitFor();
    await p.reload(); await p.getByRole('button', { name: 'Unpin Aishath Mariyam', exact: true }).click(); await p.getByRole('button', { name: 'Pin Aishath Mariyam', exact: true }).waitFor(); checks.push('person pin persists through reload and can be removed');
    await p.goto(base + '/lending/create?id=11'); await p.getByLabel('Amount (USD)', { exact: true }).fill('88'); await p.getByText('Draft saved on this device', { exact: true }).waitFor();
    await p.reload(); await p.getByRole('button', { name: 'Discard draft & start new', exact: true }).click(); assert.equal(await p.getByLabel('Amount (USD)', { exact: true }).inputValue(), ''); checks.push('successful saves clear drafts and explicit discard starts an empty form');
    failOverview = true; await p.goto(base + '/'); await p.getByText('Lending overview couldn’t load', { exact: true }).waitFor();
    assert.equal(await p.getByText('Nothing needs your attention right now.', { exact: true }).count(), 0); failOverview = false;
    await p.getByRole('button', { name: 'Try again', exact: true }).click(); await p.getByRole('button', { name: 'Overdue loans', exact: true }).waitFor(); checks.push('failed overview stays distinct from empty data and supports retry');
    slowOverview = true; await p.emulateMedia({ colorScheme: 'dark' }); await p.setViewportSize({ width: 320, height: 740 }); await p.goto(base + '/'); await p.getByText('Checking your records…', { exact: true }).waitFor(); await p.getByRole('button', { name: 'Overdue loans', exact: true }).waitFor();
    assert(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth)); await p.screenshot({ path: output + '/home-dark.png' }); checks.push('slow loading and 320px dark layout remain usable');
    await p.evaluate(() => {
      const text = [...document.querySelectorAll('[dir="auto"]')].map(node => ({ node, size: parseFloat(getComputedStyle(node).fontSize), line: parseFloat(getComputedStyle(node).lineHeight) }));
      for (const { node, size, line } of text) { node.style.fontSize = `${size * 1.5}px`; if (Number.isFinite(line)) node.style.lineHeight = `${line * 1.5}px`; }
    });
    assert(await p.evaluate(() => [...document.querySelectorAll('[dir="auto"]')].every(node => node.getBoundingClientRect().right <= innerWidth + 1)));
    await p.getByRole('button', { name: 'Record a loan', exact: true }).scrollIntoViewIfNeeded();
    await p.screenshot({ path: output + '/home-large-text.png' }); checks.push('150% browser text stress keeps amounts within the viewport and the main action reachable');
    assert.deepEqual(errors, []); fs.writeFileSync(output + '/home-browser-results.json', JSON.stringify({ checks, errors }, null, 2)); console.log(JSON.stringify({ checks, errors }));
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
