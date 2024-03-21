# New Relic Guzzle Client

This package provides a workaround to resolve performance and memory bottlenecks in Guzzle requests caused by the New Relic Agent's current implementation of metrics tracking middleware for Guzzle 6 clients.

:warning: This library is a *workaround* relying on the current implementation of the newrelic extension!

Test it carefully before to use it with a new extension release.

See Overview section below for more details.

## Installation

```
composer require lcatlett/newrelic-guzzle-client
```
That's all! The script is automatically imported thanks to [Composer autoloader](https://getcomposer.org/doc/04-schema.md#files).

ℹ️ You can also copy the file [`NewRelicGuzzle.php`](NewRelicGuzzle.php) and include directly in your project (this is needed for wordpress sites not using composer)

## Overview

The [newrelic extension](https://github.com/newrelic/newrelic-php-agent) [defines a Php function](https://github.com/newrelic/newrelic-php-agent/blob/3f93ee47f80703d46d8fccd53be7d6b80361a594/agent/lib_guzzle6.c#L433-L461),
a [Guzzle](https://github.com/guzzle/guzzle) middleware to report some metrics on each requests sent from Php.

The current implementation of the New Relic agent extension registers the Guzzle middleware on every new client instance and wraps the request in `curl_exec` rather than `curl_multi_exec` or a stream. This results in performance issues and memory exhaustion as middleware is re-registered on every request which are then wrapped into a single call.

Issues seen on Pantheon customer sites include increased memory usage and request times, particularly for requests intended to be asyncronous actually being blocking calls. These bottlenecks can often be seen in the Pantheon application container php-slow.logs where `newrelic\Guzzle6` is invoked:

```
[20-Mar-2024 01:14:07]  [pool www] pid 10017
script_filename = /code/web//index.php
[0x00007f9e4ee17230] curl_exec() /code/vendor/guzzlehttp/guzzle/src/Handler/CurlHandler.php:40
[0x00007f9e4ee17190] __invoke() newrelic/Guzzle6:1
[0x00007f9e4ee16ff0] newrelic\Guzzle6\{closure}() /code/vendor/guzzlehttp/guzzle/src/Middleware.php:233
[0x00007f9e4ee16f50] GuzzleHttp\{closure}() /code/vendor/guzzlehttp/guzzle/src/HandlerStack.php:71
[0x00007f9e4ee16ec0] __invoke() /code/vendor/guzzlehttp/guzzle/src/Client.php:351
[0x00007f9e4ee16db0] transfer() /code/vendor/guzzlehttp/guzzle/src/Client.php:112
[0x00007f9e4ee16d30] sendAsync() /code/vendor/guzzlehttp/guzzle/src/Client.php:129
[0x00007f9e4ee16cb0] send() /code/web/modules/contrib/search_api_pantheon/src/Services/PantheonGuzzle.php:92
[0x00007f9e4ee16c40] sendRequest() /code/vendor/solarium/solarium/src/Core/Client/Adapter/Psr18Adapter.php:66
[0x00007f9e4ee16ae0] execute() /code/vendor/solarium/solarium/src/Core/Client/Client.php:846
[0x00007f9e4ee16a30] executeRequest() /code/web/modules/contrib/search_api_pantheon/src/Services/SolariumClient.php:61
[0x00007f9e4ee169b0] executeRequest() /code/vendor/solarium/solarium/src/Core/Client/Client.php:817
[0x00007f9e4ee168e0] execute() /code/web/modules/contrib/search_api_pantheon/src/Services/SolariumClient.php:49
[0x00007f9e4ee16860] execute() /code/web/modules/contrib/search_api_solr/src/SolrConnector/SolrConnectorPluginBase.php:974
[0x00007f9e4ee167d0] execute() /code/web/modules/contrib/search_api_solr/src/SolrConnector/SolrConnectorPluginBase.php:938
[0x00007f9e4ee16730] update() /code/web/modules/contrib/search_api_solr/src/Plugin/search_api/backend/SearchApiSolrBackend.php:1129
[0x00007f9e4ee165e0] indexItems() /code/web/modules/contrib/search_api/src/Entity/Server.php:350
[0x00007f9e4ee16510] indexItems() /code/web/modules/contrib/search_api/src/Entity/Index.php:994
[0x00007f9e4ee163c0] indexSpecificItems() /code/web/modules/contrib/search_api/src/Entity/Index.php:930
[0x00007f9e4ee16290] indexItems() /code/web/modules/contrib/search_api/search_api.module:116
```

The above example is when the Pantheon Search API indexes content. An additional example is when the `tmgmt_smartling` module syncs translations:


```
[16-Mar-2024 02:32:50]  [pool www] pid 3593
script_filename = /code/web//index.php
[0x00007f6e9d617100] curl_exec() /code/vendor/guzzlehttp/guzzle/src/Handler/CurlHandler.php:44
[0x00007f6e9d617060] __invoke() /code/vendor/guzzlehttp/guzzle/src/Handler/Proxy.php:28
[0x00007f6e9d616fb0] GuzzleHttp\Handler\{closure}() /code/vendor/guzzlehttp/guzzle/src/Handler/Proxy.php:48
[0x00007f6e9d616f00] GuzzleHttp\Handler\{closure}() newrelic/Guzzle6:1
[0x00007f6e9d616d60] newrelic\Guzzle6\{closure}() /code/vendor/guzzlehttp/guzzle/src/PrepareBodyMiddleware.php:35
[0x00007f6e9d616c80] __invoke() /code/vendor/guzzlehttp/guzzle/src/Middleware.php:31
[0x00007f6e9d616bd0] GuzzleHttp\{closure}() /code/vendor/guzzlehttp/guzzle/src/RedirectMiddleware.php:71
[0x00007f6e9d616b30] __invoke() /code/vendor/guzzlehttp/guzzle/src/Middleware.php:66
[0x00007f6e9d616a90] GuzzleHttp\{closure}() /code/vendor/guzzlehttp/guzzle/src/HandlerStack.php:75
[0x00007f6e9d616a00] __invoke() /code/vendor/guzzlehttp/guzzle/src/Client.php:333
[0x00007f6e9d6168b0] transfer() /code/vendor/guzzlehttp/guzzle/src/Client.php:169
[0x00007f6e9d6167d0] requestAsync() /code/vendor/guzzlehttp/guzzle/src/Client.php:189
[0x00007f6e9d616740] request() /code/vendor/smartling/api-sdk-php/src/BaseApiAbstract.php:491
[0x00007f6e9d6165f0] sendRequest() /code/vendor/smartling/api-sdk-php/src/ProgressTracker/ProgressTrackerApi.php:61
[0x00007f6e9d616560] getToken() /code/web/modules/contrib/tmgmt_smartling/src/Smartling/ConfigManager/FirebaseConfigManager.php:69
[0x00007f6e9d616460] getAvailableConfigs() /code/web/modules/contrib/tmgmt_smartling/tmgmt_smartling.module:886
[0x00007f6e9d6163c0] tmgmt_smartling_page_attachments() /code/web/core/lib/Drupal/Core/Render/MainContent/HtmlRenderer.php:311
[0x00007f6e9d616340] Drupal\Core\Render\MainContent\{closure}() /code/web/core/lib/Drupal/Core/Extension/ModuleHandler.php:388
[0x00007f6e9d616280] invokeAllWith() /code/web/core/lib/Drupal/Core/Render/MainContent/HtmlRenderer.php:312
[0x00007f6e9d6161f0] invokePageAttachmentHooks() /code/web/core/lib/Drupal/Core/Render/MainContent/HtmlRenderer.php:285
```



## Relevant New Relic Issues

There are some longstanding issues in the https://github.com/newrelic/newrelic-php-agent issue queue which are likely related to this problem:

- https://github.com/newrelic/newrelic-php-agent/issues/320
- https://github.com/newrelic/newrelic-php-agent/issues/48
- https://github.com/newrelic/newrelic-php-agent/issues/320
- https://github.com/newrelic/newrelic-php-agent/issues/602
- https://github.com/newrelic/newrelic-php-agent/issues/255
-

Note: This workaround will need to be refactored when Guzzle 7 support is added to the New Relic Agent: https://github.com/newrelic/newrelic-php-agent/pull/498


