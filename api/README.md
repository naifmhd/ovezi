<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

## Realtime production checklist

Domain broadcasts use the dedicated `broadcasts` queue. Laravel Cloud must run a continuous
worker for it (for example, `php artisan queue:work --queue=broadcasts --tries=3 --backoff=3`).
Expense and settlement push notifications use the default queue, so a continuous default
worker must also run (for example, `php artisan queue:work --queue=default --tries=4`).
The worker and web process must share the same Reverb app ID, key, secret, host, port, scheme,
and TLS settings. A stopped worker must never block the original financial mutation because
events are queued only after the database transaction commits.

For every release, verify two authenticated clients can subscribe to their private user
channels, create and edit an expense, receive the versioned `domain.changed` event within two
seconds, recover after a network interruption, reject cross-user channel authorization, and
disconnect on logout. Monitor failed jobs, `broadcasts` queue depth, authorization failures,
connection count, and event latency.

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## Ovezi production domain

The public homepage is `https://ovezi.ninesixty.mv`, and the mobile API base URL is
`https://ovezi.ninesixty.mv/api/v1`. Configure `APP_URL=https://ovezi.ninesixty.mv` in the
Laravel Cloud production environment and redeploy after changing environment settings.
Keep local development's `APP_URL` pointed at the local server.

Public links for store listings and Google OAuth branding:

- Homepage / marketing: `https://ovezi.ninesixty.mv`
- Privacy policy: `https://ovezi.ninesixty.mv/privacy`
- Terms: `https://ovezi.ninesixty.mv/terms`
- Support: `https://ovezi.ninesixty.mv/support`
- Account deletion: `https://ovezi.ninesixty.mv/delete-account`

Verify ownership of `ninesixty.mv` as a Domain property in Google Search Console using
the Google Cloud project owner's account. Add `ninesixty.mv` as the OAuth authorized
domain and use the public URLs above in Google Auth Platform's branding settings.
Keep the previous hostname serving existing app versions and previously issued signed
links during the migration; redirecting signed URLs to a different host can invalidate them.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
