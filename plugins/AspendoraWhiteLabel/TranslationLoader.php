<?php

namespace Piwik\Plugins\AspendoraWhiteLabel;

use Piwik\Plugin\Manager;
use Piwik\Translation\Loader\LoaderInterface;

/**
 * Makes this plugin's lang/en.json the last word on every translation key.
 *
 * Matomo registers plugin translation directories in whatever order _glob() returns them —
 * alphabetically (Plugin\Manager::getAllPluginsNames -> readPluginsDirectory) — and
 * JsonFileLoader merges them with array_replace_recursive, so the LAST directory wins.
 * "AspendoraWhiteLabel" sorts before "CoreAdminHome", "CoreHome", "PrivacyManager" and most of
 * the rest, which meant the rebranded strings were being overwritten right back by the very
 * plugins they were meant to override. Moving this plugin's directory to the end of the list
 * fixes that without depending on what the plugin is called.
 *
 * Wired in config/config.php as a decorator around the outermost LoaderInterface, so the
 * reordering happens before LoaderCache sees the list — the merged result is still cached, and
 * under a cache key that reflects the new order.
 */
class TranslationLoader implements LoaderInterface
{
    /**
     * @var LoaderInterface
     */
    private $loader;

    public function __construct(LoaderInterface $loader)
    {
        $this->loader = $loader;
    }

    public function load($language, array $directories)
    {
        $ours = Manager::getPluginDirectory('AspendoraWhiteLabel') . '/lang';

        $reordered = [];
        foreach ($directories as $directory) {
            if (rtrim($directory, '/') !== rtrim($ours, '/')) {
                $reordered[] = $directory;
            }
        }
        $reordered[] = $ours;

        return $this->loader->load($language, $reordered);
    }
}
