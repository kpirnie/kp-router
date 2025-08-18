<?php

use PHPUnit\Framework\TestCase;

// Mock the Logger and Cache classes to avoid dependencies
class Logger {
    public static function debug($msg, $ctx = []) {}
    public static function error($msg, $ctx = []) {}
}

class Cache {
    public static function get($key) { return false; }
    public static function set($key, $val, $ttl = 3600) {}
    public static function delete($key) {}
}

class SimpleRouterTest extends TestCase
{
    public function testRouterCreation()
    {
        $router = new KPT\Router();
        $this->assertInstanceOf(KPT\Router::class, $router);
    }

    public function testRouterWithPaths()
    {
        $router = new KPT\Router('/api', '/tmp');
        $this->assertInstanceOf(KPT\Router::class, $router);
    }

    public function testSanitizePath()
    {
        $this->assertEquals('/', KPT\Router::sanitizePath(''));
        $this->assertEquals('/test', KPT\Router::sanitizePath('/test/'));
        $this->assertEquals('/test/path', KPT\Router::sanitizePath('//test///path//'));
    }

    public function testGetUserIp()
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $ip = KPT\Router::getUserIp();
        $this->assertIsString($ip);
    }

    public function testRouteRegistration()
    {
        $router = new KPT\Router();
        $router->get('/test', function() { return 'test'; });
        
        $routes = $router->getRoutes();
        $this->assertContains('/test', $routes['GET']);
    }
}