<?php

class FastlySiteTreeExtension extends SiteTreeExtension
{
    public function onAfterPublish(&$original)
    {
        $strategy = Fastly::SITETREE_STRATEGY_SINGLE;
        if (
            $this->owner->URLSegment != $original->URLSegment || // the slug has been altered
            $this->owner->MenuTitle != $original->MenuTitle || // the navigation label has been altered
            $this->owner->Title != $original->Title // the title has been altered
        ) {
            $strategy = Fastly::SITETREE_STRATEGY_ALL;
        } else if (
            $this->owner->getParent()
        ) {
            $strategy = Fastly::SITETREE_STRATEGY_PARENTS;
        }

        Fastly::flushSiteTree($this->owner->ID, $strategy);
    }

    public function onAfterUnpublish()
    {
        Fastly::flushAll();
    }

    public function getPageSurrogateKeys()
    {
        $keys = [$this->owner->getPageSurrogateKey()];
        $parent = $this->owner->getParent();
        while ($parent && is_a($parent, 'SiteTree')) {
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
