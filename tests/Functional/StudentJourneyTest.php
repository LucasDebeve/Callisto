<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Progress\Service\ProgressRecorder;
use App\Quiz\Entity\Attempt;
use App\Quiz\Repository\AttemptRepository;

final class StudentJourneyTest extends AppTestCase
{
    public function testLoginWithWrongPasswordGivesAGenericMessage(): void
    {
        $crawler = $this->client->request('GET', '/connexion');
        $this->client->submit($crawler->selectButton('Se connecter')->form(['_username' => 'lea.m', '_password' => 'mauvais']));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-error', 'Identifiant ou mot de passe incorrect.');
    }

    public function testLoginAttemptsAreThrottledPerAccount(): void
    {
        $this->client->disableReboot(); // les compteurs vivent dans un cache en mémoire en test
        for ($i = 0; $i < 6; ++$i) {
            $crawler = $this->client->request('GET', '/connexion');
            $this->client->submit($crawler->selectButton('Se connecter')->form(['_username' => 'alan.t', '_password' => 'mauvais'.$i]));
        }
        // Même le bon mot de passe est refusé pendant le blocage temporaire (S3).
        $crawler = $this->client->request('GET', '/connexion');
        $this->client->submit($crawler->selectButton('Se connecter')->form(['_username' => 'alan.t', '_password' => 'callisto-demo-2026']));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-error', 'Trop d\'essais');
    }

    public function testTeacherCannotLogInThroughTheStudentForm(): void
    {
        $crawler = $this->client->request('GET', '/connexion');
        $this->client->submit($crawler->selectButton('Se connecter')->form(['_username' => 'prof', '_password' => 'callisto-demo-2026']));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-error', 'Identifiant ou mot de passe incorrect.');
    }

    public function testLoginAndFirstPasswordChangeIsMandatory(): void
    {
        $student = $this->user('ines.k');
        $student->setPassword(static::getContainer()->get('security.user_password_hasher')->hashPassword($student, 'comete-ruban-42'), true);
        static::getContainer()->get('doctrine')->getManager()->flush();

        $crawler = $this->client->request('GET', '/connexion');
        $this->client->submit($crawler->selectButton('Se connecter')->form(['_username' => 'ines.k', '_password' => 'comete-ruban-42']));
        $this->client->followRedirect();
        self::assertResponseRedirects('/premiere-connexion');

        $this->client->request('GET', '/historique');
        self::assertResponseRedirects('/premiere-connexion');

        $crawler = $this->client->request('GET', '/premiere-connexion');
        $form = $crawler->selectButton('Enregistrer mon mot de passe')->form();
        $this->client->submit($form, ['change_password[newPassword][first]' => 'azerty123', 'change_password[newPassword][second]' => 'azerty123']);
        self::assertResponseStatusCodeSame(422);
        self::assertAnySelectorTextContains('.field-error', 'trop courant');

        $this->client->submit($form, ['change_password[newPassword][first]' => 'Orbite-Jaune-42', 'change_password[newPassword][second]' => 'Orbite-Jaune-42']);
        self::assertResponseRedirects('/ciel');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Mon ciel');
    }

    public function testSkyConstellationAndHistory(): void
    {
        $this->loginStudent();
        $this->client->request('GET', '/ciel');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', '3 sur 8 validés');
        self::assertSelectorTextContains('main', 'Prochaines étapes');

        $this->client->request('GET', '/ciel/snt-internet?etoile=snt-int-tcp');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a.star.is-valide[data-code="snt-int-adresse-ip"]');
        self::assertSelectorExists('a.star.is-disponible[data-code="snt-int-tcp"]');
        self::assertSelectorExists('a.star.is-verrouille[data-code="snt-int-c-reseau"]');
        self::assertSelectorTextContains('[data-constellation-target=detail][data-code="snt-int-tcp"]:not([hidden])', 'Prérequis');

        // Un élève de SNT n'accède pas aux constellations d'un autre niveau.
        $this->client->request('GET', '/ciel/prem-donnees');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/historique');
        self::assertSelectorTextContains('main', 'Très bonne méthode');
    }

    public function testQuizFlowValidatesTheKnowledgeOnceAndNeverLeaksAnswers(): void
    {
        $student = $this->loginStudent('tom.b');
        $crawler = $this->client->request('GET', '/qcm/snt-int-tcp');
        $this->client->submit($crawler->selectButton('Commencer le QCM')->form());
        self::assertResponseRedirects();
        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertMatchesRegularExpression('#^/tentative/[0-9a-f-]+/question/1$#', $location);

        $attempt = static::getContainer()->get(AttemptRepository::class)->findOneBy(['student' => $student]);
        self::assertInstanceOf(Attempt::class, $attempt);
        $questions = (new \ReflectionProperty(Attempt::class, 'questions'))->getValue($attempt);

        for ($n = 1; $n <= $attempt->getTotal(); ++$n) {
            $crawler = $this->client->request('GET', \sprintf('/tentative/%s/question/%d', $attempt->getId(), $n));
            self::assertResponseIsSuccessful();
            $html = (string) $this->client->getResponse()->getContent();
            self::assertStringNotContainsString('"correct"', $html);
            self::assertStringNotContainsString('TCP signifie', $html, 'Aucune explication avant envoi.');

            // Réponse juste, lue dans l'instantané côté serveur.
            $q = $questions[$n - 1];
            $correct = array_values(array_map(static fn (array $c) => $c['key'], array_filter($q['choices'], static fn (array $c) => $c['correct'])));
            $answer = match ($q['type']) {
                'unique' => $correct[0],
                'multiple' => $correct,
                default => $q['acceptedAnswers'][0],
            };
            $form = $crawler->selectButton($n < $attempt->getTotal() ? 'Suivante' : 'Voir le récapitulatif')->form();
            $values = $form->getPhpValues();
            $values['reponse'] = $answer;
            $this->client->request('POST', $form->getUri(), $values);
            self::assertResponseRedirects();
        }

        $crawler = $this->client->request('GET', '/tentative/'.$attempt->getId().'/recapitulatif');
        self::assertSelectorTextContains('main', 'Tu as répondu à 3 questions sur 3');
        $this->client->submit($crawler->selectButton('Envoyer mes réponses')->form());
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Savoir validé. Une étoile de plus');
        self::assertSelectorTextContains('main', 'Explication');

        self::assertTrue(static::getContainer()->get(ProgressRecorder::class)->ledgerFor($student)->isValidated('snt-int-tcp'));

        // Une tentative ne se soumet qu'une fois.
        $this->client->request('POST', '/tentative/'.$attempt->getId().'/envoyer', ['_token' => $crawler->filter('input[name=_token]')->last()->attr('value')]);
        self::assertResponseRedirects('/tentative/'.$attempt->getId().'/resultat');
    }

    public function testCoursesArePublicButFilesAreNot(): void
    {
        $this->client->request('GET', '/cours/snt-cours-adresses-paquets');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Connecte-toi pour passer les QCM');
        self::assertSelectorExists('pre.code-block code.hljs');

        $this->client->request('GET', '/exercices/snt-ex-decoupage');
        self::assertSelectorTextContains('details summary', 'Afficher le corrigé');
        $this->client->request('GET', '/exercices/snt-ex-meme-reseau');
        self::assertSelectorNotExists('details');
        self::assertStringNotContainsString('Oui, non, oui', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/fichiers/snt/01-internet/01-protocoles/fichiers/reseau-local.svg');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/fichiers/snt/01-internet/01-protocoles/fichiers/tp-reseau.txt');
        self::assertResponseRedirects('/connexion');
        $this->loginStudent();
        $this->client->request('GET', '/fichiers/snt/01-internet/01-protocoles/fichiers/tp-reseau.txt');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('attachment', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
    }

    public function testSecurityHeaders(): void
    {
        $this->client->request('GET', '/connexion');
        $headers = $this->client->getResponse()->headers;
        $csp = (string) $headers->get('Content-Security-Policy');

        self::assertStringContainsString("script-src 'self' 'nonce-", $csp);
        self::assertStringNotContainsString('unsafe-inline', $csp);
        self::assertStringContainsString("frame-ancestors 'none'", $csp);
        self::assertSame('nosniff', $headers->get('X-Content-Type-Options'));
        self::assertSame('same-origin', $headers->get('Referrer-Policy'));
        self::assertSame('DENY', $headers->get('X-Frame-Options'));
    }

    public function testHealthCheck(): void
    {
        $this->client->request('GET', '/sante');
        self::assertResponseIsSuccessful();
        self::assertSame(['statut' => 'ok', 'base' => 'ok'], json_decode((string) $this->client->getResponse()->getContent(), true));
    }
}
