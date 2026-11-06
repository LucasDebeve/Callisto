<?php

declare(strict_types=1);

namespace App\Account\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class AcceptablePasswordValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof AcceptablePassword) {
            throw new UnexpectedTypeException($constraint, AcceptablePassword::class);
        }
        if (null === $value || '' === $value) {
            return;
        }
        $value = (string) $value;

        foreach (PasswordRules::violations($value, $constraint->username) as $rule) {
            $message = match ($rule) {
                PasswordRules::TOO_SHORT => $constraint->tooShort,
                PasswordRules::TOO_COMMON => $constraint->tooCommon,
                PasswordRules::CONTAINS_USERNAME => $constraint->containsUsername,
                default => $constraint->tooSimple,
            };
            $this->context->buildViolation($message)->setParameter('{{ min }}', (string) AcceptablePassword::MIN_LENGTH)->addViolation();
        }
    }
}
