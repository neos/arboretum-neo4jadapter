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

use Neos\Arboretum\Domain\Projection\AbstractGraphProjector;
use Neos\Arboretum\Infrastructure\Dto\Node;
use Neos\Arboretum\Neo4jAdapter\Infrastructure\Dto\Statement;
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


    public function reset()
    {
        $this->client->send([new Statement('MATCH (n) DETACH DELETE n')]);
    }

    public function isEmpty(): bool
    {
        $result = $this->client->send([new Statement('MATCH () RETURN count(*) AS count')]);;
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
        $this->getClient()->send([
            new Statement('CREATE (n: $nodeLabel) SET n += $properties', [
                'nodeLabel' => $nodeLabel,
                'properties' => $properties
            ])
        ]);
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
        $result = $this->getClient()->send([
            new Statement('MATCH (n {_identifierInGraph: $identifier}) RETURN n', [
                'identifier' => $identifierInGraph
            ])
        ])[0];

        $rawData = $result->getItem(0)->get('n');
        $properties = [];
        foreach ($rawData as $propertyName => $value) {
            if (strpos($propertyName, '_') !== 0) {
                $properties = Arrays::setValueByPath($properties, $propertyName, $value);
            }
        }

        return new Node(
            $rawData['_identifierInGraph'],
            $rawData['_identifierInSubgraph'],
            $rawData['_subgraphIdentifier'],
            $properties,
            $rawData['_nodeTypeName']
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
            $this->getClient()->send([
                new Statement(
                    'MATCH (p {_identifierInGraph: $parentIdentifier})'
                    . 'MATCH (c {_identifierInGraph: $childIdentifier})'
                    . 'CREATE (p)-[e:PARENT]->(c)'
                    . 'SET e+= $properties',
                    [
                        'parentIdentifier' => $parentNodesIdentifierInGraph,
                        'childIdentifier' => $childNodesIdentifierInGraph,
                        'properties' => $properties
                    ]
                )
            ]);
        }
    }

    protected function reconnectHierarchy(
        string $fallbackNodesIdentifierInGraph,
        string $newVariantNodesIdentifierInGraph,
        array $subgraphIdentifiers
    ) {
        $statements = [];
        foreach ($subgraphIdentifiers as $subgraphIdentifier) {
            $statementResult = $this->getClient()->send([
                new Statement(
                    'MATCH (p)-[e:PARENT {_subgraphIdentifier: $subgraphIdentifier}]->(c {_identifierInGraph: $childIdentifier})'
                    . ' RETURN e,p',
                    [
                        'subgraphIdentifier' => $subgraphIdentifier,
                        'childIdentifier' => $fallbackNodesIdentifierInGraph
                    ]
                )
            ]);

            \Neos\Flow\var_dump($statementResult);
            $statementResult= $statementResult[0];

            $parentNodeData = $statementResult->getItem(0)->get('p');
            $inboundEdgeData = $statementResult->getItem(0)->get('e');

            $statements[] = new Statement(
                'MATCH (p {_identifierInGraph: $parentIdentifier})'
                . 'MATCH (c {_identifierInGraph: $childIdentifier})'
                . 'CREATE (p)-[e:PARENT]->(c)'
                . 'SET e += $properties',
                [
                    'parentIdentifier' => $parentNodeData['_identifierInGraph'],
                    'childIdentifier' => $newVariantNodesIdentifierInGraph,
                    'properties' => $inboundEdgeData
                ]
            );

            $statements[] = new Statement(
                'MATCH ()-[e:PARENT {_subgraphIdentifier: $subgraphIdentifier}]->({_identifierInGraph: $childIdentifier})'
                . ' DELETE e',
                [
                    'subgraphIdentifier' => $subgraphIdentifier,
                    'childIdentifier' => $fallbackNodesIdentifierInGraph
                ]
            );
        }
        $this->getClient()->send($statements);
    }

    protected function connectRelation(string $startNodesIdentifierInGraph, string $endNodesIdentifierInGraph, string $relationshipName, array $properties, array $subgraphIdentifiers)
    {
        // TODO: Implement connectRelation() method.
    }

    protected function transactional(callable $operations)
    {
        $this->getClient()->transactional($operations);
    }


    protected function getClient(): Neo4jClient
    {
        return $this->client;
    }
}
