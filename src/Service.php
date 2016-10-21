<?php

namespace Duplexmedia\PageSpeed;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\Promise;
use Psr\Http\Message\ResponseInterface;

/**
 * The class that wraps the Google PageSpeed API and supports firing
 * off API requests in parallel.
 *
 * @package Duplexmedia\PageSpeed
 */
class Service
{
    /** @var string the PageSpeed URL. */
    static $url = 'https://www.googleapis.com/pagespeedonline/v2/';

    /** @var Client the HTTP client. */
    private $client;

    /**
     * Constructs a new parallel PageSpeed service.
     *
     * @param int $timeout_secs the timeout in seconds. Pass 0 for disabling the timeout.
     */
    public function __construct($timeout_secs = 0) {
        $this->client = new Client([
            'allow_redirects' => true,
            'base_uri' => Service::$url,
            'handler' => HandlerStack::create(new CurlMultiHandler()),
            'timeout' => $timeout_secs
        ]);
    }

    /**
     * Synchronously queries the PageSpeed API and returns the results.
     *
     * @param $urls array|string a URL or a list of URLs to query.
     * @param string $locale the locale for the generated results.
     * @param string $strategy the strategy to analyze by. Can be 'desktop', 'mobile' or 'both'.
     * @return mixed the PageSpeed API results.
     */
    public function query($urls, $locale = 'en_US', $strategy = 'desktop') {
        return $this->queryAsync($urls, $locale, $strategy)->wait();
    }

    /**
     * Asynchronously queries the PageSpeed API and returns a future of the results.
     *
     * @param $urls array|string a URL or a list of URLs to query.
     * @param string $locale the locale for the generated results.
     * @param string $strategy the strategy to analyze by. Can be 'desktop', 'mobile' or 'both'.
     * @return mixed the PageSpeed API results.
     */
    public function queryAsync($urls, $locale = 'en_US', $strategy = 'desktop') {
        if (is_string($urls)) {
            return $this->queryAsync([$urls], $locale, $strategy);
        }

        $strategies = ($strategy == 'both') ?
            array('desktop', 'mobile') :
            array($strategy);

        $requests = [];
        foreach ($strategies as $strategy) {
            foreach ($urls as $url) {
                $requests[$url][$strategy] = $this->client->getAsync('runPagespeed', [
                    'query' => [
                        'url' => $url,
                        'locale' => $locale,
                        'strategy' => $strategy
                    ]
                ]);
            }
        }

        // If Guzzle\Promises supported nested arrays for Promise\settle
        // those two blocks wouldn't be necessary. :(

        $promises = [];
        foreach ($requests as $url => $reqs) {
            $promises[$url] = Promise\settle($reqs)->then(function ($results) {
                $finalResults = [];

                // We get the results as a nested array of the URLs and strategies.
                // Unfold that and parse the body, if available.
                foreach ($results as $strategy => $result) {
                    $res = new \stdClass();
                    $res->success = $result['state'] == Promise\PromiseInterface::FULFILLED;
                    /** @var ResponseInterface $response */
                    $response = $res->success ? $result['value'] : $result['reason']->getResponse();
                    $res->data = json_decode($response->getBody()->getContents(), false);

                    $finalResults[$strategy] = $res;
                }

                return $finalResults;
            });
        }

        return Promise\settle($promises)->then(function ($results) use ($urls) {
            $result = [];
            foreach ($urls as $url) {
                $result[$url] = $results[$url]['value'];
            }
            return $result;
        });
    }
}
