<?php

namespace App\Tests\Api;

use App\Message\BudgetExceededNotification;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class BudgetApiTest extends ApiTestCase
{
    public function testExceedingBudgetFlagsItAndQueuesAlert(): void
    {
        $token = $this->authenticate('budget@example.com');

        $this->jsonRequest('POST', '/api/accounts', ['name' => 'Main', 'currency' => 'USD'], $token);
        $accountId = $this->lastJson()['id'];

        $this->jsonRequest('POST', '/api/categories', ['name' => 'Dining', 'type' => 'expense'], $token);
        $categoryId = $this->lastJson()['id'];

        $period = (new \DateTimeImmutable('today'))->format('Y-m');
        $this->jsonRequest('POST', '/api/budgets', [
            'categoryId' => $categoryId, 'limitAmount' => '50.00', 'period' => $period,
        ], $token);
        self::assertResponseStatusCodeSame(201);

        // The test client reboots the kernel before each request, which resets the
        // in-memory transport — so capture dispatched messages right after this POST.
        $this->client->disableReboot();

        // Spend 60 > 50 limit.
        $this->jsonRequest('POST', '/api/transactions', [
            'accountId' => $accountId, 'categoryId' => $categoryId, 'type' => 'expense', 'amount' => '60.00',
        ], $token);
        self::assertResponseStatusCodeSame(201);

        // An alert message should have been dispatched to the async transport.
        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        $alerts = array_filter(
            $transport->getSent(),
            static fn ($env) => $env->getMessage() instanceof BudgetExceededNotification,
        );
        self::assertCount(1, $alerts);

        // Budget should report as exceeded.
        $this->jsonRequest('GET', '/api/budgets?period=' . $period, token: $token);
        $budget = $this->lastJson()[0];
        self::assertTrue($budget['exceeded']);
        self::assertEqualsWithDelta(60.0, $budget['spent'], 0.001);
    }
}
