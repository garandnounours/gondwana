<?php

namespace Gondwana\BookingApi;

class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function route(string $method, string $uri): mixed
    {
        // Parse URI to get path without query parameters
        $path = parse_url($uri, PHP_URL_PATH);
        
        // Check if route exists
        if (!isset($this->routes[$method][$path])) {
            return null;
        }

        $handler = $this->routes[$method][$path];

        // Handle closure
        if (is_callable($handler)) {
            return $handler();
        }

        // Handle class method array [ClassName::class, 'methodName']
        if (is_array($handler) && count($handler) === 2) {
            [$className, $methodName] = $handler;
            
            if (!class_exists($className)) {
                throw new \Exception("Controller class {$className} not found");
            }
            
            $controller = new $className();
            
            if (!method_exists($controller, $methodName)) {
                throw new \Exception("Method {$methodName} not found in {$className}");
            }
            
            return $controller->$methodName();
        }

        throw new \Exception("Invalid route handler");
    }
}
