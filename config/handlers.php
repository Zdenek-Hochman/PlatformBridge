<?php

return [
    'handlers' => [
        \Handler\Fields\RadioHandler::class,
        \Handler\Fields\SelectHandler::class,
        \Handler\Fields\CheckboxHandler::class,
        \Handler\Fields\TextareaHandler::class,
        \Handler\Fields\TickBoxHandler::class,
        \Handler\Fields\TextHandler::class,
        \Handler\Fields\NumberHandler::class,
        \Handler\Fields\DateHandler::class,
        \Handler\Fields\FileHandler::class,
    ],
    'default' => \Handler\Fields\TextHandler::class,
];