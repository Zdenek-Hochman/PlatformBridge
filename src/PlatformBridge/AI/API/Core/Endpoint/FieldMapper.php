<?php

declare(strict_types=1);

namespace PlatformBridge\AI\API\Core\Endpoint;

use PlatformBridge\Config\{ConfigManager, ConfigKeys};

/**
 * Mapuje názvy formulářových polí na AI klíče dle konfigurace generátoru.
 *
 * Čte layout generátoru (sekce → bloky → name/ai_key) a transformuje vstupní pole:
 *   ['topic' => 'PHP']  →  ['subject_topic' => 'PHP']
 */
final class FieldMapper
{
    public function __construct(
        private ?ConfigManager $configManager = null,
    ) {}

    public function transformToAiKeys(array $input, ?string $generatorId): array
    {
        $mapping = $this->buildMapping($generatorId);
        $transformed = [];

        foreach ($input as $fieldName => $value) {
            $transformed[$mapping[$fieldName] ?? $fieldName] = $value;
        }

        return $transformed;
    }

    /**
     * @return array<string, string> fieldName → aiKey
     */
    private function buildMapping(?string $generatorId): array
    {
        if ($generatorId === null || $this->configManager === null) {
            return [];
        }

        $generator = $this->configManager->findGenerator($generatorId);
        if ($generator === null || !isset($generator['layout'])) {
            return [];
        }

        $mapping = [];

        foreach ($generator['layout'][ConfigKeys::SECTIONS->value] ?? [] as $section) {
            foreach ($section[ConfigKeys::BLOCKS->value] ?? [] as $block) {
                $fieldName = $block[ConfigKeys::NAME->value] ?? null;
                $aiKey = $block[ConfigKeys::AI_KEY->value] ?? null;

                if ($fieldName !== null && $aiKey !== null) {
                    $mapping[$fieldName] = $aiKey;
                }
            }
        }

        return $mapping;
    }
}
