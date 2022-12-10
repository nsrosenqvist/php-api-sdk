<?php

use GuzzleHttp\Psr7\Request;
use NSRosenqvist\ApiToolkit\ResponseFactory;
use NSRosenqvist\ApiToolkit\Tests\InspectableManifest;

it('can load a manifest from file', function () {
    $manifest = new InspectableManifest(MANIFESTS_ROOT . '/simple.json');

    $this->assertNotTrue(is_null($manifest->dump()));
    $this->assertNotTrue($manifest->dump() === new stdClass());
});

it('can load a manifest from object', function () {
    $manifest = new InspectableManifest((object) [
        'status/{id}' => (object) [],
        'status/400' => (object) [],
    ]);

    $this->assertNotTrue(is_null($manifest->dump()));
    $this->assertNotTrue($manifest->dump() === new stdClass());
});

it('can get manifest definition with alternative syntax', function () {
    $factory = new ResponseFactory(STUBS_ROOT);
    $manifest = new InspectableManifest(MANIFESTS_ROOT . '/syntax.yml', $factory);

    $request = new Request('GET', '/alternative/syntax/short-code');
    $response = $manifest->match($request);
    $this->assertEquals(100, $response->getStatusCode());

    $request = new Request('GET', '/alternative/syntax/short-content');
    $response = $manifest->match($request);
    $this->assertEquals('body', $response->getBody()->getContents());

    $request = new Request('GET', '/alternative/syntax/short-stub');
    $response = $manifest->match($request);
    $this->assertEquals('body', trim($response->getBody()->getContents()));

    $request = new Request('GET', '/alternative/syntax/rest');
    $response = $manifest->match($request);
    $this->assertEquals('body', $response->getBody()->getContents());

    $request = new Request('POST', '/alternative/syntax/rest');
    $response = $manifest->match($request);
    $this->assertEquals(204, $response->getStatusCode());

    $request = new Request('PUT', '/alternative/syntax/rest');
    $response = $manifest->match($request);
    $this->assertEquals(404, $response->getStatusCode());
});

it('can access manifest routes through properties', function () {
    $manifest = new InspectableManifest(MANIFESTS_ROOT . '/simple.json');

    $this->assertTrue(is_array($manifest->{'top/{middle}/low'}));
});

it('can order routes in order of specificity', function () {
    $manifest = new InspectableManifest(MANIFESTS_ROOT . '/specificity-routes.yml');
    $routes = $manifest->sortManifestRoutes(array_keys(get_object_vars($manifest->dump())));

    $this->assertEquals([
        'route/specificity/200',
        'route/{detail}/100',
        'route/specificity/{id}',
        'route/specificity/*',
        'route/{detail}/*',
        'route/specificity',
        'route/{detail}',
        'route/*',
    ], $routes);
});

it('can order definitions in order of specificity', function () {
    $manifest = new InspectableManifest(MANIFESTS_ROOT . '/specificity-definitions.yml');
    $definitions = $manifest->sortRouteDefinitions($manifest->get('definitions/specificity'));

    $this->assertEquals([
        'method',
        'query-more',
        'query-less',
        'empty',
    ], array_column($definitions, 'id'));
});

it('can extract route variables', function () {
    $manifest = new InspectableManifest();
    $variables = $manifest->getRouteVariables('/{first}/second/{id}');

    $this->assertEquals([
        'first',
        'id',
    ], $variables);
});

it('can populate route variables', function () {
    $manifest = new InspectableManifest();
    $route = $manifest->populateRouteVariables('/{first}/second/{id}', [
        'first' => 'third',
        'id' => 1,
    ]);

    $this->assertEquals('/third/second/1', $route);
});

it('can be transformed into an array', function () {
    $manifest = new InspectableManifest((object) $array = [
        'array/transform' => [],
    ]);

    $this->assertEquals($array, $manifest->toArray());
});

it('can be transformed into JSON', function () {
    $manifest = new InspectableManifest($object = (object) [
        'json/transform' => [],
    ]);

    $this->assertEquals(json_encode($object), $manifest->toJson());
});

it('can get manifest response with static match', function () {
    $manifest = new InspectableManifest(MANIFESTS_ROOT . '/conditions.yml');
    $request = new Request('GET', '/route/matching/static');
    $response = $manifest->match($request);
    $data = current($response->getHeader('data'));
    $data = json_decode($data);

    $this->assertNotTrue(empty($data));
    $this->assertEquals('static', $data->id);
});

it('can get manifest response with method match', function () {
    $manifest = new InspectableManifest(MANIFESTS_ROOT . '/conditions.yml');
    $request = new Request('PUT', '/route/matching/method');
    $response = $manifest->match($request);
    $data = current($response->getHeader('data'));
    $data = json_decode($data);

    $this->assertNotTrue(empty($data));
    $this->assertEquals('method-put', $data->id);
});

it('can get manifest response with variable match', function () {
    $manifest = new InspectableManifest(MANIFESTS_ROOT . '/conditions.yml');
    $request = new Request('GET', '/route/matching/static/200');
    $response = $manifest->match($request);
    $data = current($response->getHeader('data'));
    $data = json_decode($data);

    $this->assertNotTrue(empty($data));
    $this->assertEquals('var', $data->id);
});

it('can get manifest response with query match', function () {
    $manifest = new InspectableManifest(MANIFESTS_ROOT . '/conditions.yml');
    $request = new Request('GET', '/route/matching/query?' . http_build_query([
        'id' => 'bar'
    ]));
    $response = $manifest->match($request);
    $data = current($response->getHeader('data'));
    $data = json_decode($data);

    $this->assertNotTrue(empty($data));
    $this->assertEquals('query-bar', $data->id);
});

it('can get manifest response with catch-all asterisk', function () {
    $manifest = new InspectableManifest(MANIFESTS_ROOT . '/conditions.yml');
    $request = new Request('GET', '/route/matching/non-existent');
    $response = $manifest->match($request);
    $data = current($response->getHeader('data'));
    $data = json_decode($data);

    $this->assertNotTrue(empty($data));
    $this->assertEquals('glob', $data->id);
});

it('can load object response content', function () {
    $manifest = new InspectableManifest(MANIFESTS_ROOT . '/content.yml');
    $request = new Request('GET', '/content/types/object');
    $response = $manifest->match($request);
    $content_type = current($response->getHeader('Content-Type'));
    $body = $response->getBody()->getContents();
    $data = json_decode($body);

    $this->assertTrue(json_last_error() === JSON_ERROR_NONE);
    $this->assertTrue($content_type === 'application/json');
});

it('can load stub response content', function () {
    $factory = new ResponseFactory(STUBS_ROOT);
    $manifest = new InspectableManifest(MANIFESTS_ROOT . '/content.yml', $factory);
    $request = new Request('GET', '/content/types/file');
    $response = $manifest->match($request);
    $content_type = current($response->getHeader('Content-Type'));
    $body = $response->getBody()->getContents();
    $data = json_decode($body);

    $this->assertTrue(json_last_error() === JSON_ERROR_NONE);
    $this->assertTrue($content_type === 'application/json');
});

it('can load string response content', function () {
    $manifest = new InspectableManifest(MANIFESTS_ROOT . '/content.yml');
    $request = new Request('GET', '/content/types/string');
    $response = $manifest->match($request);

    $this->assertEquals('Foobar', $response->getBody()->getContents());
});

it('can match condition against elements in array', function () {
    $manifest = new InspectableManifest(MANIFESTS_ROOT . '/conditions.yml');
    $request = new Request('GET', '/route/matching/query?id=2');
    $response = $manifest->match($request);
    $id = (json_decode(current($response->getHeader('data'))))->id;

    $this->assertEquals('query-in', $id);
});

it('can process special query conditions', function () {
    $manifest = new InspectableManifest(MANIFESTS_ROOT . '/conditions.yml');

    // __isset__
    $request = new Request('GET', '/query/comparisons/special?isset=true');
    $response = $manifest->match($request);
    $id = (json_decode(current($response->getHeader('data'))))->id;
    $this->assertEquals('isset', $id);

    // __missing__
    $request = new Request('GET', '/query/comparisons/missing');
    $response = $manifest->match($request);
    $id = (json_decode(current($response->getHeader('data'))))->id;
    $this->assertEquals('missing', $id);

    // __true__
    $request = new Request('GET', '/query/comparisons/special?true=1');
    $response = $manifest->match($request);
    $id = (json_decode(current($response->getHeader('data'))))->id;
    $this->assertEquals('booly-true', $id);

    // __false__
    $request = new Request('GET', '/query/comparisons/special?false=0');
    $response = $manifest->match($request);
    $id = (json_decode(current($response->getHeader('data'))))->id;
    $this->assertEquals('booly-false', $id);

    // __bool__
    $request = new Request('GET', '/query/comparisons/special?bool=yes');
    $response = $manifest->match($request);
    $id = (json_decode(current($response->getHeader('data'))))->id;
    $this->assertEquals('booly', $id);

    // __string__
    $request = new Request('GET', '/query/comparisons/special?string=word');
    $response = $manifest->match($request);
    $id = (json_decode(current($response->getHeader('data'))))->id;
    $this->assertEquals('string', $id);

    // __numeric__
    $request = new Request('GET', '/query/comparisons/special?numeric=1.2');
    $response = $manifest->match($request);
    $id = (json_decode(current($response->getHeader('data'))))->id;
    $this->assertEquals('numeric', $id);

    // __int__
    $request = new Request('GET', '/query/comparisons/special?int=10');
    $response = $manifest->match($request);
    $id = (json_decode(current($response->getHeader('data'))))->id;
    $this->assertEquals('int', $id);

    // __float__
    $request = new Request('GET', '/query/comparisons/special?float=1.2');
    $response = $manifest->match($request);
    $id = (json_decode(current($response->getHeader('data'))))->id;
    $this->assertEquals('float', $id);

    // __array__
    $array = http_build_query(['array' => [1, 2, 3]]);
    $request = new Request('GET', '/query/comparisons/special?' . $array);
    $response = $manifest->match($request);
    $id = (json_decode(current($response->getHeader('data'))))->id;
    $this->assertEquals('array', $id);
});
