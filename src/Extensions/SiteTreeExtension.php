<?php

namespace Innoweb\Fastly\Extensions;

use Innoweb\Fastly\Fastly;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Extension;

class SiteTreeExtension extends Extension
{
    public function onAfterPublish(&$original)
    {
        $strategy = Fastly::SITETREE_STRATEGY_SINGLE;
        if (
            !$original
            || $this->getOwner()->URLSegment != $original->URLSegment // the slug has been altered
            || $this->getOwner()->MenuTitle != $original->MenuTitle // the navigation label has been altered
            || $this->getOwner()->Title != $original->Title // the title has been altered
        ) {
            $strategy = Fastly::SITETREE_STRATEGY_ALL;
        } else if (
            ($parent = $this->getOwner()->getParent())
            && is_a($parent, SiteTree::class)
        ) {
            $strategy = Fastly::SITETREE_STRATEGY_PARENTS;
        }

        Fastly::flushSiteTree($this->getOwner()->ID, $strategy);
    }

    public function onAfterPublishRecursive(&$original)
    {
        $this->getOwner()->onAfterPublish($original);
    }

    public function onAfterUnpublish()
    {
        Fastly::flushAll();
    }

    public function getPageSurrogateKeys()
    {
        $keys = [$this->getOwner()->getPageSurrogateKey()];
        $parent = $this->getOwner()->getParent();
        while ($parent && is_a($parent, SiteTree::class)) {
            $keys[] = $parent->getPageSurrogateKey();
            $parent = $parent->getParent();
        }
        if (Fastly::config()->soft_purge) {
            $keys[] = 'page-all';
        }
        return implode(' ', $keys);
    }

    public function getPageSurrogateKey()
    {
        return 'page-' . $this->getOwner()->ID;
    }
}
