<?php

namespace Innoweb\Fastly\Extensions;

use Innoweb\Fastly\Fastly;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\CMS\Model\SiteTreeExtension as SSSiteTreeExtension;

class SiteTreeExtension extends SSSiteTreeExtension
{
    public function onAfterPublish(&$original)
    {
        $strategy = Fastly::SITETREE_STRATEGY_SINGLE;
        if (
            !$original
            || $this->owner->URLSegment != $original->URLSegment // the slug has been altered
            || $this->owner->MenuTitle != $original->MenuTitle // the navigation label has been altered
            || $this->owner->Title != $original->Title // the title has been altered
        ) {
            $strategy = Fastly::SITETREE_STRATEGY_ALL;
        } else if (
            ($parent = $this->owner->getParent())
            && is_a($parent, SiteTree::class)
        ) {
            $strategy = Fastly::SITETREE_STRATEGY_PARENTS;
        }

        Fastly::flushSiteTree($this->owner->ID, $strategy);
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
        $keys = [$this->owner->getPageSurrogateKey()];
        $parent = $this->owner->getParent();
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
        return 'page-' . $this->owner->ID;
    }
}
