@extends('layouts.public')
@section('title', $title)
@section('content')
<article class="document">
    <h1>{{ $title }}</h1>
    @if ($error)
        <p role="alert">{{ $error }}</p>
    @else
        <p>Open this link in Ovezi to continue securely.</p>
        <a class="email-button" href="{{ $deepLink }}">Open Ovezi</a>
        <h2>Don’t have Ovezi yet?</h2>
        <p>Install Ovezi, then return to this page and tap “Open Ovezi.” You can sign in or create an account before accepting an invitation.</p>
        @if (config('ovezi.app_store_url')) <a class="email-button" href="{{ config('ovezi.app_store_url') }}">Get Ovezi for iPhone</a> @endif
        @if (config('ovezi.play_store_url')) <a class="email-button" href="{{ config('ovezi.play_store_url') }}">Get Ovezi for Android</a> @endif
        @if (!config('ovezi.app_store_url') && !config('ovezi.play_store_url')) <p>Ovezi is preparing for release. Keep this link and open it once you have installed the app.</p> @endif
    @endif
    <p><a href="{{ route('support') }}">Need help?</a></p>
</article>
@endsection
