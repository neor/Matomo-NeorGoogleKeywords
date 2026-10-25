<?php
// Shared setup for standalone tests: real Matomo JSON loader and translator,
// without reading the installation's configuration or connecting to a database.
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

function ngkTestTranslator(): \Piwik\Translation\Translator
{
    static $translator;
    if ($translator === null) {
        $translator = new class(new \Piwik\Translation\Loader\JsonFileLoader(), [dirname(__DIR__) . '/lang']) extends \Piwik\Translation\Translator {
            public function getDefaultLanguage() { return 'ru'; }
        };
    }
    return $translator;
}

function ngkTestTranslate($key, $parameters = [], $language = null): string
{
    return ngkTestTranslator()->translate($key, $parameters, $language);
}

function ngkTestTwigTranslations(\Twig\Environment $twig): void
{
    // Match core/Twig.php: positional Twig arguments become PHP's parameter array.
    $twig->addFilter(new \Twig\TwigFilter('translate', function ($key, ...$parameters) {
        return ngkTestTranslate($key, $parameters);
    }));
}


if (method_exists(\Piwik\Container\StaticContainer::class, 'push')) {
    $container = new \Piwik\Container\Container();
    $container->set(\Piwik\Translation\Translator::class, ngkTestTranslator());
    \Piwik\Container\StaticContainer::push($container);
}
