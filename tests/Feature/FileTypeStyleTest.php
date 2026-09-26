<?php

declare(strict_types=1);

use VmEngine\Fm\Support\FileTypeStyle;

it('maps extensions to a phosphor icon and tone', function (string $ext, string $icon, string $tone) {
    expect(FileTypeStyle::for($ext))->toBe(['icon' => $icon, 'tone' => $tone]);
})->with([
    ['pdf', 'ph-file-pdf', 'pdf'],
    ['DOCX', 'ph-file-doc', 'doc'],
    ['xlsx', 'ph-file-xls', 'sheet'],
    ['pptx', 'ph-file-ppt', 'slide'],
    ['zip', 'ph-file-zip', 'zip'],
    ['mp4', 'ph-file-video', 'video'],
    ['mp3', 'ph-file-audio', 'audio'],
    ['jpg', 'ph-file-image', 'image'],
    ['txt', 'ph-file-text', 'text'],
    ['bin', 'ph-file', 'other'],
]);
