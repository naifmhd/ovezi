<?php

use function Pest\Laravel\get;

it('serves the branded public homepage', function () {
    get('/')
        ->assertOk()
        ->assertSee('Shared expenses, made easy.')
        ->assertSee('Available now for iPhone and Android.')
        ->assertSee('href="https://apps.apple.com/app/ovezi/id6813832162"', false)
        ->assertSee('href="https://play.google.com/store/apps/details?id=com.ovezi.app"', false)
        ->assertDontSee('Coming soon')
        ->assertDontSee('Laravel has an incredibly rich ecosystem');
});

it('uses configured download destinations across public entry points', function (string $path) {
    config([
        'ovezi.app_store_url' => 'https://apps.apple.com/mv/app/ovezi/id6813832162',
        'ovezi.play_store_url' => 'https://play.google.com/store/apps/details?id=com.ovezi.app&hl=en',
    ]);

    get($path)
        ->assertSee('href="https://apps.apple.com/mv/app/ovezi/id6813832162"', false)
        ->assertSee('href="https://play.google.com/store/apps/details?id=com.ovezi.app&amp;hl=en"', false)
        ->assertDontSee('preparing for release');
})->with(['/', '/support', '/lending']);

it('serves the public legal and support pages', function (string $path, string $heading) {
    get($path)
        ->assertOk()
        ->assertSee($heading)
        ->assertSee('Ovezi');
})->with([
    ['/privacy', 'Privacy Policy'],
    ['/terms', 'Terms of Service'],
    ['/support', 'Ovezi Support'],
]);
