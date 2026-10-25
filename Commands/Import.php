<?php
namespace Piwik\Plugins\NeorGoogleKeywords\Commands;

use Piwik\Plugins\NeorGoogleKeywords\Importer;
use Piwik\Plugins\NeorGoogleKeywords\Settings;

class Import extends \Piwik\Plugin\ConsoleCommand
{
    protected function configure()
    {
        $this->setName('ngk:import');
        $this->setAliases(['gsc-lite:import']);
        $this->setDescription(\Piwik\Piwik::translate('NeorGoogleKeywords_CliDescription'));
        $this->addRequiredValueOption('idsite', null, \Piwik\Piwik::translate('NeorGoogleKeywords_CliSiteId'));
        $this->addRequiredValueOption('from', null, \Piwik\Piwik::translate('NeorGoogleKeywords_CliFrom'));
        $this->addRequiredValueOption('to', null, \Piwik\Piwik::translate('NeorGoogleKeywords_CliTo'));
    }

    protected function doExecute(): int
    {
        $input = $this->getInput();
        $id = (int) $input->getOption('idsite');
        if ($id < 1 || !$input->getOption('from')) {
            throw new \InvalidArgumentException(\Piwik\Piwik::translate('NeorGoogleKeywords_CliArgumentsRequired'));
        }
        $to = $input->getOption('to') ?: Settings::latest()->format('Y-m-d');
        $days = (new Importer())->run($id, (string) $input->getOption('from'), (string) $to);
        $this->getOutput()->writeln(\Piwik\Piwik::translate('NeorGoogleKeywords_CliImportedDays', [$days]));
        return 0;
    }
}
