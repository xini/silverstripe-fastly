<?php

namespace Innoweb\Fastly\Extensions;

use Innoweb\Fastly\Fastly;
use SilverStripe\Core\Extension;

class DMSFileExtension extends Extension
{
    public function onAfterWrite()
    {
        Fastly::flushURL($this->getOwner()->DMSDownloadLink());
    }
}
