<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Account\Repository\UserRepository;
use App\Content\Import\ContentImporter;
use App\Progress\Entity\Validation;
use App\Progress\Repository\ValidationRepository;
use App\Progress\Service\ProgressRecorder;
use App\Referential\Service\ReferentialCatalog;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

final class IntegrityTest extends KernelTestCase
{
    public function testValidationsAreAppendOnlyInTheDatabase(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $id = $connection->fetchOne('SELECT id FROM progress_validation LIMIT 1');
        self::assertNotFalse($id);

        foreach (['UPDATE progress_validation SET comment = \'x\' WHERE id = ?', 'DELETE FROM progress_validation WHERE id = ?'] as $sql) {
            $connection->executeStatement('SAVEPOINT t');
            try {
                $connection->executeStatement($sql, [$id]);
                self::fail('Le déclencheur aurait dû refuser : '.$sql);
            } catch (DbalException $e) {
                self::assertStringContainsString('ajout seul', $e->getMessage());
            } finally {
                $connection->executeStatement('ROLLBACK TO SAVEPOINT t');
            }
        }
    }

    public function testDoctrineRefusesToModifyAValidation(): void
    {
        $em = static::getContainer()->get('doctrine')->getManager();
        $validation = static::getContainer()->get(ValidationRepository::class)->findOneBy([]);
        self::assertInstanceOf(Validation::class, $validation);
        $em->remove($validation);

        $this->expectException(\LogicException::class);
        $em->flush();
    }

    public function testQuizValidationIsCreatedOnlyOnce(): void
    {
        $recorder = static::getContainer()->get(ProgressRecorder::class);
        $lea = static::getContainer()->get(UserRepository::class)->findOneBy(['username' => 'lea.m']);
        self::assertNotNull($lea);

        self::assertFalse($recorder->grantFromQuiz($lea, 'snt-int-adresse-ip', \Symfony\Component\Uid\Uuid::v7()), 'Déjà validé.');
        self::assertTrue($recorder->grantFromQuiz($lea, 'snt-int-tcp', \Symfony\Component\Uid\Uuid::v7()));
        self::assertFalse($recorder->grantFromQuiz($lea, 'snt-int-tcp', \Symfony\Component\Uid\Uuid::v7()));
        self::assertFalse($recorder->grantFromQuiz($lea, 'snt-int-sf-reseau', \Symfony\Component\Uid\Uuid::v7()), 'Un savoir-faire ne se valide pas par QCM.');
    }

    public function testImportRejectsCyclesMissingAnswersHtmlAndBrokenLinks(): void
    {
        $dir = sys_get_temp_dir().'/callisto-import-'.bin2hex(random_bytes(4));
        $fs = new Filesystem();
        $fs->mirror(\dirname(__DIR__).'/Fixtures/content', $dir);
        $chapter = $dir.'/snt/01-internet/01-protocoles';
        $fs->dumpFile($chapter.'/chapitre.yaml', str_replace('prerequis: [snt-int-paquets]', 'prerequis: [snt-int-paquets, snt-int-c-reseau]', (string) file_get_contents($chapter.'/chapitre.yaml')));
        $fs->dumpFile($chapter.'/qcm/casse.yaml', "savoir: snt-int-tcp\nquestions:\n  - code: casse-q1\n    type: unique\n    enonce: Sans bonne réponse\n    choix:\n      - texte: A\n      - texte: B\n");
        $fs->appendToFile($chapter.'/cours/01-adresses-et-paquets.md', "\n<div>html</div>\n\n[mort](cours:inexistant)\n");

        try {
            [$report] = static::getContainer()->get(ContentImporter::class)->analyze($dir, false);
            $errors = implode("\n", $report->getErrors());

            self::assertFalse($report->isValid());
            self::assertStringContainsString('cycle', $errors);
            self::assertStringContainsString('exactement une bonne réponse', $errors);
            self::assertStringContainsString('HTML brut interdit', $errors);
            self::assertStringContainsString('Lien interne invalide', $errors);
        } finally {
            $fs->remove($dir);
        }
    }

    public function testReimportArchivesMissingItemsWithoutTouchingValidations(): void
    {
        $dir = sys_get_temp_dir().'/callisto-import-'.bin2hex(random_bytes(4));
        $fs = new Filesystem();
        $fs->mirror(\dirname(__DIR__).'/Fixtures/content', $dir);
        $fs->remove($dir.'/snt/01-internet/02-dns');
        $before = (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM progress_validation');

        try {
            $importer = static::getContainer()->get(ContentImporter::class);
            [$report, $content] = $importer->analyze($dir);
            self::assertTrue($report->isValid(), implode("\n", $report->getErrors()));
            self::assertContains('élément « snt-int-dns »', $report->getArchived());
            $importer->publish($content, $report, 'test');
        } finally {
            $fs->remove($dir);
        }

        $catalog = static::getContainer()->get(ReferentialCatalog::class);
        $catalog->reset();
        self::assertTrue($catalog->item('snt-int-dns')?->isArchived());
        self::assertSame($before, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM progress_validation'));
    }
}
