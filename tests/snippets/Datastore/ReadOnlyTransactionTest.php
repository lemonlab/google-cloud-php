<?php
/**
 * Copyright 2018 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Google\Cloud\Tests\Snippets\Datastore;

use Google\Cloud\Core\Testing\Snippet\SnippetTestCase;
use Google\Cloud\Core\Testing\TestHelpers;
use Google\Cloud\Datastore\Connection\ConnectionInterface;
use Google\Cloud\Datastore\DatastoreClient;
use Google\Cloud\Datastore\Entity;
use Google\Cloud\Datastore\EntityMapper;
use Google\Cloud\Datastore\Key;
use Google\Cloud\Datastore\Operation;
use Google\Cloud\Datastore\Query\QueryInterface;
use Google\Cloud\Datastore\ReadOnlyTransaction;
use Prophecy\Argument;

/**
 * @group datastore
 */
class ReadOnlyTransactionTest extends SnippetTestCase
{
    const PROJECT = 'my-awesome-project';

    private $connection;
    private $operation;
    private $transaction;
    private $transactionId = 'foo';
    private $datastore;
    private $key;

    public function setUp()
    {
        $this->connection = $this->prophesize(ConnectionInterface::class);
        $this->operation = TestHelpers::stub(Operation::class, [
            $this->connection->reveal(),
            self::PROJECT,
            '',
            new EntityMapper(self::PROJECT, false, false)
        ]);
        $this->transaction = new ReadOnlyTransaction($this->operation, self::PROJECT, $this->transactionId);
        $this->datastore = new DatastoreClient;
        $this->key = new Key('my-awesome-project', [
            [
                'path' => [
                    ['kind' => 'Person', 'name' => 'Bob']
                ]
            ]
        ]);
    }

    public function testClass()
    {
        $this->connection->beginTransaction(Argument::any())
            ->shouldBeCalled()
            ->willReturn([
                'transaction' => 'foo'
            ]);

        $client = TestHelpers::stub(DatastoreClient::class);
        $client->___setProperty('connection', $this->connection->reveal());
        $snippet = $this->snippetFromClass(ReadOnlyTransaction::class);
        $snippet->setLine(2, '');
        $snippet->addLocal('datastore', $client);

        $res = $snippet->invoke('transaction');
        $this->assertInstanceOf(ReadOnlyTransaction::class, $res->returnVal());
    }

    public function testClassRollback()
    {
        $this->connection->beginTransaction(Argument::any())
            ->shouldBeCalled()
            ->willReturn([
                'transaction' => 'foo'
            ]);
        $this->connection->lookup(Argument::any())
            ->shouldBeCalled()
            ->willReturn([]);
        $this->connection->rollback(Argument::any())
            ->shouldBeCalled();

        $snippet = $this->snippetFromClass(ReadOnlyTransaction::class, 1);

        $client = TestHelpers::stub(DatastoreClient::class, [], ['connection', 'operation']);
        $this->operation->___setProperty('connection', $this->connection->reveal());
        $client->___setProperty('operation', $this->operation);
        $client->___setProperty('connection', $this->connection->reveal());

        $transaction = $client->readOnlyTransaction();

        $snippet->addLocal('datastore', $client);
        $snippet->addLocal('transaction', $transaction);

        $snippet->invoke('userData');
    }

    public function testLookup()
    {
        $snippet = $this->snippetFromMethod(ReadOnlyTransaction::class, 'lookup');
        $snippet->addLocal('datastore', $this->datastore);
        $snippet->addLocal('transaction', $this->transaction);

        $this->connection->lookup(Argument::that(function ($args) {
            if ($args['transaction'] !== $this->transactionId) return false;
            return true;
        }))
            ->shouldBeCalled()
            ->willReturn([
                'found' => [
                    [
                        'entity' => [
                            'key' => [
                                'path' => [
                                    ['kind' => 'Person', 'name' => 'Bob']
                                ]
                            ],
                            'properties' => [
                                'firstName' => [
                                    'stringValue' => 'Bob'
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

        $this->operation->___setProperty('connection', $this->connection->reveal());

        $res = $snippet->invoke();
        $this->assertEquals('Bob', $res->output());
    }

    public function testLookupBatch()
    {
        $snippet = $this->snippetFromMethod(ReadOnlyTransaction::class, 'lookupBatch');
        $snippet->addLocal('datastore', $this->datastore);
        $snippet->addLocal('transaction', $this->transaction);

        $this->connection->lookup(Argument::that(function ($args) {
            if ($args['transaction'] !== $this->transactionId) return false;
            return true;
        }))
            ->shouldBeCalled()
            ->willReturn([
                'found' => [
                    [
                        'entity' => [
                            'key' => [
                                'path' => [
                                    ['kind' => 'Person', 'name' => 'Bob']
                                ]
                            ],
                            'properties' => [
                                'firstName' => [
                                    'stringValue' => 'Bob'
                                ]
                            ]
                        ]
                    ],
                    [
                        'entity' => [
                            'key' => [
                                'path' => [
                                    ['kind' => 'Person', 'name' => 'John']
                                ]
                            ],
                            'properties' => [
                                'firstName' => [
                                    'stringValue' => 'John'
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

        $this->operation->___setProperty('connection', $this->connection->reveal());

        $res = $snippet->invoke();
        $this->assertEquals("Bob", explode("\n", $res->output())[0]);
        $this->assertEquals("John", explode("\n", $res->output())[1]);
    }

    public function testRunQuery()
    {
        $snippet = $this->snippetFromMethod(ReadOnlyTransaction::class, 'runQuery');
        $snippet->addLocal('datastore', $this->datastore);
        $snippet->addLocal('transaction', $this->transaction);
        $snippet->addLocal('query', $this->prophesize(QueryInterface::class)->reveal());

        $this->connection->runQuery(Argument::that(function ($args) {
            if ($args['transaction'] !== $this->transactionId) return false;
            return true;
        }))
            ->shouldBeCalled()
            ->willReturn([
                'batch' => [
                    'entityResults' => [
                        [
                            'entity' => [
                                'key' => [
                                    'path' => [
                                        ['kind' => 'Person', 'name' => 'Bob']
                                    ]
                                ],
                                'properties' => [
                                    'firstName' => [
                                        'stringValue' => 'Bob'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

        $this->operation->___setProperty('connection', $this->connection->reveal());

        $res = $snippet->invoke('result');
        $this->assertEquals('Bob', $res->output());
    }

    public function testRollback()
    {
        $snippet = $this->snippetFromMethod(ReadOnlyTransaction::class, 'rollback');
        $snippet->addLocal('transaction', $this->transaction);

        $this->connection->rollback(Argument::any())
            ->shouldBeCalled();

        $this->operation->___setProperty('connection', $this->connection->reveal());

        $snippet->invoke();
    }
}
