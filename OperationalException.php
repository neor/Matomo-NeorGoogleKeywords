<?php
namespace Piwik\Plugins\NeorGoogleKeywords;

/** Safe, user-facing error; never include a remote response or secret. */
class OperationalException extends \RuntimeException
{
}
