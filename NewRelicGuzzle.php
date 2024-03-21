<?php

/**
 * This file is a copy of what is dynamically evaluated in the newrelic php extension.
 * https://github.com/newrelic/newrelic-php-agent/blob/3f93ee47f80703d46d8fccd53be7d6b80361a594/agent/lib_guzzle6.c#L433-L461
 * It defines a middleware function intended for Guzzle6.
 * It's safe to include this file even if newrelic extension is not installed,
 * because this function is called  only by the extension itself.
 */
namespace newrelic\Guzzle6;

use Psr\Http\Message\RequestInterface;

if (!function_exists('newrelic\\Guzzle6\\middleware')) {
    function middleware(callable $handler)
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            /*
            * Start by adding the outbound CAT/DT/Synthetics headers to the request.
            */
            foreach (newrelic_get_request_metadata('Guzzle 6') as $k => $v) {
                $request = $request->withHeader($k, $v);
            }

            /*
             * NewRelic creates an object responsible to send data according to an input request.
             * Create a copy of the original request for this purpose.
             * Intercept initial request from New Relic and resolve the host according to HOST http header.
             * This allows for extending the Guzzle middleware as expected.
             */

            if($request->hasHeader('host')) {
                /**
                 * Take the first 'host' header.
                 * /!\ PSR doesn't require to start headers at index 0
                 */
                $headers = $request->getHeader('host');
                $requestCopy = $request->withUri(
                    $request->getUri()->withHost(reset($headers))
                );
            } else {
                $requestCopy = $request;
            }

            /*
            * Set up the RequestHandler object and attach it to the promise so that
            * we create an external node and deal with the CAT headers coming back
            * from the far end.
            */
            $rh = new RequestHandler($requestCopy);
            $promise = $handler($request, $options);
            $promise->then([$rh, 'onFulfilled'], [$rh, 'onRejected']);

            return $promise;
        };
    }
}
