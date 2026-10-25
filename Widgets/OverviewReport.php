<?php
namespace Piwik\Plugins\NeorGoogleKeywords\Widgets;

use Piwik\Common;
use Piwik\Piwik;
use Piwik\Plugins\NeorGoogleKeywords\Controller;
use Piwik\Widget\Widget;
use Piwik\Widget\WidgetConfig;

class OverviewReport extends Widget
{
    public static function configure(WidgetConfig $config)
    {
        $config->setCategoryId('NeorGoogleKeywords_MenuGoogleSearch');
        $config->setSubcategoryId('NeorGoogleKeywords_Overview');
        $config->setName(Piwik::translate('NeorGoogleKeywords_MenuGoogleSearch'));
        $config->setOrder(1);
        $config->setIsNotWidgetizable();
        $idSite = Common::getRequestVar('idSite', 0, 'int');
        if ($idSite > 0 && !Piwik::isUserHasViewAccess($idSite)) {
            $config->disable();
        }
    }

    public function render()
    {
        $id = Common::getRequestVar('idSite', 0, 'int');
        if ($id > 0) {
            Piwik::checkUserHasViewAccess($id);
        }
        $controller = new Controller();
        $v = $controller->getViewVariables();
        $view = new \Piwik\View('@NeorGoogleKeywords/report');
        foreach ($v as $key => $value) {
            $view->$key = $value;
        }
        return $view->render();
    }
}
