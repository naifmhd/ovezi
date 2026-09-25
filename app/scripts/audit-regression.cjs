/* Run against Expo web with NODE_PATH pointing to a Playwright installation.
   All API traffic is intercepted; these tests never use live financial data. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const { respond, expenses } = require('./fixtures/review-data.cjs');
const base = process.env.OVEZI_REVIEW_URL || 'http://localhost:8085';
const output = path.resolve(__dirname, '../../output/app-audit/fixed');
fs.mkdirSync(output, { recursive: true });
(async () => {
  const browser = await chromium.launch({ headless: true, ...(process.env.OVEZI_CHROME_PATH ? { executablePath: process.env.OVEZI_CHROME_PATH } : {}) });
  const evidence = [], errors = [];
  async function scenario(name, handler, check) {
    if (process.env.OVEZI_AUDIT_SCENARIO && process.env.OVEZI_AUDIT_SCENARIO !== name) return;
    const context = await browser.newContext({ viewport: { width: 390, height: 844 }, colorScheme: 'light', reducedMotion: 'reduce' });
    await context.addInitScript(() => {
      sessionStorage.setItem('ovezi.auth-token', 'audit-account-a');
      localStorage.setItem('ovezi.onboarding-complete', 'true');
    });
    await context.route('**/api/v1/**', route => handler(route) || route.fulfill({ json: respond(route.request().url()) }));
    const page = await context.newPage();
    page.on('pageerror', e => errors.push(`${name}: ${e.message}`));
    try {
      await check(page, context);
      await page.waitForTimeout(150);
      await page.screenshot({ path: `${output}/${name}.png` });
      evidence.push({ name, passed: true });
      console.log('PASS', name);
    } finally { await context.close(); }
  }
  try {
    await scenario('appearance', () => null, async (p) => {
      async function scheme(expected) {
        await p.waitForFunction(value => document.documentElement.style.colorScheme === value, expected, { timeout: 10000 });
      }
      await p.goto(base + '/profile');
      await p.getByRole('button', { name: /Appearance/ }).click();
      await p.getByRole('radio', { name: 'Auto', exact: true }).waitFor();
      await scheme('light');
      await p.getByRole('radio', { name: 'Dark', exact: true }).click();
      await scheme('dark');
      assert.equal(await p.getByRole('radio', { name: 'Dark', exact: true }).getAttribute('aria-checked'), 'true');
      assert.equal(await p.evaluate(() => localStorage.getItem('ovezi.appearance')), 'dark');
      await p.screenshot({ path: `${output}/appearance-dark.png` });
      await p.reload();
      await p.getByRole('radio', { name: 'Dark', exact: true }).waitFor();
      await scheme('dark');
      await p.getByRole('radio', { name: 'Light', exact: true }).click();
      await p.emulateMedia({ colorScheme: 'dark' });
      await scheme('light');
      await p.screenshot({ path: `${output}/appearance-light.png` });
      await p.getByRole('radio', { name: 'Auto', exact: true }).click();
      await scheme('dark');
      await p.emulateMedia({ colorScheme: 'light' });
      await scheme('light');
      await p.setViewportSize({ width: 320, height: 640 });
      assert(await p.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth));
      await p.getByRole('button', { name: 'Done', exact: true }).click();
      await p.getByText('Auto · Follow device settings', { exact: true }).waitFor();
    });
    await scenario('unavailable-balance', r => r.request().url().endsWith('/groups/1/balances') ? r.fulfill({ status: 403, json: { message: 'Balance data unavailable' } }) : null, async p => {
      await p.goto(base + '/groups/1');
      await p.getByText('Balance unavailable', { exact: true }).waitFor();
      assert.equal(await p.getByText('All settled up', { exact: true }).count(), 0);
      assert.equal(await p.getByText('0.00', { exact: true }).count(), 0);
    });
    let failAuth = true;
    await scenario('session-retry', r => failAuth && r.request().url().endsWith('/me') ? r.fulfill({ status: 503, json: { message: 'Temporarily unavailable' } }) : null, async p => {
      await p.goto(base + '/'); await p.getByText('Let’s reconnect', { exact: true }).waitFor();
      assert.equal(await p.evaluate(() => sessionStorage.getItem('ovezi.auth-token')), 'audit-account-a');
      assert.equal(await p.getByText('Bali with the whole crew', { exact: true }).count(), 0);
      failAuth = false; await p.getByRole('button', { name: 'Retry connection', exact: true }).click();
      await p.getByText('Hello, Ahmed', { exact: true }).waitFor();
    });
    await scenario('invalid-session', r => r.request().url().endsWith('/me') ? r.fulfill({ status: 401, json: { message: 'Unauthenticated' } }) : null, async p => {
      await p.goto(base + '/'); await p.getByText('Welcome back', { exact: true }).waitFor();
      assert.equal(await p.evaluate(() => sessionStorage.getItem('ovezi.auth-token')), null);
    });
    let accountB = false;
    await scenario('account-switch-isolation', r => {
      const url = r.request().url();
      if (url.endsWith('/auth/login')) {
        accountB = true;
        const user = { ...respond(base + '/api/v1/me').data, id: 99, name: 'Zara Review', email: 'zara@example.test' };
        return r.fulfill({ json: { data: { user, token: 'audit-account-b' } } });
      }
      if (r.request().method() === 'DELETE') return r.fulfill({ status: 204 });
      if (accountB) return r.fulfill({ json: respond(url, true) });
    }, async p => {
      await p.goto(base + '/'); await p.getByText('Bali with the whole crew', { exact: true }).waitFor();
      await p.getByRole('tab', { name: /Profile/ }).click(); await p.getByRole('button', { name: 'Sign out', exact: true }).click();
      await p.getByLabel('Email', { exact: true }).fill('zara@example.test'); await p.getByLabel('Password', { exact: true }).fill('fictional-password');
      await p.getByRole('button', { name: 'Sign in', exact: true }).click(); await p.getByText('Hello, Zara', { exact: true }).waitFor();
      await p.getByText('Good times start here', { exact: true }).waitFor();
      assert.equal(await p.getByText('Bali with the whole crew', { exact: true }).count(), 0);
      assert.equal(await p.getByText('1,610.00', { exact: true }).count(), 0);
    });
    let nextAccount = false, releaseOldGroups;
    await scenario('late-account-response', r => {
      const url = new URL(r.request().url());
      if (url.pathname.endsWith('/auth/login')) {
        nextAccount = true;
        return r.fulfill({ json: { data: { user: { ...respond(base + '/api/v1/me').data, id: 100, name: 'Lina Review' }, token: 'audit-account-c' } } });
      }
      if (r.request().method() === 'DELETE') return r.fulfill({ status: 204 });
      if (nextAccount) return r.fulfill({ json: respond(url.href, true) });
      if (url.pathname.endsWith('/groups')) return new Promise(resolve => { releaseOldGroups = () => r.fulfill({ json: respond(url.href) }).then(resolve); });
    }, async p => {
      await p.goto(base + '/'); await p.getByText('Loading groups…', { exact: true }).waitFor();
      await p.getByRole('tab', { name: /Profile/ }).click(); await p.getByRole('button', { name: 'Sign out', exact: true }).click();
      await p.getByLabel('Email', { exact: true }).fill('lina@example.test'); await p.getByLabel('Password', { exact: true }).fill('fictional-password');
      await p.getByRole('button', { name: 'Sign in', exact: true }).click(); await p.getByText('Hello, Lina', { exact: true }).waitFor();
      await releaseOldGroups(); await p.waitForTimeout(350);
      assert.equal(await p.getByText('Bali with the whole crew', { exact: true }).count(), 0);
      await p.getByText('Good times start here', { exact: true }).waitFor();
    });
    let settlement;
    await scenario('suggested-settlement', r => r.request().method() === 'POST' && r.request().url().endsWith('/settlements') ? (settlement = r.request().postDataJSON(), r.fulfill({ json: { data: { id: 1 } } })) : null, async p => {
      await p.goto(base + '/groups/1'); await p.getByRole('button', { name: 'Settle up', exact: true }).click();
      await p.getByText('Aishath Mariyam Mohamed pays you', { exact: true }).waitFor();
      assert.equal(await p.getByLabel('Amount · MVR', { exact: true }).inputValue(), '1000.00');
      await p.getByLabel('Amount · MVR', { exact: true }).fill('100.00');
      await p.getByRole('button', { name: 'Record payment', exact: true }).click(); await p.waitForURL('**/groups/1');
      assert.equal(settlement.from_user_id, 2); assert.equal(settlement.to_user_id, 1); assert.equal(settlement.amount_minor, 10000);
    });
    let failGroup = true;
    await scenario('expense-load-retry', r => failGroup && /\/groups\/1$/.test(r.request().url()) ? r.fulfill({ status: 403, json: { message: 'Group unavailable' } }) : null, async p => {
      await p.goto(base + '/expenses/create?groupId=1');
      await p.getByText('Expense details couldn’t load', { exact: true }).waitFor();
      await p.getByLabel('Expense amount', { exact: true }).fill('100'); await p.getByLabel('What was it for?', { exact: true }).fill('Keep my input');
      assert(await p.getByRole('button', { name: 'Add expense', exact: true }).isDisabled());
      failGroup = false; await p.getByText('Try again', { exact: true }).click();
      await p.getByText('Bali with the whole crew', { exact: true }).waitFor();
      assert.equal(await p.getByLabel('What was it for?', { exact: true }).inputValue(), 'Keep my input');
      assert.equal(await p.getByRole('button', { name: 'Add expense', exact: true }).isDisabled(), false);
    });
    let savedExpense;
    await scenario('shared-expense-choice', r => r.request().method() === 'POST' && /\/expenses$/.test(r.request().url()) ? (savedExpense = r.request().postDataJSON(), r.fulfill({ json: { data: expenses[0] } })) : null, async p => {
      await p.goto(base + '/expenses/create'); await p.getByLabel('Expense amount', { exact: true }).fill('1200'); await p.getByLabel('What was it for?', { exact: true }).fill('Dinner together');
      await p.getByRole('button', { name: 'Add expense', exact: true }).click(); await p.getByText('Choose who this expense is for.', { exact: true }).waitFor();
      assert.equal(savedExpense, undefined); await p.getByRole('button', { name: 'Bali with the whole crew', exact: true }).click();
      await p.getByText('MVR 300.00 per person', { exact: true }).waitFor();
      await p.getByRole('button', { name: 'Add expense', exact: true }).click(); await p.waitForURL('**/groups/1');
      assert.equal(savedExpense.expense_type, 'group'); assert.equal(savedExpense.split_type, 'equal'); assert.equal(savedExpense.amount_minor, 120000);
    });
    await scenario('group-actions', () => null, async p => {
      await p.goto(base + '/groups/1'); await p.getByRole('button', { name: 'Group actions', exact: true }).click();
      for (const name of ['Group settings', 'Export CSV', 'Export PDF', 'Cancel']) await p.getByRole('button', { name, exact: true }).waitFor();
      await p.getByRole('button', { name: 'Group settings', exact: true }).click(); await p.waitForURL('**/groups/1/settings');
      await p.getByLabel('Group name', { exact: true }).waitFor();
      assert.equal(await p.getByText('Take photo', { exact: true }).count(), 0);
      await p.getByRole('button', { name: 'Custom exchange rates', exact: false }).click();
      await p.getByText('Base currency', { exact: true }).waitFor();
    });
    const historyRequests = [];
    await scenario('complete-group-history', r => {
      const url = new URL(r.request().url());
      if (url.pathname.endsWith('/expenses') && r.request().method() === 'GET') {
        historyRequests.push(url.search);
        const rows = Array.from({ length: 31 }, (_, i) => ({ ...expenses[0], id: i + 1, description: `Ferry expense ${i + 1}` }));
        const page = Number(url.searchParams.get('page') || 1);
        return r.fulfill({ json: { data: rows.slice((page - 1) * 30, page * 30), meta: { current_page: page, last_page: 2, total: 31, per_page: 30 }, links: {} } });
      }
    }, async p => {
      await p.goto(base + '/groups/1'); await p.getByRole('button', { name: 'View all group expenses', exact: true }).click();
      await p.getByLabel('Search expenses', { exact: true }).fill('Ferry');
      await p.waitForTimeout(400);
      await p.getByRole('button', { name: /Filter by date/ }).click();
      await p.getByLabel('From date', { exact: true }).fill('2026-09-01');
      await p.getByLabel('Through date', { exact: true }).fill('2026-09-30');
      await p.waitForTimeout(350);
      const scrollDown = () => p.evaluate(() => { document.querySelectorAll('div').forEach(el => { if (el.scrollHeight > el.clientHeight && getComputedStyle(el).overflowY === 'auto') el.scrollTop = el.scrollHeight; }); });
      await scrollDown(); await p.waitForTimeout(400); await scrollDown(); await p.getByText('Ferry expense 31', { exact: true }).waitFor();
      assert(historyRequests.some(q => q.includes('group_id=1') && q.includes('page=2') && q.includes('q=Ferry') && q.includes('from=') && q.includes('before=')));
    });
    const activityRequests = [];
    await scenario('complete-activity-history', r => {
      const url = new URL(r.request().url());
      if (url.pathname.endsWith('/activity')) {
        activityRequests.push(url.search);
        const sample = respond(base + '/api/v1/activity').data[0];
        const rows = Array.from({ length: 51 }, (_, i) => ({ ...sample, id: i + 1, actor: { id: i + 1, name: `History actor ${i + 1}` } }));
        const page = Number(url.searchParams.get('page') || 1);
        return r.fulfill({ json: { data: rows.slice((page - 1) * 30, page * 30), meta: { current_page: page, last_page: 2, total: 51 }, links: {} } });
      }
    }, async p => {
      await p.goto(base + '/activity'); await p.getByText('History actor 1 added an expense', { exact: true }).waitFor();
      for (let i = 0; i < 3; i++) { await p.evaluate(() => { document.querySelectorAll('div').forEach(el => { if (el.scrollHeight > el.clientHeight && getComputedStyle(el).overflowY === 'auto') el.scrollTop = el.scrollHeight; }); }); await p.waitForTimeout(300); }
      await p.getByText('History actor 51 added an expense', { exact: true }).waitFor();
      assert(activityRequests.some(q => q.includes('page=2')));
    });
    await scenario('friend-actions', () => null, async p => {
      await p.goto(base + '/friends'); await p.getByText('Your friends', { exact: true }).waitFor();
      assert.equal(await p.getByLabel('Email', { exact: true }).count(), 0);
      await p.getByRole('button', { name: 'More options for Aishath Mariyam Mohamed', exact: true }).click();
      await p.getByRole('button', { name: 'Remove friend…', exact: true }).click();
      await p.getByText('Remove Aishath Mariyam Mohamed?', { exact: true }).waitFor();
      await p.getByRole('button', { name: 'Cancel', exact: true }).click();
      await p.getByText('Add expense', { exact: true }).click(); await p.waitForURL('**/expenses/create?friendId=2');
      await p.getByText('Equally between 2 people', { exact: true }).waitFor();
    });
    assert.deepEqual(errors, []);
  } finally {
    await browser.close();
    fs.writeFileSync(`${output}/regression-results.json`, JSON.stringify({ evidence, errors }, null, 2));
  }
})().catch(e => { console.error(e); process.exitCode = 1; });
