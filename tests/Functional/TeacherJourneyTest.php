<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Administration\Command\LoadDemoCommand;
use App\Progress\Service\ProgressRecorder;
use OTPHP\TOTP;
use Symfony\Component\Clock\NativeClock;

final class TeacherJourneyTest extends AppTestCase
{
    public function testPasswordAloneIsNotEnough(): void
    {
        $crawler = $this->client->request('GET', '/enseignant/connexion');
        $this->client->submit($crawler->selectButton('Continuer')->form(['_username' => 'prof', '_password' => 'callisto-demo-2026']));
        self::assertResponseRedirects();

        $this->client->request('GET', '/enseignant');
        self::assertResponseRedirects('/enseignant/2fa');

        $crawler = $this->client->request('GET', '/enseignant/2fa');
        $this->client->submit($crawler->selectButton('Vérifier')->form(['_auth_code' => '000000']));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-error', 'Code incorrect');

        $code = TOTP::createFromSecret(LoadDemoCommand::DEMO_TOTP_SECRET, new NativeClock())->now();
        $crawler = $this->client->request('GET', '/enseignant/2fa');
        $this->client->submit($crawler->selectButton('Vérifier')->form(['_auth_code' => $code]));
        self::assertResponseRedirects('/enseignant');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Bonjour');
    }

    public function testStudentCannotLogIntoTeacherSpace(): void
    {
        $crawler = $this->client->request('GET', '/enseignant/connexion');
        $this->client->submit($crawler->selectButton('Continuer')->form(['_username' => 'lea.m', '_password' => 'callisto-demo-2026']));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-error', 'Identifiant ou mot de passe incorrect');
    }

    public function testEntryGridSavesWithoutReloadAndRevokes(): void
    {
        $this->loginTeacher();
        $tom = $this->user('tom.b');
        $class = $tom->getSchoolClass();
        $crawler = $this->client->request('GET', '/enseignant/classes/'.$class?->getId().'/saisie');
        self::assertResponseIsSuccessful();
        $token = (string) $crawler->filter('[data-entry-grid-token-value]')->attr('data-entry-grid-token-value');

        $this->client->request('POST', '/enseignant/saisie', [
            '_token' => $token, 'student' => $tom->getId(), 'item' => 'snt-int-sf-trajet', 'action' => 'valider', 'date' => '2026-11-02', 'comment' => 'Bien',
        ], [], ['HTTP_X-Requested-With' => 'fetch']);
        self::assertResponseIsSuccessful();
        self::assertSame(['validated' => true, 'date' => '02/11/2026', 'comment' => 'Bien'], $this->json());

        $this->client->request('POST', '/enseignant/saisie', [
            '_token' => $token, 'student' => $tom->getId(), 'item' => 'snt-int-sf-trajet', 'action' => 'retirer',
        ], [], ['HTTP_X-Requested-With' => 'fetch']);
        self::assertFalse($this->json()['validated']);
        self::assertFalse(static::getContainer()->get(ProgressRecorder::class)->ledgerFor($this->user('tom.b'))->isValidated('snt-int-sf-trajet'));

        // Jeton CSRF obligatoire.
        $this->client->request('POST', '/enseignant/saisie', ['student' => $tom->getId(), 'item' => 'snt-int-sf-trajet']);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/enseignant/journal?action=validation_retiree');
        self::assertSelectorTextContains('table', 'tom.b');
    }

    public function testClassImportShowsPasswordsOnce(): void
    {
        $this->loginTeacher();
        $crawler = $this->client->request('GET', '/enseignant/classes/import');
        $this->client->submit($crawler->selectButton('Importer et générer les mots de passe')->form([
            'csv' => "identifiant;prénom;classe\nzoe.a;Zoé A.;2nde5\nnoa.b;Noa B.;2nde5",
            'level' => 'snt',
            'year' => '2026-2027',
        ]));
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(2, '.print-sheet');
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertTrue($this->user('zoe.a')->mustChangePassword());

        // Identifiant déjà pris : rien n'est importé.
        $crawler = $this->client->request('GET', '/enseignant/classes/import');
        $this->client->submit($crawler->selectButton('Importer et générer les mots de passe')->form([
            'csv' => "lea.m;Léa M.;2nde5\nnew.c;New C.;2nde5", 'level' => 'snt', 'year' => '2026-2027',
        ]));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.alert-error', 'existe déjà');
    }

    public function testStudentViewExportAndDeletion(): void
    {
        $this->loginTeacher();
        $lea = $this->user('lea.m');
        $crawler = $this->client->request('GET', '/enseignant/eleves/'.$lea->getId());
        self::assertSelectorTextContains('main', '/ 20');

        $this->client->request('GET', '/enseignant/eleves/'.$lea->getId().'/export');
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('lea.m', $data['compte']['identifiant']);
        self::assertNotEmpty($data['validations']);

        $form = $crawler->selectButton('Supprimer')->form(['confirmation' => 'lea.m']);
        $this->client->submit($form);
        self::assertResponseRedirects();
        static::getContainer()->get('doctrine')->getManager()->clear();
        self::assertNull(static::getContainer()->get(\App\Account\Repository\UserRepository::class)->findOneBy(['username' => 'lea.m']));
    }

    public function testContentReportAndQuizBank(): void
    {
        $this->loginTeacher();
        $this->client->request('GET', '/enseignant/contenus');
        self::assertSelectorTextContains('main', 'Contenu valide');
        $this->client->request('GET', '/enseignant/qcm?savoir=snt-int-tcp');
        self::assertSelectorTextContains('main', 'Transmission Control Protocol');
    }

    /**
     * Lit la réponse courante (change à chaque requête).
     *
     * @return array<string, mixed>
     *
     * @phpstan-impure
     */
    private function json(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
