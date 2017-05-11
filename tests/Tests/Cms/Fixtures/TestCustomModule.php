<?php declare(strict_types = 1);

namespace Dms\Package\Content\Tests\Cms\Fixtures;

use Dms\Core\Module\Definition\ModuleDefinition;
use Dms\Core\Module\Module;

/**
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class TestCustomModule extends Module
{
    /**
     * Defines the module.
     *
     * @param ModuleDefinition $module
     */
    protected function define(ModuleDefinition $module)
    {
        $module->name('custom-module');
    }
}