<?php

namespace Innoweb\Fastly\Extensions;

use Innoweb\Fastly\Fastly;
use SilverStripe\ORM\DataExtension;

class DMSFileExtension extends DataExtension
{
    public function onAfterWrite()
    {
        Fastly::flushURL($this->owner->DMSDownloadLink());
    }
}
