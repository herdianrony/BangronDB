<?php

declare(strict_types=1);

namespace BangronDB\Enums;

/**
 * Hook event type enum.
 */
enum HookEvent: string
{
    case BeforeInsert = 'beforeInsert';
    case AfterInsert = 'afterInsert';
    case BeforeUpdate = 'beforeUpdate';
    case AfterUpdate = 'afterUpdate';
    case BeforeRemove = 'beforeRemove';
    case AfterRemove = 'afterRemove';
}
