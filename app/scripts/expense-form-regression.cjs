/* Synthetic API responses only; never writes to a real account. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const { respond, expenses } = require('./fixtures/review-data.cjs');
const base = process.env.OVEZI_REVIEW_URL || 'http://localhost:8085';
const output = path.resolve(__dirname, '../../output/lending-release');
(async () => {
  fs.mkdirSync(output, { recursive: true });
  const browser = await chromium.launch({ headless: true, executablePath: process.env.OVEZI_CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
  const context = await browser.newContext({ viewport: { width: 390, height: 844 }, reducedMotion: 'reduce' });
  await context.addInitScript(() => { sessionStorage.setItem('ovezi.auth-token', 'synthetic-expense-account'); localStorage.setItem('ovezi.onboarding-complete', 'true'); });
  const checks = [], errors = [], writes = [], categoryWrites = [], groupRequests = [];
  let categories = respond(base + '/api/v1/expense-categories').data;
  await context.route('**/api/v1/**', route => {
    const r = route.request(), url = new URL(r.url()), endpoint = url.pathname.replace(/^.*\/api\/v1/, '');
    if (endpoint.startsWith('/expense-categories')) {
      if (r.method() === 'POST') { const category = { id: 1, name: r.postDataJSON().name, archived_at: null }; categories.push(category); categoryWrites.push(r.postDataJSON()); return route.fulfill({ status: 201, json: { data: category } }); }
      if (r.method() === 'PATCH') { const change = r.postDataJSON(), category = categories.find(c => c.id === 1); if (change.name) category.name = change.name; if ('archived' in change) category.archived_at = change.archived ? '2026-10-16' : null; categoryWrites.push(change); return route.fulfill({ json: { data: category } }); }
      return route.fulfill({ json: { data: categories, recent: ['food'] } });
    }
    if (endpoint === '/groups') {
      groupRequests.push(url.search);
      const sample = respond(base + '/api/v1/groups').data[0];
      const rows = Array.from({ length: 31 }, (_, i) => ({ ...sample, id: i + 1, name: i === 0 ? sample.name : `Coffee group ${i + 1}` })).filter(g => g.name.toLowerCase().includes((url.searchParams.get('q') || '').toLowerCase()));
      const page = Number(url.searchParams.get('page') || 1);
      return route.fulfill({ json: { data: rows.slice((page - 1) * 30, page * 30), meta: { current_page: page, last_page: Math.max(1, Math.ceil(rows.length / 30)) } } });
    }
    if ((['/expenses', '/recurring-expenses'].includes(endpoint) && r.method() === 'POST') || (endpoint === '/expenses/1' && r.method() === 'PUT')) { writes.push(r.postDataJSON()); return route.fulfill({ status: 422, json: { message: 'Synthetic save captured.' } }); }
    if (endpoint === '/expenses/1') return route.fulfill({ json: { data: { ...expenses[0], category: 'school fees' } } });
    return route.fulfill({ json: respond(r.url()) });
  });
  const p = await context.newPage(); p.setDefaultTimeout(15000); p.on('pageerror', e => errors.push(e.message));
  const button = name => p.getByRole('button', { name, exact: true });
  const chooseSplit = async name => { await button(/^Split method:/).click(); await p.getByRole('radio', { name, exact: true }).click(); };
  const save = async () => { const count = writes.length; await Promise.all([p.waitForResponse(r => /\/(expenses|recurring-expenses)$/.test(r.url()) && r.request().method() === 'POST'), button('Add expense').click()]); await p.getByText('Synthetic save captured.', { exact: true }).waitFor(); assert.equal(writes.length, count + 1); return writes.at(-1); };
  try {
    await p.goto(base + '/expenses/create?groupId=1');
    await button('Group: Bali with the whole crew').waitFor();
    await p.getByLabel('Expense amount', { exact: true }).fill('100');
    await p.getByLabel('What was it for?', { exact: true }).fill('Dinner together');
    await button('Date: Today').waitFor(); await button('Category: Choose category').waitFor();
    await button('Paid by: You').waitFor(); await button('Split: Equally · 4 people').waitFor();
    assert.equal(await p.getByRole('radio').count(), 0); assert.equal(await p.getByRole('checkbox').count(), 0);
    assert.equal(await button('Repeat: One time').count(), 0);
    await p.getByLabel('Expense amount', { exact: true }).blur();
    await p.screenshot({ path: output + '/expense-simple-light.png' });
    checks.push('Main form shows amount, description, date, category and two summaries without chip rows');
    await button('Date: Today').click(); await button('Yesterday').click();
    let body = await save(); const yesterday = new Date(); yesterday.setDate(yesterday.getDate() - 1);
    assert.equal(new Date(body.occurred_at).toDateString(), yesterday.toDateString()); assert.equal(body.split_type, 'equal'); assert.equal(body.amount_minor, 10000);
    checks.push('Date is available immediately and Yesterday saves the correct local day');
    await button('Category: Choose category').click(); await p.getByLabel('Search categories').fill('School fees'); await button('Add “School fees”').click();
    await button('Category: School fees').waitFor(); body = await save(); assert.equal(body.category, 'school fees');
    await button('Category: School fees').click(); await button('Manage categories').click(); await button('Rename School fees').click();
    await p.getByLabel('Category name', { exact: true }).fill('Education'); await button('Save category name').click(); await button('Archive Education').click(); await button('Restore Education').waitFor();
    await button('Back to categories').click(); assert.equal(await p.getByRole('radio', { name: 'Education', exact: true }).count(), 0);
    await button('Manage categories').click(); await button('Restore Education').click(); await button('Archive Education').waitFor(); await button('Back to categories').click(); await p.getByRole('radio', { name: 'Education', exact: true }).click();
    checks.push('Custom categories can be created, renamed, archived and restored, with selection in the form');
    await button('Split: Equally · 4 people').click(); await p.getByRole('checkbox', { name: 'Include Ahmed Umar', exact: true }).click(); await button('Done with split expense').click();
    body = await save(); assert.equal(body.participants.find(x => x.user_id === 1).included_in_split, false); assert.equal(body.participants.find(x => x.user_id === 1).amount_paid_minor, 10000);
    checks.push('A payer can be excluded from the split while their payment is preserved');
    await button('Split: Equally · 3 people').click(); await p.getByRole('checkbox', { name: 'Include Ahmed Umar', exact: true }).click(); await chooseSplit('Exact');
    await p.getByLabel('Ahmed Umar exact split', { exact: true }).fill('40'); await button('Fill unassigned').click(); await p.getByText('All assigned', { exact: true }).waitFor();
    await p.getByLabel('Ahmed Umar exact split', { exact: true }).fill('50'); await button('Done with split expense').click(); const before = writes.length; await button('Add expense').click();
    await p.getByRole('dialog').getByText(/over the total.*Adjust the shares/).waitFor(); assert.equal(writes.length, before);
    await button('Assign remaining to Aishath Mariyam Mohamed').click(); await p.getByText('All assigned', { exact: true }).waitFor();
    await p.screenshot({ path: output + '/expense-split-light.png' }); await button('Done with split expense').click();
    await button('Paid by: You').click(); await p.getByRole('radio', { name: 'Multiple people paid', exact: true }).click();
    await p.getByLabel('Ahmed Umar paid', { exact: true }).fill('70'); await p.getByLabel('Aishath Mariyam Mohamed paid', { exact: true }).fill('30');
    await p.getByText('Payments match the total', { exact: true }).waitFor(); await button('Done with paid by').click();
    body = await save(); assert.equal(body.participants.reduce((a, b) => a + b.amount_paid_minor, 0), 10000); assert.equal(body.participants.reduce((a, b) => a + b.value, 0), 10000);
    checks.push('Exact split errors open the focused editor; remainder correction and multiple payers save correctly');
    await button('Split: Exact · 4 people').click(); await chooseSplit('Percent'); await button('Fill unassigned').click(); await button('Done with split expense').click();
    body = await save(); assert.deepEqual(body.participants.map(x => x.value), [2500, 2500, 2500, 2500]);
    checks.push('Percentage splits remain available in the focused split editor');
    await button('More details: Repeat expense').click(); await button('Repeat: One time').click(); await button('Monthly').click();
    body = await save(); assert.equal(body.frequency, 'monthly'); checks.push('Monthly recurrence remains under More details and submits with the expense');
    await button('Group: Bali with the whole crew').click(); await button('More groups').click(); await p.getByRole('radio', { name: 'Coffee group 31', exact: true }).waitFor();
    await p.getByLabel('Search groups for expense').fill('Coffee group 31'); await p.waitForResponse(r => r.url().includes('q=Coffee') && r.url().includes('/groups'));
    assert(groupRequests.some(q => q.includes('page=2'))); await button('Done with choose a group').click(); checks.push('Group picker supports pagination and server search');
    await p.emulateMedia({ colorScheme: 'dark' }); await p.setViewportSize({ width: 320, height: 740 });
    await button(/^More details:/).click(); await button(/^Date:/).scrollIntoViewIfNeeded();
    assert(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth)); await p.screenshot({ path: output + '/expense-simple-dark.png' });
    await button('Split: Percent · 4 people').click(); assert(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth)); await p.screenshot({ path: output + '/expense-split-dark.png' }); await button('Done with split expense').click();
    await p.goto(base + '/expenses/create?expenseId=1'); await button('Category: school fees').waitFor(); await button('Category: school fees').click(); await p.getByRole('radio', { name: 'No category', exact: true }).click();
    await button('Save changes').click(); await p.getByText('Synthetic save captured.', { exact: true }).waitFor(); assert.equal(writes.at(-1).category, ''); checks.push('Editing preserves custom category labels and allows clearing them');
    assert.deepEqual(errors, []); fs.writeFileSync(output + '/expense-browser-results.json', JSON.stringify({ checks, errors }, null, 2)); console.log(JSON.stringify({ checks, errors }, null, 2));
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
