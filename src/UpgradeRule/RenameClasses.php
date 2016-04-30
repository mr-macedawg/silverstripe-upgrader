<?php

namespace Sminnee\Upgrader\UpgradeRule;

use PhpParser\NodeVisitor\NameResolver;
use Sminnee\Upgrader\Util\MutableSource;

class RenameClasses extends AbstractUpgradeRule
{
    public function upgradeFile($contents, $filename)
    {
        $this->warningCollector = [];

        $source = new MutableSource($contents);

        $visitors = [
        ];

        $this->transformWithVisitors($source->getAst(), [
            new NameResolver(),
            new RenameClassesVisitor($source, $this->parameters['mappings']),
        ]);

        return [ $source->getModifiedString(), $this->warningCollector ];
    }
}