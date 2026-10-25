<?php
namespace Piwik\Plugins\NeorGoogleKeywords\Categories;

use Piwik\Category\Category;
use Piwik\Piwik;

class GoogleSearchCategory extends Category
{
    protected $id = 'NeorGoogleKeywords_MenuGoogleSearch';
    protected $order = 18;
    protected $icon = 'icon-search';

    public function getDisplayName()
    {
        return Piwik::translate('NeorGoogleKeywords_MenuGoogleSearch');
    }
}
