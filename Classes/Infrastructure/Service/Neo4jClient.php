<?php

namespace Neos\Arboretum\Neo4jAdapter\Infrastructure\Service;

/*
 * This file is part of the Neos.Arboretum.Neo4jAdapter package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use GraphAware\Neo4j\Client\Client;
use GraphAware\Neo4j\Client\ClientBuilder;
use Neos\Flow\Annotations as Flow;

/**
 * The neo4j client adapter
 *
 * @Flow\Scope("singleton")
 */
class Neo4jClient
{
    /**
     * @Flow\InjectConfiguration(path="persistence.backendOptions")
     * @var array
     */
    protected $backendOptions;

    /**
     * @var Client
     */
    protected $client;


    public function initializeObject()
    {
        $this->client = ClientBuilder::create()
            ->addConnection(
                $this->backendOptions['alias'],
                'bolt://' . $this->backendOptions['username'] .
                ':' . $this->backendOptions['password'] .
                '@' . $this->backendOptions['host'] . ':' . $this->backendOptions['port'])
            ->build();
    }

    public function getClient(): Client
    {
        return $this->client;
    }
}
