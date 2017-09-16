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
 * A neo4j statement result item
 */
class StatementResultItem
{
    /**
     * @var array
     */
    protected $columnMap;

    /**
     * @var array
     */
    protected $data;

    /**
     * @var array
     */
    protected $metadata;


    public function __construct(array $data, array $metadata, array $columnMap)
    {
        $this->data = $data;
        $this->metadata = $metadata;
        $this->columnMap = $columnMap;
    }


    /**
     * @param string $variableName
     * @return array|null
     */
    public function get(string $variableName)
    {
        return isset($this->columnMap[$variableName]) ? $this->data[$this->columnMap[$variableName]] : null;
    }

    /**
     * @param string $variableName
     * @return array|null
     */
    public function getMeta(string $variableName)
    {
        return isset($this->columnMap[$variableName]) ? $this->metadata[$this->columnMap[$variableName]] : null;
    }
}
