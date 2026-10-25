<?php
namespace Piwik\Plugins\NeorGoogleKeywords\Categories;

use Piwik\Category\Subcategory;
use Piwik\Piwik;

class GoogleSearchSubcategory extends Subcategory
{
    protected $categoryId = 'NeorGoogleKeywords_MenuGoogleSearch';
    protected $id = 'NeorGoogleKeywords_Overview';
    protected $order = 1;

    public function getDisplayName()
    {
        return Piwik::translate('NeorGoogleKeywords_MenuGoogleSearch');
    }
}
