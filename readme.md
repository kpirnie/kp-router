# KPT Router

A comprehensive PHP routing library with middleware support, rate limiting, view rendering, and controller resolution capabilities.

## Features

- **HTTP Method Support**: Full support for GET, POST, PUT, PATCH, DELETE, HEAD, TRACE, and CONNECT methods
- **Middleware Pipeline**: Global and route-specific middleware with execution control
- **Rate Limiting**: Built-in rate limiting with Redis and file-based storage backends
- **View Rendering**: Template rendering system with data sharing and caching
- **Controller Resolution**: Automatic controller instantiation and method calling
- **Route Parameters**: Dynamic route parameters with named capture groups
- **Error Handling**: Comprehensive error handling and logging
- **Caching**: Built-in caching support for views and controllers
- **Method Override**: Support for HTTP method override in forms

## Requirements

- PHP 8.0 or higher
- Redis extension (optional, for Redis-based rate limiting)

## Installation

Install via Composer:

```bash
composer require kevinpirnie/kpt-router
```

## Basic Usage

### Constructor Parameters

```php
new Router($basePath = '', $appPath = '')
```

- **`$basePath`** (string): URL prefix for all routes (e.g., `/api/v1`, `/admin`)
- **`$appPath`** (string): File system path to your application root. Defaults to `getcwd()`

### Directory Structure

The router expects this directory structure:
```
your-app/
├── views/           # Templates (auto-detected as {appPath}/views)
├── controllers/     # Controllers to be used
├── tmp/            # Cache and rate limiting data (auto-created)
│   └── kpt_rate_limits/
├── vendor/         # Composer dependencies
└── index.php       # Your app entry point
```

**Note:** The router will automatically create the `tmp/kpt_rate_limits` directory if it doesn't exist. Ensure your application has write permissions to the app directory.

### Creating a Router

```php
<?php
use KPT\Router;

// Create router instance
$router = new Router('/api/v1', '/path/to/your/app'); // Optional base path and app path
// OR
$router = new Router('', __DIR__); // No URL prefix, set app path to current directory
// OR  
$router = new Router(); // Uses current working directory as app path

// Basic route registration
$router->get('/', function() {
    return 'Hello World!';
});

$router->post('/users', function() {
    return 'Create user';
});

// Route with parameters
$router->get('/users/{id}', function($id) {
    return "User ID: $id";
});

// Dispatch the router
$router->dispatch();
```

### Array-Based Route Registration

```php
$routes = [
    [
        'method' => 'GET',
        'path' => '/',
        'handler' => 'HomeController@index',
        'middleware' => ['auth', 'throttle'],
        'should_cache' => true,
        'cache_length' => 3600
    ],
    [
        'method' => 'POST',
        'path' => '/users',
        'handler' => 'UserController@store',
        'middleware' => ['auth']
    ],
    [
        'method' => 'GET',
        'path' => '/profile/{id}',
        'handler' => 'view:profile.html',
        'data' => ['title' => 'User Profile']
    ]
];

$router->registerRoutes($routes);
```

## Advanced Features

### Middleware

#### Global Middleware

```php
// Add global middleware
$router->addMiddleware(function() {
    // Authentication check
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo 'Unauthorized';
        return false; // Stop execution
    }
    return true; // Continue
});
```

#### Named Middleware

```php
// Register middleware definitions
$router->registerMiddlewareDefinitions([
    'auth' => function() {
        return isset($_SESSION['user']);
    },
    'admin' => function() {
        return $_SESSION['user']['role'] === 'admin';
    },
    'throttle' => function() {
        // Rate limiting logic
        return true;
    }
]);

// Use in routes
$router->registerRoutes([
    [
        'method' => 'GET',
        'path' => '/admin',
        'handler' => 'AdminController@dashboard',
        'middleware' => ['auth', 'admin']
    ]
]);
```

### Rate Limiting

#### Enable Rate Limiting

```php
// With Redis (preferred)
$router->enableRateLimiter([
    'host' => '127.0.0.1',
    'port' => 6379,
    'password' => 'your_redis_password' // optional
]);

// File-based fallback is automatic if Redis unavailable
```

#### Configure Rate Limits

Rate limiting is applied globally with default settings:
- **Limit**: 100 requests
- **Window**: 60 seconds
- **Storage**: Auto-detect (Redis preferred, file fallback)

### View Rendering

#### Set Views Directory

```php
// Views directory is automatically set to {appPath}/views
// You can override it if needed:
$router->setViewsPath('/custom/path/to/views');
```

#### Render Views

```php
// Direct view rendering
$router->get('/home', function() use ($router) {
    return $router->view('home.php', [
        'title' => 'Welcome',
        'user' => $_SESSION['user']
    ]);
});

// String-based view handler
$router->registerRoutes([
    [
        'method' => 'GET',
        'path' => '/about',
        'handler' => 'view:about.html',
        'data' => ['title' => 'About Us']
    ]
]);
```

#### Share Data with All Views

```php
$router->share('site_name', 'My Website');
$router->share([
    'version' => '1.0.0',
    'environment' => 'production'
]);
```

### Controller Resolution

#### Basic Controller Usage

```php
// Controller format: ClassName@methodName
$router->get('/users', 'UserController@index');
$router->post('/users', 'UserController@store');
$router->get('/users/{id}', 'UserController@show');
```

#### Array-Based Controller Routes

```php
$router->registerRoutes([
    [
        'method' => 'GET',
        'path' => '/dashboard',
        'handler' => 'controller:DashboardController@index',
        'middleware' => ['auth'],
        'should_cache' => true
    ]
]);
```

### Caching

#### View Caching

```php
$router->registerRoutes([
    [
        'method' => 'GET',
        'path' => '/heavy-page',
        'handler' => 'view:heavy-page.php',
        'should_cache' => true,
        'cache_length' => 7200 // 2 hours
    ]
]);
```

#### Controller Caching

```php
$router->registerRoutes([
    [
        'method' => 'GET',
        'path' => '/api/stats',
        'handler' => 'StatsController@getData',
        'should_cache' => true,
        'cache_length' => 900 // 15 minutes
    ]
]);
```

### Error Handling

#### Custom 404 Handler

```php
$router->notFound(function() {
    http_response_code(404);
    return '<h1>Page Not Found</h1><p>The requested page could not be found.</p>';
});
```

## Route Parameters

### Named Parameters

```php
$router->get('/users/{id}/posts/{slug}', function($id, $slug) {
    return "User $id, Post: $slug";
});
```

### Getting Current Route Information

```php
$router->get('/current-route', function() {
    $route = Router::getCurrentRoute();
    return json_encode([
        'method' => $route->method,
        'path' => $route->path,
        'params' => $route->params,
        'matched' => $route->matched
    ]);
});
```

## Utility Methods

### Get User IP Address

```php
$userIp = Router::getUserIp();
```

### Get Current URI

```php
$currentUri = Router::getUserUri();
```

### Path Sanitization

```php
$cleanPath = Router::sanitizePath('/path//with///slashes/');
// Result: /path/with/slashes
```

## Configuration

### Environment Setup

The router expects certain constants and classes to be defined:

```php
// Optional: Define KPT_URI for Redis prefixing
define('KPT_URI', 'myapp');
```

### Required Packages

- kevinpirnie/kpt-logger
- kevinpirnie/kpt-cache

## Example Application

```php
<?php
require_once 'vendor/autoload.php';

use KPT\Router;

// Initialize router with app path
$router = new Router('', __DIR__); // No URL prefix, app runs from current directory

// Views directory is automatically set to __DIR__ . '/views'
// You can override if needed: $router->setViewsPath(__DIR__ . '/custom/views');

// Share common data
$router->share('app_name', 'My Application');

// Enable rate limiting
$router->enableRateLimiter();

// Add authentication middleware
$router->registerMiddleware('auth', function() {
    session_start();
    return isset($_SESSION['user_id']);
});

// Register routes
$router->registerRoutes([
    [
        'method' => 'GET',
        'path' => '/',
        'handler' => 'view:home.php',
        'should_cache' => true
    ],
    [
        'method' => 'GET',
        'path' => '/dashboard',
        'handler' => 'DashboardController@index',
        'middleware' => ['auth']
    ],
    [
        'method' => 'GET',
        'path' => '/api/users/{id}',
        'handler' => 'UserController@show',
        'middleware' => ['auth']
    ]
]);

// Set 404 handler
$router->notFound(function() {
    return '<h1>404 - Page Not Found</h1>';
});

// Dispatch
$router->dispatch();
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Author

**Kevin Pirnie** - [me@kpirnie.com](mailto:me@kpirnie.com)
