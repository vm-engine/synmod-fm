<?php

declare(strict_types=1);

use VmEngine\Fm\Http\Controllers\AssetController;

it('serves the stylesheet with a long-lived cache header', function () {
    $response = $this->get(route('fm.assets', ['file' => 'fm.css']));

    $response->assertOk()->assertHeader('Content-Type', 'text/css; charset=UTF-8');
    expect($response->headers->get('Cache-Control'))->toContain('max-age=31536000');
});

it('serves the script', function () {
    $this->get(route('fm.assets', ['file' => 'fm.js']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8');
});

it('404s for anything outside the whitelist', function (string $file) {
    $this->get('/fm/assets/'.$file)->assertNotFound();
})->with(['app.css', '..%2Fcomposer.json', 'fm.css.bak']);

it('builds a cache-busted url', function () {
    expect(AssetController::url('fm.css'))->toContain('/fm/assets/fm.css?v=');
});
