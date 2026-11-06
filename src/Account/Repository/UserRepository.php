<?php

declare(strict_types=1);

namespace App\Account\Repository;

use App\Account\Entity\SchoolClass;
use App\Account\Entity\User;
use App\Account\Entity\UserKind;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findOneByUsernameAndKind(string $username, UserKind $kind): ?User
    {
        return $this->findOneBy(['username' => $username, 'kind' => $kind]);
    }

    public function findStudent(int $id): ?User
    {
        return $this->findOneBy(['id' => $id, 'kind' => UserKind::Student]);
    }

    /** @return list<User> */
    public function findStudentsOf(SchoolClass $class): array
    {
        return $this->findBy(['schoolClass' => $class, 'kind' => UserKind::Student], ['displayName' => 'ASC', 'username' => 'ASC']);
    }

    /**
     * @param list<string> $usernames
     *
     * @return list<string> identifiants déjà utilisés
     */
    public function existingUsernames(array $usernames): array
    {
        if ([] === $usernames) {
            return [];
        }

        return array_column($this->createQueryBuilder('u')
            ->select('u.username')
            ->where('u.username IN (:names)')
            ->setParameter('names', $usernames)
            ->getQuery()
            ->getArrayResult(), 'username');
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }
        $user->setPassword($newHashedPassword, $user->mustChangePassword());
        $this->getEntityManager()->flush();
    }

    public function hashAndSet(User $user, string $plainPassword, bool $provisional, UserPasswordHasherInterface $hasher): void
    {
        $user->setPassword($hasher->hashPassword($user, $plainPassword), $provisional);
    }
}
