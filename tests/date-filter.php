<?php
// Standalone check: php plugins/NeorGoogleKeywords/tests/date-filter.php
namespace Piwik\Plugin {
    class Controller {}
}

namespace {
    require __DIR__ . '/translations.php';
    $root = dirname(__DIR__);
    require dirname($root, 2) . '/vendor/autoload.php';
    require $root . '/Settings.php';
    require $root . '/Controller.php';

    $controller = new \Piwik\Plugins\NeorGoogleKeywords\Controller();
    $input = new \ReflectionMethod($controller, 'reportDateInput');
    $input->setAccessible(true);
    foreach (['29-09-2026' => '2026-09-29', '2026-09-29' => '2026-09-29', '29-02-2024' => '2024-02-29'] as $value => $expected) {
        $_GET = ['from' => $value];
        if ($input->invoke($controller, 'from', '2026-09-01') !== $expected) {
            throw new \RuntimeException('Incorrect date conversion');
        }
    }
    $_GET = [];
    if ($input->invoke($controller, 'from', '2026-09-01') !== '2026-09-01') {
        throw new \RuntimeException('Default date changed');
    }
    foreach (['31-09-2026', '29-02-2025', '01-13-2026', '1-9-2026', '2026-09-31', '', '<script>', ['29-09-2026']] as $value) {
        $_GET = ['from' => $value];
        try {
            $input->invoke($controller, 'from', '2026-09-01');
        } catch (\InvalidArgumentException $e) {
            continue;
        }
        throw new \RuntimeException('Invalid date accepted');
    }

    $loader = new \Twig\Loader\FilesystemLoader($root . '/templates');
    $loader->addPath($root . '/templates', 'NeorGoogleKeywords');
    $twig = new \Twig\Environment($loader, ['autoescape' => 'html']);
    ngkTestTwigTranslations($twig);
    $html = $twig->render('detail.twig', [
        'ngkFrom' => '2026-09-01', 'ngkTo' => '2026-09-29', 'ngkQuery' => null,
        'ngkRows' => [], 'ngkHeaders' => [],
    ]);
    $dom = new \DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new \DOMXPath($dom);
    foreach (['from' => '01-09-2026', 'to' => '29-09-2026'] as $name => $expected) {
        $field = $xpath->query('//input[@name="' . $name . '"]')->item(0);
        if (!$field || $field->getAttribute('type') !== 'text' || $field->getAttribute('value') !== $expected) {
            throw new \RuntimeException('Incorrect displayed date');
        }
    }
    foreach (['23-09-2026', '2026-09-23'] as $value) {
        $_GET = ['import_from' => '01-01-2000'];
        $_POST = ['import_from' => $value, 'import_to' => '29-09-2026'];
        $from = $input->invoke($controller, 'import_from', '2026-09-01', true);
        $to = $input->invoke($controller, 'import_to', '2026-09-01', true);
        if ($from !== '2026-09-23' || $to !== '2026-09-29') {
            throw new \RuntimeException('Incorrect POST import dates');
        }
        \Piwik\Plugins\NeorGoogleKeywords\Settings::range($from, $to, 7);
        try {
            \Piwik\Plugins\NeorGoogleKeywords\Settings::range('2026-09-22', $to, 7);
            throw new \RuntimeException('Eight-day import accepted');
        } catch (\InvalidArgumentException $e) {
            // The seven-day web import limit still applies.
        }
    }
    $html = $twig->render('import.twig', [
        'ngkImportFrom' => '23-09-2026', 'ngkImportTo' => '29-09-2026', 'ngkLatest' => '2026-09-29',
    ]);
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new \DOMXPath($dom);
    foreach (['import_from' => '23-09-2026', 'import_to' => '29-09-2026'] as $name => $expected) {
        $field = $xpath->query('//input[@name="' . $name . '"]')->item(0);
        if (!$field || $field->getAttribute('value') !== $expected
            || $field->getAttribute('class') !== 'ngk-date-input'
            || $field->getAttribute('data-max-date') !== '2026-09-29') {
            throw new \RuntimeException('Incorrect import date picker field');
        }
    }
    echo "PASS: report and import dates, ISO compatibility, POST input, picker limits and validation.\n";
}
