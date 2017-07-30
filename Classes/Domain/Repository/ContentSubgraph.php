<?php

namespace Neos\Arboretum\Neo4jAdapter\Domain\Repository;

/*
 * This file is part of the Neos.ContentRepository.EventSourced package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */
use GraphAware\Neo4j\Client\Client;
use Neos\Arboretum\Domain\Entity\NodeAdapter;
use Neos\Arboretum\Domain as Arboretum;
use Neos\Arboretum\Neo4jAdapter\Infrastructure\Service\Neo4jClient;
use Neos\ContentRepository\Domain as ContentRepository;
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


    public function findNodeByIdentifier(string $nodeIdentifier): ContentRepository\Model\NodeInterface
    {
        // @todo: escape stuff
        /** @var \GraphAware\Bolt\Result\Type\Node $node */
        $rawNode = $this->getClient()->run('MATCH ()-[:PARENT {_subgraphIdentifier:"' . $this->identifier . '"}]->(n {_identifierInSubgraph: "' . $nodeIdentifier . '"}) RETURN n')->firstRecord()->get('n');

        return $this->mapNode($rawNode);
    }

    /**
     * @param string $parentIdentifier
     * @return array|ContentRepository\Model\NodeInterface[]
     */
    public function findNodesByParent(string $parentIdentifier): array
    {
        $nodes = [];
        foreach ($this->getClient()->run('MATCH (p {_identifierInSubgraph:"' . $parentIdentifier . '"})-[:PARENT {_subgraphIdentifier:"' . $this->identifier . '"}]->(c) RETURN c')->records() as $record) {
            $rawNode = $record->get('n');
            $nodes[] = $this->mapNode($rawNode);
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
        foreach ($this->getClient()->run(
            'MATCH ()'
            . '-[:PARENT* {_subgraphIdentifier:"' . $this->identifier . '"}]'
            . '->(c {_nodeTypeName:"' . $nodeTypeName . '"}) RETURN c')->records() as $record) {
            $rawNode = $record->get('c');
            $nodes[] = $this->mapNode($rawNode);
        }

        return $nodes;
    }

    public function traverse(ContentRepository\Model\NodeInterface $parent, callable $callback)
    {
        $callback($parent);
        // @todo use native graph db traversal features
        foreach ($this->getClient()->run(
            'MATCH (p {_identifierInSubgraph:"' . $parent->getIdentifier() . '"})'
            . '-[:PARENT* {_subgraphIdentifier:"' . $this->identifier . '"}]'
            . '->(c)'
            . ' RETURN c'
        )->records() as $record) {
            $node = $this->mapNode($record->get('c'));
            $callback($node);
        }
    }

    protected function mapNode(\GraphAware\Bolt\Result\Type\Node $node): ContentRepository\Model\NodeInterface
    {
        $mappedNode = new NodeAdapter($this);
        $mappedNode->nodeType = $this->nodeTypeManager->getNodeType($node->get('_nodeTypeName'));
        $mappedNode->identifier = $node->get('_identifierInSubgraph');
        $mappedNode->subgraphIdentifier = $node->get('_subgraphIdentifier');

        $properties = [];
        foreach ($node->values() as $propertyName => $value) {
            if (strpos($propertyName, '_') !== 0) {
                $properties = Arrays::setValueByPath($properties, $propertyName, $value);
            }
        }

        $mappedNode->properties = $properties;

        return $mappedNode;
    }

    protected function getClient(): Client
    {
        return $this->client->getClient();
    }
}
