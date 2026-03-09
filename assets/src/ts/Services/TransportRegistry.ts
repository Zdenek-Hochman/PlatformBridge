import { type ApiTransport, type TransportMode } from "@/Types";

/**
 * TransportRegistry – spravuje zaregistrované transporty a řadí je podle priority.
 *
 * Při odeslání requestu se projdou transporty od nejvyšší priority (nejnižší číslo).
 * První transport, který `canHandle() === true` a vrátí nenull výsledek, vyhrává.
 * Pokud selže a je povolen fallback, zkouší se další v pořadí.
 */
export class TransportRegistry {
    private transports: ApiTransport[] = [];

    /** Zaregistruje transport a seřadí seznam podle priority */
    register(transport: ApiTransport): void {
        // Zamezit duplicitám
        this.unregister(transport.name);
        this.transports.push(transport);
        this.transports.sort((a, b) => a.priority - b.priority);
    }

    /** Odebere transport podle jména */
    unregister(name: TransportMode): void {
        this.transports = this.transports.filter((t) => t.name !== name);
    }

    /** Vrací seřazený seznam všech transportů */
    getAll(): ReadonlyArray<ApiTransport> {
        return this.transports;
    }

    /** Vrací pouze transporty, které aktuálně mohou zpracovat request */
    getAvailable(): ApiTransport[] {
        return this.transports.filter((t) => t.canHandle());
    }

    /** Vrací transport podle jména, nebo undefined */
    get(name: TransportMode): ApiTransport | undefined {
        return this.transports.find((t) => t.name === name);
    }

    /** Přeruší všechny transporty */
    abortAll(): void {
        for (const transport of this.transports) {
            transport.abort();
        }
    }
}