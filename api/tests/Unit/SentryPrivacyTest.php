<?php

use App\Support\SentryPrivacy;
use Sentry\Event;
use Sentry\UserDataBag;
use Tests\TestCase;

uses(TestCase::class);

it('removes sensitive event context while retaining method and internal user id', function () {
    $event = Event::createEvent();
    $event->setRequest([
        'method' => 'POST',
        'url' => 'https://api.ovezi.app/reset?token=secret',
        'headers' => ['Authorization' => 'Bearer secret'],
        'data' => ['email' => 'person@example.com', 'password' => 'secret'],
    ]);
    $event->setExtra(['receipt_url' => 'https://private.example/receipt.jpg']);
    $event->setUser(new UserDataBag(42, 'person@example.com', '192.0.2.1', 'Naif'));

    $scrubbed = SentryPrivacy::scrub($event);

    expect($scrubbed->getRequest())->toBe(['method' => 'POST'])
        ->and($scrubbed->getExtra())->toBe([])
        ->and($scrubbed->getUser()?->getId())->toBe(42)
        ->and($scrubbed->getUser()?->getEmail())->toBeNull()
        ->and($scrubbed->getUser()?->getIpAddress())->toBeNull()
        ->and($scrubbed->getUser()?->getUsername())->toBeNull();
});

it('keeps privacy-sensitive collection and performance monitoring disabled', function () {
    expect(config('sentry.send_default_pii'))->toBeFalse()
        ->and(config('sentry.traces_sample_rate'))->toBe(0.0)
        ->and(config('sentry.profiles_sample_rate'))->toBe(0.0)
        ->and(config('sentry.enable_logs'))->toBeFalse()
        ->and(config('sentry.enable_metrics'))->toBeFalse()
        ->and(config('sentry.max_breadcrumbs'))->toBe(0);
});
