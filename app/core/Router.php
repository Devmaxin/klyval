<?php
final class Router {
    private array $routes = [];
    public function add(string $method, string $pattern, string $handler, array $mw = []): void {
        $re = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[a-z0-9\-]+)', $pattern) . '$#i';
        $this->routes[] = [$method, $re, $handler, $mw];
    }
    public function get(string $p, string $h, array $mw = []): void { $this->add('GET', $p, $h, $mw); }
    public function post(string $p, string $h, array $mw = []): void { $this->add('POST', $p, $h, array_merge(['Csrf'], $mw)); }

    public function dispatch(string $method, string $uri): void {
        $path = rtrim(parse_url($uri, PHP_URL_PATH) ?: '/', '/') ?: '/';
        foreach ($this->routes as [$m, $re, $handler, $mw]) {
            if ($m !== $method || !preg_match($re, $path, $found)) continue;
            $params = array_values(array_filter($found, 'is_string', ARRAY_FILTER_USE_KEY));
            foreach ($mw as $class) { $class::handle(); }
            [$ctrl, $action] = explode('@', $handler);
            (new $ctrl())->$action(...$params);
            return;
        }
        http_response_code(404);
        View::render('pages/404', ['meta' => ['title' => 'Page not found', 'robots' => 'noindex']]);
    }
}
