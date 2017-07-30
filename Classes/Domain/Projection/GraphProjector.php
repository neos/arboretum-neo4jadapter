<?php

namespace Neos\Arboretum\Neo4jAdapter\Domain\Projection;

/*
 * This file is part of the Neos.Arboretum.Neo4jAdapter package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use GraphAware\Bolt\Result\Type\Relationship;
use GraphAware\Neo4j\Client\Client;
use GraphAware\Neo4j\Client\Transaction\Transaction;
use Neos\Arboretum\Domain\Projection\AbstractGraphProjector;
use Neos\Arboretum\Infrastructure\Dto\Node;
use Neos\Arboretum\Neo4jAdapter\Infrastructure\Service\Neo4jClient;
use Neos\Flow\Annotations as Flow;
use Neos\Utility\Arrays;

/**
 * The alternate reality-aware graph projector for the neo4j backend
 *
 * @Flow\Scope("singleton")
 */
class GraphProjector extends AbstractGraphProjector
{
    /**
     * @Flow\Inject
     * @var Neo4jClient
     */
    protected $client;

    /**
     * @var Transaction
     */
    protected $transaction;


    public function reset()
    {
        $this->getClient()->run('MATCH (n) DETACH DELETE n');
    }

    public function isEmpty(): bool
    {
        $result = $this->getClient()->run('MATCH () RETURN count(*) AS cnt');
        \Neos\Flow\var_dump($result, 'isEmpty');
        #\Neos\Flow\var_dump($result);
        exit;
    }

    protected function addNode(Node $node)
    {
        $properties = array_merge($node->properties, [
            '_identifierInGraph' => $node->identifierInGraph,
            '_identifierInSubgraph' => $node->identifierInSubgraph,
            '_subgraphIdentifier' => $node->subgraphIdentifier,
            '_nodeTypeName' => $node->nodeTypeName
        ]);
        foreach ($properties as $propertyName => $propertyValue) {
            if (is_array($propertyValue)) {
                $this->flattenPropertyValue($properties, $propertyName, $propertyValue);
            }
        }
        $nodeLabel = str_replace(['.', ':'], ['', ''], $node->nodeTypeName);
        $this->getClient()->run('CREATE (n:' . $nodeLabel . ') SET n += {properties}', ['properties' => $properties]);
    }

    protected function flattenPropertyValue(array & $properties, string $propertyPath, array $propertyValue)
    {
        foreach ($propertyValue as $key => $value) {
            if (is_array($value)) {
                $this->flattenPropertyValue($properties, $propertyPath . '.' . $key, $value);
            } else {
                $properties[$propertyPath . '.' . $key] = $value;
            }
        }
        unset($properties[$propertyPath]);
    }

    protected function getNode(string $identifierInGraph): Node
    {
        // @todo: escape stuff
        /** @var \GraphAware\Bolt\Result\Type\Node $node */
        $node = $this->getClient()->run(
            'MATCH (n {_identifierInGraph: "' . $identifierInGraph . '"}) RETURN n'
        )->firstRecord()->get('n');
        $properties = [];
        foreach ($node->values() as $propertyName => $value) {
            if (strpos($propertyName, '_') !== 0) {
                $properties = Arrays::setValueByPath($properties, $propertyName, $value);
            }
        }

        return new Node(
            $node->get('_identifierInGraph'),
            $node->get('_identifierInSubgraph'),
            $node->get('_subgraphIdentifier'),
            $properties,
            $node->get('_nodeTypeName')
        );
    }

    protected function connectHierarchy(
        string $parentNodesIdentifierInGraph,
        string $childNodesIdentifierInGraph,
        string $elderSiblingsIdentifierInGraph = null,
        string $name = null,
        array $subgraphIdentifiers
    ) {
        $properties = [];
        if ($name) {
            $properties['_name'] = 'name';
        }
        foreach ($subgraphIdentifiers as $subgraphIdentifier) {
            $properties['_subgraphIdentifier'] = $subgraphIdentifier;
            $this->getClient()->run(
                'MATCH (p {_identifierInGraph:"' . $parentNodesIdentifierInGraph . '"})'
                . 'MATCH (c {_identifierInGraph:"' . $childNodesIdentifierInGraph . '"})'
                . 'CREATE (p)-[e:PARENT]->(c)'
                . 'SET e+= {properties}',
                ['properties' => $properties]
            );
        }
    }

    protected function reconnectHierarchy(
        string $fallbackNodesIdentifierInGraph,
        string $newVariantNodesIdentifierInGraph,
        array $subgraphIdentifiers
    ) {
        foreach ($subgraphIdentifiers as $subgraphIdentifier) {
            $record = $this->getClient()->run(
                'MATCH (p)'
                . '-[e:PARENT {_subgraphIdentifier:"' . $subgraphIdentifier . '"}]'
                . '->(c {_identifierInGraph:"' . $fallbackNodesIdentifierInGraph . '"})'
                . ' RETURN e,p'
            )->firstRecord();
            /** @var Relationship $inboundEdge */
            $inboundEdge = $record->get('e');
            /** @var \GraphAware\Bolt\Result\Type\Node $parentNode */
            $parentNode = $record->get('p');

            $this->getClient()->run(
                'MATCH (p {_identifierInGraph:"' . $parentNode->value('_identifierInGraph') . '"})'
                . 'MATCH (c {_identifierInGraph:"' . $newVariantNodesIdentifierInGraph . '"})'
                . 'CREATE (p)-[e:PARENT]->(c)'
                . 'SET e += {properties}',
                ['properties' => $inboundEdge->values()]
            );

            $this->getClient()->run(
                'MATCH (p)'
                . '-[e:PARENT {_subgraphIdentifier:"' . $subgraphIdentifier . '"}]'
                . '->(c {_identifierInGraph:"' . $fallbackNodesIdentifierInGraph . '"})'
                . ' DELETE e'
            );
        }
    }

    protected function connectRelation(string $startNodesIdentifierInGraph, string $endNodesIdentifierInGraph, string $relationshipName, array $properties, array $subgraphIdentifiers)
    {
        // TODO: Implement connectRelation() method.
    }

    protected function transactional(callable $operations)
    {
        $this->transaction = $this->getClient()->transaction();
        $this->transaction->begin();
        $operations();
        $this->transaction->commit();
        $this->transaction = null;
    }


    /**
     * @return Client|Transaction
     */
    protected function getClient()
    {
        return $this->transaction ?: $this->client->getClient();
    }
}
