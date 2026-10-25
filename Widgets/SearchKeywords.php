<?php
namespace Piwik\Plugins\NeorGoogleKeywords\Widgets;

use Piwik\Common;
use Piwik\Date;
use Piwik\Period\Factory;
use Piwik\Piwik;
use Piwik\Site;
use Piwik\Widget\Widget;
use Piwik\Widget\WidgetConfig;
use Piwik\Plugins\NeorGoogleKeywords\OperationalException;
use Piwik\Plugins\NeorGoogleKeywords\Repository;
use Piwik\Plugins\NeorGoogleKeywords\Settings;

class SearchKeywords extends Widget
{
    public static function configure(WidgetConfig $config)
    {
        $config->setCategoryId('Referrers_Referrers');
        $config->setSubcategoryId('Referrers_SubmenuSearchEngines');
        $config->setName(\Piwik\Piwik::translate('NeorGoogleKeywords_WidgetTitle'));
        $config->setOrder(35);
        if (!Piwik::isUserHasViewAccess(Common::getRequestVar('idSite', 0, 'int'))) {
            $config->disable();
        }
    }

    public function render()
    {
        $id = Common::getRequestVar('idSite', 0, 'int');
        Piwik::checkUserHasViewAccess($id);
        $view = ['rows' => [], 'message' => '', 'reportUrl' => '', 'from' => '', 'to' => '', 'days' => 0];
        try {
            if (Common::getRequestVar('segment', '', 'string') !== '') {
                throw new OperationalException(\Piwik\Piwik::translate('NeorGoogleKeywords_WidgetSegmentHelp'));
            }
            $property = Settings::property($id);
            $periodName = Common::getRequestVar('period', 'day', 'string');
            $date = Common::getRequestVar('date', 'today', 'string');
            if (!in_array($periodName, ['day', 'week', 'month', 'year', 'range'], true)) {
                throw new OperationalException(\Piwik\Piwik::translate('NeorGoogleKeywords_WidgetPeriodHelp'));
            }
            $timezone = (new Site($id))->getTimezone();
            $period = Factory::build($periodName,
                $periodName === 'range' ? $date : Date::factoryInTimezone($date, $timezone), $timezone);
            $view['from'] = $period->getDateStart()->toString('Y-m-d');
            $view['to'] = $period->getDateEnd()->toString('Y-m-d');
            Settings::range($view['from'], $view['to'], 3660);
            $report = (new Repository())->report($id, $property, $view['from'], $view['to'], null, 0);
            $view['rows'] = $report['rows'];
            $view['days'] = (int) ($report['totals']['days'] ?? 0);
            $view['reportUrl'] = 'index.php?' . http_build_query([
                'module' => 'NeorGoogleKeywords', 'action' => 'index', 'section' => 'detail',
                'idSite' => $id, 'from' => $view['from'], 'to' => $view['to'],
                'sort' => 'clicks', 'direction' => 'desc',
            ], '', '&', PHP_QUERY_RFC3986);
        } catch (\Throwable $e) {
            $view['message'] = $e instanceof OperationalException
                ? $e->getMessage() : \Piwik\Piwik::translate('NeorGoogleKeywords_WidgetLoadFailed');
        }
        return $this->renderTemplate('keywordsWidget', $view);
    }
}
