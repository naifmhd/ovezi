@extends('layouts.public')

@section('title', 'Support')
@section('description', 'Get help with your Ovezi account, expenses, balances, and privacy requests.')

@section('content')
    <article class="document">
        <header class="document-header">
            <p class="eyebrow">We’re here to help</p>
            <h1>Ovezi Support</h1>
            <p class="document-intro">Find answers to common questions or contact us about your account, shared expenses, or privacy.</p>
            <a class="email-button" href="mailto:{{ config('ovezi.support_email') }}?subject=Ovezi%20Support">Email {{ config('ovezi.support_email') }}</a>
        </header>

        <div class="support-grid">
            <section class="support-card">
                <h2>Account access</h2>
                <p>Use “Forgot password” on the sign-in screen for email/password accounts. If you use Apple or Google, select the same provider and account used when you registered.</p>
            </section>
            <section class="support-card">
                <h2>Incorrect balance</h2>
                <p>Check the payer, participants, split method, currency, captured exchange rate, and recorded settlements. Group activity shows changes that may have affected the balance.</p>
            </section>
            <section class="support-card">
                <h2>Invites and placeholders</h2>
                <p>Open an invite link on a device with Ovezi installed. Placeholder history can be claimed only after the matching email or phone is verified and you confirm the records.</p>
            </section>
            <section class="support-card">
                <h2>Notifications</h2>
                <p>Check Ovezi’s notification preferences and iOS Settings. You can mute individual groups and supported event types without disabling every notification.</p>
            </section>
        </div>

        <section>
            <h2>Export or delete your account</h2>
            <p>In Ovezi, open <strong>Profile → Security</strong>. From there you can export your personal data, log out of all devices, or delete your account.</p>
            <p>Deleting your account removes access and active profile use. Minimum historical records may remain in an inactive or de-identified form so shared group history and other members’ balances stay accurate. See the <a href="{{ route('privacy') }}">Privacy Policy</a> for details.</p>
        </section>

        <section>
            <h2>When contacting support</h2>
            <p>Include the email address connected to your Ovezi account and a short description of what happened. Screenshots are helpful, but do not send passwords, authentication codes, full payment-card details, or other sensitive credentials.</p>
        </section>
    </article>
@endsection
