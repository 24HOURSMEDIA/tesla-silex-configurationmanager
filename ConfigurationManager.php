<?php
/**
 * Created by PhpStorm.
 * User: eapbachman
 * Date: 06/02/14
 * Time: 16:30
 */

namespace Tesla\Silex\ConfigurationManager;

use Symfony\Component\Filesystem\Filesystem;
use Tesla\Silex\ConfigurationManager\Exception\ConfigurationException;
use Symfony\Component\Finder\Finder;

class ConfigurationManager extends \ArrayObject
{

    private $parameters = array();
    private $config = null;
    private $parameterFiles = array();
    private $isLoaded = false;
    private $confFiles = array();

    public function __construct($parameterFile)
    {
        if (!is_array($parameterFile)) {
            $parameterFile = array($parameterFile);
        }
        $fs = new Filesystem();
        foreach ($parameterFile as $path) {
            if (!$fs->exists($path)) {
                throw new ConfigurationException('parameter file ' . $path . ' or configuration directory not found');
            }
            $this->parameterFiles[] = $path;
        }

    }


    public function registerConfigFiles(array $files)
    {
        foreach ($files as $file) {
            $f = realpath($file);
            if (!$f) {
                throw new ConfigurationException("config file " . $file . " not found");
            }
            $this->confFiles[] = $file;
        }
    }

    function registerConfigFilesInDirectories($dirs) {

        $finder = new Finder();
        $files = array();

        foreach ($finder->files()->in($dirs)->name('/(.*)(.ini|.json)$/')->sortByName() as $file) {
           $files[]= $file->getRealPath();
        }
        $this->registerConfigFiles($files);

    }

    /**
     * Loads a file and parses it into an array
     * supported are .ini files (sections are parsed as keys) and .json files
     *
     * @param $path
     * @return array
     * @throws \Exception
     */
    protected function parseFile($path) {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $results = array();
        switch ($ext) {
            case 'json':
                $results= json_decode(file_get_contents($path), true);
            break;
            case 'ini':
                $results= parse_ini_file($path, true);
            break;
            throw new \Exception('Config file extension ' . $ext . ' not supported');
        }
        return $results;
    }

    public function load()
    {
        if ($this->isLoaded) {
            return;
        }

        // first, load 'parameters' from the parameters files..
        $t = microtime(true);
        $parameters = array();
        foreach ($this->parameterFiles as $path) {
            $parameters = array_merge_recursive($parameters, $this->parseFile($path));
        }
        $this->parameters = $parameters;

        // load all config files
        $config = array();
        foreach ($this->confFiles as $file) {
            $config = array_replace_recursive($config, $this->parseFile($file));
        }

        // replace parameter strings
        $encoded = json_encode($config);
        foreach ($this->parameters as $k => $v) {
            if (!is_array($v) && !is_object($v)) {
                $encoded = str_replace('%' . $k . '%', $v, $encoded);
            } else {
                if ((false !== strpos($encoded, '%' . $k . '%'))) {
                    throw new ConfigurationException('ConfigurationManager does not support object or arrays for substitution - key ' . $k);
                }
            }
        }
        $this->config = json_decode($encoded, true);
        $t = microtime(true) - $t;
        // append to array object
        foreach ($this->config as $k => $v) {
            $this->offsetSet($k, $v);
        }
        $this->isLoaded = true;
    }


    function getParameter($key)
    {
        $this->isLoaded or $this->load();
        if (!isset($this->parameters[$key])) {
            throw new ConfigurationException('Parameter ' . $key . ' not found');
        }

        return $this->parameters[$key];
    }


    function getSection($section)
    {
        $this->isLoaded or $this->load();
        if (!isset($this->config[$section])) {
            throw new ConfigurationException('Section ' . $section . ' not defined in configuration');
        }

        return $this->config[$section];
    }

    function setSection($section, $cfg)
    {
        $this->config[$section] = $cfg;
    }

    function getSetting($section, $key)
    {
        $this->isLoaded or $this->load();
        $section = $this->getSection($section);
        if (!isset($section[$key])) {
            throw new ConfigurationException('Key ' . $key . ' not found in section ' . $section);
        }

        return $section[$key];
    }


} 