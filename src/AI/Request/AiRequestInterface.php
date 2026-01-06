<?php

namespace AI\Request;

/**
 * Rozhraní pro všechny AI requesty
 *
 * Validace se neprovádí na úrovni requestu - data jsou validována
 * na frontendu pomocí HTML5 atributů a JavaScriptu
 */
interface AiRequestInterface
{
    /**
     * Získá název endpointu
     */
    public function getEndpoint(): string;

    /**
     * Převede request na API payload
     */
    public function toPayload(): array;

    /**
     * Získá HTTP metodu
     */
    public function getMethod(): string;

    /**
     * Získá dodatečné hlavičky
     */
    public function getHeaders(): array;

    /**
     * Získá GET parametry pro URL
     */
    public function getQueryParams(): array;

    /**
     * Získá raw data
     */
    public function getData(): array;
}
