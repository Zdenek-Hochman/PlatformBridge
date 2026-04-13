<?php

declare(strict_types=1);

namespace PlatformBridge\Handler;

final class HandlerConfig
{
    public const HANDLERS = [
        \PlatformBridge\Handler\Fields\RadioHandler::class,
        \PlatformBridge\Handler\Fields\SelectHandler::class,
        \PlatformBridge\Handler\Fields\CheckboxHandler::class,
        \PlatformBridge\Handler\Fields\TextareaHandler::class,
        \PlatformBridge\Handler\Fields\TickBoxHandler::class,
        \PlatformBridge\Handler\Fields\HiddenHandler::class,
        \PlatformBridge\Handler\Fields\TextHandler::class,
        \PlatformBridge\Handler\Fields\NumberHandler::class,
        \PlatformBridge\Handler\Fields\DateHandler::class,
        \PlatformBridge\Handler\Fields\FileHandler::class,
    ];

    public const DEFAULT = \PlatformBridge\Handler\Fields\TextHandler::class;
}