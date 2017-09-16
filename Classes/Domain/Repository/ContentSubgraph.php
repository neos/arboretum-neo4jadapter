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
use Neos\Arboretum\Neo4jAdapter\Infrastructure\Dto\Statement;
use Neos\Arboretum\Neo4jAdapter\Infrastructure\Service\Neo4jClient;
use Neos\ContentRepository\Domain as ContentRepository;
use Neos\ContentRepository\EventSourced\Domain\Model\Content\PropertyCollection;
use Neos\Flow\Annotations as Flow;
use Neos\Utility\Arrays;

/**
 * The content subgraph
 *
 * To be used as a read-only source of nodes
 *
 * @api
 */
class ContentSubgraph extends Arboretum\Repository\AbstractContentSubgraph
{
    /**
     * @Flow\Inject
     * @var Neo4jClient
     */
    protected $client;


    /**
     * @param string $nodeIdentifier
     * @return ContentRepository\Model\NodeInterface|null
     */
    public function findNodeByIdentifier(string $nodeIdentifier)
    {
        $statementResult = $this->client->send([
            new Statement(
                'MATCH ()-[:PARENT {_subgraphIdentifier: $subgraphIdentifier}]->(n {_identifierInSubgraph: $nodeIdentifier}) RETURN n',
                [
                    'subgraphIdentifier' => $this->identifier,
                    'nodeIdentifier' => $nodeIdentifier
                ]
            )
        ])[0];

        if ($statementResult->getItem(0)) {
            return $this->mapNode($statementResult->getItem(0)->get('n'));
        }

        return null;
    }

    /**
     * @param string $parentIdentifier
     * @return array|ContentRepository\Model\NodeInterface[]
     */
    public function findNodesByParent(string $parentIdentifier): array
    {
        $nodes = [];
        $statementResult = $this->client->send([
            new Statement(
                'MATCH ({_identifierInSubgraph: $parentIdentifier})-[:PARENT {_subgraphIdentifier: $subgraphIdentifier}]->(c) RETURN c',
                [
                    'parentIdentifier' => $parentIdentifier,
                    'subgraphIdentifier' => $this->identifier
                ]
            )
        ])[0];
        foreach ($statementResult->getItems() as $item) {
            $nodes[] = $this->mapNode($item->get('c'));
        }

        return $nodes;
    }

    /**
     * @param string $nodeTypeName
     * @return array|ContentRepository\Model\NodeInterface[]
     */
    public function findNodesByType(string $nodeTypeName): array
    {
        $nodes = [];
        $statementResult = $this->client->send([
            new Statement(
                'MATCH ()-[:PARENT* {_subgraphIdentifier: $subgraphIdentifier}]->(c {_nodeTypeName: $nodeTypeName}) RETURN c',
                [
                    'subgraphIdentifier' => $this->identifier,
                    'nodeTypeName' => $nodeTypeName
                ]
            )
        ])[0];
        foreach ($statementResult->getItems() as $item) {
            $nodes[] = $this->mapNode($item->get('c'));
        }

        return $nodes;
    }

    public function traverse(ContentRepository\Model\NodeInterface $parent, callable $callback)
    {
        $callback($parent);

        $statementResult = $this->client->send([
            new Statement(
                'MATCH ({_identifierInSubgraph: $parentIdentifier})-[:PARENT* {_subgraphIdentifier: $subgraphIdentifier}]->(c) RETURN c',
                [
                    'subgraphIdentifier' => $this->identifier,
                    'parentIdentifier' => $parent->getIdentifier()
                ]
            )
        ])[0];

        foreach ($statementResult->getItems() as $item) {
            $node = $this->mapNode($item->get('c'));
            $callback($node);
        }
    }

    protected function mapNode(array $rawData)
    {
        $mappedNode = new Arboretum\Entity\NodeAdapter($this);
        $mappedNode->nodeType = $this->nodeTypeManager->getNodeType($rawData['_nodeTypeName']);
        $mappedNode->identifier = $rawData['_identifierInSubgraph'];
        $mappedNode->subgraphIdentifier = $rawData['_subgraphIdentifier'];

        $properties = [];
        foreach ($rawData as $propertyName => $value) {
            if (strpos($propertyName, '_') !== 0) {
                $properties = Arrays::setValueByPath($properties, $propertyName, $value);
            }
        }
        $mappedNode->properties = new PropertyCollection($properties);

        return $mappedNode;
    }

    /**
     * @return Neo4jClient
     */
    protected function getClient()
    {
        return $this->client;
    }
}
