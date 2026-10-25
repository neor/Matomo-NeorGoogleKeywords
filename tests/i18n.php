<?php
// No database or Google API: catalogs, language switching, Twig and escaping.
require __DIR__ . '/translations.php';

use Piwik\Plugins\NeorGoogleKeywords\Overview;

function i18nCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS $message\n";
}

$root = dirname(__DIR__);
$en = json_decode(file_get_contents($root . '/lang/en.json'), true, 512, JSON_THROW_ON_ERROR)['NeorGoogleKeywords'];
$languages = ['en' => 'Su', 'ru' => 'вс', 'fr' => 'di', 'es' => 'do', 'pt' => 'do', 'de' => 'So', 'it' => 'do'];
foreach ($languages as $language => $sunday) {
    $catalog = json_decode(file_get_contents($root . '/lang/' . $language . '.json'), true, 512, JSON_THROW_ON_ERROR)['NeorGoogleKeywords'];
    i18nCheck(array_keys($en) === array_keys($catalog), "$language catalog has the same keys as English");
    foreach ($en as $key => $value) {
        if ($value === '' || $catalog[$key] === '') {
            throw new RuntimeException('Empty translation: ' . $language . '/' . $key);
        }
        preg_match_all('/%(?:[0-9]+\$)?s/', $value, $englishParameters);
        preg_match_all('/%(?:[0-9]+\$)?s/', $catalog[$key], $translatedParameters);
        sort($englishParameters[0]);
        sort($translatedParameters[0]);
        if ($englishParameters[0] !== $translatedParameters[0]) {
            throw new RuntimeException('Placeholder mismatch: ' . $language . '/' . $key);
        }
    }
}
echo "PASS all translations are nonempty and placeholders match\n";
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach ($files as $file) {
    if (!$file->isFile() || str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR)
        || !in_array($file->getExtension(), ['php', 'twig', 'js'], true)) {
        continue;
    }
    $source = file_get_contents($file->getPathname());
    preg_match_all('/NeorGoogleKeywords_([A-Za-z][A-Za-z0-9]*)/', $source, $keys);
    foreach ($keys[1] as $key) {
        if (!isset($en[$key])) {
            throw new RuntimeException('Missing translation: ' . $key);
        }
    }
    if (preg_match('/[А-Яа-яЁё]/u', $source)) {
        throw new RuntimeException('Hardcoded Russian text: ' . $file->getPathname());
    }
}
echo "PASS production translation keys exist and Russian text is in catalogs\n";

$loader = new Twig\Loader\FilesystemLoader($root . '/templates');
$loader->addPath($root . '/templates', 'NeorGoogleKeywords');
$twig = new Twig\Environment(new Twig\Loader\ChainLoader([
    new Twig\Loader\ArrayLoader(['dashboard.twig' => '{% block topcontrols %}{% endblock %}{% block content %}{% endblock %}']),
    $loader,
]), ['autoescape' => 'html', 'strict_variables' => true]);
ngkTestTwigTranslations($twig);

$query = 'O\'Reilly & "<script>alert(1)</script>"';
$row = ['query_text' => $query, 'page_url' => 'https://example.com/', 'clicks' => 12,
    'impressions' => 1000, 'ctr' => 1.2, 'position' => 2, 'drill_url' => 'index.php',
    'url' => 'index.php', 'google_url' => 'https://www.google.com/search?q=' . rawurlencode($query)];
$days = Overview::series([['data_date' => '2026-09-06', 'clicks' => 12, 'impressions' => 1000]], '2026-09-06', '2026-09-07');
foreach ($days as &$day) { $day['url'] = 'index.php'; } unset($day);
$totals = ['clicks' => 12, 'impressions' => 1000, 'ctr' => 1.2, 'position' => 2,
    'days' => 1, 'capped' => true, 'imported_at' => '2026-09-08 12:00:00',
    'first_date' => '2026-09-06', 'last_date' => '2026-09-06'];
$fixture = [
    'ngkId' => 1, 'ngkSites' => [['idsite' => 1, 'name' => 'Example']], 'ngkAdmin' => true,
    'ngkFrom' => '2026-09-06', 'ngkTo' => '2026-09-07', 'ngkLatest' => '2026-09-07',
    'ngkSort' => 'clicks', 'ngkDirection' => 'desc', 'ngkProperty' => 'sc-domain:example.com',
    'ngkError' => '', 'ngkMessage' => '', 'ngkQuery' => null, 'ngkExpectedDays' => 2,
    'ngkTotals' => $totals, 'ngkRows' => [$row], 'ngkAll' => $totals,
    'ngkHeaders' => [], 'ngkBack' => 'index.php', 'ngkPrevious' => 'index.php', 'ngkNext' => 'index.php',
    'ngkRanges' => ['chartFrom' => '2026-09-06', 'end' => '2026-09-07'],
    'ngkDays' => $days, 'ngkCharts' => [Overview::chart($days, 'clicks'), Overview::chart($days, 'impressions')],
    'ngkTop' => [], 'ngkPost' => 'index.php', 'ngkNonce' => 'fixture',
    'ngkSettingsUrl' => 'index.php', 'ngkJournalUrl' => 'index.php',
    'ngkImportFrom' => '06-09-2026', 'ngkImportTo' => '07-09-2026',
    'ngkProperties' => [['siteUrl' => 'sc-domain:example.com', 'permissionLevel' => 'siteOwner']],
    'ngkStatus' => '', 'ngkLogSource' => '', 'ngkLogCount' => 1,
    'ngkLogs' => [['time' => '2026-09-08 12:00:00', 'message' => $query, 'context' => ['status' => 'success', 'source' => 'web', 'days' => 1]]],
    'ngkKeyError' => '', 'ngkInfo' => ['project' => '', 'email' => '', 'file' => '',
        'googleUrl' => 'https://console.cloud.google.com/', 'connection' => ['time' => '2026-09-08', 'available' => true, 'permission' => 'siteOwner']],
];
foreach ($languages as $language => $sunday) {
    ngkTestTranslator()->setCurrentLanguage($language);
    $nav = [];
    foreach (['stream' => 'Stream', 'detail' => 'Detail', 'pages' => 'Pages', 'import' => 'Import', 'journal' => 'Journal', 'settings' => 'Settings'] as $section => $key) {
        $nav[] = ['key' => $section, 'url' => 'index.php', 'label' => ngkTestTranslate('NeorGoogleKeywords_' . $key)];
    }
    $quickRanges = Overview::quickRanges('2026-01-01', '2026-09-07');
    foreach ($quickRanges as &$range) { $range += ['url' => 'index.php', 'active' => false]; } unset($range);
    foreach (['stream', 'detail', 'pages', 'import', 'journal', 'settings'] as $section) {
        $html = $twig->render('index.twig', $fixture + ['ngkSection' => $section, 'ngkNav' => $nav, 'ngkQuickRanges' => $quickRanges]);
        i18nCheck(!str_contains($html, 'NeorGoogleKeywords_'), "$language $section renders without untranslated keys");
        if ($language !== 'ru') {
            i18nCheck(!preg_match('/[А-Яа-яЁё]/u', $html), "$language $section contains no Russian UI text");
        }
        i18nCheck(!str_contains($html, '<script>'), "$language $section escapes dynamic values");
        if ($section === 'stream') {
            i18nCheck(str_contains($html, '2026-09-06 ' . $sunday . ': '), "$language chart uses translated two-letter weekdays");
        }
        if ($section === 'detail') {
            $dom = new DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $search = (new DOMXPath($dom))->query('//a[@class="ngk-google-link"]')->item(0);
            i18nCheck($search->getAttribute('title') === ngkTestTranslate('NeorGoogleKeywords_SearchGoogle', [$query]), "$language translated attribute retains escaped query");
        }
    }
    $widget = $twig->render('keywordsWidget.twig', ['message' => '', 'from' => '2026-09-06',
        'to' => '2026-09-07', 'days' => 1, 'rows' => [$row], 'reportUrl' => 'index.php']);
    i18nCheck(!str_contains($widget, 'NeorGoogleKeywords_') && !str_contains($widget, '<script>'), "$language widget translates and escapes values");
    try {
        \Piwik\Plugins\NeorGoogleKeywords\Settings::date('invalid');
        throw new RuntimeException('Invalid date accepted');
    } catch (InvalidArgumentException $e) {
        i18nCheck($e->getMessage() === ngkTestTranslate('NeorGoogleKeywords_IsoDateRequired'), "$language PHP errors use current language");
    }
}
ngkTestTranslator()->setCurrentLanguage('nl');
i18nCheck(ngkTestTranslate('NeorGoogleKeywords_Clicks') === 'Clicks', 'unsupported language falls back to English');
$plugin = (new ReflectionClass(\Piwik\Plugins\NeorGoogleKeywords\NeorGoogleKeywords::class))->newInstanceWithoutConstructor();
$keys = [];
$plugin->getClientSideTranslationKeys($keys);
i18nCheck(($plugin->registerEvents()['Translate.getClientSideTranslationKeys'] ?? '') === 'getClientSideTranslationKeys'
    && in_array('NeorGoogleKeywords_ChooseDate', $keys, true), 'date picker translation is registered for JavaScript');
echo "Translation checks passed.\n";
