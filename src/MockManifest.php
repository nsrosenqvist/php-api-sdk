<?php

namespace NSRosenqvist\ApiToolkit;

use CastToType;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use NSRosenqvist\ApiToolkit\Parsers\Json;
use NSRosenqvist\ApiToolkit\Parsers\Jsonc;
use NSRosenqvist\ApiToolkit\Parsers\ParserInterface;
use NSRosenqvist\ApiToolkit\Parsers\Php;
use NSRosenqvist\ApiToolkit\Parsers\Yaml;
use NSRosenqvist\ApiToolkit\ResponseFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use stdClass;

class MockManifest implements Arrayable, Jsonable
{
    /**
     * Manifest data store
     *
     * @var object
     */
    protected $manifest;

    /**
     * Response factory
     *
     * @var ResponseFactoryInterface
     */
    protected $factory;

    /**
     * @param object|array|string $manifest
     * @param ResponseFactoryInterface|null $factory
     */
    public function __construct($manifest = null, ?ResponseFactoryInterface $factory = null)
    {
        $this->factory = $factory ?? new ResponseFactory();
        $this->manifest = $this->loadManifest($manifest ?: new stdClass());
    }

    /**
     * Load and process specified manifest
     *
     * @param string|array|object $manifest
     * @return object
     */
    protected function loadManifest($manifest): object
    {
        // Strings can either be a filepath or an encoded string
        if (is_string($manifest)) {
            $manifest = trim($manifest);

            // Filesystem path
            if (@file_exists($manifest)) {
                $format = pathinfo($manifest, PATHINFO_EXTENSION);
                $parser = $this->getParser($format);

                if (is_null($parser)) {
                    throw new \InvalidArgumentException("Unsupported mock manifest format: {$format}");
                }

                try {
                    $manifest = $parser->parse($manifest);
                } catch (\Exception $e) {
                    throw new \InvalidArgumentException("Failed to load mock manifest: {$e->getMessage()}");
                }
            // Load string
            } else {
                try {
                    $manifest = (new Jsonc())->parse($manifest);
                } catch (\Exception $e) {
                    throw new \InvalidArgumentException("Failed to load mock manifest: {$e->getMessage()}");
                }
            }
        // Associative arrays are treated as objects
        } elseif (is_array($manifest) && is_assoc($manifest)) {
            $manifest = to_object($manifest);
        // Manifest can also be defined as object,
        // but other types aren't supported
        } elseif (! is_object($manifest)) {
            throw new \InvalidArgumentException('Mock manifest is of invalid type' . gettype($manifest));
        }

        // Normalize manifest so that all definitions are arrays
        $manifest = $this->normalizeManifest($manifest);

        // Sort routes and definitions in order of specificity
        $manifest = $this->sortManifest($manifest);

        return $manifest;
    }

    /**
     * Get manifest parser
     *
     * @param string $format
     * @return ParserInterface|null
     */
    protected function getParser(string $format): ?ParserInterface
    {
        switch (strtolower($format)) {
            case 'json':
                return new Json();
            case 'jsonc':
                return new Jsonc();
            case 'php':
                return new Php();
            case 'yml':
            case 'yaml':
                return new Yaml();
            default:
                return null;
        }
    }

    /**
     * Expand short manifest syntax
     *
     * @param mixed $definition
     * @return array|object
     */
    protected function expandShortSyntax($definition)
    {
        // Depending on format, we can get either an empty
        // string (yaml) or an empty object (json) if definition
        // is "empty", so we must normalize cases when the definition
        // is neither an object or an array
        if (! is_array($definition) && ! is_object($definition)) {
            // Empty becomes [object]
            if (empty($definition)) {
                $definition = new stdClass();
            // String gets mapped to [object]->content
            } elseif (is_string($definition)) {
                $definition = (object) [
                    'content' => $definition,
                ];
            // Int gets mapped to [object]->code
            } elseif (is_numeric($definition)) {
                $definition = (object) [
                    'code' => $definition,
                ];
            }
        }

        return $definition;
    }

    /**
     * Normalize manifest
     *
     * @param object $manifest
     * @return object
     */
    protected function normalizeManifest(object $manifest): object
    {
        // Normalize  so that all definitions are arrays
        foreach ($manifest as $pattern => $definitions) {
            // Normalize definition syntax
            $definitions = $this->expandShortSyntax($definitions);

            // Make sure all definitions are contained within a top-level array
            if (! is_array($definitions)) {
                $properties = get_object_vars($definitions);
                $keys = array_map('strtoupper', array_keys($properties));
                $methods =  ['GET', 'HEAD', 'OPTIONS', 'TRACE', 'DELETE', 'PUT', 'PATCH', 'POST', '*'];

                // If defined with alternate syntax, remove object properties
                // and set them as match->method
                if (! empty(array_intersect($keys, $methods))) {
                    foreach ($definitions as $method => &$definition) {
                        // Normalize definition syntax
                        $definition = $this->expandShortSyntax($definition);

                        // Ignore method if catch-all
                        if ($method === '*') {
                            if (isset($definition->match)) {
                                unset($definition->match->method);
                            }
                            continue;
                        }

                        // Set method in match query
                        if (! isset($definition->match)) {
                            $definition->match = new stdClass();
                        }

                        $definition->match->method = $method;
                    }

                    $definitions = array_values(get_object_vars($definitions));
                } else {
                    $definitions = [$definitions];
                }
            }

            $manifest->{$pattern} = $definitions;
        }

        return $manifest;
    }

    /**
     * Sort manifest entires
     *
     * @param object $manifest
     * @return object
     */
    protected function sortManifest(object $manifest): object
    {
        $properties = get_object_vars($manifest);
        $routes = array_keys($properties);
        $sorted = new stdClass();

        foreach ($this->sortManifestRoutes($routes) as $route) {
            $sorted->{$route} = $this->sortRouteDefinitions($manifest->{$route});
        }

        return $sorted;
    }

    /**
     * Sort manifest routes according to route specificity
     *
     * @param array $routes
     * @return array
     */
    protected function sortManifestRoutes(array $routes): array
    {
        // Get all route patterns and split them
        $exploded = array_map(function ($route) {
            return explode('/', $route);
        }, $routes);

        // Sort according to depth and each depth by name,
        // the more specific patterns are first
        usort($exploded, function ($a, $b) {
            $countA = count($a);
            $countB = count($b);

            if (($countA = count($a)) == ($countB = count($b))) {
                $nameA = $a[$countA - 1];
                $nameB = $b[$countB - 1];
                $idPosA = ($pos = strpos($nameA, '{')) !== false ? $pos : -1;
                $idPosB = ($pos = strpos($nameB, '{')) !== false ? $pos : -1;
                $wildPosA = ($pos = strpos($nameA, '*')) !== false ? $pos : -1;
                $wildPosB = ($pos = strpos($nameB, '*')) !== false ? $pos : -1;

                if ($idPosA >= 0 && $idPosB >= 0) {
                    return 0;
                } elseif ($wildPosA >= 0 || $wildPosB >= 0) {
                    return $wildPosA < $wildPosB ? -1 : 1;
                } elseif ($idPosA >= 0 || $idPosB >= 0) {
                    return $idPosA < $idPosB ? -1 : 1;
                }

                return strnatcasecmp($nameA, $nameB) * -1;
            }

            return $countA < $countB ? 1 : -1;
        });

        // Reassemble the patterns
        return array_map(function ($split) {
            return implode('/', $split);
        }, $exploded);
    }

    /**
     * Sort route definitions according to match statement specificity
     *
     * @param array $definitions
     * @return array
     */
    protected function sortRouteDefinitions(array $definitions): array
    {
        // sort definitions in order of specificitiy
        if (count($definitions) > 1) {
            usort($definitions, function ($a, $b) {
                $matchA = ! empty($a->match);
                $matchB = ! empty($b->match);
                $methodA = ($matchA && ! empty($a->match->method));
                $methodB = ($matchB && ! empty($b->match->method));
                $queryA = ($matchA && ! empty($a->match->query));
                $queryB = ($matchB && ! empty($b->match->query));

                if ($matchA !== $matchB) {
                    return $matchA ? -1 : 1;
                } elseif ($methodA !== $methodB) {
                    return $methodA ? -1 : 1;
                } elseif ($queryA !== $queryB) {
                    return $queryA ? -1 : 1;
                } elseif ($queryA && $queryB) {
                    $countA = count(get_object_vars($a->match->query));
                    $countB = count(get_object_vars($b->match->query));

                    if ($countA !== $countB) {
                        return $countA < $countB ? 1 : -1;
                    }

                    return 0;
                }

                return 0;
            });
        }

        return $definitions;
    }

    /**
     * Populate route variables with specified strings
     *
     * @param string $route
     * @param array<string> $variables
     * @return string
     */
    protected function populateRouteVariables(string $route, array $variables): string
    {
        if (empty($variables)) {
            return $route;
        }

        $values = array_values($variables);
        $needles = array_map(function ($name) {
            return '{' . $name . '}';
        }, array_keys($variables));

        return str_replace($needles, $values, $route);
    }

    /**
     * Extract named variables from route
     *
     * @param string $route
     * @return array
     */
    protected function getRouteVariables(string $route): array
    {
        $matches = [];
        $variables = [];

        preg_match_all('#\{\w+\}#', $route, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        foreach ($matches as $match) {
            $name = substr($match[0][0], 1, -1);

            if (is_numeric($name)) {
                throw new \InvalidArgumentException("Route variable name can not be a number: $name ($route)");
            }

            if (in_array($name, $variables)) {
                throw new \LogicException("More than one occurrence of variable name \"$name\"");
            }

            $variables[] = $name;
        }

        return $variables;
    }

    /**
     * Evaluate query condition
     *
     * @param array $conditions
     * @param array $query
     * @return boolean
     */
    protected function evaluateCondition(array $conditions, array $query): bool
    {
        foreach ($conditions as $key => $expected) {
            $value = $query[$key] ?? null;

            $success = (function () use ($expected, $value): bool {
                switch ($expected) {
                    case '__isset__':
                        return ! is_null($value);
                    case '__missing__':
                        return is_null($value);
                    case '__true__':
                        return CastToType::_bool($value) === true;
                    case '__false__':
                        return CastToType::_bool($value) === false;
                    case '__bool__':
                        return ! is_null(CastToType::_bool($value));
                    case '__string__':
                        return is_string($value);
                    case '__numeric__':
                        return is_numeric($value);
                    case '__int__':
                        return ! is_null($value)
                               && (string) CastToType::_int(round((float) $value)) === (string) $value;
                    case '__float__':
                        return ! is_null($value)
                               && (string) CastToType::_float($value) === (string) $value;
                    case '__array__':
                        return is_array($value);
                    default:
                        if (is_null($value)) {
                            return false;
                        }

                        // Arrays are treated as an "in" condition,
                        // therefore we can test it the same as we
                        // we would a regular direct comparison
                        $expected = is_array($expected) ? $expected : [$expected];

                        foreach ($expected as $x) {
                            if ($x === CastToType::cast($value, gettype($x))) {
                                return true;
                            }
                        }

                        return false;
                }
            })();

            if (! $success) {
                break;
            }
        }

        return $success;
    }

    /**
     * Router that returns a response if a match is found for the request
     *
     * @param RequestInterface $request
     * @param string|null $stubsDirectory
     * @return ResponseInterface|null
     */
    public function match(RequestInterface $request, ?string $stubsDirectory = null): ?ResponseInterface
    {
        $uri = $request->getUri();
        $endpoint = trim($uri->getPath(), '/');

        // Loop through all routes in descending order of specificity
        foreach ($this->manifest as $pattern => $definitions) {
            // Loop through all definitions in descending order of specificity
            foreach ($definitions as $definition) {
                $route = trim($pattern, '/');
                $queryCondition = [];
                $method = '';

                $variables = isset($definition->match)
                    ? get_object_vars($definition->match)
                    : [];

                // If the definition has a match-statement, we must populate
                // the route with the variant's variable definitions
                if (! empty($variables)) {
                    $queryCondition = $variables['query'] ?? [];
                    unset($variables['query']);
                    $method = $variables['method'] ?? '';
                    unset($variables['method']);

                    $route = $this->populateRouteVariables($route, $variables);
                }

                // If the route still has variable braces in it,
                // then it can't be a match
                if (strpos($route, '{') !== false) {
                    continue;
                }

                // Make sure method matches
                if (! empty($method) && strtoupper($request->getMethod()) !== strtoupper($method)) {
                    continue;
                }

                // Make sure that the route pattern matches the current request URI
                if (! fnmatch($route, $endpoint)) {
                    continue;
                }

                // Also check query conditions
                if (! empty($queryCondition)) {
                    $query = $uri->getQuery();
                    $queryArgs = [];
                    parse_str($query, $queryArgs);

                    if (! $this->evaluateCondition(get_object_vars($queryCondition), $queryArgs)) {
                        continue;
                    }
                }

                // Custom definition properties gets passed as the header "data"
                if (! isset($definition->headers)) {
                    $definition->headers = [];
                }

                $data = get_object_vars($definition);
                unset($data['match']);
                unset($data['code']);
                unset($data['content']);
                unset($data['headers']);
                unset($data['version']);
                unset($data['reason']);

                if (! empty($data) && ! isset($definition->headers['data'])) {
                    $definition->headers['data'] = json_encode($data);
                }

                // Create response
                return $this->factory->create(
                    $definition->code    ?? $this->getDefaultStatusCode($method),
                    $definition->content ?? '',
                    $definition->headers ?? [],
                    $definition->version ?? '1.1',
                    $definition->reason  ?? null,
                    $stubsDirectory
                );
            }
        }

        return null;
    }

    /**
     * Get default status code
     *
     * @param string $method
     * @return integer
     */
    protected function getDefaultStatusCode(string $method): int
    {
        switch (strtoupper($method)) {
            case 'POST':
                return 201;
            case 'PUT':
            case 'PATCH':
            case 'DELETE':
                return 202;
            // case 'HEAD':
            // case 'TRACE':
            // case 'OPTIONS':
            // case 'GET':
            default:
                return 200;
        }
    }

    /**
     * Dump the manifest
     *
     * @return object|null
     */
    public function dump(): ?object
    {
        return $this->manifest;
    }

    /**
     * Get all of the object properties as an array
     *
     * @return array
     */
    public function toArray()
    {
        return json_decode(json_encode($this->manifest), true);
    }

    /**
     * Get all of the object properties as encoded JSON
     *
     * @return array
     */
    public function toJson($options = 0)
    {
        return json_encode($this->manifest, $options);
    }

    /**
     * Get manifest definition by route
     *
     * @param string $route
     * @return array|null
     */
    public function get(string $route): ?array
    {
        return $this->manifest->{$route} ?? null;
    }

    /**
     * Set manifest definition by route
     *
     * @param string $route
     * @param array $value
     * @return void
     */
    public function set(string $route, array $value): void
    {
        $this->manifest->{$route} = ! is_array($value)
            ? [$value]
            : $value;
    }

    /**
     * Check if route exists
     *
     * @param string $route
     * @return boolean
     */
    public function has(string $route): bool
    {
        return isset($this->manifest->{$route});
    }

    /**
     * @param string $route
     * @return array|null
     */
    public function __get(string $route): ?array
    {
        return $this->get($route);
    }

    /**
     * @param string $route
     * @param array $value
     * @return void
     */
    public function __set(string $route, array $value): void
    {
        $this->set($route, $value);
    }

    /**
     * @param string $route
     * @return boolean
     */
    public function __isset(string $route): bool
    {
        return $this->has($route);
    }
}
