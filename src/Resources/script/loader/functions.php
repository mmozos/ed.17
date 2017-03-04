<?php

/**
 * @author Martin Mozos <martinmozos@gmail.com>
 * Class def.
 */
final class def extends defDB
{
    private static $configLoaded = false;
    private static $dbCodes;
    private static $dbSecurity;
    private static $dbTargets;
    private static $homePath;
    private static $homeSlug;
    private static $langCodes;
    private static $langISOCodes;
    private static $metric;
    private static $metricLoaded = false;
    private static $paths;
    private static $pathsLoaded = false;
    private static $stages;
    private static $routesLoaded = false;
    private static $routes;

    private static function loadRoutes()
    {
        if (!static::$routesLoaded) {
            static::$routes = parseConfig(CONFIG_DIR, 'routes')['routes'];
            static::$routesLoaded = true;
        }
    }

    private static function loadPaths()
    {
        if (!static::$pathsLoaded) {
            static::$paths = parseConfig(CONFIG_DIR, 'paths')['paths'];
            static::$pathsLoaded = true;
        }
    }

    private static function loadMetric()
    {
        if (!static::$metricLoaded) {
            static::$metric = parseConfig(CONFIG_DIR, 'metric')['metric'];
            static::$metricLoaded = true;
        }
    }

    private static function loadConfig()
    {
        if (!static::$configLoaded) {
            $config = parseConfig(CONFIG_DIR, 'config')['configuration'];
            static::$dbCodes = $config['codes'];
            static::$dbSecurity = $config['security'];
            static::$dbTargets = $config['targets'];
            static::$homePath = $config['homepage_path'];
            static::$homeSlug = $config['homepage_slug'];
            static::$langCodes = $config['languages'];
            static::$langISOCodes = array_unique(array_values($config['languages']));
            static::$stages = $config['stages'];
            static::$configLoaded = true;
        }
    }

    public static function dbCodes()
    {
        static::loadConfig();

        return static::$dbCodes;
    }

    public static function dbSecurity()
    {
        static::loadConfig();

        return static::$dbSecurity;
    }

    public static function dbTargets()
    {
        static::loadConfig();

        return static::$dbTargets;
    }

    public static function homePath()
    {
        static::loadConfig();

        return static::$homePath;
    }

    public static function homeSlug()
    {
        static::loadConfig();

        return static::$homeSlug;
    }

    public static function langCodes()
    {
        static::loadConfig();

        return static::$langCodes;
    }

    public static function langISOCodes()
    {
        static::loadConfig();

        return static::$langISOCodes;
    }

    public static function metric()
    {
        static::loadMetric();

        return static::$metric;
    }

    public static function paths()
    {
        static::loadPaths();

        return static::$paths;
    }

    public static function stages()
    {
        static::loadConfig();

        return static::$stages;
    }

    public static function routes()
    {
        static::loadRoutes();

        return static::$routes;
    }
}

/**
 * Class defDb.
 */
class defDb
{
    private static $adminUsername;
    private static $adminPassword;
    private static $dbDist;
    private static $dbLocal;
    private static $userEntity;
    private static $extraEntity;
    private static $initialized = false;

    private static function loadDbConfig()
    {
        if (!static::$initialized) {
            $connectionConfig = parseConfig(ROOT_DIR, 'app/connection');
            $connectionDist = $connectionConfig['default_connection'];
            $connections = $connectionConfig['connections'];
            $localUsers = $connectionConfig['users']['local'];
            static::$adminUsername = $localUsers['admin']['name'];
            static::$adminPassword = $localUsers['admin']['pw'];
            static::$dbDist = $connections[$connectionDist];
            static::$dbLocal = $connections['local'];
            static::$userEntity = $connectionConfig['users'][$connectionDist];
            static::$extraEntity = $connectionConfig['users']['extra'];
            static::$initialized = true;
        }
    }

    public static function adminUsername()
    {
        static::loadDbConfig();

        return static::$adminUsername;
    }

    public static function adminPassword()
    {
        static::loadDbConfig();

        return static::$adminPassword;
    }

    public static function dbDist()
    {
        static::loadDbConfig();

        return static::$dbDist;
    }

    public static function dbLocal()
    {
        static::loadDbConfig();

        return static::$dbLocal;
    }

    public static function userEntity()
    {
        static::loadDbConfig();

        return static::$userEntity;
    }

    public static function extraEntity()
    {
        static::loadDbConfig();

        return static::$extraEntity;
    }
}

/**
 * Function parseConfig.
 *
 * @param string      $path
 * @param string|null $filename
 *
 * @return array|mixed
 */
function parseConfig($path, $filename = null)
{
    is_null($filename) ?: $path = "$path/$filename.yml";
    if (is_file($path)) {
        /** @var array $config */
        $config = \Symfony\Component\Yaml\Yaml::parse(file_get_contents($path));

        return isset($config['parameters']) ? $config['parameters'] : $config;
    }
}
