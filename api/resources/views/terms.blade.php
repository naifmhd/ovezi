@extends('layouts.public')

@section('title', 'Terms of Service')
@section('description', 'Terms governing use of the Ovezi expense-sharing service.')

@section('content')
    <article class="document">
        <header class="document-header">
            <p class="eyebrow">Using Ovezi</p>
            <h1>Terms of Service</h1>
            <p class="updated">Effective 19 September 2026</p>
            <p class="document-intro">These Terms govern your use of Ovezi. By creating an account or using the service, you agree to them.</p>
        </header>

        <section>
            <h2>1. What Ovezi does</h2>
            <p>Ovezi helps people record shared expenses, calculate balances, track personal spending, and record settlements. Ovezi does not hold, transfer, receive, or process money between users. A settlement is only a record of a payment made outside Ovezi and is not proof that payment occurred.</p>
        </section>

        <section>
            <h2>2. Eligibility and accounts</h2>
            <p>You must be at least 13, or the minimum age required to use an online service where you live. You must provide accurate account information, keep your login methods secure, and promptly notify us if you believe your account has been compromised.</p>
            <p>You are responsible for activity performed through your account. You may not impersonate another person, create an account using information you are not entitled to use, or transfer your account to someone else.</p>
        </section>

        <section>
            <h2>3. Your records and shared groups</h2>
            <p>You retain ownership of information and content you submit. You give Ovezi permission to host, process, reproduce, and display that content only as needed to operate, secure, and improve the service.</p>
            <p>Group members can add or change records according to their role and permissions. These changes may alter balances and will ordinarily appear in group activity. You are responsible for checking records and resolving disagreements with the people involved.</p>
            <p>If you add a placeholder or invite someone, you confirm that you are permitted to provide their name and contact information for that purpose.</p>
        </section>

        <section>
            <h2>4. Currency and calculations</h2>
            <p>Currency rates are informational estimates from third-party sources or rates entered by users. Ovezi captures the rate used for a record so historical amounts remain stable. You are responsible for confirming any amount before relying on it or making a payment elsewhere.</p>
        </section>

        <section>
            <h2>5. Acceptable use</h2>
            <p>You may not use Ovezi to:</p>
            <ul>
                <li>Break the law, facilitate fraud, harassment, threats, or unauthorized financial activity.</li>
                <li>Upload malicious code or interfere with the service, its security, or another user’s access.</li>
                <li>Probe, scrape, reverse engineer, or access Ovezi through unauthorized automated means except where applicable law permits it.</li>
                <li>Submit content that infringes another person’s rights or exposes information you are not permitted to share.</li>
            </ul>
            <p>We may restrict or suspend access when reasonably necessary to protect Ovezi, its users, or others.</p>
        </section>

        <section>
            <h2>6. Third-party services</h2>
            <p>Ovezi relies on third-party services for functions such as sign-in, hosting, email, notifications, diagnostics, and currency data. Their terms and privacy practices may also apply when you use those services. Ovezi is not responsible for payments, communications, or disputes occurring through services outside Ovezi.</p>
        </section>

        <section>
            <h2>7. Availability and changes</h2>
            <p>We work to keep Ovezi reliable, but the service may occasionally be unavailable, delayed, or changed. We may add, modify, suspend, or discontinue features. Keep independent records of information you cannot afford to lose.</p>
        </section>

        <section>
            <h2>8. Export, deletion, and termination</h2>
            <p>You can export your personal data and request account deletion from the app. Shared financial history may be retained in an inactive or de-identified form where removing it would make other users’ records inaccurate.</p>
            <p>You may stop using Ovezi at any time. We may suspend or terminate access for a material or repeated breach of these Terms, unlawful activity, or a serious security risk.</p>
        </section>

        <section>
            <h2>9. Disclaimers and responsibility</h2>
            <p>To the extent permitted by law, Ovezi is provided “as is” and “as available.” We do not guarantee that user-entered records, exchange rates, balances, exports, or settlement confirmations are complete or error-free. Ovezi does not provide financial, tax, legal, or accounting advice.</p>
            <p>Nothing in these Terms excludes rights or remedies that cannot legally be excluded. To the extent permitted by law, Ovezi is not responsible for indirect or consequential losses arising from use of the service or dealings between users.</p>
        </section>

        <section>
            <h2>10. Changes and contact</h2>
            <p>We may update these Terms as the service changes. If a change materially affects your rights, we will provide reasonable notice where required. Continued use after the effective date means you accept the updated Terms.</p>
            <p>Questions about these Terms can be sent to <a href="mailto:{{ config('ovezi.support_email') }}">{{ config('ovezi.support_email') }}</a>.</p>
        </section>

    </article>
@endsection
