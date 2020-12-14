<?php

/**
 * Command provider registerer
 * @package iqomp/watcher
 * @version 1.0.0
 */

namespace Iqomp\Watcher;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;

class CommandProvider implements CommandProviderCapability
{
    public function getCommands()
    {
        return [new Watcher()];
    }
}
