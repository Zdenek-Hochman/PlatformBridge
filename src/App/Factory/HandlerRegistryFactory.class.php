<?php

namespace App\Factory;

use Handler\HandlerRegistry;

final class HandlerRegistryFactory
{
    public static function create(): HandlerRegistry
    {
        $config = require __DIR__ . '/../../../config/handlers.php';

        $registry = new HandlerRegistry();

        foreach ($config['handlers'] as $handlerClass) {
            $registry->addHandler(new $handlerClass());
        }

        $registry->setDefaultHandler($config['default']);

        return $registry;
    }
	// $registry->mapVariant('color-picker', CustomColorPickerHandler::class);
}