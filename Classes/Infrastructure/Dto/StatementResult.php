<?php

namespace Neos\Arboretum\Neo4jAdapter\Infrastructure\Dto;

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
 * A neo4j statement result
 *
 * @todo check whether this can be implemented as a generator via JSON stream
 */
class StatementResult
{
    /**
     * @var array|StatementResultItem[]
     */
    protected $items;


    public function __construct(array $columnMap, array $rawData)
    {
        $columnMap = array_flip($columnMap);
        array_walk($rawData, function (&$item) use ($columnMap) {
            $item = new StatementResultItem($item['row'], $item['meta'], $columnMap);
        });
        $this->items = $rawData;
    }


    /**
     * @return array|StatementResultItem[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @param int $index
     * @return StatementResultItem|null
     */
    public function getItem(int $index)
    {
        return $this->items[$index] ?? null;
    }
}
