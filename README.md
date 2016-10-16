# parallel-pagespeed
A small PHP module that queries Google's PageSpeed API in a parallel way.

Use it like this:
```php
use Duplexmedia\PageSpeed\Service;

/**
 * Gets the pagespeed ratings for the given URLs.
 *
 * @param array|string $urls a URL or an array of URLs (you can pass both)
 */
function query_pagespeed($urls) {
    // Create a new PageSpeed client
    $service = new Service();
    
    // Request the pagespeed ratings either synchronous (blocking fashion)...
    $results = $service->query($urls, 'en_US', 'both');
    // ... or asynchronous, using Guzzle Promises (nonblocking fashion)
    $promise = $service->queryAsync($urls, 'en_US', 'both');
    
    // In the asnyc case, you can use the results either by calling
    // ->wait() or by chaining a computation using ->then(...).
    // See https://github.com/guzzle/promises.
}
```
