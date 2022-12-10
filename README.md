# PHP Api Toolkit

This toolkit is a wrapper around [Guzzle 7](https://docs.guzzlephp.org/en/stable/) that allow you to work with dedicated clients for a particular remote API. It automatically applies configuration in a seamless manner and keeps code modular and neatly organized. The goal of this library is to remove the need for API specific SDKs and instead provide most functionality you need out of the box, and this includes a comprehensive mocking middleware.

## Installation

The library requires PHP 7.4+ and can be installed using composer:
```sh
composer require nsrosenqvist/php-api-toolkit
```

## Usage

The core concept is that each remote API and its associated configuration and authorization is contained within a single class which inherits `NSRosenqvist\ApiToolkit\ADK`. The ADK class contains the core functionality which the library is utilizing, but a more featured base class comes bundled that provides the functionality that one would commonly need. Therefore, one would most often base one's drivers on `NSRosenqvist\ApiToolkit\StandardApi` instead, and any required customizations can easily be performed by overloading methods.

A simple driver only needs basic client configuration and the means of authorization:

```php
namespace MyApp\Api;

use NSRosenqvist\ApiToolkit\StandardApi;

class Example extends StandardApi
{
    public function __construct()
    {
        parent::__construct([
            'base_uri' => 'https://example.com',
            'http_errors' => false,
        ], [
            'cache' => true,
            'retries' => 3,
            'auth' => [
                getenv('EXAMPLE_USER'),
                getenv('EXAMPLE_PASS')
            ],
        ]);
    }

    // ...
}
```

The first parameter to the parent ADK class is an `array` defining the Guzzle client options, the second one define the default request options. All the default middleware are configured through request options.

Now one can make requests using this configuration by invoking the regular Guzzle methods on the driver class.

```php
$example = new MyApp\Api\Example();

// Synchronous request
$response = $example->get('foo/bar', [
    'query' => ['id' => 100],
]);

// Asynchronous request
$promise = $example->getAsync('foo/bar', [
    'query' => ['id' => 100],
]);

$response = $promise->wait();
```

### Inheritance

No methods are private, since the intention is that child-classes should be able to overload and modify functionality of the base library as needed. The documentation touches on the most common use cases, but please refer to the source code for a comprehensive overview.

#### Handlers

Most often, only two methods need to be overloaded by the driver. The first is `resultHandler` which processes the resulting response, both for synchronous and asynchronous requests. It needs to return a `\Psr\Http\Message\ResponseInterface`, and the example below makes use of the built-in `\NSRosenqvist\ApiToolkit\Result` structure that implements the interface while also providing some additional useful features.

```php
use Psr\Http\Message\ResponseInterface;
use NSRosenqvist\ApiToolkit\Structures\Result;
use NSRosenqvist\ApiToolkit\Structures\ListData;
use NSRosenqvist\ApiToolkit\Structures\ObjectData;

// ...

    public function resultHandler(ResponseInterface $response, array $options): Result
    {
        $type = $this->contentType($response);
        $data = $this->decode($response->getBody(), $type);

        if (is_array($data)) {
            $data = new ListData($data);
        } elseif (is_object($data)) {
            $data = new ObjectData($data);
        }

        return new Result($response, $data);
    }
```

The error handler gets invoked whenever the request fails for some reason. Here you can take any required action, such as logging, and then either return the exception or rethrow it.

```php
    public function errorHandler(RequestException $reason, array $options): RequestException
    {
        $this->logError($reason->getMessage());

        return $reason;
    }
```

Please note that if `http_errors` are set to false (as in the example), then the error handler won't process automatically but must instead be manually called from the result handler. Either one could handle successful requests and errors both in the result handler, or just create a `\GuzzleHttp\Exception\RequestException` and pass it on to the error handler. The built-in recorder middleware makes sure that the original request is accessible through the response.

```php
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use NSRosenqvist\ApiToolkit\Structures\Result;

// ...

    public function resultHandler(ResponseInterface $response, array $options): Result
    {
        $result = new Result($response);

        if ($result->failure()) {
            $request = $response->getRequest();
            $exception = RequestException::create($request, $response);

            $this->errorHandler($exception);
        } else {
            // ...
        }

        return $result;
    }
```

### Manager

A manager class comes built-in, which is meant to be used as a singleton. Use this to easily access APIs from different places in your codebase, preferably through dependency injection or a facade (see the included `LaravelApiProvider` service provider).

```php
require 'vendor/autoload.php';

$manager = new NSRosenqvist\ApiToolkit\Manager();
$manager->registerDriver('example', '\\MyApp\\Api\\Example');

$example = $manager->get('example'); // instance of \MyApp\Api\Example
```

If you register the APIs by their FQN as strings and use an autoloader, then they won't be loaded into memory before you actually make use of them. 

### Chains

One of the big additions of this library is the concept of request chains. If a non-existant property is accessed on the driver, a new request chain is created. These are simple object-oriented representations of a request, suitable for when integrating towards RESTful APIs. All usual Guzzle magic methods are supported, and the only difference is that they don't accept the URI in the method call, instead it gets dynamically resolved from the chain.

```php
$api = $manager->get('example');

$result = $api->customers->get();        // GET /customers
$result = $api->customers->{100}->get(); // GET /customers/100

// The chain object is immutable, so one could even store a specific endpoint
// and make later chained requests based off of it
$settings = $api->settings; // Instance of \NSRosenqvist\ApiToolkit\RequestChain
$bar = $settings->foo->get();
$ipsum = $settings->lorem->get();

// Async calls works just as well
$promise = $api->customers->getAsync();

// There are also helper functions, these three all perform the same request
$deals = $api->deals->get([
    'query' => ['sort' => 'desc'],
]);
$deals = $api->deals->option('query', ['sort' => 'desc'])->get();
$deals = $api->deals->query(['sort' => 'desc'])->get();
$deals = $api->deals->queryArg('sort', 'desc')->get();
```

The chain gets resolved by the initating driver, so to customize how it should be resolved one would overload the `resolve` method.

```php
    // ... 

    public function resolve(RequestChain $chain): string
    {
        $options = $chain->getOptions();

        if (isset($options['api_version'])) {
            return 'v' . $options['api_version'] . '/' . $chain->resolve();
        } else {
            return $chain->resolve();
        }
    }
```

Chains can easily be initated with custom options, if one would like to provide a simple convenience method for defining the target API version, like in the example above, one could create a custom method on the driver returning a new request chain.

```php
use NSRosenqvist\ApiToolkit\RequestChain;

    // ...

    public function version($version = '1.5'): RequestChain
    {
        return $this->chain([
            'api_version' => (string) $version,
        ]);
    }
```

Then one could simply call a specific version of the API by initiating the chain using the method `version`:

```php
$result = $api->version(2)->customers->get();
```

### Mocking

It's easy to test APIs built using this toolkit, either include the mock middleware in your handler stack or base the driver on `\NSRosenqvist\ApiToolkit\StandardApi` to have it included by default. To simply make all requests return 200 (by default), and never be sent away to a remote server, just enable mocking using the request option `mock` or by toggling it for subsequent requests through the method `mocking`.

```php
$api = $manager->get('httpbin');
$api->mocking(true);

$result = $api->status->{500}->get(); // Will return 200 instead of 500 

$api->mocking(false);

$result = $api->status->{500}->get(); // 500 Server Error
```

#### Queue

A better method is to predefine what responses should be returned using the queue system. When responses are queued, the mocking middleware will be enabled automatically and doesn't need to be disabled afterwards.

```php
$api = $manager->get('httpbin');
$api->queue([
    $api->response(200),
    $api->response(201),
    $api->response(202),
]);

$result = $api->status->{500}->get(); // Will return 200
$result = $api->status->{500}->get(); // Will return 201
$result = $api->status->{500}->get(); // Will return 202
```

#### Manifest

Mock manifests are basically router files that define what responses should be given to different requests. It's a quite flexible syntax that is designed to help you write as little configuration as possible. Only PHP and JSON files are supported out of the box, but you can easily enable JSONC or Yaml support by requiring either `adhocore/json-comment` or  `symfony/yaml` in your `composer.json` file.

The basic syntax is as follows:

```jsonc
{
    // Request URI
    "foo/bar": {
        "code": 200,                     // Return code
        "content": "Lorem Ipsum",        // Response body
        "headers": {                     // Response headers
            "Content-Type": "text/plain" // This isn't required, we will attempt to set the mime type automatically
        },
        "reasonPhrase": "OK",            // Reason phrase
        "version": "1.1",                // Protocol version
    }
}
```

`content` can either be a simple string, or a path to directory containing files defining response bodies (set the default request parameter `stubs`). Absolute paths will work as well.

##### Variable matching

One can create multiple response definitions for the same route by wrapping them in an array and using a match statement. In the example below, a request to `foo/bar/lorem` would yield a 200 response, while a request to `foo/bar/ipsum` would yield a 202.

```jsonc
{
    // Named variable in route definition
    "foo/bar/{var}": [
        {
            "code": 200,
            "match": {
                "var": "lorem"
            }
        },
        {
            "code": 202,
            "match": {
                "var": "ipsum"
            }
        }
    ]
}
```

The variable names "query" and "method" are reserved, since they are used for defining conditions for method and query matching.

##### Wildcard patterns

You can even mix and match named variables with glob wildcards:

```jsonc
{
    // Named variable in route definition
    "foo/bar/{var}": [
        // ...
    ],
    "foo/bar/*": {
        "code": 404
    }
}
```

The routes will be matched according to specificity, so named variables will be prioritized before wildcards. Under the hood the wildcard matching uses `fnmatch` and therefore one could use more advanced patterns, but they are not officially supported.

##### Method matching

The method key in the match definition allows one to specify different responses for different methods.

```jsonc
{
    "foo/bar": [
        {
            "code": 200,
            "match": {
                "method": "GET"
            }
        },
        {
            "code": 202,
            "match": {
                "method": "POST"
            }
        }
    ]
}
```

##### Query conditions

In addition to variable matching, one can also test the query parameters (these will also be prioritized in order of specificity).

```jsonc
{
    "foo/bar": [
        {
            "code": 200,
            "match": {
                "query": {
                    "type": "foo"
                }
            }
        },
        {
            "code": 202,
            "match": {
                "query": {
                    "type": "bar"
                }
            }
        }
    ]
}
```

###### Advanced query matching

In addition to direct comparisons, one can instead set any of these special comparison operators:

- `__isset__`: Tests whether the parameter exist.
- `__missing__`: Tests whether the parameter does not exist.
- `__true__`: Tests whether the parameter is truthy (this includes, "yes", "y", 1, etc.).
- `__false__`: Tests whether the parameter is falsey (this includes, "no", "n", 0, etc.).
- `__bool__`: Tests whether the parameter is booly.
- `__string__`: Tests whether the parameter is a string.
- `__numeric__`: Tests whether the parameter is a numeric.
- `__int__`: Tests whether the parameter is an int.
- `__float__`: Tests whether the parameter is a float.
- `__array__`: Tests whether the parameter is an array.

##### Alternative syntaxes

In order to minimize required configuration, some alternative syntaxes are also supported. These will be normalized upon import.

###### Short syntax

```jsonc
{
    "alternative/syntax/short-code": 100,                // Will return a response with status 100
    "alternative/syntax/short-content": "Response body", // Will return a 200 response with custom body
    "alternative/syntax/short-stub": "file.txt",         // Will return a 200 response with body loaded from a file
}
```

###### REST syntax

The REST syntax allows one to define responses according to HTTP method (the method will be expanded into `match->method`, like a regular method match). This syntax also supports short syntax definitions.

```jsonc
{
    "alternative/syntax/rest": {
        "GET": "response body",
        "POST": 204,
        "*": 404 // Catch-all definition is also supported
    }
}
```

### Structures

The library comes with a couple of built-in general data structures that are meant to remove the need for custom data structures. However, this comes with no type validation, but that could easily be implemented in the result handler using something like JSON Schema, if desired.

#### ObjectData

The `NSRosenqvist\ApiToolkit\Structures\ObjectData` class is a general purpose object that provides both property and array access to its members. This means that the following means of using it are equivalent and the code sample will output "true":

```php
use NSRosenqvist\ApiToolkit\Structures\ObjectData;

$data = new ObjectData([
    'foo' => 'bar',
]);

if ($data['foo'] === $data->foo) {
    echo 'true';
}
```

It also implements `Illuminate\Contracts\Support\Arrayable` and `Illuminate\Contracts\Support\Jsonable` which allows it to be easily converted into an array (`toArray`) or JSON (`toJson`).

#### ListData

The `NSRosenqvist\ApiToolkit\Structures\ListData` class is a general purpose list data structure. It supports array access, and like `NSRosenqvist\ApiToolkit\Structures\ObjectData`, it can also be easily converted to an array or JSON.

#### Macros

Both `NSRosenqvist\ApiToolkit\Structures\ObjectData` and `NSRosenqvist\ApiToolkit\Structures\ListData` supports macros. You can define custom methods that will be available on all instances of the classes (or on a child-class if you want to keep macros separate per API driver).

```php
use NSRosenqvist\ApiToolkit\Structures\ObjectData;

ObjectData::macro('html', function() {
    return "<p>{$this->foo}</p>";
});

$data = new ObjectData([
    'foo' => 'bar',
]);

echo $data->html(); // "<p>bar</p>"
```

#### Result

The `NSRosenqvist\ApiToolkit\Structures\Result` class is an implementation of `\Psr\Http\Message\ResponseInterface` that provides direct access to the underlying response body data. It defines a couple of convenience methods, like `success` and `failure`, but mostly it serves as a wrapper around the provided data. If the data is a `NSRosenqvist\ApiToolkit\Structures\ListData`, it can be iterated upon directly, and if `NSRosenqvist\ApiToolkit\Structures\ObjectData`, then it can be accessed directly.

```php
use GuzzleHttp\Psr7\Response;
use NSRosenqvist\ApiToolkit\Structures\Result;
use NSRosenqvist\ApiToolkit\Structures\ListData;
use NSRosenqvist\ApiToolkit\Structures\ObjectData;

// ObjectData
$result = new Result(new Response(/* ... */), new ObjectData([
    'foo' => 'bar',
]));

echo $result->foo; // "bar"

// ListData
$result = new Result(new Response(/* ... */), new ListData([
    new ObjectData(['foo' => 'bar']),
    new ObjectData(['foo' => 'ipsum']),
]));

foreach ($result as $item) {
    echo $result->foo;
    // First iteration: "bar"
    // Second iteration: "ipsum"
}
```

### Included Middleware

`\NSRosenqvist\ApiToolkit\StandardApi` includes a couple of default middleware that are meant to provide common API SDK functionality.

#### CacheMiddleware

The included caching mechanism is a very simple implementation that only makes sure that the same request isn't executed more than once per PHP request process. It only does this for GET requests. The middleware can be toggled on and off by using the option flag `cache`. 

`\NSRosenqvist\ApiToolkit\StandardApi` implements a helper method, named `cache`, that toggles the setting for current request chain.

```php
$api = $manager->get('example');

$customer = $api->cache(true)->customer->{100}->get(); // Fetched from remote
$customer = $api->cache(true)->customer->{100}->get(); // Fetched from cache
$customer = $api->cache(false)->customer->{100}->get(); // Since cache was set to false, it disregarded the cache and fetched it anew from the remote
```

#### MockMiddleware

The functionality of the mocking middleware is described under the section on mocking. It is configured for each request using request options, but this is handled automatically in `\NSRosenqvist\ApiToolkit\StandardApi`. The options it accepts are:

- `mock`: Enable or disable the middleware.
- `manifest`: An object or path to the manifest for mock routing.
- `stubs`: The directory containing files that the manifest can return as content.
- `response`: A directly defined response that will be returned (used by StandardApi's queue functionality).

#### RecorderMiddleware

The recorder middleware simply replaces the response with a `\NSRosenqvist\ApiToolkit\RetrospectiveResponse` which also keeps a reference to the current request. This is in order to be able to do request dependent processing in the result handler. If this isn't required you can simply overload the `getHandlerStack` method and skip including this middleware. 

Similar functionality can be achieved by inspecting the option `endpoint`, which gets automatically set on requests relative to the base URI. Absolute requests will instead have the option `url` set.

#### RetryMiddleware

The retry middleware will perform the request again if the remote returned 429, with a maximun number of retries and exponential delay. This middleware is controlled by the option `max_retries`. It also supports custom retry-decider callbacks.

## License

The library is licensed under [MIT](https://raw.githubusercontent.com/nsrosenqvist/php-api-toolkit/master/LICENSE.md) and certain middlewares are based on ones included by default in Guzzle 7, which are also under [MIT](https://raw.githubusercontent.com/guzzle/guzzle/7.5.0/LICENSE). Some of the data structures are based on ones from the Illuminate libraries, which are also licensed under [MIT](https://raw.githubusercontent.com/illuminate/support/master/LICENSE.md).

## Development

Contributions are very much welcome. If you clone the repo and then run `composer install`, certain hooks should automatically be configured that make sure code standards are upheld before any changes can be pushed. We try to follow PSR-12 and maintain compatibity with PHP 7.4 for now. See [composer.json](https://raw.githubusercontent.com/nsrosenqvist/php-api-toolkit/master/composer.json) for configured commands (e.g. `test`, `lint`, `compat`).
