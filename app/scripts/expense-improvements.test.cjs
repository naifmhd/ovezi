const assert = require('node:assert/strict');
const fs = require('node:fs');
const { chromium } = require('playwright');
const { respond } = require('./fixtures/review-data.cjs');
const base = process.env.OVEZI_REVIEW_URL || 'http://localhost:8085';
const output = '/tmp/ovezi-expense-review';
fs.mkdirSync(output, { recursive: true });
(async () => {
  const browser = await chromium.launch({ headless: true, ...(process.env.OVEZI_CHROME_PATH ? { executablePath: process.env.OVEZI_CHROME_PATH } : {}) });
  const errors = [];
  try {
    if (!process.env.OVEZI_RECURRING_ONLY) {
    const auth = await browser.newContext({ viewport: { width: 390, height: 844 } });
    await auth.addInitScript(() => localStorage.setItem('ovezi.onboarding-complete', 'true'));
    const signup = await auth.newPage();
    signup.on('pageerror', e => errors.push(e.message));
    let registrations = 0;
    await auth.route('**/api/v1/**', route => {
      if (route.request().url().endsWith('/auth/register')) {
        registrations++;
        return route.fulfill({ status: 422, json: { message: 'The email has already been taken.', errors: { email: ['The email has already been taken.'] } } });
      }
      return route.fulfill({ json: respond(route.request().url()) });
    });
    await signup.goto(base + '/sign-up');
    await signup.getByRole('button', { name: 'Create account', exact: true }).click();
    await signup.getByText('Enter your name to create your account.', { exact: true }).waitFor();
    assert.equal(registrations, 0);
    await signup.getByLabel('Name', { exact: true }).fill('New user');
    await signup.getByLabel('Email', { exact: true }).fill('new@example.test');
    await signup.getByLabel('Password', { exact: true }).fill('password123');
    await signup.getByLabel('Confirm password', { exact: true }).fill('password456');
    await signup.getByRole('button', { name: 'Create account', exact: true }).click();
    await signup.getByText('Your passwords do not match. Check both password fields.', { exact: true }).waitFor();
    assert.equal(registrations, 0);
    await signup.screenshot({ path: output + '/signup-feedback.png' });
    await signup.getByLabel('Confirm password', { exact: true }).fill('password123');
    await signup.getByRole('button', { name: 'Create account', exact: true }).click();
    await signup.getByText('The email has already been taken.', { exact: true }).waitFor();
    assert.equal(registrations, 1);
    await auth.close();
    console.log('PASS signup provides validation feedback and submits valid forms');
    }

    const context = await browser.newContext({ viewport: { width: 390, height: 844 }, reducedMotion: 'reduce' });
    await context.addInitScript(() => {
      sessionStorage.setItem('ovezi.auth-token', 'test-session');
      localStorage.setItem('ovezi.onboarding-complete', 'true');
    });
    let submitted;
    let submittedSchedule;
    const schedule = {
      id: 1, expense_type: 'direct', group_id: null, payer: { user_id: 1, placeholder_id: null, name: 'Ahmed Umar' },
      amount_minor: 10000, currency_code: 'MVR', description: 'Weekly dinner', category: 'food', split_type: 'equal',
      frequency: 'weekly', start_on: '2026-10-01', ends_on: null, can_manage: true, status: 'active',
      splits: [{ id: 1, user_id: 1, name: 'Ahmed Umar', amount_paid_minor: 7000, split_value: null, included_in_split: true },
        { id: 2, user_id: 2, name: 'Aishath Mariyam Mohamed', amount_paid_minor: 3000, split_value: null, included_in_split: true }],
    };
    await context.route('**/api/v1/**', route => {
      if (route.request().url().endsWith('/recurring-expenses/1')) {
        if (route.request().method() === 'PATCH') {
          submittedSchedule = route.request().postDataJSON();
          return route.fulfill({ status: 422, json: { message: 'Schedule update captured.' } });
        }
        return route.fulfill({ json: { data: schedule } });
      }
      if (route.request().method() === 'POST' && route.request().url().endsWith('/expenses')) {
        submitted = route.request().postDataJSON();
        return route.fulfill({ status: 422, json: { message: 'Test save captured; no real data changed.' } });
      }
      return route.fulfill({ json: respond(route.request().url()) });
    });
    const p = await context.newPage();
    p.on('pageerror', e => errors.push(e.message));
    if (!process.env.OVEZI_RECURRING_ONLY) {
    await p.goto(base + '/expenses/create?friendId=2');
    await p.getByLabel('Expense amount', { exact: true }).fill('100');
    await p.getByLabel('What was it for?', { exact: true }).fill('Dinner together');
    await p.getByText('Category (optional)', { exact: true }).waitFor();
    await p.screenshot({ path: output + '/category-visible.png' });
    await p.getByRole('button', { name: /Paid by.*Split/ }).click();
    const own = p.getByRole('checkbox', { name: 'Include Ahmed Umar', exact: true });
    await own.click();
    assert.equal(await own.isChecked(), false);
    await p.getByRole('button', { name: 'Add expense', exact: true }).click();
    await p.getByText('Test save captured; no real data changed.', { exact: true }).waitFor();
    assert.equal(submitted.participants.find(x => x.user_id === 1).included_in_split, false);
    assert.equal(submitted.participants.find(x => x.user_id === 1).amount_paid_minor, 10000);
    assert.equal(submitted.participants.find(x => x.user_id === 2).included_in_split, true);
    console.log('PASS one-to-one expense can be entirely for the friend');

    await own.click();
    await p.getByRole('button', { name: 'Exact', exact: true }).click();
    await p.getByLabel('Ahmed Umar exact split', { exact: true }).fill('30');
    await p.getByText(/70\.00 remaining to assign/).waitFor();
    await p.getByRole('button', { name: 'Fill unassigned', exact: true }).click();
    assert.equal(await p.getByLabel('Aishath Mariyam Mohamed exact split', { exact: true }).inputValue(), '70.00');
    await p.getByText('All assigned', { exact: true }).waitFor();
    await p.getByLabel('Ahmed Umar exact split', { exact: true }).fill('40');
    await p.getByText(/10\.00 over the total/).waitFor();
    await p.getByRole('button', { name: 'Assign remaining to Aishath Mariyam Mohamed', exact: true }).click();
    assert.equal(await p.getByLabel('Aishath Mariyam Mohamed exact split', { exact: true }).inputValue(), '60.00');
    await p.getByRole('button', { name: 'Multiple people', exact: true }).click();
    await p.getByLabel('Ahmed Umar paid', { exact: true }).fill('70');
    await p.getByLabel('Aishath Mariyam Mohamed paid', { exact: true }).fill('30');
    await p.getByText('Payments match the total', { exact: true }).waitFor();
    await p.getByRole('button', { name: 'Add expense', exact: true }).click();
    await p.getByText('Test save captured; no real data changed.', { exact: true }).waitFor();
    assert.deepEqual(submitted.participants.map(x => x.amount_paid_minor), [7000, 3000]);
    assert.deepEqual(submitted.participants.map(x => x.value), [4000, 6000]);
    await p.getByLabel('Ahmed Umar paid', { exact: true }).scrollIntoViewIfNeeded();
    await p.screenshot({ path: output + '/multi-payer-light.png' });
    await p.emulateMedia({ colorScheme: 'dark' });
    await p.setViewportSize({ width: 320, height: 740 });
    await p.getByLabel('Ahmed Umar exact split', { exact: true }).scrollIntoViewIfNeeded();
    assert(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
    await p.screenshot({ path: output + '/exact-split-dark-small.png' });
    console.log('PASS exact remainder, fill unassigned, shared payments, and small-screen layout');

    await p.goto(base + '/friends');
    await p.getByText(/You owe.*Settle up/).waitFor();
    await p.getByRole('button', { name: 'Expenses', exact: true }).click();
    await p.waitForURL(/friendId=2/);
    await p.getByText('1-on-1 expenses', { exact: true }).waitFor();
    console.log('PASS friend balances lead to settlement and filtered expenses');
    await p.goto(base + '/');
    await p.getByText('1-on-1 balances', { exact: true }).waitFor();
    await p.screenshot({ path: output + '/home-balances.png' });
    }
    await p.goto(base + '/profile/recurring-expenses/1');
    await p.waitForFunction(() => document.querySelector('[aria-label="Amount"]')?.value === '100.00');
    await p.getByLabel('Amount', { exact: true }).fill('120');
    await p.getByRole('button', { name: 'Save future schedule', exact: true }).click();
    await p.getByText('Update the payment amounts so they match the new total.', { exact: true }).waitFor();
    assert.equal(submittedSchedule, undefined);
    await p.getByLabel('Ahmed Umar pays', { exact: true }).fill('90');
    await p.getByRole('button', { name: 'Save future schedule', exact: true }).click();
    await p.getByText('Schedule update captured.', { exact: true }).waitFor();
    assert.deepEqual(submittedSchedule.participants.map(x => x.amount_paid_minor), [9000, 3000]);
    await p.getByLabel('Ahmed Umar pays', { exact: true }).scrollIntoViewIfNeeded();
    await p.screenshot({ path: output + '/recurring-payments.png' });
    console.log('PASS recurring payment amounts remain editable and validated');
    await context.close();
    assert.deepEqual(errors, []);
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
