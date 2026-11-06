<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Account\Entity\User;
use App\Account\Entity\UserKind;
use App\Quiz\Domain\AnswerChecker;
use App\Quiz\Domain\AnswerNormalizer;
use App\Quiz\Domain\QuestionDrawer;
use App\Quiz\Domain\QuestionType;
use App\Quiz\Domain\ThresholdPolicy;
use App\Quiz\Entity\Attempt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

final class QuizDomainTest extends TestCase
{
    /** @return iterable<array{string, string}> */
    public static function normalizations(): iterable
    {
        yield ['  TCP ', 'tcp'];
        yield ["Domain   Name\tSystem", 'domain name system'];
        yield ["0xF\u{00A0}", '0xf'];
    }

    #[DataProvider('normalizations')]
    public function testShortAnswersAreNormalized(string $input, string $expected): void
    {
        self::assertSame($expected, AnswerNormalizer::normalize($input));
    }

    public function testAnswerChecking(): void
    {
        self::assertTrue(AnswerChecker::isCorrect(QuestionType::Single, ['b'], [], 'b'));
        self::assertFalse(AnswerChecker::isCorrect(QuestionType::Single, ['b'], [], 'a'));
        self::assertFalse(AnswerChecker::isCorrect(QuestionType::Single, ['b'], [], null));
        self::assertTrue(AnswerChecker::isCorrect(QuestionType::Multiple, ['a', 'c'], [], ['c', 'a']));
        self::assertFalse(AnswerChecker::isCorrect(QuestionType::Multiple, ['a', 'c'], [], ['a']));
        self::assertFalse(AnswerChecker::isCorrect(QuestionType::Multiple, ['a', 'c'], [], ['a', 'b', 'c']));
        self::assertTrue(AnswerChecker::isCorrect(QuestionType::Short, [], ['DNS', 'Domain Name System'], ' domain name  system'));
        self::assertFalse(AnswerChecker::isCorrect(QuestionType::Short, [], ['DNS'], '   '));
    }

    public function testThreshold(): void
    {
        self::assertTrue(ThresholdPolicy::isReached(10, 10, 100));
        self::assertFalse(ThresholdPolicy::isReached(9, 10, 100));
        self::assertTrue(ThresholdPolicy::isReached(8, 10, 80));
        self::assertTrue(ThresholdPolicy::isReached(1, 2, 50));
        self::assertFalse(ThresholdPolicy::isReached(0, 0, 50));
        self::assertSame(3, ThresholdPolicy::requiredScore(4, 70));
    }

    public function testDrawerTakesAtMostTheBankAndIsRandom(): void
    {
        $drawer = new QuestionDrawer(new Randomizer(new Mt19937(42)));
        $pool = range(1, 20);
        $drawn = $drawer->draw($pool, 10);

        self::assertCount(10, $drawn);
        self::assertCount(10, array_unique($drawn));
        self::assertNotSame(range(1, 10), $drawn);
        self::assertCount(3, $drawer->draw([1, 2, 3], 10));
    }

    private function attempt(\DateTimeImmutable $now): Attempt
    {
        $student = new User('eleve', 'Élève E.', UserKind::Student);
        $questions = [
            ['code' => 'q1', 'type' => 'unique', 'promptHtml' => '<p>Q1</p>', 'codeHtml' => null, 'choices' => [['key' => 'a', 'html' => 'A', 'correct' => false], ['key' => 'b', 'html' => 'B', 'correct' => true]], 'acceptedAnswers' => [], 'explanationHtml' => null],
            ['code' => 'q2', 'type' => 'courte', 'promptHtml' => '<p>Q2</p>', 'codeHtml' => null, 'choices' => [], 'acceptedAnswers' => ['TCP'], 'explanationHtml' => null],
        ];

        return new Attempt($student, 'savoir', 100, $questions, $now, $now->modify('+60 minutes'));
    }

    public function testPublicQuestionNeverContainsTheCorrection(): void
    {
        $attempt = $this->attempt(new \DateTimeImmutable());
        $public = $attempt->getPublicQuestion(0);

        self::assertStringNotContainsString('correct', json_encode($public, \JSON_THROW_ON_ERROR));
        $this->expectException(\LogicException::class);
        $attempt->getCorrectedQuestions();
    }

    public function testSubmitScoresOnceAndRejectsLateOrRepeatedSubmissions(): void
    {
        $now = new \DateTimeImmutable('2026-11-06 10:00');
        $attempt = $this->attempt($now);
        $attempt->recordAnswer(0, 'b', $now);
        $attempt->recordAnswer(1, ' tcp ', $now);
        $attempt->recordAnswer(0, 'z', $now); // clé inconnue : réponse effacée
        self::assertFalse($attempt->isAnswered(0));
        $attempt->recordAnswer(0, 'b', $now);

        self::assertTrue($attempt->submit($now->modify('+5 minutes')));
        self::assertSame(2, $attempt->getScore());

        $this->expectException(\DomainException::class);
        $attempt->submit($now->modify('+6 minutes'));
    }

    public function testExpiredAttemptCannotBeSubmitted(): void
    {
        $now = new \DateTimeImmutable('2026-11-06 10:00');
        $attempt = $this->attempt($now);

        $this->expectException(\DomainException::class);
        $attempt->submit($now->modify('+2 hours'));
    }
}
