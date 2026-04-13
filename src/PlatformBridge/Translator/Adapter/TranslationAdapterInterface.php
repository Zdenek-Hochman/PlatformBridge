<?php

declare(strict_types=1);

namespace PlatformBridge\Translator\Adapter;
use PlatformBridge\Translator\Domain;

interface TranslationAdapterInterface
{
    public function fetch(string $locale, Domain $domain): array;
    public function supports(): bool;
    public function availableLocales(): array;
}