<?php

namespace Neos\Arboretum\Neo4jAdapter\Domain\Value;

/*
 * This file is part of the Neos.Arboretum.Neo4jAdapter package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */
use Neos\Flow\Annotations as Flow;

/**
 * The node label domain value object
 */
final class NodeLabel
{
    /**
     * @var string
     */
    protected $label;


    public function __construct(string $label)
    {
        $this->label = $label;
    }

    public static function fromNodeTypeName(string $nodeTypeName)
    {
        return new NodeLabel(str_replace(['.', ':'], ['_', '__'], $nodeTypeName));
    }


    public function __toString(): string
    {
        return $this->label;
    }
}
