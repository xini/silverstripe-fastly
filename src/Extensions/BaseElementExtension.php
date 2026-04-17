<?php

namespace Innoweb\Fastly\Extensions;

use Innoweb\Fastly\Fastly;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Extension;

class BaseElementExtension extends Extension
{
    public function onAfterPublish(&$original)
    {
        if ($this->getOwner()->hasExtension(\DNADesign\Elemental\TopPage\SiteTreeExtension::class)
            && ($page = $this->getOwner()->getTopPage())
            && $page->exists()
        ) {
            Fastly::flushSiteTree($page->ID, Fastly::SITETREE_STRATEGY_SINGLE);
        }
    }
}
