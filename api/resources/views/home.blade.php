@extends('layouts.public')

@section('title', 'Ovezi')
@section('description', 'Split shared expenses, understand balances, and settle up without ads, daily limits, or paywalled essentials.')

@section('content')
    <section class="hero shell">
        <div class="hero-mark"><x-brand-mark /></div>
        <p class="eyebrow">Split. Share. Settle.</p>
        <h1>Shared expenses, made easy.</h1>
        <p class="lead">Ovezi helps friends and groups split costs, understand balances, and record settlements—without ads, daily limits, or paywalled essentials.</p>
        <div class="pill"><span class="pill-dot"></span> Coming soon to iPhone</div>
    </section>

    <section class="feature-grid shell" aria-label="Ovezi features">
        <article class="card">
            <span class="card-number">01</span>
            <h2>Split your way</h2>
            <p>Divide expenses equally, by exact amounts, percentages, or shares—with clear rounding and partial-group support.</p>
        </article>
        <article class="card">
            <span class="card-number">02</span>
            <h2>Know who owes what</h2>
            <p>See group and overall balances, simplify debts, and keep every expense and settlement in a transparent activity history.</p>
        </article>
        <article class="card">
            <span class="card-number">03</span>
            <h2>Keep money outside</h2>
            <p>Ovezi never moves your money. Record payments made elsewhere and keep the group’s shared record accurate.</p>
        </article>
    </section>
@endsection
