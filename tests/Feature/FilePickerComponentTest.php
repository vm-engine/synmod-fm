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
