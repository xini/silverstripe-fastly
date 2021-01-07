<?php

class FastlyContentControllerExtension extends Extension
{
    public function onBeforeInit()
    {
        // update default page caching
        $this->owner->invokeWithExtensions('updateCacheControl');

        // add surrogate keys for all parent pages to be used in tree purging
        $page = $this->owner->data();
        $response = $this->owner->getResponse();
        $response->addHeader('Surrogate-Key', $page->getPageSurrogateKeys());

        // update vary header
        $this->owner->invokeWithExtensions('updateVaryHeader');
    }

    public function updateCacheControl()
    {
        HTTPCacheControl::singleton()->publicCache();
        HTTPCacheControl::singleton()->setMaxAge(600);
        HTTPCacheControl::singleton()->setSharedMaxAge(3600);
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
