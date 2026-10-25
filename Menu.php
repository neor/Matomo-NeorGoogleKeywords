<?php
namespace Piwik\Plugins\NeorGoogleKeywords;

use Piwik\Menu\MenuTop;
use Piwik\Menu\MenuAdmin;
use Piwik\Piwik;

class Menu extends \Piwik\Plugin\Menu
{
    public function configureTopMenu(MenuTop $menu)
    {
    }

    public function configureAdminMenu(MenuAdmin $menu)
    {
        if (Piwik::hasUserSuperUserAccess()) {
            $menu->addSystemItem('Google Search Console', $this->urlForAction('index'), 35);
        }
    }
}
