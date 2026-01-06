<?php

require_once 'config/config.php';
require_once 'autoload.php';

use TemplateEngine\TemplateEngine;
use Handler\FieldFactory;

use App\Bootstrap;
use App\Factory\HandlerRegistryFactory;
use App\Renderer\FormRenderer;
use Parser\Resolver;
use Parser\UrlParameterParser;

Bootstrap::init();

$urlParser = UrlParameterParser::fromGlobals();

$generator = $urlParser->getGenerator();

$registry = HandlerRegistryFactory::create();
$factory = new FieldFactory($registry);

$renderer = new FormRenderer($factory);
$sections = $renderer->build($generator);

$view = new TemplateEngine([
	"base_url" => null,
	"tpl_dir" => VIEW_DIR,
	"cache_dir" => CACHE_DIR,
	"remove_comments" => true,
	"debug" => true,
]);

// Parametry pro hidden inputy
$hiddenParams = array_merge(
	["endpoint" => Resolver::generatorConfigPath($generator, "endpoint")],
	$urlParser->getAllForHiddenInputs()
);

echo $view->assign([
	"title" => Resolver::generatorLabel($generator),
	"data" => $sections,
	"params" => $hiddenParams
])->render("Wrapper");