<?php
declare(strict_types=1);

namespace PlatformBridge\Config\DTO;

final readonly class UrlsDto {
    public function __construct(
        public string $assetUrl,
        public string $apiUrl,
    ) {}
}