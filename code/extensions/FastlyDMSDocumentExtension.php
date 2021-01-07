<?php

class FastlyDMSDocumentExtension extends DataExtension
{
    public function onAfterWrite()
    {
        Fastly::flushURL($this->owner->getLink());
    }
}
