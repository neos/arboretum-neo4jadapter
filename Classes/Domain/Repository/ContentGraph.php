<?php

namespace Neos\Arboretum\Neo4jAdapter\Domain\Repository;

/*
 * This file is part of the Neos.Arboretum.Neo4jAdapter package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */
use Neos\Arboretum\Domain as Arboretum;
use Neos\ContentRepository\Domain\DimensionCombination;
use Neos\ContentRepository\Domain\Content;
use Neos\Flow\Annotations as Flow;

/**
 * The neo4j adapter content graph
 *
 * To be used as a read-only source of nodes
 *
 * @Flow\Scope("singleton")
 * @api
 */
class ContentGraph extends Arboretum\Repository\AbstractContentGraph
{
    protected function createSubgraph(string $editingSessionName, DimensionCombination\Value\ContentDimensionValueCombination $dimensionValues): Content\Repository\ContentSubgraphInterface
    {
        return new ContentSubgraph($editingSessionName, $dimensionValues);
    }
}
