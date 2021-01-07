<?php

class FastlyFileExtension extends DataExtension
{
    public function onAfterWrite()
    {
        if (is_a($this->owner, 'Image')) {
            Fastly::flushImage($this->owner->ID);
        } else {
            Fastly::flushFile($this->owner->ID);
        }
    }
}
