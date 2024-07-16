<?php

namespace RRZE\Workflow;

defined('ABSPATH') || exit;

class Helper
{
    public static function isModuleActivated(string $modName = ''): bool
    {
        if ($options = get_option("_cms_workflow_{$modName}_options")) {
            return (bool) $options->activated;
        }
        return false;
    }
}
