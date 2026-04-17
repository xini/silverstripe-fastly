<?php

namespace Innoweb\Fastly\Extensions;

use Innoweb\Fastly\Fastly;
use SilverStripe\Assets\Image;
use SilverStripe\Core\Extension;

class FileExtension extends Extension
{
    public function onAfterWrite()
    {
        if (is_a($this->getOwner(), Image::class)) {
            Fastly::flushImage($this->getOwner()->ID);
        } else {
            Fastly::flushFile($this->getOwner()->ID);
        }
    }
}
