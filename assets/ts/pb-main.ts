// Importuje hlavní třídu aplikace
import { App } from "./app";

// Po načtení DOMu inicializuje aplikaci
document.addEventListener("DOMContentLoaded", () => {
    // Vytvoří instanci aplikace
    const app = new App();
    // Spustí inicializační logiku aplikace (async kvůli i18n bootu)
    app.init().catch(err => console.error('[PlatformBridge] Init failed:', err));
});