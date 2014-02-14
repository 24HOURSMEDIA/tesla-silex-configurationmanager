tesla-silex-configurationmanager
================================

Configuration manager for Silex projects


## General Usage

Register as a service:

```
// configure your app for the production environment
$app['config'] = $app->share(
    function () {
        $parameterFile = __DIR__ . '/parameters.json';
        $confDir = __DIR__ . '/conf.d';
        $service = new \Tesla\Silex\ConfigurationManager\ConfigurationManager($parameterFile);
        $service->registerConfigFiles(
            array(
                $confDir . '/conffile1.json',
                $confDir . '/conffile2.conf.json'
            )
        );

        return $service;
    }
);
```
