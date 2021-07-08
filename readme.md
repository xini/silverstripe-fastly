# Silverstripe Fastly integration

[![Version](http://img.shields.io/packagist/v/innoweb/silverstripe-fastly.svg?style=flat-square)](https://packagist.org/packages/innoweb/silverstripe-fastly)
[![License](http://img.shields.io/packagist/l/innoweb/silverstripe-fastly.svg?style=flat-square)](license.md)

## Overview

Adds [Fastly CDN](https://www.fastly.com/) integration to a Silverstripe Site.

## Requirements

* Silverstripe CMS ^4.4
* Guzzle 6

## Installation

Install the module using composer:
```
composer require innoweb/silverstripe-fastly
```
Then run dev/build.

## Configuration

### Silverstripe

You need to add the following configuration to your environment:

```
Fastly:
  service_id: [your Fastly service ID]
  api_token: [your personal API token]
```

Additionally, the following configuration options are available on the `Fastly` class:

* `soft_purge`: `[true|false]` flag to enable Fastly soft purges, see [https://docs.fastly.com/en/guides/soft-purges](https://docs.fastly.com/en/guides/soft-purges). Defaults to `true`
* `verify_ssl`: `[true|false]` flag whether Guzzle should verify the SSL certificate. Useful for dev environments. Defaults to `true`
* `debug_log`: if you want to debug the Guzzle calls made to the Fastly API you can configure the path to a log file where all Guzzle requests are logged to. Defaults to `''`
* `flush_on_dev_build`: `[true|false]` flag whether all content (pages, images, css, js etc.) should be purged from Fastly. Does not use the `soft_purge` feature. Defaults to `true`
* `sitetree_flush_strategy`: `'[single|parents|all|smart|everything]'` lets you select the purge strategy used when a page is changed and published. Defaults to `'smart'`
  * `single`: only the current page URL is purged
  * `parents`: the current page as well as all its parent pages are purged
  * `all`: all pages are purged
  * `smart`: depending on what fields of the page have changed, `single`, `parents` or `all` is applied
  * `everything`: all content is purged from the fastly cache. That includes pages, images, css, js etc. 
* `always_include_in_sitetree_flush`: array of page type classes that should always be purged when a page is changed, e.g. a sitemap. Defaults to `[]`

#### Image surrogate keys

Because in Silverstripe 4 we still have no way of getting all image variants (see [https://github.com/silverstripe/silverstripe-assets/issues/109](https://github.com/silverstripe/silverstripe-assets/issues/109)), we need to mark all images  and image variants with a Surrogate Key in order to purge them.

Because the filename of all image variants in SS 4.4+ have the variant hash to the original filename, e.g. `my-file__FitWzYwLDYwXQ.jpg`, we can extract the file name without hash and add it as a surrogate key header. The module then purges the original URL of the file as well as the Surrogate Key to clear the original image as well as all variants. (This might purge other images if there are multiple images with the same name in different folders, but I think we can live with that.)

For Apache, add the following snippet to your `.htaccess` file to add the surrogate key:

```
### FASTLY START ###
	<ifModule mod_headers.c>
		<FilesMatch "\.(?i:html|htm|xhtml|js|css|bmp|png|gif|jpg|jpeg|ico|pcx|tif|tiff|svg|au|mid|midi|mpa|mp3|ogg|m4a|ra|wma|wav|cda|avi|mpg|mpeg|asf|wmv|m4v|mov|mkv|mp4|ogv|webm|swf|flv|ram|rm|doc|docx|txt|rtf|xls|xlsx|pages|ppt|pptx|pps|csv|cab|arj|tar|zip|zipx|sit|sitx|gz|tgz|bz2|ace|arc|pkg|dmg|hqx|jar|xml|pdf|gpx|kml)$">
			SetEnvIfNoCase Request_URI "([^\/]*)__[^\.]*(\.[A-Za-z]*)|([^\/]*)(\.[A-Za-z]*)$" FASTLY_FILE_NAME=$1$3$2$4
			Header set Surrogate-Key %{FASTLY_FILE_NAME}e
			Header set Vary Accept-Encoding
		</FilesMatch>
	</ifModule>
### FASTLY END ###
```

### Fastly

#### Conditions

*type*: cache

*title*: not admin, logged in or form

```
!(req.url ~ "^/(Security|admin|dev)") && !(req.http.Cookie ~ "sslogin=") && !(beresp.http.Cache-Control ~ "no-cache") && !(req.url ~ "stage=Stage")
```

*type*: request

*title*: admin or logged in

```
req.url ~ "^/(Security|admin|dev)" || req.http.Cookie ~ "sslogin=" || req.url ~ "stage=Stage"
```

#### Request settings

```
condition: admin or logged in
name: pass if logged in
action: pass
X-Forwarded-For: Append
```

#### Headers

```
condition: not admin, logged in or form
name: set stale while revalidate
type: Cache
action: set
destination: stale_while_revalidate
source: 86400s
```

#### VCL snippets

*type*: recv

*title*: clean up requests

```
# remove cookies for static content
if (req.http.Cookie && req.url ~ "^[^?]*\.(?:js|css|bmp|png|gif|jpg|jpeg|ico|pcx|tif|tiff|au|mid|midi|mpa|mp3|ogg|m4a|ra|wma|wav|cda|avi|mpg|mpeg|asf|wmv|m4v|mov|mkv|mp4|ogv|webm|swf|flv|ram|rm|doc|docx|txt|rtf|xls|xlsx|pages|ppt|pptx|pps|csv|cab|arj|tar|zip|zipx|sit|sitx|gz|tgz|bz2|ace|arc|pkg|dmg|hqx|jar|pdf|woff|woff2|eot|ttf|otf|svg)(\?.*)?$") {
		unset req.http.cookie;
}
# remove common cookies
if (req.http.Cookie) {
	# remove silverstripe cookies
	set req.http.Cookie = regsuball(req.http.Cookie, "(^|;\s*)(cms-panel-collapsed-cms-menu)=[^;]*", "");
	set req.http.Cookie = regsuball(req.http.Cookie, "(^|;\s*)(cms-menu-sticky)=[^;]*", "");
	
	# Remove any Google Analytics based cookies 
	# (removes everything starting with an underscore, which also includes AddThis, DoubleClick and others)
	set req.http.Cookie = regsuball(req.http.Cookie, "(^|;\s*)(_[_a-zA-Z0-9\-]+)=[^;]*", "");
	set req.http.Cookie = regsuball(req.http.Cookie, "(^|;\s*)(utm[a-z]+)=[^;]*", "");
	
	# Remove the Avanser phone tracking cookies
	set req.http.Cookie = regsuball(req.http.Cookie, "(^|;\s*)(AUA[0-9]+)=[^;]*", "");
	
	# Remove the StatCounter cookies
	set req.http.Cookie = regsuball(req.http.Cookie, "(^|;\s*)(sc_is_visitor_unique)=[^;]*", "");

	# Remove a ";" prefix, if present.
	set req.http.Cookie = regsub(req.http.Cookie, "^;\s*", "");

	# remove empty cookie
	if (req.http.Cookie == "") {
		unset req.http.cookie;
	}
}
# remove adwords gclid parameter
set req.url = regsuball(req.url,"\?gclid=[^&]+$",""); # strips when QS = "?gclid=AAA"
set req.url = regsuball(req.url,"\?gclid=[^&]+&","?"); # strips when QS = "?gclid=AAA&foo=bar"
set req.url = regsuball(req.url,"&gclid=[^&]+",""); # strips when QS = "?foo=bar&gclid=AAA" or QS = "?foo=bar&gclid=AAA&bar=baz"
# strip hash, server doesn't need it
if (req.url ~ "\#") {
	set req.url = regsub(req.url, "\#.*$", "");
}
# Strip a trailing questionsmark if it exists
if (req.url ~ "\?$") {
	set req.url = regsub(req.url, "\?$", "");
}
```

*type*: fetch

*title*: remove cookie header from static content

```
if (bereq.url ~ ".*\.(?:css|js)(?=\?|&|$)") { 
	unset beresp.http.set-cookie;
}
if (bereq.url ~ ".*\.(?:bmp|png|gif|jpg|jpeg|ico|pcx|tif|tiff|au|mid|midi|mpa|mp3|ogg|m4a|ra|wma|wav|cda|avi|mpg|mpeg|asf|wmv|m4v|mov|mkv|mp4|ogv|webm|swf|flv|ram|rm)(?=\?|&|$)") {
	unset beresp.http.set-cookie;
}
if (bereq.url ~ ".*\.(?:doc|docx|txt|rtf|xls|xlsx|pages|ppt|pptx|pps|csv|cab|arj|tar|zip|zipx|sit|sitx|gz|tgz|bz2|ace|arc|pkg|dmg|hqx|jar|pdf)(?=\?|&|$)") {
	unset beresp.http.set-cookie;
}
if (bereq.url ~ ".*\.(?:woff|woff2|eot|ttf|otf|svg)(?=\?|&|$)") {
	unset beresp.http.set-cookie;
}
```

*type*: deliver

*title*: remove session cookie for non-form pages

```
if (
	(resp.http.Content-Type ~ "^text/html") &&
	(req.http.Cookie) &&
	!(req.url ~ "^/(Security|admin|dev)") &&
	!(req.url ~ "stage=") &&
	!(req.method == "POST") &&
	!(req.http.Cookie ~ "sslogin=") &&
	!(resp.http.Chache-Control ~ "no-store")
) {
	set resp.http.set-cookie = "PHPSESSID=deleted; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Path=/; HttpOnly";
}
```


### GEO fencing

To serve different content based on the user's location add the following VCL snippet to your Fastly configuration:

```
# sub routine: recv(vcl_recv)
set req.http.client-geo-country = client.geo.country_code;
set req.http.client-geo-continent = client.geo.continent_code;
set req.http.client-geo-city = client.geo.city
set req.http.client-geo-longitude = client.geo.longitude
set req.http.client-geo-latitude = client.geo.latitude
```
You can choose any or all of the lines above to add to your config, depending on what you need. 

For continent and country codes, automatic VARY headers are added to all page requests. You can override this behaviour in your own page controllers using the `updateVaryHeader` method.

See [https://developer.fastly.com/reference/vcl/variables/geolocation/](https://developer.fastly.com/reference/vcl/variables/geolocation/) and [https://developer.fastly.com/solutions/patterns/geofence/](https://developer.fastly.com/solutions/patterns/geofence/) for further information.


## License

BSD 3-Clause License, see [License](license.md)
