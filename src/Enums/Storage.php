<?php

namespace Ninja\Metronome\Enums;

enum Storage: string
{
    case Realtime = 'realtime';
    case Persistent = 'persistent';

    public static function default(): self
    {
        return self::Realtime;
    }
}
