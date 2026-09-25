@extends('layouts.public')
@section('title', 'Delete your account')
@section('description', 'Request deletion of your Ovezi account and personal information.')
@section('content')
<article class="document">
    <h1>{{ !empty($completed) ? 'Your account has been deleted' : 'Delete your Ovezi account' }}</h1>
    @if (!empty($completed))
        <p>Your sessions and personal profile have been removed. Shared financial history remains under “Deleted member” so other people’s balances stay accurate.</p>
        <p>If you used Sign in with Apple before Ovezi supported automatic revocation, also remove Ovezi from your Apple account’s Sign in with Apple settings.</p>
    @else
        <p>Deletion removes your name, email, sign-in connections, notification settings, and active sessions. Personal expenses are queued for permanent removal. Shared expenses and settlements remain under “Deleted member”; deletion does not cancel money owed.</p>
        <p>If you own a group, ownership passes to another active member, or the group is archived if nobody can take over. Some security and backup records may remain for a limited period, as explained in our privacy policy.</p>
        @if (session('status')) <p role="status">{{ session('status') }}</p> @endif
        @if ($errors->any()) <p role="alert">{{ $errors->first() }}</p> @endif
        @if (!empty($confirmationToken))
            <form method="POST" action="{{ route('account-deletion.destroy', $confirmationToken) }}">
                @csrf
                <label style="display:flex;gap:12px;align-items:start;padding:16px 0"><input type="checkbox" name="confirm" value="1" required> I understand this permanently removes my account and cannot be undone.</label>
                <button class="email-button" type="submit">Permanently delete account</button>
            </form>
        @else
            <form method="POST" action="{{ route('account-deletion.store') }}">
                @csrf
                <label for="email">Account email</label>
                <input id="email" name="email" type="email" autocomplete="email" required maxlength="255" value="{{ old('email') }}" style="display:block;width:100%;min-height:48px;margin:12px 0 20px;padding:12px;border:1px solid var(--line);border-radius:12px;background:var(--surface);color:var(--ink)">
                <button class="email-button" type="submit">Send confirmation link</button>
            </form>
            <p>You can request deletion here without reinstalling Ovezi. Confirm through the email associated with your account.</p>
        @endif
    @endif
    <p><a href="{{ route('privacy') }}">Privacy and retention</a> · <a href="{{ route('support') }}">Get help</a></p>
</article>
@endsection
