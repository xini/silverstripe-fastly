<?php

namespace Innoweb\Fastly\Extensions;

use SilverStripe\Control\Middleware\HTTPCacheControlMiddleware;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Extension;
use SilverStripe\Security\BasicAuth;

class ContentControllerExtension extends Extension
{
    public function onBeforeInit()
    {
        // update default page caching
        $this->owner->invokeWithExtensions('updateCacheControl');

        // add surrogate keys for all parent pages to be used in tree purging
        $page = $this->owner->data();
        if ($page->hasMethod('getPageSurrogateKeys')) {
            $response = $this->owner->getResponse();
            $response->addHeader('Surrogate-Key', $page->getPageSurrogateKeys());
        }

        // update vary header
        $this->owner->invokeWithExtensions('updateVaryHeader');
    }

    public function updateCacheControl()
    {
        if (BasicAuth::config()->get('entire_site_protected')
            || Environment::getEnv(BasicAuth::USE_BASIC_AUTH)
        ) {
            HTTPCacheControlMiddleware::singleton()
                ->privateCache()
                ->setMaxAge(600); // 10 minutes
        } else {
            HTTPCacheControlMiddleware::singleton()
                ->publicCache()
                ->setMaxAge(600) // 10 minutes
                ->setSharedMaxAge(3600); // 1 hour
        }
    }

    public function updateVaryHeader()
    {
        // add vary if geo ip request headers found
        $request = $this->owner->getRequest();
        $response = $this->owner->getResponse();

        if ($request->getHeader('client-geo-country')) {
            $response->addHeader('Vary',  $response->getHeader('Vary') . ' client-geo-country');
        }
        if ($request->getHeader('client-geo-continent')) {
            $response->addHeader('Vary',  $response->getHeader('Vary') . ' client-geo-continent');
        }
    }
}
