<?php
declare(strict_types=1);

namespace PlatformBridge\Config\DTO;

final readonly class TranslationsDto {
    public function __construct(
        public ?\mysqli $mysqli,
        public string $table = 'pb_translations',
        public string $locale = 'cs',
    ) {}
}