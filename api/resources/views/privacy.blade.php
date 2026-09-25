@extends('layouts.public')

@section('title', 'Privacy Policy')
@section('description', 'How Ovezi collects, uses, shares, and protects information.')

@section('content')
    <article class="document">
        <header class="document-header">
            <p class="eyebrow">Your information</p>
            <h1>Privacy Policy</h1>
            <p class="updated">Effective 19 September 2026</p>
            <p class="document-intro">This policy explains how Ovezi collects, uses, shares, and retains information when you use the Ovezi mobile application and related services.</p>
        </header>

        <section>
            <h2>1. Information we collect</h2>
            <h3>Information you provide</h3>
            <ul>
                <li>Account and profile information, including your name, email address, optional phone number, avatar, password credentials, and default currency.</li>
                <li>Groups, friendships, invitations, expenses, splits, categories, settlements, recurring expenses, notes, currency preferences, and exchange-rate overrides.</li>
                <li>Images you choose to upload, such as group photos and receipt images.</li>
                <li>Support requests and other communications you send to us.</li>
            </ul>
            <h3>Information about other people</h3>
            <p>A user may create a placeholder for someone who does not yet have an Ovezi account. A placeholder includes a name and exactly one email address or phone number. Users should only provide this information when they are permitted to do so.</p>
            <h3>Information collected automatically</h3>
            <p>We may receive device and application information needed to operate the service, including push-notification tokens, authentication and security events, IP-derived request information, and privacy-filtered crash diagnostics. We do not use third-party advertising trackers.</p>
            <h3>Connected accounts</h3>
            <p>If you sign in with Apple or Google, we receive an account identifier and the profile information you authorize that provider to share. Google accounts are linked automatically only when Google reports the email address as verified.</p>
        </section>

        <section>
            <h2>2. How we use information</h2>
            <p>We use information to:</p>
            <ul>
                <li>Create and secure accounts, verify email addresses, and provide account recovery.</li>
                <li>Create groups, calculate and simplify balances, record expenses and settlements, and maintain activity history.</li>
                <li>Deliver invitations, realtime updates, service emails, and notifications you have enabled.</li>
                <li>Provide currency conversion, exports, search, recurring expenses, and requested support.</li>
                <li>Detect abuse, investigate failures, protect users, and improve the reliability of Ovezi.</li>
                <li>Comply with legal obligations and enforce our Terms.</li>
            </ul>
            <p>“Just for me” expenses are private tracking records. They are not shared with another user and never affect Ovezi balances.</p>
        </section>

        <section>
            <h2>3. How information is shared</h2>
            <p>Group members can see the group’s members, expenses, splits, settlements, balances, and activity. People involved in a direct expense or settlement can see the records relevant to them.</p>
            <p>We use service providers to operate Ovezi, including providers for cloud hosting and storage, authentication, email delivery, push notifications, crash reporting, and currency-rate data. They may process only the information needed to provide their services to us.</p>
            <p>We may disclose information when required by law, to protect the rights and safety of users or others, or as part of a business transfer. We do not sell personal information or share it for cross-context behavioral advertising.</p>
        </section>

        <section>
            <h2>4. Placeholder claims</h2>
            <p>A signed-in person may be shown placeholder records that match their verified email address or phone number. Ovezi does not merge those records automatically. The person must review the matches and confirm a single atomic claim operation.</p>
        </section>

        <section>
            <h2>5. Retention and deletion</h2>
            <p>We retain information while your account is active and as needed to provide Ovezi. Export your information from Profile and delete your account from Profile → Security, or use the <a href="{{ route('account-deletion.create') }}">account deletion page</a>.</p>
            <p>Deleting an account removes active sessions, sign-in connections, name, email, avatar references, and notification settings. Personal expenses are queued for permanent removal. The minimum historical information needed to keep shared group expenses, settlements, and balances understandable is preserved under an anonymized “Deleted member” record. Security, backup, fraud-prevention, and legal records may be retained for a limited period where reasonably necessary.</p>
        </section>

        <section>
            <h2>6. Your choices</h2>
            <ul>
                <li>Update your profile, email, password, default currency, and notification preferences in the app.</li>
                <li>Mute supported notifications by group or event type.</li>
                <li>Disconnect a social-login provider after adding another login method.</li>
                <li>Export your personal data or delete your account from the app.</li>
                <li>Control camera, photo-library, and notification permissions in iOS Settings.</li>
            </ul>
        </section>

        <section>
            <h2>7. Security and international processing</h2>
            <p>We use technical and organizational safeguards designed to protect information, including encrypted network connections, authenticated access, private storage, authorization policies, and restricted production credentials. No system can guarantee absolute security.</p>
            <p>Ovezi and its service providers may process information in countries other than your own. Where required, we use appropriate safeguards for those transfers.</p>
        </section>

        <section>
            <h2>8. Children</h2>
            <p>Ovezi is not directed to children under 13, or the minimum age required to consent to an online service where they live. If you believe a child has provided personal information without appropriate permission, contact us.</p>
        </section>

        <section>
            <h2>9. Changes and contact</h2>
            <p>We may update this policy as Ovezi changes. We will publish the updated date here and provide additional notice when required.</p>
            <p>For privacy questions or requests, email <a href="mailto:{{ config('ovezi.support_email') }}">{{ config('ovezi.support_email') }}</a> or visit our <a href="{{ route('support') }}">support page</a>.</p>
        </section>

    </article>
@endsection
