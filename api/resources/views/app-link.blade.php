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
        @include('partials.store-links')
    @endif
    <p><a href="{{ route('support') }}">Need help?</a></p>
</article>
@endsection
