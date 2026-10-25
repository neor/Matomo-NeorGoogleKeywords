<?php
// No database: exercise the real Matomo controller's initialization/render dispatch.
namespace Piwik {
    class Common {
        public static function getRequestVar($name, $default = null, $type = null) { return $_GET[$name] ?? $default; }
        public static function unsanitizeInputValue($value) { return html_entity_decode($value); }
    }
    class Config {
        public static ?self $instance = null;
        public array $NeorGoogleKeywords = [];
        public static function getInstance(): self { return self::$instance ??= new self(); }
    }
    class Site {
        public static array $created = [];
        private $id;
        public function __construct($id) {
            if (!$id) { throw new \Exception('Missing site'); }
            self::$created[] = $id;
            $this->id = $id;
        }
        public function getName() { return 'Site ' . $this->id; }
        public function getTimezone() { return 'Europe/Tallinn'; }
    }
    class Piwik {
        public static array $allowed = [7, 9];
        public static function checkUserIsNotAnonymous() {}
        public static function checkUserHasViewAccess($id) {
            if (!in_array($id, self::$allowed, true)) { throw new \Exception('Denied site'); }
        }
        public static function hasUserSuperUserAccess() { return false; }
        public static function translate($key, $parameters = []) { return \ngkTestTranslate($key, $parameters); }
    }
    #[\AllowDynamicProperties]
    class View {
        public static \Twig\Environment $twig;
        private $template;
        public function __construct($template) { $this->template = $template . '.twig'; }
        public function render() { return self::$twig->render($this->template, get_object_vars($this)); }
    }
}
namespace Piwik\Container {
    class StaticContainer {
        public static function get($name) { return null; }
    }
}
namespace Piwik\Plugins\SitesManager {
    class API {
        public static array $sites = [['idsite' => 7, 'name' => 'Site 7'], ['idsite' => 9, 'name' => 'Site 9']];
        public static function getInstance() { return new self(); }
        public function getSitesWithAtLeastViewAccess() { return self::$sites; }
    }
}
namespace Piwik\Plugins\NeorGoogleKeywords {
    // Settings rejects the unconfigured property before any database call.
    class Repository {}
    require dirname(__DIR__, 3) . '/vendor/autoload.php';
    class DashboardController extends Controller {
        protected function setBasicVariablesViewAs($view, $viewType = null) {}
        protected function setGeneralVariablesViewAs($view, $viewType) {
            $view->siteName = $this->site->getName();
            $view->idSite = $this->idSite;
            $view->date = $this->strDate;
        }
        public function context() { return [$this->idSite, $this->strDate, $this->date]; }
    }
}
namespace {
    require __DIR__ . '/translations.php';
    $root = dirname(__DIR__, 3);
    $filesystem = new \Twig\Loader\FilesystemLoader();
    $filesystem->addPath(dirname(__DIR__) . '/templates', 'NeorGoogleKeywords');
    $filesystem->addPath($root . '/plugins/Morpheus/templates');
    $loader = new \Twig\Loader\ChainLoader([
        new \Twig\Loader\ArrayLoader([
            'layout.twig' => '<title>{{ title }}</title><div data-site="{{ idSite }}" data-date="{{ date }}">{% block topcontrols %}{% endblock %}{% block content %}{% endblock %}</div>',
        ]),
        $filesystem,
    ]);
    \Piwik\View::$twig = new \Twig\Environment($loader, ['autoescape' => 'html', 'strict_variables' => true]);
    ngkTestTwigTranslations(\Piwik\View::$twig);
    \Piwik\View::$twig->addFunction(new \Twig\TwigFunction('postEvent', fn(...$args) => ''));
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST = [];
    function check($condition, $label) {
        if (!$condition) { throw new \RuntimeException($label); }
        echo "PASS $label\n";
    }
    \Piwik\Config::getInstance()->NeorGoogleKeywords = ['property_7' => 'sc-domain:example.com'];
    foreach ([[], ['idSite' => 7], ['idSite' => 9], ['date' => '2026-09-29'],
        ['period' => 'range', 'date' => '2026-09-01,2026-09-29']] as $params) {
        $_GET = $params + ['section' => 'detail'];
        $controller = new \Piwik\Plugins\NeorGoogleKeywords\DashboardController();
        $html = $controller->index();
        $id = $params['idSite'] ?? 7;
        check(str_contains($html, '<title>Site ' . $id . ' - '), 'real dashboard title has selected site');
        check(str_contains($html, 'data-site="' . $id . '"'), 'dashboard and report site match');
        check(str_contains($html, 'name="category" value="NeorGoogleKeywords_MenuGoogleSearch"'), 'category parameter preserved in form inputs');
        check(str_contains($html, 'name="subcategory" value="NeorGoogleKeywords_Overview"'), 'subcategory parameter preserved in form inputs');
        check(str_contains($html, 'value="7"') && str_contains($html, 'class="is-configured" style="color: var(--ngk-clicks, #1967d2);"'), 'configured site 7 has clicks color');
        check(str_contains($html, 'value="9"') && str_contains($html, 'style="color: var(--ngk-text, #202a42);"'), 'unconfigured site 9 has text color');
        if ($id === 7) {
            check(str_contains($html, 'class="browser-default is-configured" style="color: var(--ngk-clicks, #1967d2);"'), 'select reflects configured site color');
        } else {
            check(str_contains($html, 'class="browser-default"'), 'select for unconfigured site has standard class');
        }
        $vars = $controller->getViewVariables();
        check(str_contains($vars['ngkNav'][0]['url'], 'category=NeorGoogleKeywords_MenuGoogleSearch'), 'category parameter preserved in navigation links');
        check(str_contains($vars['ngkNav'][0]['url'], 'subcategory=NeorGoogleKeywords_Overview'), 'subcategory parameter preserved in navigation links');
        [$contextId, $date, $dateObject] = $controller->context();
        check($contextId === $id && $date !== null, 'site and date context initialized');
        if (isset($params['date'])) {
            check($date === $params['date'], 'requested date or range retained');
        }
    }
    $_GET = ['idSite' => 99, 'section' => 'detail'];
    $controller = new \Piwik\Plugins\NeorGoogleKeywords\DashboardController();
    try {
        $controller->index();
        throw new \RuntimeException('Unauthorized site rendered');
    } catch (\Exception $e) {
        check($e->getMessage() === 'Denied site', 'unauthorized site rejected');
    }
    \Piwik\Plugins\SitesManager\API::$sites = [];
    $_GET = [];
    try {
        (new \Piwik\Plugins\NeorGoogleKeywords\DashboardController())->index();
        throw new \RuntimeException('Empty site list rendered');
    } catch (\Piwik\Plugins\NeorGoogleKeywords\OperationalException $e) {
        check(true, 'no accessible sites handled before rendering');
    }
}
