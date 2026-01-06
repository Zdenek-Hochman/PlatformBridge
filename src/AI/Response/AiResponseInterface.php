<?php

namespace AI\Response;

/**
 * Rozhraní pro AI odpovědi
 */
interface AiResponseInterface
{
    /**
     * Zda je odpověď úspěšná
     */
    public function isSuccess(): bool;

    /**
     * Získá data odpovědi
     */
    public function getData(): mixed;

    /**
     * Získá surová data
     */
    public function getRaw(): array;

    /**
     * Získá HTTP status kód
     */
    public function getStatusCode(): int;

    /**
     * Získá chybovou zprávu
     */
    public function getError(): ?string;

    /**
     * Převede na pole
     */
    public function toArray(): array;
}
