# AI Form Builder

AI-powered form builder with template engine and field factory.

## Installation

### Via Composer (Private Gitea Repository)

1. Add the repository to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://your-gitea-instance.com/your-username/ai-form-builder.git"
        }
    ],
    "require": {
        "your-vendor/ai-form-builder": "^1.0"
    }
}
```

2. Configure authentication for private repository:

**Option A: Using auth.json (recommended)**
```bash
composer config --global --auth http-basic.your-gitea-instance.com your-username your-token
```

**Option B: Using composer config**
Create or edit `auth.json` in your project or global Composer directory:
```json
{
    "http-basic": {
        "your-gitea-instance.com": {
            "username": "your-username",
            "password": "your-access-token"
        }
    }
}
```

3. Install the package:
```bash
composer require your-vendor/ai-form-builder
```

## Requirements

- PHP >= 8.1
- ext-curl
- ext-json

## Configuration

After installation, copy the configuration files:

```php
// In your project
require_once 'vendor/autoload.php';

use App\Bootstrap;

// Initialize the library
Bootstrap::init();
```

### Configuration Constants

Define these constants before using the library:

```php
define('ROOT_DIR', __DIR__);
define('VIEW_DIR', ROOT_DIR . '/view/');
define('CACHE_DIR', ROOT_DIR . '/cache/');
define('CONFIG_DIR', ROOT_DIR . '/config/');
define('OPENAI_API_KEY', 'your-api-key');
define('URL_SIGNING_KEY', 'your-secret-key');
```

## Usage

```php
<?php

require_once 'vendor/autoload.php';

use App\Bootstrap;
use App\Factory\HandlerRegistryFactory;
use App\Renderer\FormRenderer;
use Handler\FieldFactory;
use TemplateEngine\TemplateEngine;

// Initialize
Bootstrap::init();

// Create form
$registry = HandlerRegistryFactory::create();
$factory = new FieldFactory($registry);
$renderer = new FormRenderer($factory);

// Build form sections
$sections = $renderer->build('your-generator-name');

// Render with template engine
$view = new TemplateEngine([
    "base_url" => null,
    "tpl_dir" => VIEW_DIR,
    "cache_dir" => CACHE_DIR,
    "remove_comments" => true,
    "debug" => true,
]);

echo $view->assign([
    "title" => "Form Title",
    "data" => $sections,
])->render("Wrapper");
```

## Directory Structure

```
├── cache/           # Template cache (auto-generated)
├── config/          # Configuration files
│   ├── json/        # JSON configurations
│   └── translations/ # Translation files
├── docs/            # Documentation
├── src/             # Source code
│   ├── AI/          # AI client and API
│   ├── App/         # Application bootstrap and factories
│   ├── Error/       # Error handling
│   ├── FieldFactory/ # Form field factories
│   ├── Handler/     # Field handlers
│   ├── Parser/      # Configuration parsers
│   ├── TemplateEngine/ # Template engine
│   └── Translator/  # Translation service
└── view/            # Template files
```

## License

Proprietary - All rights reserved.
