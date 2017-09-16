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

use Neos\Arboretum\Neo4jAdapter\Infrastructure\Dto\Statement;
use Neos\Arboretum\Neo4jAdapter\Infrastructure\Dto\StatementResult;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http;

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
     * @Flow\Inject
     * @var Http\Client\CurlEngine
     */
    protected $curlEngine;

    /**
     * @var string
     */
    protected $baseUri;

    /**
     * @var int
     */
    protected $currentTransactionId;


    public function initializeObject()
    {
        $this->baseUri = 'http://' . $this->backendOptions['host'] . ':' . $this->backendOptions['port'];
    }

    /**
     * @param array $statements
     * @return array|StatementResult[]
     */
    public function send(array $statements): array
    {
        $statementsRequest = $this->createRequest();
        $statementsRequest->setContent(json_encode(['statements' => $statements]));
        $statementsResponse = $this->curlEngine->sendRequest($statementsRequest);

        $results = [];
        foreach (json_decode($statementsResponse->getContent(), true)['results'] as $result) {
            $results[] = new StatementResult($result['columns'], $result['data']);
        }

        return $results;
    }

    public function transactional(callable $operations)
    {
        $transactionStartRequest = $this->createRequest();
        $transactionStartResponse = $this->curlEngine->sendRequest($transactionStartRequest);
        $commit = json_decode($transactionStartResponse->getContent(), true)['commit'];
        $commit = mb_substr($commit, 0, mb_strrpos($commit, '/'));
        $this->currentTransactionId = (int)mb_substr($commit, mb_strrpos($commit, '/') + 1);

        $operations();

        $this->currentTransactionId = null;
    }

    protected function createRequest(): Http\Request
    {
        $path = '/db/data/transaction';
        if (!is_null($this->currentTransactionId)) {
            $path .= '/' . $this->currentTransactionId;
        };
        $request = Http\Request::create(new Http\Uri($this->baseUri . $path), 'POST');
        $request->setHeader('Accept', 'application/json; charset=UTF-8');
        $request->setHeader('Content-Type', 'application/json');
        $request->setHeader('X-Stream', 'true');
        $request->setHeader('Authorization', base64_encode($this->backendOptions['username'] . ':' . $this->backendOptions['password']));

        return $request;
    }

    /**
     * @param array|Statement[] $statements
     * @return Http\Response
     */
    /*public function transactional(array $statements): Http\Response
    {
        $request = Http\Request::create(new Http\Uri($this->baseUri . '/db/data/transaction/commit'), 'POST');
        $request->setContent(json_encode(['statements' => $statements]));
        $request->setHeader('Accept', 'application/json; charset=UTF-8');
        $request->setHeader('Content-Type', 'application/json');
        $request->setHeader('X-Stream', 'true');
        $request->setHeader('Authorization', base64_encode($this->backendOptions['username'] . ':' . $this->backendOptions['password']));
        $response = $this->curlEngine->sendRequest($request);

        return $response;
    }*/
}
