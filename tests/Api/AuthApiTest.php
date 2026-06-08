<?php

namespace App\Tests\Api;

class AuthApiTest extends ApiTestCase
{
    public function testRegisterLoginAndMe(): void
    {
        $token = $this->authenticate('auth@example.com');

        $this->jsonRequest('GET', '/api/me', token: $token);
        self::assertResponseIsSuccessful();
        self::assertSame('auth@example.com', $this->lastJson()['email']);
        self::assertContains('ROLE_USER', $this->lastJson()['roles']);
    }

    public function testMeRequiresAuthentication(): void
    {
        $this->jsonRequest('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);
    }

    public function testDuplicateEmailIsRejected(): void
    {
        $this->jsonRequest('POST', '/api/register', ['email' => 'dup@example.com', 'password' => 'secret123']);
        self::assertResponseStatusCodeSame(201);

        $this->jsonRequest('POST', '/api/register', ['email' => 'dup@example.com', 'password' => 'secret123']);
        self::assertResponseStatusCodeSame(409);
    }

    public function testRegistrationValidationFails(): void
    {
        $this->jsonRequest('POST', '/api/register', ['email' => 'not-an-email', 'password' => 'x']);
        self::assertResponseStatusCodeSame(422);
    }
}
