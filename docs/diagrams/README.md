# PlatformBridge – Architekturní diagramy

Tato složka obsahuje UML a C4 diagramy celé aplikace. Soubory jsou ve formátu **PlantUML** (`.puml`).

> 💡 **Jak zobrazit:** Nainstalujte VS Code rozšíření [PlantUML](https://marketplace.visualstudio.com/items?itemName=jebbs.plantuml) nebo vložte obsah na [plantuml.com/plantuml](https://www.plantuml.com/plantuml/uml/).

---

## Přehled diagramů

| Soubor | Typ | Popis |
|---|---|---|
| [`c4-context.puml`](c4-context.puml) | C4 Level 1 – Context | Systém z pohledu okolí (kdo s čím interaguje) |
| [`c4-container.puml`](c4-container.puml) | C4 Level 2 – Container | Hlavní subsystémy a jejich komunikace |
| [`c4-components.puml`](c4-components.puml) | C4 Level 3 – Components (Core + Form) | Vnitřní komponenty jádra a formulářového engine |
| [`c4-components-ai.puml`](c4-components-ai.puml) | C4 Level 3 – Components (AI) | Vnitřní komponenty AI vrstvy a HTTP handleru |
| [`uml-class-diagram.puml`](uml-class-diagram.puml) | UML Class Diagram | Kompletní třídy, rozhraní, dědičnost a závislosti |
| [`sequence-form-render.puml`](sequence-form-render.puml) | UML Sequence Diagram | Průchod voláním `renderFullForm()` krok po kroku |
| [`sequence-ai-request.puml`](sequence-ai-request.puml) | UML Sequence Diagram | Průchod AI HTTP požadavkem přes `ApiHandler.php` |

---

## Architektura v kostce

### C4 Level 1 – Context
```
Vývojář ──► PlatformBridge ──► VirtualZoom AI API
                │
                └──► Souborový systém (JSON konfigurace, TPL šablony, PHP cache)
```

### C4 Level 2 – Containers

```
┌─────────────────────────────────────────────────────┐
│                 PlatformBridge System                │
│                                                      │
│  Bootstrap (index.php/demo.php)                      │
│      └─► PlatformBridge Core (fasáda)                │
│              ├─► Config System                       │
│              ├─► Form Engine                         │
│              ├─► Template Engine                     │
│              ├─► Asset Manager                       │
│              └─► Security (HMAC)                     │
│                                                      │
│  HTTP API Handler (ApiHandler.php)                   │
│      ├─► Config System                               │
│      ├─► Security (HMAC verify)                      │
│      ├─► AI Layer ──► VirtualZoom AI API             │
│      └─► Template Engine                             │
└─────────────────────────────────────────────────────┘
```

### Hlavní toky dat

#### 1. Renderování formuláře (synchronní)
```
demo.php
  → PlatformBridgeBuilder::build()
  → PlatformBridge::renderFullForm('subject')
  → ConfigManager::getResolvedSections()
  → FormRenderer::build()
    → FieldFactory → HandlerRegistry → FieldHandler.create()
    → Form::renderWrapped()
      → LayoutManager::wrapBlock()
      → Engine::render('Element/Input')
  → Engine::render('Handlers.tpl')
  ← HTML formuláře
```

#### 2. AI požadavek (HTTP AJAX)
```
Browser POST
  → ApiHandler.php
  → ConfigManager::create()
  → SignedParams::verify()
  → EndpointRegistry::getOrFail('subject')
  → EndpointDefinition::createRequest()
  → AiClient::send(AiRequest)
    → cURL POST api.virtualzoom.com
    ← AiResponse
  → AiResponseRenderer::render(response, 'Components/NestedResult')
  ← JSON { html: "..." }
```

---

## Klíčové návrhové vzory

| Vzor | Kde je použit |
|---|---|
| **Builder** | `PlatformBridgeBuilder` → `PlatformBridge` |
| **Factory** | `FormElementFactory`, `FieldFactory`, statické factory metody výjimek |
| **Registry** | `HandlerRegistry`, `EndpointRegistry` |
| **Strategy** | `FieldHandler` implementace (10 handlerů) |
| **Facade** | `PlatformBridge` (fasáda nad všemi subsystémy) |
| **Singleton** | `EndpointRegistry::getInstance()`, `Translator` |
| **Template Method** | `EndpointDefinition` (abstraktní třída s hooky) |
| **Proxy** | `ElementProxy` obaluje `Element` instanci |
| **Value Object** | `PlatformBridgeConfig` (readonly), `AiResponse` |
| **Chain of Responsibility** | `HandlerRegistry::resolve()` – prochází handlery |
