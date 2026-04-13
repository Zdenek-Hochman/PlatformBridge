<?php

declare(strict_types=1);

namespace PlatformBridge\Handler;

/**
 * Výčet typů komponent formuláře pro mapování handlerů.
 *
 * Hodnota odpovídá klíči 'component' v konfiguračním bloku.
 */
enum ComponentType: string
{
    case Input = 'input';
    case Select = 'select';
    case Textarea = 'textarea';
    case TickBox = 'tick-box';
}
