<?php

/**
 * KPT Router Class
 *
 * This class provides a comprehensive routing solution with middleware support,
 * rate limiting, and view rendering capabilities.
 *
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// if the class does not exist already
if (! class_exists('Router')) {

    /**
     * KPT Router Class
     *
     * Handles HTTP routing with support for all standard methods (GET, POST, etc.),
     * middleware pipelines, rate limiting, and view rendering.
     *
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    class Router
    {
        // inherit our traits
        use RouterRateLimiter;
        use RouterMiddlewareHandler;
        use RouterRouteHandler;
        use RouterRequestProcessor;
        use RouterResponseHandler;

        /** @var string the routing base path */
        private string $basePath = '';
        private string $appPath = '';

        /**
         * Constructor
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $basePath The base path for all routes
         */
        public function __construct(string $basePath = '', string $appPath = '')
        {

            // set the base paths
            $this->basePath = self::sanitizePath($basePath);
            $this->appPath = !empty($appPath) ? rtrim($appPath, '/') : getcwd();
            $this->viewsPath = $this->appPath . '/views';
            $this->rateLimitPath = $this->appPath . '/tmp/kpt_rate_limits';
            // if the file base rate limiter path doesnt exist, create it
            if (! file_exists($this->rateLimitPath)) {
                // try to create the directory
                try {
                    // create the directory
                    mkdir($this->rateLimitPath, 0755, true);
                    // whoopsie...
                } catch (\Exception $e) {
                    // error logging
                    Logger::error("Router Rate Limit Directory Creation Failed", [
                        'path' => $this->rateLimitPath,
                        'message' => $e->getMessage()
                    ]);
                }
            }

            // debug logging
            Logger::debug("Router Constructor Completed", [
                'base_path' => $this->basePath,
                'views_path' => $this->viewsPath
            ]);
        }

        /**
         * Destructor
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         */
        public function __destruct()
        {

            // clean up the arrays
            if (isset($this->routes)) {
                $this->routes = [];
            }
            if (isset($this->middlewares)) {
                $this->middlewares = [];
            }
            if (isset($this->middlewareDefinitions)) {
                $this->middlewareDefinitions = [];
            }

            // try to clean up the redis connection
            try {
                // if we have the object, close it
                if (isset($this->redis) && $this->redis) {
                    // close the redis connection
                    $this->redis->close();
                }

                // whoopsie... log an error
            } catch (\Throwable $e) {
                // error logging
                Logger::error("Router Redis Connection Close Error", [
                    'message' => $e->getMessage(),
                ]);
            }

            // debug logging
            Logger::debug("Router Destructor Completed", []);
        }

        /**
         * getUserIp
         *
         * Gets the current users public IP address
         *
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @return string Returns a string containing the users public IP address
         *
         */
        public static function getUserIp(): string
        {
            // evaluate trusted headers in order and return first valid address
            $candidates = [];

            // proxy headers can include multiple values, so evaluate each
            foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $header) {
                if (! isset($_SERVER[$header]) || ! is_string($_SERVER[$header])) {
                    continue;
                }

                foreach (explode(',', $_SERVER[$header]) as $ip) {
                    $candidates[] = trim($ip);
                }
            }

            // first try public addresses
            foreach ($candidates as $candidate) {
                if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $candidate;
                }
            }

            // fallback to any syntactically valid address
            foreach ($candidates as $candidate) {
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    return $candidate;
                }
            }

            // default return
            return '';
        }

        /**
         * getUserUri
         *
         * Gets the current users URI that was attempted
         *
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @return string Returns a string containing the URI
         *
         */
        public static function getUserUri(): string
        {

            $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');

            // strip host header port and validate allowed host characters
            $host = preg_replace('/:\\d+$/', '', trim($host));
            if (! preg_match('/^[a-z0-9.-]+$/i', $host)) {
                $host = 'localhost';
            }

            // return the current URL
            // phpcs:ignore Generic.Files.LineLength.TooLong
            return filter_var((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $host . $requestUri, FILTER_SANITIZE_URL);
        }

        /**
         * Sanitize path
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string|null $path Path to sanitize
         * @return string Sanitized path
         */
        public static function sanitizePath(?string $path): string
        {

            if (empty($path)) {
                return '/';
            }
            $path = parse_url($path, PHP_URL_PATH) ?? '';
            $path = preg_replace('#/+#', '/', $path);
            // Only normalize multiple slashes
            $path = trim($path, '/');
            return $path === '' ? '/' : '/' . $path;
        }
    }
}
