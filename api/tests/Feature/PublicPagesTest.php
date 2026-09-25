<?php

use function Pest\Laravel\get;

it('serves the branded public homepage', function () {
    get('/')
        ->assertOk()
        ->assertSee('Shared expenses, made easy.')
        ->assertSee('Coming soon to iOS and Android')
        ->assertDontSee('Laravel has an incredibly rich ecosystem');
});

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
