<?php

declare(strict_types=1);

namespace PlatformBridge\Error;

/**
 * Interface pro výjimky, které definují vlastní způsob prezentace v error rendereru.
 *
 * Každá aplikační výjimka (AI, Config, Security, ...) implementuje toto rozhraní,
 * aby error renderer mohl zobrazit specifický titulek, nápovědu a další kontext.
 *
 * Systémové chyby (ErrorException, RuntimeException, ...) toto rozhraní neimplementují
 * a zobrazí se generickým způsobem.
 */
interface RenderableException
{
    /**
     * Lidsky čitelný titulek chyby (např. "Chyba konfigurace", "AI selhalo").
     */
    public function getTitle(): string;

    /**
     * HTTP status kód, který se pošle klientovi (výchozí 500).
     */
    public function getHttpStatusCode(): int;

    /**
     * Volitelná nápověda pro vývojáře, jak chybu opravit.
     */
    public function getHint(): ?string;

    /**
     * Dodatečné klíč–hodnota páry zobrazené v detailu chyby.
     *
     * Příklad: ['Soubor' => 'blocks.json', 'Klíč' => 'fields']
     *
     * @return array<string, string>
     */
    public function getRenderContext(): array;
}
