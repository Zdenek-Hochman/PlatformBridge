<?php

/**
 * Example configuration file
 * Copy this file to your project and adjust values as needed
 */

// Define paths relative to your project root
define('ROOT_DIR', dirname(__DIR__));

define('VIEW_DIR', ROOT_DIR . '/vendor/your-vendor/ai-form-builder/view/');
define('CACHE_DIR', ROOT_DIR . '/cache/');
define('CONFIG_DIR', ROOT_DIR . '/vendor/your-vendor/ai-form-builder/config/');

// API Keys - CHANGE THESE!
define('OPENAI_API_KEY', 'your-openai-api-key-here');

// Secret key for URL signing - CHANGE THIS to a secure random string (min 32 characters)!
define('URL_SIGNING_KEY', 'change-this-to-secure-random-string-min-32-chars');
