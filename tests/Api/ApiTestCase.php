<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function jsonRequest(string $method, string $uri, array $payload = [], ?string $token = null): void
    {
        $headers = ['CONTENT_TYPE' => 'application/json'];
        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        $this->client->request($method, $uri, server: $headers, content: $payload === [] ? '' : json_encode($payload));
    }

    /**
     * @return array<string, mixed>
     */
    protected function lastJson(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true) ?? [];
    }

    /**
     * Register a user and return a valid JWT for them.
     */
    protected function authenticate(string $email = 'tester@example.com', string $password = 'secret123'): string
    {
        $this->jsonRequest('POST', '/api/register', [
            'email' => $email,
            'password' => $password,
            'name' => 'Tester',
        ]);
        self::assertResponseStatusCodeSame(201);

        $this->jsonRequest('POST', '/api/login', ['email' => $email, 'password' => $password]);
        self::assertResponseIsSuccessful();

        return $this->lastJson()['token'];
    }
}
