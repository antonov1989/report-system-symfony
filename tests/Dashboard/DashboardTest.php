<?php

namespace App\Tests\Dashboard;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class DashboardTest extends WebTestCase
{
    public function testDashboardRequiresLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/dashboard');

        // Anonymous users are redirected to the login page.
        self::assertResponseRedirects();
    }

    public function testDashboardRendersForAuthenticatedUser(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        $hasher = $container->get(UserPasswordHasherInterface::class);
        $em = $container->get('doctrine')->getManager();

        $user = new User();
        $user->setEmail('dash@example.com');
        $user->setName('Dash');
        $user->setPassword($hasher->hashPassword($user, 'secret123'));
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);
        $client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Overview');
        self::assertSelectorExists('#monthlyChart');
    }
}
