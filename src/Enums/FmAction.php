<?php

declare(strict_types=1);

namespace VmEngine\Fm\Enums;

enum FmAction: string
{
    case Read = 'read';
    case Upload = 'upload';
    case Delete = 'delete';
    case Rename = 'rename';
    case Move = 'move';
    case Copy = 'copy';
    case Mkdir = 'mkdir';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
