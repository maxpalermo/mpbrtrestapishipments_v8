<?php

/**
 * @author    Massimiliano Palermo <maxx.palermo@gmail.com>
 * @copyright Since 2016 Massimiliano Palermo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace MpSoft\MpBrtRestApiShipments\Helpers;

use Twig\Environment;

class GetTwigEnvironment
{
    /** @var string */
    protected $module_name;
    /** @var \Context */
    protected $context;
    /** @var string */
    protected $path = null;
    /** @var Environment */
    protected $twig;

    public function __construct($module_name)
    {
        $this->module_name = $module_name;
        $this->context = \Context::getContext();
    }

    public function load($path)
    {
        $baseUrl         = $this->context->link->getBaseLink();
        $basePath        = _PS_MODULE_DIR_;
        $moduleName      = $this->module_name;
        $modulePath      = "{$basePath}{$moduleName}/";
        $moduleViewsPath = "{$basePath}{$moduleName}/views/";
        $moduleTwigPath  = "{$basePath}{$moduleName}/views/twig/";
        $moduleAssetsPath= "{$basePath}{$moduleName}/views/assets/";

        if (!preg_match('/\.html\.twig$/i', $path)) {
            $path .= '.html.twig';
        }

        $loader = new \Twig\Loader\FilesystemLoader($moduleTwigPath);
        $loader->addPath($basePath, 'Modules');
        $loader->addPath($modulePath, 'Module');
        $loader->addPath($moduleViewsPath, 'ModuleViews');

        if (file_exists($moduleAssetsPath)) {
            $loader->addPath($moduleAssetsPath, 'ModuleAssets');
        }

        $this->twig = new Environment($loader);
        $this->twig->addGlobal('baseUrl', $baseUrl);
        $this->twig->addGlobal('modulePathUrl', "{$baseUrl}modules/{$moduleName}/");
        $this->twig->addGlobal('moduleViewsUrl', "{$baseUrl}modules/{$moduleName}/views/");
        $this->twig->addGlobal('moduleViewsPath', $moduleViewsPath);

        if (file_exists($moduleAssetsPath)) {
            $this->twig->addGlobal('moduleAssetsPath', $moduleAssetsPath);
            $this->twig->addGlobal('moduleAssetsUrl', "{$baseUrl}modules/{$moduleName}/views/assets/");
            $this->twig->addFunction(new \Twig\TwigFunction('asset', function ($p) use ($baseUrl, $moduleName) {
                return "{$baseUrl}modules/{$moduleName}/views/assets/" . ltrim($p, '/');
            }));
        } else {
            $this->twig->addFunction(new \Twig\TwigFunction('asset', function ($p) use ($baseUrl, $moduleName) {
                return "{$baseUrl}modules/{$moduleName}/views/" . ltrim($p, '/');
            }));
        }

        if (file_exists($moduleTwigPath)) {
            $this->twig->addGlobal('moduleTwigPath', $moduleTwigPath);
            $this->twig->addGlobal('moduleTwigUrl', "{$baseUrl}modules/{$moduleName}/views/twig/");
            $loader->addPath($moduleTwigPath, 'ModuleTwig');
        }

        $this->path = $path;

        return $this;
    }

    public function render(array $params = []): string
    {
        if (!$this->twig) {
            throw new \Exception('Twig environment not initialized');
        }
        if (!$this->path) {
            throw new \Exception('Template path not set');
        }

        $templatePath   = $this->path;
        $moduleTwigPath = _PS_MODULE_DIR_ . $this->module_name . '/views/twig/';

        if (strpos($templatePath, $moduleTwigPath) === 0) {
            $templatePath = substr($templatePath, strlen($moduleTwigPath));
        }

        return $this->twig->render($templatePath, $params);
    }
}
