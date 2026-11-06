<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Account\Entity\SchoolClass;
use App\Account\Service\ClassCsvParser;
use App\Account\Service\ProvisionalPasswordGenerator;
use App\Account\Validator\PasswordRules;
use PHPUnit\Framework\TestCase;

final class AccountTest extends TestCase
{
    public function testCsvParsing(): void
    {
        $result = (new ClassCsvParser())->parse("identifiant;prénom;classe\nlea.m;Léa M.;2nde3\r\nTOM.B;Tom B.;2nde3\n\n");

        self::assertSame([], $result['errors']);
        self::assertCount(2, $result['rows']);
        self::assertSame('tom.b', $result['rows'][1]['username']);
    }

    public function testCsvRejectsFullNamesDuplicatesAndBadIdentifiers(): void
    {
        $result = (new ClassCsvParser())->parse("lea.m,Léa Martin,2nde3\nlea.m,Léa M.,2nde3\nlea.m,Léa M.,2nde3\nmauvais id,Zoé Z.,2nde3\nx");

        self::assertCount(4, $result['errors']);
        self::assertStringContainsString('initiale', $result['errors'][0]);
        self::assertStringContainsString('double', $result['errors'][1]);
    }

    public function testPasswordRules(): void
    {
        self::assertContains(PasswordRules::TOO_SHORT, PasswordRules::violations('abc1'));
        self::assertContains(PasswordRules::TOO_COMMON, PasswordRules::violations('azerty123'));
        self::assertContains(PasswordRules::TOO_COMMON, PasswordRules::violations('Football2026!'));
        self::assertContains(PasswordRules::CONTAINS_USERNAME, PasswordRules::violations('lea.m-2026-ok', 'lea.m'));
        self::assertContains(PasswordRules::TOO_SIMPLE, PasswordRules::violations('abcdefghijkl'));
        self::assertSame([], PasswordRules::violations('Orbite-Jaune-42', 'lea.m'));
    }

    public function testProvisionalPasswordsAreAcceptable(): void
    {
        $generator = new ProvisionalPasswordGenerator();
        for ($i = 0; $i < 50; ++$i) {
            self::assertSame([], PasswordRules::violations($generator->generate()));
        }
    }

    public function testSchoolYear(): void
    {
        self::assertSame('2026-2027', SchoolClass::currentSchoolYear(new \DateTimeImmutable('2026-11-06')));
        self::assertSame('2026-2027', SchoolClass::currentSchoolYear(new \DateTimeImmutable('2027-06-30')));
    }
}
