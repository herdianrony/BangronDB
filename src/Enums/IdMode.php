<?php

declare(strict_types=1);

namespace BangronDB\Enums;

/**
 * ID generation mode enum.
 */
enum IdMode: string
{
    case Auto = 'auto';
    case Manual = 'manual';
    case Prefix = 'prefix';
}
