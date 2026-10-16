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
    await scenario('navigation-guide', () => null, async p => {
      await p.goto(base + '/');
      await p.getByRole('button', { name: 'See what’s changed', exact: true }).click();
      for (const name of ['Home', 'Groups', 'Lending', 'Profile', 'Open previous records', 'View all activity']) await p.getByRole('button', { name, exact: true }).waitFor();
      await p.screenshot({ path: `${output}/navigation-guide-light.png`, fullPage: true });
      await p.emulateMedia({ colorScheme: 'dark' }); await p.setViewportSize({ width: 320, height: 740 });
      assert(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
      await p.screenshot({ path: `${output}/navigation-guide-dark.png`, fullPage: true });
      await p.getByRole('button', { name: 'Open previous records', exact: true }).click(); await p.getByText('Previous records', { exact: true }).waitFor();
      await p.goto(base + '/'); await p.getByRole('button', { name: 'Record a loan', exact: true }).waitFor();
      assert.equal(await p.getByRole('button', { name: 'See what’s changed', exact: true }).count(), 0);
      await p.goto(base + '/profile'); await p.getByRole('button', { name: /Find your way around/ }).click();
      await p.getByRole('button', { name: 'View all activity', exact: true }).click(); await p.getByRole('heading', { name: 'All activity', exact: true }).waitFor();
    });
    await scenario('dismiss-navigation-guide', () => null, async p => {
      await p.goto(base + '/'); await p.getByRole('button', { name: 'Dismiss introduction', exact: true }).click();
      await p.reload(); await p.getByRole('button', { name: 'Record a loan', exact: true }).waitFor();
      assert.equal(await p.getByRole('button', { name: 'Dismiss introduction', exact: true }).count(), 0);
    });
    let archivedGroup = false, archiveAttempts = 0;
    await scenario('settled-group-archive', r => {
      const url = new URL(r.request().url());
      const group = { ...respond(base + '/api/v1/groups/1').data, is_archived: archivedGroup };
      if (url.pathname.endsWith('/groups/1/archive')) {
        archiveAttempts++;
        if (archiveAttempts === 1) return r.fulfill({ status: 503, json: { message: 'Could not archive. Try again.' } });
        archivedGroup = true; return r.fulfill({ json: { data: { ...group, is_archived: true } } });
      }
      if (url.pathname.endsWith('/groups/1')) return r.fulfill({ json: { data: group } });
      if (url.pathname.endsWith('/groups/1/balances')) return r.fulfill({ json: respond(r.request().url(), true) });
    }, async p => {
      await p.goto(base + '/groups/1'); await p.getByText('Everyone is settled', { exact: true }).waitFor();
      await p.screenshot({ path: `${output}/settled-group-light.png` });
      await p.getByRole('button', { name: 'Archive settled group', exact: true }).click();
      await p.getByRole('button', { name: 'Keep group active', exact: true }).click(); assert.equal(archiveAttempts, 0);
      await p.getByRole('button', { name: 'Archive settled group', exact: true }).click();
      await p.getByRole('button', { name: 'Confirm archive', exact: true }).click(); await p.getByText('Could not archive. Try again.', { exact: true }).waitFor();
      await p.getByRole('button', { name: 'Confirm archive', exact: true }).click(); await p.getByText('Group archived', { exact: true }).waitFor();
      assert.equal(archiveAttempts, 2); assert.equal(await p.getByRole('button', { name: 'Add expense', exact: true }).count(), 0);
      await p.getByText('Dinner at the harbour', { exact: true }).waitFor();
    });
    await scenario('group-completion-safety', r => {
      if (r.request().url().endsWith('/groups/1/balances')) {
        const data = respond(r.request().url()).data;
        return r.fulfill({ json: { data: { ...data, members: data.members.map((m, i) => ({ ...m, balance_minor: [0, 100, -100, 0][i] })), suggested_settlements: [] } } });
      }
    }, async p => {
      await p.goto(base + '/groups/1'); await p.getByText('Your balance is settled', { exact: true }).waitFor();
      assert.equal(await p.getByText('Everyone is settled', { exact: true }).count(), 0);
      assert.equal(await p.getByRole('button', { name: 'Archive settled group', exact: true }).count(), 0);
    });
    const groupRequests = [];
    await scenario('find-groups', r => {
      const url = new URL(r.request().url());
      if (!url.pathname.endsWith('/groups')) return null;
      groupRequests.push(url.search);
      const sample = respond(base + '/api/v1/groups').data[0];
      const archived = url.searchParams.get('status') === 'archived';
      const rows = Array.from({ length: 101 }, (_, i) => ({ ...sample, id: i + 1, name: `Coffee group ${i + 1}`, is_archived: archived }));
      const q = url.searchParams.get('q') || '';
      const matches = rows.filter(g => g.name.toLowerCase().includes(q.toLowerCase()));
      const page = Number(url.searchParams.get('page') || 1);
      return r.fulfill({ json: { data: matches.slice((page - 1) * 30, page * 30), meta: { current_page: page, last_page: Math.max(1, Math.ceil(matches.length / 30)), total: matches.length, per_page: 30 }, links: {} } });
    }, async p => {
      await p.goto(base + '/groups');
      await p.getByText('Coffee group 1', { exact: true }).waitFor();
      assert(await p.getByText(/^Coffee group \d+$/).count() < 100);
      await p.getByLabel('Find a group', { exact: true }).fill('Coffee group 101');
      await p.getByText('Coffee group 101', { exact: true }).waitFor();
      assert.equal(await p.getByText('Coffee group 1', { exact: true }).count(), 0);
      await p.getByRole('button', { name: 'Show groups: Active', exact: true }).click();
      await Promise.all([
        p.waitForResponse(r => r.url().includes('status=archived')),
        p.getByRole('button', { name: 'Archived', exact: true }).click(),
      ]);
      await p.getByText('Coffee group 101', { exact: true }).waitFor();
      assert(groupRequests.some(q => q.includes('status=archived') && q.includes('q=Coffee')));
      await p.getByLabel('Find a group', { exact: true }).fill('Missing group');
      await p.getByText('No matching groups', { exact: true }).waitFor();
      await p.getByLabel('Find a group', { exact: true }).fill('');
      await p.getByText('Coffee group 1', { exact: true }).waitFor();
      for (let i = 0; i < 10 && await p.getByText('Coffee group 101', { exact: true }).count() === 0; i++) {
        await p.evaluate(() => document.querySelectorAll('div').forEach(el => { if (el.scrollHeight > el.clientHeight && getComputedStyle(el).overflowY === 'auto') el.scrollTop = el.scrollHeight; }));
        await p.waitForTimeout(250);
      }
      await p.getByText('Coffee group 101', { exact: true }).waitFor();
      assert(groupRequests.some(q => q.includes('page=4')));
    });
    await scenario('group-recognition', () => null, async p => {
      await p.goto(base + '/groups');
      await p.getByText('Friday coffee club', { exact: true }).waitFor();
      const avatarColors = await p.getByText(/^(BW|HA|FC)$/).evaluateAll(nodes => nodes.map(node => getComputedStyle(node.parentElement).backgroundColor));
      assert.equal(new Set(avatarColors).size, 3);
      await p.screenshot({ path: `${output}/groups-light.png` });
      await p.emulateMedia({ colorScheme: 'dark' });
      await p.waitForFunction(() => document.documentElement.style.colorScheme === 'dark');
      await p.setViewportSize({ width: 320, height: 700 });
      assert(await p.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth));
      await p.screenshot({ path: `${output}/groups-dark.png` });
      await p.goto(base + '/search');
      await p.getByLabel('Search Ovezi', { exact: true }).fill('Dinner');
      await p.getByText('Dinner at the harbour', { exact: true }).waitFor();
      assert(await p.getByText('Bali with the whole crew', { exact: true }).count() >= 5);
      assert(await p.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth));
      await p.screenshot({ path: `${output}/search-groups-dark.png` });
      await p.goto(base + '/groups');
      await p.getByRole('button', { name: 'Activity', exact: true }).click();
      await p.getByText('Ahmed Umar added an expense', { exact: true }).first().waitFor();
      assert.equal(await p.getByText('Bali with the whole crew', { exact: true }).filter({ visible: true }).count(), 4);
      await p.screenshot({ path: `${output}/activity-groups-dark.png` });
      await p.goto(base + '/expenses/create');
      await p.getByRole('button', { name: 'Bali with the whole crew', exact: true }).waitFor();
    });
    await scenario('unified-activity', r => {
      const url = new URL(r.request().url());
      if (url.pathname.endsWith('/activity')) {
        const group = { ...respond(base + '/api/v1/activity').data[0], timeline_id: 'activity:1', source: 'groups' };
        const loan = { id: 1, timeline_id: 'lending:1', source: 'lending', group_id: null, person: { id: 11, name: 'Aishath Mariyam' }, actor: { id: 1, name: 'Ahmed Umar' }, subject: { type: 'lending_loan', id: 77 }, event: 'loan.created', metadata: { direction: 'lent', amount_minor: 500000, currency_code: 'MVR', description: 'House deposit' }, created_at: '2026-10-15T10:00:00Z' };
        const repayment = { ...loan, id: 2, timeline_id: 'lending:2', event: 'repayment.reported', metadata: { ...loan.metadata, amount_minor: 10000 } };
        const source = url.searchParams.get('source') || 'all';
        const rows = [repayment, loan, group].filter(row => (source === 'all' || row.source === source) && (!url.searchParams.get('q') || (row.metadata.description || '').toLowerCase().includes(url.searchParams.get('q').toLowerCase())) && (!url.searchParams.get('before') || row.created_at < url.searchParams.get('before')));
        return r.fulfill({ json: { data: rows, meta: { current_page: 1, last_page: 1, total: rows.length } } });
      }
      if (url.pathname.endsWith('/lending/loans/77')) return r.fulfill({ json: { data: { id: 77, contact_id: 11, direction: 'lent', amount_minor: 500000, repaid_minor: 0, outstanding_minor: 500000, pending_minor: 10000, currency_code: 'MVR', description: 'House deposit', occurred_on: '2026-10-01', sharing: 'shared', acknowledgement: 'acknowledged', is_creator: true, can_confirm: true } } });
    }, async p => {
      await p.goto(base + '/activity');
      await p.getByRole('heading', { name: 'All activity', exact: true }).waitFor();
      await p.getByText('Ahmed Umar recorded this loan', { exact: true }).waitFor();
      await p.getByText('Ahmed Umar added an expense', { exact: true }).waitFor();
      await p.getByText('Ahmed Umar reported a repayment', { exact: true }).waitFor();
      await p.getByLabel('Search activity', { exact: true }).fill('House');
      await p.getByText('Ahmed Umar added an expense', { exact: true }).waitFor({ state: 'hidden' });
      await p.getByRole('button', { name: 'Filter by date', exact: true }).click(); await p.getByLabel('To date', { exact: true }).fill('2026-10-01');
      await p.getByText('No matching activity', { exact: true }).waitFor();
      await p.getByRole('button', { name: 'Clear filters', exact: true }).click(); await p.getByText('Ahmed Umar added an expense', { exact: true }).waitFor();
      await p.screenshot({ path: `${output}/all-activity-light.png` });
      await p.getByRole('button', { name: 'Activity filter: Everything', exact: true }).click();
      await p.getByRole('button', { name: 'Loans & repayments', exact: true }).click();
      await p.getByText('Ahmed Umar recorded this loan', { exact: true }).waitFor();
      assert.equal(await p.getByText('Ahmed Umar added an expense', { exact: true }).count(), 0);
      await p.emulateMedia({ colorScheme: 'dark' }); await p.setViewportSize({ width: 320, height: 740 });
      assert(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
      await p.screenshot({ path: `${output}/all-activity-dark.png` });
      await p.getByRole('button').filter({ hasText: 'Ahmed Umar recorded this loan' }).click();
      await p.getByText('Loan details', { exact: true }).waitFor(); await p.getByText('Still to receive', { exact: true }).waitFor();
      await p.getByRole('button', { name: 'Back', exact: true }).click();
      await p.getByRole('button', { name: 'Activity filter: Loans & repayments', exact: true }).click();
      await p.getByRole('button', { name: 'Personal & previous 1-on-1', exact: true }).click();
      await p.getByText('No activity here yet', { exact: true }).waitFor();
    });
    let searchRequests = [], cancelledSearch = false;
    await scenario('search-cancellation', r => {
      const url = new URL(r.request().url());
      if (!url.pathname.endsWith('/search')) return null;
      searchRequests.push(url.searchParams.get('q'));
      if (url.searchParams.get('q') === 'Slow') return new Promise(resolve => setTimeout(() => resolve(r.fulfill({ json: respond(url.href) }).catch(() => {})), 1500));
      return null;
    }, async p => {
      p.on('requestfailed', r => { if (r.url().includes('/search?q=Slow')) cancelledSearch = true; });
      await p.goto(base + '/search');
      const input = p.getByLabel('Search Ovezi', { exact: true });
      await input.fill('a'); await input.press('Enter'); await p.waitForTimeout(350);
      assert.deepEqual(searchRequests, []);
      await input.fill('Slow');
      await p.waitForRequest(r => r.url().includes('/search?q=Slow'));
      await input.fill('Dinner');
      await p.getByText('Dinner at the harbour', { exact: true }).waitFor();
      assert.equal(cancelledSearch, true);
      await input.fill('');
      await p.getByText('Find anything quickly', { exact: true }).waitFor();
      assert.equal(await p.getByText('Dinner at the harbour', { exact: true }).count(), 0);
    });
    let notificationPreferences = { expense_created: true, payment_received: true, settle_up_reminders: true, groups: [] };
    await scenario('notifications', r => {
      if (!r.request().url().endsWith('/notification-preferences')) return null;
      if (r.request().method() === 'PATCH') notificationPreferences = { ...notificationPreferences, ...r.request().postDataJSON() };
      return r.fulfill({ json: { data: notificationPreferences } });
    }, async p => {
      await p.goto(base + '/profile');
      await p.getByRole('button', { name: /Notifications/ }).click();
      await p.getByText('Available in the iOS and Android apps', { exact: true }).waitFor();
      assert.equal(await p.getByRole('switch', { name: 'Enable notifications', exact: true }).isDisabled(), true);
      const expenses = p.getByRole('switch', { name: 'New expenses', exact: true });
      await expenses.click();
      await p.waitForFunction(() => document.querySelector('[aria-label="New expenses"]').checked === false);
      await p.reload();
      await p.getByText('Available in the iOS and Android apps', { exact: true }).waitFor();
      assert.equal(await expenses.isChecked(), false);
      await p.screenshot({ path: `${output}/notifications-light.png` });
      await p.emulateMedia({ colorScheme: 'dark' });
      await p.waitForFunction(() => document.documentElement.style.colorScheme === 'dark');
      await p.setViewportSize({ width: 320, height: 700 });
      assert(await p.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth));
      await p.screenshot({ path: `${output}/notifications-dark.png` });
      await p.getByRole('button', { name: '‹ Back', exact: true }).click();
      await p.getByText('Enable or disable alerts and manage permissions', { exact: true }).waitFor();
    });
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
      await p.getByText('Group balances', { exact: true }).waitFor();
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
      await p.goto(base + '/groups'); await p.getByText('Bali with the whole crew', { exact: true }).waitFor();
      await p.getByRole('tab', { name: /Profile/ }).click(); await p.getByRole('button', { name: 'Sign out', exact: true }).click();
      await p.getByLabel('Email', { exact: true }).fill('zara@example.test'); await p.getByLabel('Password', { exact: true }).fill('fictional-password');
      await p.getByRole('button', { name: 'Sign in', exact: true }).click(); await p.getByRole('tab', { name: /Groups/ }).click(); await p.getByText('Bring your people together', { exact: true }).waitFor();
      await p.getByText('Bring your people together', { exact: true }).waitFor();
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
      await p.goto(base + '/groups'); await p.getByText('Loading groups…', { exact: true }).waitFor();
      await p.getByRole('tab', { name: /Profile/ }).click(); await p.getByRole('button', { name: 'Sign out', exact: true }).click();
      await p.getByLabel('Email', { exact: true }).fill('lina@example.test'); await p.getByLabel('Password', { exact: true }).fill('fictional-password');
      await p.getByRole('button', { name: 'Sign in', exact: true }).click(); await p.getByRole('tab', { name: /Groups/ }).click(); await p.getByText('Bring your people together', { exact: true }).waitFor();
      await releaseOldGroups(); await p.waitForTimeout(350);
      assert.equal(await p.getByText('Bali with the whole crew', { exact: true }).count(), 0);
      await p.getByText('Bring your people together', { exact: true }).waitFor();
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
      await p.getByRole('button', { name: 'Add expense', exact: true }).click(); await p.getByText('Choose a group for this expense.', { exact: true }).waitFor();
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
      await p.getByText('Create group', { exact: true }).click(); await p.waitForURL('**/groups/create?name=*');
      assert.equal(await p.getByLabel('Group name', { exact: true }).inputValue(), 'With Aishath Mariyam Mohamed');
    });
    assert.deepEqual(errors, []);
  } finally {
    await browser.close();
    fs.writeFileSync(`${output}/regression-results.json`, JSON.stringify({ evidence, errors }, null, 2));
  }
})().catch(e => { console.error(e); process.exitCode = 1; });
