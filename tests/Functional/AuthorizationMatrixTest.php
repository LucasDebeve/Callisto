<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Account\Entity\User;
use App\Quiz\Repository\AttemptRepository;
use App\Quiz\Service\QuizRunner;
use App\Referential\Service\ReferentialCatalog;
use Symfony\Component\Routing\RouterInterface;

/**
 * S9 : pour chaque route, un élève ne peut ni lire ni modifier les données d'un autre élève,
 * ni atteindre l'espace enseignant. Toute nouvelle route doit être classée ici, sinon le test échoue.
 */
final class AuthorizationMatrixTest extends AppTestCase
{
    /** Routes publiques ou propres à l'élève connecté (aucun identifiant d'un autre élève dans l'URL). */
    private const SELF_OR_PUBLIC = [
        'home', 'app_login', 'app_logout', 'app_first_password', 'legal_notice', 'health',
        'course_index', 'course_show', 'exercise_show', 'content_file',
        'student_sky', 'student_constellation', 'student_history', 'student_preferences',
        'quiz_intro', 'quiz_start',
    ];

    /** Routes portant l'identifiant d'une tentative : réservées à son propriétaire. */
    private const ATTEMPT_ROUTES = ['quiz_question', 'quiz_review', 'quiz_submit', 'quiz_result'];

    /** Routes de connexion de l'espace enseignant, accessibles sans être connecté. */
    private const TEACHER_PUBLIC = ['teacher_login', 'teacher_logout', 'teacher_2fa_login', 'teacher_2fa_login_check'];

    public function testEveryRouteIsClassified(): void
    {
        $router = static::getContainer()->get(RouterInterface::class);
        foreach ($router->getRouteCollection()->all() as $name => $route) {
            if (str_starts_with($name, '_')) {
                continue;
            }
            $known = \in_array($name, [...self::SELF_OR_PUBLIC, ...self::ATTEMPT_ROUTES, ...self::TEACHER_PUBLIC], true)
                || str_starts_with($route->getPath(), '/enseignant');
            self::assertTrue($known, \sprintf('Route « %s » non classée dans la matrice d\'autorisation.', $name));
            if (str_starts_with($name, 'teacher_') && !\in_array($name, self::TEACHER_PUBLIC, true)) {
                self::assertStringStartsWith('/enseignant', $route->getPath(), 'Toute route enseignant vit sous /enseignant (S8).');
            }
        }
    }

    public function testStudentCannotReachAnyTeacherRoute(): void
    {
        $router = static::getContainer()->get(RouterInterface::class);
        $lea = $this->user('lea.m');
        $tom = $this->user('tom.b');
        $this->loginStudent('lea.m');
        foreach ($router->getRouteCollection()->all() as $name => $route) {
            if (!str_starts_with($route->getPath(), '/enseignant') || \in_array($name, self::TEACHER_PUBLIC, true)) {
                continue;
            }
            $path = strtr($route->getPath(), ['{id}' => (string) ('teacher_class_show' === $name ? $lea->getSchoolClass()?->getId() : $tom->getId()), '{code}' => 'snt-internet']);
            foreach ($route->getMethods() ?: ['GET'] as $method) {
                $this->client->request($method, $path, ['student' => $tom->getId(), 'item' => 'snt-int-sf-trajet', 'action' => 'valider']);
                $status = $this->client->getResponse()->getStatusCode();
                $location = (string) $this->client->getResponse()->headers->get('Location');
                self::assertTrue(
                    \in_array($status, [401, 403, 404, 405], true) || str_contains($location, '/enseignant/connexion'),
                    \sprintf('%s %s accessible à un élève (statut %d).', $method, $path, $status),
                );
            }
        }
        self::assertFalse(static::getContainer()->get(\App\Progress\Service\ProgressRecorder::class)->ledgerFor($this->user('tom.b'))->isValidated('snt-int-sf-trajet'));
    }

    public function testStudentCannotTouchAnotherStudentsAttempt(): void
    {
        $tom = $this->user('tom.b');
        $item = static::getContainer()->get(ReferentialCatalog::class)->item('snt-int-adresse-ip');
        self::assertNotNull($item);
        $attempt = static::getContainer()->get(QuizRunner::class)->start($tom, $item);
        $id = (string) $attempt->getId();

        $this->loginStudent('lea.m');
        foreach ([
            ['GET', "/tentative/$id/question/1"],
            ['POST', "/tentative/$id/question/1"],
            ['GET', "/tentative/$id/recapitulatif"],
            ['POST', "/tentative/$id/envoyer"],
            ['GET', "/tentative/$id/resultat"],
        ] as [$method, $path]) {
            $this->client->request($method, $path, ['reponse' => 'a', 'aller' => 'suivante']);
            self::assertResponseStatusCodeSame(404, "$method $path");
        }
        static::getContainer()->get('doctrine')->getManager()->clear();
        $fresh = static::getContainer()->get(AttemptRepository::class)->find($attempt->getId());
        self::assertNotNull($fresh);
        self::assertFalse($fresh->isSubmitted());
        self::assertSame([], $fresh->getAnswers());
    }

    public function testTeacherCannotManageAnotherTeachersStudents(): void
    {
        $other = new User('autre.prof', 'Autre P.', \App\Account\Entity\UserKind::Teacher);
        $other->setTotpSecret('JBSWY3DPEHPK3PXP');
        $em = static::getContainer()->get('doctrine')->getManager();
        $em->persist($other);
        $em->flush();
        $lea = $this->user('lea.m');

        $this->client->loginUser($other, 'teacher');
        $this->client->request('GET', '/enseignant/eleves/'.$lea->getId());
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/enseignant/classes/'.$lea->getSchoolClass()?->getId());
        self::assertResponseStatusCodeSame(403);
    }

    public function testAnonymousIsSentToLogin(): void
    {
        foreach (['/ciel', '/historique', '/preferences', '/qcm/snt-int-tcp'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseRedirects('/connexion', null, $path);
        }
        $this->client->request('GET', '/enseignant/classes');
        self::assertResponseRedirects('/enseignant/connexion');
    }
}
