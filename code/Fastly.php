<?php

use GuzzleHttp\Client as GuzzleClient;

class Fastly extends SS_Object implements Flushable
{
    const SITETREE_STRATEGY_SINGLE = 'single';
    const SITETREE_STRATEGY_PARENTS = 'parents';
    const SITETREE_STRATEGY_ALL = 'all';
    const SITETREE_STRATEGY_SMART = 'smart';
    const SITETREE_STRATEGY_EVERYTING = 'everything';

    private static $flush_on_dev_build = true;
    private static $sitetree_flush_strategy = self::SITETREE_STRATEGY_SMART;

    // classes to always flush, e.g. sitemap
    private static $always_include_in_sitetree_flush = [];

    private static $api_url = 'https://api.fastly.com';
    private static $service_id = '';
    private static $api_token = '';
    private static $soft_purge = true;
    private static $verify_ssl = true;
    private static $debug_log = '';

    /**
     * Implementation of Flushable::flush()
     * Is triggered on dev/build and ?flush=1.
     */
    public static function flush()
    {
        if (Config::inst()->get('Fastly', 'flush_on_dev_build')) {
            return static::flushAll();
        }
        return;
    }

    public static function flushAll()
    {
        return static::performFlush();
    }

    public static function flushImage($imageID)
    {
        $image = Image::get()->byID($imageID);
        if ($image && $image->exists()) {
            $success = true;
            // flush the file path
            $success = $success &&  static::performFlush($image->getFilename());
            // flush the file name as surrogate key, see readme
            $success = $success &&  static::performFlush(null, array($image->Name));
            return $success;
        }
        return false;
    }

    public static function flushFile($fileID)
    {
        $file = File::get()->byID($fileID);
        if ($file && $file->exists()) {
            return static::performFlush($file->getFilename());
        }
        return false;
    }

    public static function flushSiteTree($sitetreeID, $smartStrategy = null)
    {
        $surrogateKeys = array();
        $urls = array();

        // load page and determine flush strategy
        $sitetree = SiteTree::get()->byID($sitetreeID);
        if ($sitetree && $sitetree->exists()) {
            // get strategy config
            $strategy = Config::inst()->get('Fastly', 'sitetree_flush_strategy');
            // set smart strategy if set
            if ($strategy == Fastly::SITETREE_STRATEGY_SMART && $smartStrategy) {
                $strategy = $smartStrategy;
            }
            switch ($strategy) {

                case Fastly::SITETREE_STRATEGY_SINGLE:
                    $urls[] = $sitetree->Link();
                    break;

                case Fastly::SITETREE_STRATEGY_PARENTS:
                    $surrogateKeys[] = $sitetree->getPageSurrogateKey();
                    break;

                case Fastly::SITETREE_STRATEGY_ALL:
                    $surrogateKeys[] = 'page-all';
                    break;

                case Fastly::SITETREE_STRATEGY_EVERYTING:
                default:
                    // leave empty to flush everything
                    break;

            }
        }

        // load pages that are always flushed
        $classes = Config::inst()->get('Fastly', 'always_include_in_sitetree_flush');
        if ($classes && count($classes) > 0) {
            foreach ($classes as $class) {
                if (class_exists($class) && is_subclass_of($class, 'DataObject')) {
                    $pages = $class::get();
                    if ($pages && $pages->exists()) {
                        foreach ($pages as $page) {
                            $urls[] = $page->Link();
                        }
                    }
                }
            }
        }

        $success = true;
        if (count($surrogateKeys) > 0) {
            $success = $success && static::performFlush(null, $surrogateKeys);
        }
        if (count($urls) > 0) {
            foreach ($urls as $url) {
                $success = $success && static::performFlush($url);
            }
        }
        if (count($urls) == 0 && count($surrogateKeys) == 0) {
            $success = $success && static::performFlush();
        }

        return $success;
    }

    public static function flushURL($url) {
        if ($url) {
            return static::performFlush($url);
        }
        return false;
    }

    protected static function performFlush($url = null, $surrogateKeys = array())
    {
        if (!static::checkConfig()) {
            return false;
        }

        // check parameters
        if ($url && count($surrogateKeys) > 0) {
            user_error('Fastly::performFlush :: only one of the parameters url OR surrogateKeys is allowed', E_USER_WARNING);
        }

        // get request headers and options
        $options = [
            'verify' => Config::inst()->get(self::class, 'verify_ssl'),
            'headers' => [
                'Fastly-Key' => Config::inst()->get('Fastly', 'api_token'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'http_errors' => false,
        ];

        // get default flush method and options
        $fastlyMethod = 'purge_all';
        $httpMethod = 'POST';
        $requestURL = Controller::join_links(
            Config::inst()->get('Fastly', 'api_url'),
            'service',
            Config::inst()->get('Fastly', 'service_id'),
            $fastlyMethod
        );

        // determinte flush methods
        if ($url) {
            // if a single url is given, use the PURGE method
            $httpMethod = 'PURGE';
            $requestURL = Controller::join_links(
                Director::absoluteURL($url)
            );

            // set to soft purge if configured
            $soft_purge = Config::inst()->get('Fastly', 'soft_purge');
            if ($soft_purge) {
                $options['headers']['Fastly-Soft-Purge'] = 1;
            }
        } else if (count($surrogateKeys) > 0) {
            // if surrogate keys are given, use POST method
            $fastlyMethod = 'purge';
            $httpMethod = 'POST';

            if (count($surrogateKeys) == 1) {
                $requestURL = Controller::join_links(
                    Config::inst()->get('Fastly', 'api_url'),
                    'service',
                    Config::inst()->get('Fastly', 'service_id'),
                    $fastlyMethod,
                    $surrogateKeys[0]
                );
            } else {
                $requestURL = Controller::join_links(
                    Config::inst()->get('Fastly', 'api_url'),
                    'service',
                    Config::inst()->get('Fastly', 'service_id'),
                    $fastlyMethod
                );
                $options['json'] = ['surrogate_keys' => array_values($surrogateKeys)];
            }

            // set to soft purge if configured
            $soft_purge = Config::inst()->get('Fastly', 'soft_purge');
            if ($soft_purge) {
                $options['headers']['Fastly-Soft-Purge'] = 1;
            }
        }

        // enable debug log
        $debug_log = Config::inst()->get('Fastly', 'debug_log');
        if ($debug_log && strlen($debug_log) > 0) {
            $options['debug'] = fopen($debug_log, "w+");
        }

        // create client and make request
        $client = new GuzzleClient();
        $response = $client->request($httpMethod, $requestURL, $options);
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            return true;
        } else {
            user_error('Fastly::performFlush :: '.$response->getStatusCode().': '.$response->getBody(), E_USER_WARNING);
        }
        return false;
    }

    protected static function checkConfig()
    {
        $missing = array();
        // check config
        $api_url = Config::inst()->get('Fastly', 'api_url');
        if (!isset($api_url) || strlen($api_url) < 1) {
            $missing[] = 'Fastly.api_url';
        }
        $service_id = Config::inst()->get('Fastly', 'service_id');
        if (!isset($service_id) || strlen($service_id) < 1) {
            $missing[] = 'Fastly.service_id';
        }
        $api_token = Config::inst()->get('Fastly', 'api_token');
        if (!isset($api_token) || (!is_array($api_token) && strlen((string) $api_token) < 1)) {
            $missing[] = 'Fastly.api_token';
        }
        if (count($missing) > 0) {
			if (!Director::isDev()) {
				SS_Log::log('Fastly:: config parameters missing: ' . implode(', ', $missing), SS_Log::WARN);
			}
            return false;
        }
        return true;
    }
}
