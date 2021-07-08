<?php

namespace Innoweb\Fastly\Extensions;

use Innoweb\Fastly\Fastly;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataExtension;

class FileExtension extends DataExtension
{
    public function onAfterWrite()
    {
        if (is_a($this->owner, Image::class)) {
            Fastly::flushImage($this->owner->ID);
        } else {
            Fastly::flushFile($this->owner->ID);
        }
    }
}
