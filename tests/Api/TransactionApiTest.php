<?php

namespace App\Tests\Api;

class TransactionApiTest extends ApiTestCase
{
    public function testBalanceReflectsTransactions(): void
    {
        $token = $this->authenticate('tx@example.com');

        $this->jsonRequest('POST', '/api/accounts', ['name' => 'Wallet', 'currency' => 'USD', 'openingBalance' => '100.00'], $token);
        self::assertResponseStatusCodeSame(201);
        $accountId = $this->lastJson()['id'];

        $this->jsonRequest('POST', '/api/categories', ['name' => 'Food', 'type' => 'expense'], $token);
        $categoryId = $this->lastJson()['id'];

        // +250 income
        $this->jsonRequest('POST', '/api/transactions', [
            'accountId' => $accountId, 'type' => 'income', 'amount' => '250.00',
        ], $token);
        self::assertResponseStatusCodeSame(201);

        // -40 expense
        $this->jsonRequest('POST', '/api/transactions', [
            'accountId' => $accountId, 'categoryId' => $categoryId, 'type' => 'expense', 'amount' => '40.00',
        ], $token);
        self::assertResponseStatusCodeSame(201);

        // 100 + 250 - 40 = 310
        $this->jsonRequest('GET', '/api/accounts/' . $accountId, token: $token);
        self::assertEqualsWithDelta(310.0, $this->lastJson()['currentBalance'], 0.001);
    }

    public function testListIsScopedAndPaginated(): void
    {
        $token = $this->authenticate('scope@example.com');
        $this->jsonRequest('POST', '/api/accounts', ['name' => 'A', 'currency' => 'USD'], $token);
        $accountId = $this->lastJson()['id'];

        for ($i = 0; $i < 3; ++$i) {
            $this->jsonRequest('POST', '/api/transactions', [
                'accountId' => $accountId, 'type' => 'expense', 'amount' => '5.00',
            ], $token);
        }

        $this->jsonRequest('GET', '/api/transactions?perPage=2', token: $token);
        self::assertResponseIsSuccessful();
        $json = $this->lastJson();
        self::assertSame(3, $json['meta']['total']);
        self::assertCount(2, $json['data']);
        self::assertSame(2, $json['meta']['pages']);
    }

    public function testCannotAccessAnotherUsersAccount(): void
    {
        $tokenA = $this->authenticate('owner@example.com');
        $this->jsonRequest('POST', '/api/accounts', ['name' => 'Private', 'currency' => 'USD'], $tokenA);
        $accountId = $this->lastJson()['id'];

        $tokenB = $this->authenticate('intruder@example.com');
        $this->jsonRequest('GET', '/api/accounts/' . $accountId, token: $tokenB);
        self::assertResponseStatusCodeSame(404);
    }
}
