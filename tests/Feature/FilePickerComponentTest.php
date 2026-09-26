<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use VmEngine\Fm\Config\FmConfig;
use VmEngine\Fm\Models\FmFile;
use VmEngine\SynAuth\Models\Role;
use VmEngine\SynAuth\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    // Pin FmConfig so parallel workers writing fm.json can't interfere.
    $ref = new ReflectionProperty(FmConfig::class, 'config');
    $ref->setAccessible(true);
    $ref->setValue(null, [
        'disk' => 'public',
        'folders' => [
            ['path' => 'fm/public', 'name' => 'Public', 'permissions' => [
                'admin' => ['read', 'upload', 'delete', 'rename', 'move', 'copy', 'mkdir'],
            ]],
        ],
        'image' => ['thumbnail_width' => 200, 'thumbnail_height' => 200],
        'upload' => ['max_size_kb' => 10240, 'allowed_extensions' => ['jpg', 'png', 'pdf']],
        'trash' => ['auto_purge_days' => 30],
    ]);

    $devRole = Role::factory()->create(['name' => 'Developer', 'slug' => 'dev', 'level' => 0, 'can_access_backend' => true]);
    $this->user = User::factory()->create();
    $this->user->roles()->attach($devRole);

    Storage::disk('public')->put('fm/public/report.pdf', 'x');
    $this->file = FmFile::create([
        'disk' => 'public',
        'folder_path' => 'fm/public',
        'relative_path' => 'report.pdf',
        'filename' => 'report.pdf',
        'original_name' => 'report.pdf',
        'extension' => 'pdf',
        'mime_type' => 'application/pdf',
        'size' => 1,
        'has_thumbnail' => false,
        'is_trashed' => false,
    ]);
});

afterEach(function () {
    $ref = new ReflectionProperty(FmConfig::class, 'config');
    $ref->setAccessible(true);
    $ref->setValue(null, null);
});

it('renders the picker inside a synapse modal', function () {
    Livewire::actingAs($this->user)->test('fm::file-picker')
        ->assertOk()
        ->assertSeeHtml('open-modal-fm-picker-')
        ->assertDontSeeHtml('pickerOpen');
});

it('keeps the public open event contract', function () {
    Livewire::actingAs($this->user)->test('fm::file-picker', ['pickerKey' => 'hero'])
        ->call('openPicker', '*', 'hero')
        ->assertDispatched('fm-picker-open-hero');
});

it('keeps the public close event contract on select', function () {
    Livewire::actingAs($this->user)->test('fm::file-picker')
        ->call('selectFile', $this->file->id)
        ->assertDispatched('fm:file-selected')
        ->assertDispatched('fm-picker-close');
});

it('picks on click and selects on choose', function () {
    Livewire::actingAs($this->user)->test('fm::file-picker')
        ->call('pick', $this->file->id)
        ->assertSet('pickedId', $this->file->id)
        ->call('choose')
        ->assertDispatched('fm:file-selected')
        ->assertDispatched('fm-picker-close')
        ->assertSet('value', $this->file->getUrl());
});

it('remembers the last position per locked folder', function () {
    Storage::disk('public')->makeDirectory('fm/public/a/b');

    Livewire::actingAs($this->user)->test('fm::file-picker', ['folder' => 'fm/public'])
        ->call('navigateTo', 'a/b');

    Livewire::actingAs($this->user)->test('fm::file-picker', ['folder' => 'fm/public'])
        ->assertSet('subPath', 'a/b')
        ->assertSet('expanded', ['a', 'a/b']);
});

it('offers the storage switcher only when unlocked with several roots', function () {
    $ref = new ReflectionProperty(FmConfig::class, 'config');
    $ref->setAccessible(true);
    $config = $ref->getValue();
    $config['folders'][] = ['path' => 'fm/docs', 'name' => 'Docs', 'permissions' => ['admin' => ['read']]];
    $ref->setValue(null, $config);

    Livewire::actingAs($this->user)->test('fm::file-picker')
        ->assertSeeHtml('data-fm-storage-switcher');

    Livewire::actingAs($this->user)->test('fm::file-picker', ['folder' => 'fm/public'])
        ->assertDontSeeHtml('data-fm-storage-switcher');
});

it('ignores storage switches when locked', function () {
    Livewire::actingAs($this->user)->test('fm::file-picker', ['folder' => 'fm/public'])
        ->call('switchFolder', 'fm/docs')
        ->assertSet('currentFolder', 'fm/public');
});

it('creates a folder inline in the current folder and closes the popover', function () {
    Livewire::actingAs($this->user)->test('fm::file-picker')
        ->assertSeeHtml('data-dropdown="newfolder"')
        ->set('newFolderName', 'inbox')
        ->call('createFolder')
        ->assertHasNoErrors()
        ->assertSet('newFolderName', '')
        ->assertDispatched('fm-folder-created');

    Storage::disk('public')->assertExists('fm/public/inbox');
});

it('validates the inline folder name', function () {
    Livewire::actingAs($this->user)->test('fm::file-picker')
        ->set('newFolderName', 'bad name!')
        ->call('createFolder')
        ->assertHasErrors(['newFolderName' => 'alpha_dash'])
        ->assertNotDispatched('fm-folder-created');
});

it('still filters by accept on the server', function () {
    $items = Livewire::actingAs($this->user)->test('fm::file-picker', ['accept' => 'image/*'])
        ->instance()->items;

    expect(array_column($items, 'name'))->not->toContain('report.pdf');
});

it('shows a not-configured state when no storage exists', function () {
    $ref = new ReflectionProperty(FmConfig::class, 'config');
    $ref->setAccessible(true);
    $ref->setValue(null, ['disk' => 'public', 'folders' => []]);
    Storage::disk('public')->makeDirectory('other-module');

    $component = Livewire::actingAs($this->user)->test('fm::file-picker')
        ->assertSee(__('fm::labels.not_configured'))
        ->assertDontSeeHtml('data-fm-item');

    expect($component->instance()->items)->toBe([]);
});

it('uses a lowercase modal name so the HTML-lowercased listener matches the dispatched event', function () {
    $component = Livewire::actingAs($this->user)->test('fm::file-picker');
    $modalName = $component->instance()->jsConfig['modalName'];

    expect($modalName)->toBe(strtolower($modalName));
    $component->assertSeeHtml('synapseModal(&quot;'.$modalName.'&quot;)');
});

it('refuses to pick or select files outside the browsed storage root', function () {
    $foreign = FmFile::create([
        'disk' => 'public', 'folder_path' => 'fm/other', 'relative_path' => 'secret.pdf',
        'filename' => 'secret.pdf', 'original_name' => 'secret.pdf', 'extension' => 'pdf',
        'mime_type' => 'application/pdf', 'size' => 1, 'is_trashed' => false,
    ]);

    $component = Livewire::actingAs($this->user)->test('fm::file-picker')
        ->call('selectFile', $foreign->id)
        ->assertNotDispatched('fm:file-selected')
        ->assertSet('value', '')
        ->call('pick', $foreign->id);

    expect($component->instance()->pickedFile)->toBeNull();
});
