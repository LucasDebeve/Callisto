<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Account\Entity\User;
use App\Account\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base des tests fonctionnels : la base contient le contenu de référence (tests/Fixtures/content)
 * et les comptes de démonstration (callisto:demo:load), voir tests/bootstrap.php.
 */
abstract class AppTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    protected function user(string $username): User
    {
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['username' => $username]);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    protected function loginStudent(string $username = 'lea.m'): User
    {
        $user = $this->user($username);
        $this->client->loginUser($user, 'main');

        return $user;
    }

    protected function loginTeacher(): User
    {
        $user = $this->user('prof');
        $this->client->loginUser($user, 'teacher');

        return $user;
    }
}
