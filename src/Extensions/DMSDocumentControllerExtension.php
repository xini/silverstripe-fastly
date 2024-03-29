<?php

namespace Innoweb\Fastly\Extensions;

use Innoweb\Fastly\Fastly;
use SilverStripe\Control\Middleware\HTTPCacheControlMiddleware;
use SilverStripe\Core\Extension;

class DMSDocumentControllerExtension extends Extension
{
    public function onBeforeInit()
    {
        // update default page caching
        $this->owner->invokeWithExtensions('updateCacheControl');

        // update vary header
        $this->owner->invokeWithExtensions('updateVaryHeader');
    }

    public function updateCacheControl()
    {
        HTTPCacheControlMiddleware::singleton()->publicCache();
        HTTPCacheControlMiddleware::singleton()->setMaxAge(2592000); // 1 Month
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
