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
 * A neo4j statement
 */
class Statement implements \JsonSerializable
{
    /**
     * @var string
     */
    protected $statement;

    /**
     * @var array
     */
    protected $parameters;


    public function __construct(string $statement, array $parameters = [])
    {
        $this->statement = $statement;
        $this->parameters = $parameters;
    }


    public function getStatement(): string
    {
        return $this->statement;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }


    function jsonSerialize(): array
    {
        return [
            'statement' => $this->statement,
            'parameters' => $this->parameters
        ];
    }
}
