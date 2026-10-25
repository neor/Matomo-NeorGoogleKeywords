<?php
// Standalone check: php plugins/NeorGoogleKeywords/tests/menu.php
namespace Piwik\Menu {
    class MenuTop {
        public array $items = [];
        public function addItem($name, $parent, $url, $order) {
            $this->items[] = compact('name', 'parent', 'url', 'order');
        }
    }
    class MenuAdmin {
        public array $systemItems = [];
        public function addSystemItem($name, $url, $order) {
            $this->systemItems[] = compact('name', 'url', 'order');
        }
    }
}

namespace Piwik\Widget {
    class Widget {
        protected $view;
    }
    class WidgetConfig {
        private string $categoryId = '';
        private string $subcategoryId = '';
        private string $name = '';
        private int $order = 99;
        private bool $widgetizeable = true;
        private bool $enabled = true;

        public function setCategoryId($id) { $this->categoryId = $id; }
        public function getCategoryId() { return $this->categoryId; }
        public function setSubcategoryId($id) { $this->subcategoryId = $id; }
        public function getSubcategoryId() { return $this->subcategoryId; }
        public function setName($name) { $this->name = $name; }
        public function getName() { return $this->name; }
        public function setOrder($order) { $this->order = $order; }
        public function getOrder() { return $this->order; }
        public function setIsNotWidgetizable() { $this->widgetizeable = false; }
        public function isWidgetizeable() { return $this->widgetizeable; }
        public function disable() { $this->enabled = false; }
        public function isEnabled() { return $this->enabled; }
    }
}

namespace Piwik {
    class Common {
        public static function getRequestVar($name, $default = null, $type = null) {
            return $_GET[$name] ?? $default;
        }
        public static function isPhpCliMode(): bool {
            return true;
        }
        public static function generateUniqId(): string {
            return 'testnonce';
        }
    }
    class Config {
        public static function getInstance(): self { static $c; return $c ??= new self(); }
    }
    class Access {
        public static function getInstance(): self { static $a; return $a ??= new self(); }
        public function checkUserHasViewAccess($id) {}
    }
    class Development {
        public static function isEnabled() { return false; }
    }
    class Site {
        private $id;
        public function __construct($id) { $this->id = $id; }
        public function getName() { return 'Site ' . $this->id; }
        public function getTimezone() { return 'UTC'; }
    }
    class Piwik {
        public static function isUserIsAnonymous() { return false; }
        public static function checkUserIsNotAnonymous() {}
        public static function hasUserSuperUserAccess() { return true; }
        public static function isUserHasViewAccess($id) { return true; }
        public static function checkUserHasViewAccess($id) {}
        public static function translate($key, $parameters = []) {
            return \ngkTestTranslate($key, $parameters);
        }
    }
    #[\AllowDynamicProperties]
    class View {
        public static ?\Twig\Environment $twig = null;
        private $template;
        public function __construct($template) { $this->template = $template . '.twig'; }
        public function render() {
            if (self::$twig) {
                return self::$twig->render($this->template, get_object_vars($this));
            }
            return '<nav id="ngk-navigation"></nav><div id="ngk-report"></div>';
        }
    }
}

namespace Piwik\Container {
    class StaticContainer {
        public static function get($name) { return null; }
    }
}

namespace Piwik\Plugins\SitesManager {
    class API {
        public static array $sites = [['idsite' => 3, 'name' => 'Site 3']];
        public static function getInstance() { return new self(); }
        public function getSitesWithAtLeastViewAccess() { return self::$sites; }
    }
}

namespace {
    $root = dirname(__DIR__);
    defined('PIWIK_DOCUMENT_ROOT') || define('PIWIK_DOCUMENT_ROOT', dirname($root, 2));
    require __DIR__ . '/translations.php';
    require dirname($root, 2) . '/vendor/autoload.php';
    require $root . '/Menu.php';
    require $root . '/Categories/GoogleSearchCategory.php';
    require $root . '/Categories/GoogleSearchSubcategory.php';
    require $root . '/Widgets/OverviewReport.php';
    require $root . '/NeorGoogleKeywords.php';

    // 1. Top menu check
    $menu = new \Piwik\Plugins\NeorGoogleKeywords\Menu();
    $topMenu = new \Piwik\Menu\MenuTop();
    $menu->configureTopMenu($topMenu);
    if (!empty($topMenu->items)) {
        throw new \RuntimeException('Top menu should not contain items');
    }
    echo "PASS: top menu has no Google Search item\n";

    // 2. Admin menu check
    $adminMenu = new \Piwik\Menu\MenuAdmin();
    $menu->configureAdminMenu($adminMenu);
    if (count($adminMenu->systemItems) !== 1 || $adminMenu->systemItems[0]['name'] !== 'Google Search Console') {
        throw new \RuntimeException('Admin menu item missing or incorrect');
    }
    echo "PASS: admin menu retained for superusers\n";

    // 3. Category & Subcategory check
    $category = new \Piwik\Plugins\NeorGoogleKeywords\Categories\GoogleSearchCategory();
    if ($category->getId() !== 'NeorGoogleKeywords_MenuGoogleSearch') {
        throw new \RuntimeException('Category ID mismatch');
    }
    if ($category->getOrder() !== 18) {
        throw new \RuntimeException('Category order mismatch');
    }
    if ($category->getIcon() !== 'icon-search') {
        throw new \RuntimeException('Category icon mismatch');
    }
    if ($category->getDisplayName() !== 'Google Search') {
        throw new \RuntimeException('Category display name mismatch');
    }
    echo "PASS: GoogleSearchCategory configured correctly\n";

    $subcategory = new \Piwik\Plugins\NeorGoogleKeywords\Categories\GoogleSearchSubcategory();
    if ($subcategory->getCategoryId() !== 'NeorGoogleKeywords_MenuGoogleSearch'
        || $subcategory->getId() !== 'NeorGoogleKeywords_Overview'
        || $subcategory->getOrder() !== 1) {
        throw new \RuntimeException('Subcategory configuration mismatch');
    }
    echo "PASS: GoogleSearchSubcategory configured correctly\n";

    // 4. Widget configuration & render check
    $widgetConfig = new \Piwik\Widget\WidgetConfig();
    \Piwik\Plugins\NeorGoogleKeywords\Widgets\OverviewReport::configure($widgetConfig);
    if ($widgetConfig->getCategoryId() !== 'NeorGoogleKeywords_MenuGoogleSearch'
        || $widgetConfig->getSubcategoryId() !== 'NeorGoogleKeywords_Overview'
        || $widgetConfig->isWidgetizeable() !== false) {
        throw new \RuntimeException('OverviewReport widget config mismatch');
    }
    echo "PASS: OverviewReport widget configured correctly\n";

    $_GET['idSite'] = '3';
    $widget = new \Piwik\Plugins\NeorGoogleKeywords\Widgets\OverviewReport();
    $output = $widget->render();
    if (!str_contains($output, 'id="ngk-report"') || !str_contains($output, 'id="ngk-navigation"')) {
        throw new \RuntimeException('OverviewReport render output invalid: ' . $output);
    }
    echo "PASS: OverviewReport renders workspace content\n";

    // 5. Asset registration check
    $ref = new \ReflectionClass(\Piwik\Plugins\NeorGoogleKeywords\NeorGoogleKeywords::class);
    $plugin = $ref->newInstanceWithoutConstructor();
    $scripts = [];
    $plugin->getJavaScriptFiles($scripts);
    if (!in_array('plugins/NeorGoogleKeywords/javascripts/menu-nav.js', $scripts, true)
        || !in_array('plugins/NeorGoogleKeywords/javascripts/date-filter.js', $scripts, true)) {
        throw new \RuntimeException('menu-nav.js not registered in JavaScript files');
    }
    echo "PASS: menu-nav.js registered in getJavaScriptFiles\n";

    // 6. JS script content check
    $js = file_get_contents($root . '/javascripts/menu-nav.js');
    if (!str_contains($js, 'data-category-id="NeorGoogleKeywords_MenuGoogleSearch"')
        || !str_contains($js, 'module=NeorGoogleKeywords&action=index')) {
        throw new \RuntimeException('menu-nav.js missing required selector or target URL');
    }
    if (!str_contains($js, 'syncActiveCategory')
        || !str_contains($js, 'category=NeorGoogleKeywords_MenuGoogleSearch')
        || !str_contains($js, 'subcategory=NeorGoogleKeywords_Overview')) {
        throw new \RuntimeException('menu-nav.js missing category synchronization logic');
    }
    if (!str_contains($js, 'buildCoreHomeUrl')
        || !str_contains($js, 'module=CoreHome')
        || !str_contains($js, 'resolveSiteId')
        || !str_contains($js, 'resolvePeriodAndDate')) {
        throw new \RuntimeException('menu-nav.js missing CoreHome navigation and context resolution logic');
    }
    echo "PASS: menu-nav.js contains correct category selector, redirect target and active category sync\n";

    // 7. CoreHome redirect URL construction simulation test via Node
    $nodeCode = '
        const fs = require("fs");
        let code = fs.readFileSync(' . json_encode(str_replace('\\', '/', $root . '/javascripts/menu-nav.js')) . ', "utf8");
        let window = { location: { search: "?module=NeorGoogleKeywords&action=index&idSite=10&from=2026-07-01&to=2026-10-17", hash: "" }, matomo: { idSite: 10 }, addEventListener: () => {} };
        let document = {};
        let $ = function() { return { length: 0, val: () => null, each: () => {}, on: () => {} }; };
        $.on = () => {};
        eval(code.replace("(function ($) {", "(function ($, exports) {").replace("}(jQuery));", "exports.buildCoreHomeUrl = buildCoreHomeUrl; })($, this);"));
        const url1 = this.buildCoreHomeUrl("#?category=General_Visitors&subcategory=General_Overview");
        if (!url1.includes("module=CoreHome") || !url1.includes("idSite=10") || !url1.includes("period=range") || !url1.includes("#?category=General_Visitors")) {
            console.error("FAIL URL1:", url1);
            process.exit(1);
        }
        const url2 = this.buildCoreHomeUrl("#?idSite=7&period=day&date=yesterday&category=General_Visitors&subcategory=General_Overview");
        if (!url2.includes("module=CoreHome") || !url2.includes("idSite=7") || !url2.includes("period=day") || !url2.includes("date=yesterday")) {
            console.error("FAIL URL2:", url2);
            process.exit(2);
        }
        console.log("OK");
    ';
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open('node', $descriptors, $pipes);
    if (!is_resource($process)) {
        throw new \RuntimeException('Unable to spawn node process');
    }
    fwrite($pipes[0], $nodeCode);
    fclose($pipes[0]);
    $nodeOut = trim(stream_get_contents($pipes[1]));
    fclose($pipes[1]);
    fclose($pipes[2]);
    $nodeStatus = proc_close($process);
    if ($nodeStatus !== 0 || $nodeOut !== 'OK') {
        throw new \RuntimeException('buildCoreHomeUrl node simulation failed with status ' . $nodeStatus . ': ' . $nodeOut);
    }
    echo "PASS: buildCoreHomeUrl preserves site, date range, and target hash when leaving plugin view\n";

    // 8. CSS site dropdown styles check
    $css = file_get_contents($root . '/stylesheets/report.css');
    if (!str_contains($css, 'select.is-configured')
        || !str_contains($css, 'select option.is-configured')
        || !str_contains($css, 'var(--ngk-clicks, #1967d2)')) {
        throw new \RuntimeException('report.css missing configured site select styling');
    }
    echo "PASS: report.css contains configured site option and select styling\n";

    // 9. Check top_controls suppression in CSS and JS
    if (!str_contains($css, '.top_controls') || !str_contains($css, 'display: none !important')) {
        throw new \RuntimeException('report.css missing .top_controls hiding rules');
    }
    if (!str_contains($js, 'updateTopControlsVisibility')) {
        throw new \RuntimeException('menu-nav.js missing updateTopControlsVisibility');
    }
    echo "PASS: top_controls suppression configured in report.css and menu-nav.js\n";

    // 10. Compact navigation layout check: site selector on the left followed by nav links
    $indexTwig = file_get_contents($root . '/templates/index.twig');
    $reportTwig = file_get_contents($root . '/templates/report.twig');
    foreach (['index.twig' => $indexTwig, 'report.twig' => $reportTwig] as $name => $content) {
        $navStart = strpos($content, '<nav id="ngk-navigation"');
        $navEnd = strpos($content, '</nav>', $navStart);
        if ($navStart === false || $navEnd === false) {
            throw new \RuntimeException("$name missing #ngk-navigation");
        }
        $navHtml = substr($content, $navStart, $navEnd - $navStart);
        $formPos = strpos($navHtml, '<form');
        $linksPos = strpos($navHtml, '{% for item in ngkNav %}');
        if ($formPos === false || $linksPos === false || $formPos > $linksPos) {
            throw new \RuntimeException("$name must place the site selector form on the left preceding navigation links");
        }
    }
    echo "PASS: compact single-row navigation layout verified in index.twig and report.twig\n";

    echo "All menu checks passed successfully.\n";
}
