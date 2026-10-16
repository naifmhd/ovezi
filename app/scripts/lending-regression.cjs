/* Synthetic local API responses only; never contacts a live account or sends mail. */
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
  await context.addInitScript(() => { sessionStorage.setItem('ovezi.auth-token', 'synthetic-lending-account'); localStorage.setItem('ovezi.onboarding-complete', 'true'); });
  const errors = [], checks = [], writes = [];
  let duplicateMode = false, failDuplicateCheck = false;
  let person = { id: 11, name: 'Aishath Mariyam', email: null, is_owner: true, is_connected: false, invitation_status: null, disconnected_at: null, balances: [], last_currency_code: 'MVR' };
  const template = { contact_id: 11, direction: 'lent', pending_minor: 0, private_note: null, occurred_on: '2026-10-01', due_on: null, sharing: 'private', acknowledgement: 'private', voided_at: null, void_requested_by: null, is_creator: true, can_confirm: true };
  const loans = [
    { ...template, id: 101, amount_minor: 500000, repaid_minor: 200000, outstanding_minor: 300000, currency_code: 'MVR', description: 'House deposit', private_note: 'My private note' },
    { ...template, id: 102, amount_minor: 20000, repaid_minor: 5000, outstanding_minor: 15000, currency_code: 'USD', description: 'Travel cash' },
  ];
  function balance() { person.balances = ['MVR', 'USD'].map(currency_code => ({ currency_code, direction: 'lent', amount_minor: loans.filter(l => l.currency_code === currency_code).reduce((sum, l) => sum + l.amount_minor, 0), repaid_minor: loans.filter(l => l.currency_code === currency_code).reduce((sum, l) => sum + l.repaid_minor, 0), outstanding_minor: loans.filter(l => l.currency_code === currency_code).reduce((sum, l) => sum + l.outstanding_minor, 0) })); }
  await context.route('**/api/v1/**', async route => {
    const request = route.request(), url = new URL(request.url()), p = url.pathname.replace(/^.*\/api\/v1/, '');
    const writing = ['POST', 'PATCH', 'PUT', 'DELETE'].includes(request.method());
    const body = writing && request.postData() ? request.postDataJSON() : {};
    if (writing) writes.push({ path: p, body });
    let response;
    if (p === '/lending/invitations') response = pageOf([]);
    else if (p === '/lending/contacts') { balance(); response = pageOf([person]); }
    else if (p === '/lending/contacts/11') { if (writing) person = { ...person, ...body }; balance(); response = { data: person }; }
    else if (p === '/lending/loans/duplicates') {
      if (failDuplicateCheck) return route.fulfill({ status: 503, json: { message: 'Duplicate check unavailable. Try again.' } });
      response = { data: duplicateMode ? [{ ...loans[0], amount_minor: body.amount_minor }] : [], total: duplicateMode ? 1 : 0 };
    }
    else if (p === '/lending/contacts/11/balance') {
      balance(); const currency = url.searchParams.get('currency_code');
      const rows = url.searchParams.get('view') === 'repayments' ? [{ id: 501, loan_id: 101, repayment_id: 1, amount_minor: 200000, occurred_on: '2026-10-03', description: 'House deposit' }] : loans.filter(loan => loan.currency_code === currency);
      response = { ...pageOf(rows), summary: person.balances.find(b => b.currency_code === currency), person_name: person.name };
    }
    else if (p === '/lending/loans' && writing) { const loan = { ...template, ...body, id: 103, repaid_minor: 0, outstanding_minor: body.amount_minor }; loans.push(loan); response = { data: loan }; }
    else if (p === '/lending/contacts/11/loans') response = pageOf(loans);
    else if (p === '/lending/contacts/11/repayments') { const loan = loans.find(l => l.currency_code === body.currency_code); loan.repaid_minor += body.amount_minor; loan.outstanding_minor -= body.amount_minor; response = { data: [{ id: 1, status: 'confirmed' }] }; }
    else if (p === '/lending/contacts/11/share') { person.invitation_status = 'pending'; loans.filter(l => body.loan_ids.includes(l.id)).forEach(l => { l.sharing = 'invited'; l.acknowledgement = 'pending'; }); response = { message: 'Invitation sent' }; }
    else if (/\/lending\/loans\/\d+\/history$/.test(p)) response = pageOf([]);
    else if (/\/lending\/loans\/\d+$/.test(p)) response = { data: loans.find(l => l.id === Number(p.split('/').pop())) };
    else response = respond(request.url());
    await route.fulfill({ json: response });
  });
  const p = await context.newPage(); p.on('pageerror', e => errors.push(e.message)); p.setDefaultTimeout(15000);
  try {
    await p.goto(base + '/'); await p.getByText('Group balances', { exact: true }).waitFor();
    assert.deepEqual((await p.getByRole('tab').allTextContents()).map(s => s.replace(/[^A-Za-z ]/g, '').trim()), ['Home', 'Groups', 'Lending', 'Profile']);
    await p.getByRole('tab', { name: /Lending/ }).click(); await p.getByRole('button', { name: 'Open Aishath Mariyam' }).waitFor();
    const personRow = p.getByRole('button', { name: 'Open Aishath Mariyam' });
    assert.equal(await personRow.getByText('Still to receive', { exact: true }).count(), 2);
    await personRow.getByText('Lent: MVR 5,000.00', { exact: true }).waitFor();
    await personRow.getByText('Received back: MVR 2,000.00', { exact: true }).waitFor();
    await personRow.getByText('MVR 3,000.00', { exact: true }).waitFor();
    checks.push('original lending, received repayments and remaining amounts are labelled per currency');
    await p.getByRole('button', { name: 'Show lending records: Outstanding', exact: true }).click();
    await p.getByRole('button', { name: 'All people', exact: true }).click();
    await p.getByRole('button', { name: 'Show lending records: All people', exact: true }).waitFor();
    await p.getByRole('button', { name: 'To pay', exact: true }).click();
    await p.getByRole('button', { name: 'To receive', exact: true }).click();
    await p.getByLabel('Find a person', { exact: true }).fill('Aishath');
    await p.getByRole('button', { name: 'Clear find a person', exact: true }).click();
    assert.equal(await p.getByLabel('Find a person', { exact: true }).inputValue(), '');
    checks.push('separate direction switch, status menu and clearable search work');
    await p.screenshot({ path: output + '/lending-light.png' }); checks.push('four tabs and multi-currency person list');
    await p.getByRole('button', { name: 'Open Aishath Mariyam' }).click(); await p.getByText('House deposit', { exact: true }).waitFor();
    await p.screenshot({ path: output + '/person-light.png' });
    await p.getByRole('button', { name: 'Explain MVR to receive balance', exact: true }).click();
    await p.getByRole('heading', { name: 'How this balance adds up', exact: true }).waitFor();
    await p.getByRole('button', { name: 'House deposit', exact: true }).waitFor();
    assert.equal(await p.getByRole('button', { name: 'Travel cash', exact: true }).count(), 0);
    await p.screenshot({ path: output + '/balance-explanation-light.png' });
    await p.getByRole('button', { name: 'Confirmed repayments', exact: true }).click(); await p.getByText('MVR 2,000.00', { exact: true }).waitFor();
    await p.emulateMedia({ colorScheme: 'dark' }); await p.setViewportSize({ width: 320, height: 740 });
    assert(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth)); await p.screenshot({ path: output + '/balance-explanation-dark.png' });
    await p.getByRole('button', { name: 'House deposit', exact: true }).click(); await p.getByText('Loan details', { exact: true }).waitFor();
    await p.emulateMedia({ colorScheme: 'light' }); await p.setViewportSize({ width: 390, height: 844 });
    await p.goto(base + '/lending/11'); await p.getByText('House deposit', { exact: true }).waitFor();
    checks.push('balance calculation separates currencies and opens confirmed repayment loan history');
    await p.getByRole('button', { name: 'Record USD repayment', exact: true }).click(); await p.getByLabel('Repayment amount (USD)', { exact: true }).fill('50');
    await p.getByRole('button', { name: 'Review repayment', exact: true }).click(); await p.getByRole('button', { name: 'Confirm repayment record', exact: true }).click();
    await p.getByText('House deposit', { exact: true }).waitFor(); assert.equal(loans[0].outstanding_minor, 300000); assert.equal(loans[1].outstanding_minor, 10000); checks.push('USD repayment leaves MVR untouched');
    await p.getByRole('button', { name: 'Record a loan', exact: true }).click(); await p.getByLabel('Amount (MVR)', { exact: true }).fill('125.50');
    await p.getByLabel('Description (optional, shared with the loan)', { exact: true }).fill('Lunch loan');
    duplicateMode = true; failDuplicateCheck = true;
    await p.getByRole('button', { name: 'Save private loan', exact: true }).click(); await p.getByText('Duplicate check unavailable. Try again.', { exact: true }).waitFor(); assert.equal(loans.length, 2);
    failDuplicateCheck = false; await p.getByRole('button', { name: 'Save private loan', exact: true }).click();
    await p.getByRole('heading', { name: 'A similar loan is already recorded', exact: true }).waitFor(); assert.equal(loans.length, 2);
    await p.getByRole('heading', { name: 'A similar loan is already recorded', exact: true }).scrollIntoViewIfNeeded();
    await p.screenshot({ path: output + '/duplicate-warning-light.png' });
    await p.getByRole('button', { name: 'Review my entry', exact: true }).click();
    await p.getByRole('button', { name: 'Save private loan', exact: true }).click();
    await p.getByLabel('Amount (MVR)', { exact: true }).fill('126.50');
    await p.getByRole('button', { name: 'This is a separate loan — save it', exact: true }).click();
    await p.getByRole('button', { name: 'This is a separate loan — save it', exact: true }).waitFor(); assert.equal(loans.length, 2);
    await p.getByLabel('Amount (MVR)', { exact: true }).fill('125.50');
    await p.getByRole('button', { name: 'Review my entry', exact: true }).click();
    await p.getByRole('button', { name: 'Save private loan', exact: true }).click();
    await p.getByRole('button', { name: 'This is a separate loan — save it', exact: true }).click();
    checks.push('duplicate-check failures and warnings preserve entry; only explicit confirmation adds a similar loan');
    await p.getByText('Lunch loan', { exact: true }).filter({ visible: true }).waitFor(); assert.equal(loans[2].amount_minor, 12550); assert.equal(loans[2].shared, false); checks.push('private loan records exact minor units');
    await p.goto(base + '/lending/person?id=11'); await p.getByLabel('Email (optional)', { exact: true }).fill('aishath@example.test'); await p.getByRole('button', { name: 'Save person', exact: true }).click();
    await p.getByText('House deposit', { exact: true }).waitFor(); assert(!writes.some(w => w.path.endsWith('/share'))); checks.push('email alone does not share');
    await p.getByRole('button', { name: 'Connect & share', exact: true }).click(); await p.getByRole('checkbox').filter({ hasText: 'Travel cash' }).click(); await p.getByRole('button', { name: 'Review 1 selected loan', exact: true }).click();
    assert.equal(await p.getByRole('checkbox').count(), 1); assert.equal(await p.getByText('My private note', { exact: true }).count(), 0); await p.screenshot({ path: output + '/sharing-preview.png' });
    await p.getByRole('button', { name: 'Send invitation & share selected loans', exact: true }).click(); await p.getByText('House deposit', { exact: true }).waitFor(); assert.deepEqual(writes.find(w => w.path.endsWith('/share')).body.loan_ids, [102]); checks.push('preview shares only the selected loan');
    await p.emulateMedia({ colorScheme: 'dark' }); await p.setViewportSize({ width: 320, height: 740 }); await p.goto(base + '/lending'); await p.getByRole('button', { name: 'Open Aishath Mariyam' }).waitFor();
    assert(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth)); await p.screenshot({ path: output + '/lending-dark.png' }); checks.push('320px dark layout without overflow');
    loans[0].direction = 'borrowed'; loans[0].pending_minor = 10000;
    await p.goto(base + '/lending/loan?id=101');
    await p.getByText('Still to pay', { exact: true }).waitFor();
    await p.getByText('Borrowed: MVR 5,000.00', { exact: true }).waitFor();
    await p.getByText('Paid back: MVR 2,000.00', { exact: true }).waitFor();
    await p.getByText('Reported, awaiting receipt confirmation', { exact: true }).waitFor();
    checks.push('borrowed loan labels and pending repayments remain distinct');
    loans[0].voided_at = '2026-10-15'; loans[0].pending_minor = 0;
    await p.reload(); await p.getByText('Original amount · Voided', { exact: true }).waitFor();
    assert.equal(await p.getByText('Still to pay', { exact: true }).count(), 0);
    checks.push('voided loans do not appear repaid');
    assert.deepEqual(errors, []); fs.writeFileSync(output + '/browser-results.json', JSON.stringify({ checks, errors }, null, 2)); console.log(JSON.stringify({ checks, errors }));
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
