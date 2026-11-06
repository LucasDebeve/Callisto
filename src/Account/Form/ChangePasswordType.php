<?php

declare(strict_types=1);

namespace App\Account\Form;

use App\Account\Validator\AcceptablePassword;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<null>
 */
final class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['require_current']) {
            $builder->add('currentPassword', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'mapped' => false,
                'attr' => ['autocomplete' => 'current-password'],
                'constraints' => [new NotBlank(message: 'Indique ton mot de passe actuel.'), new UserPassword(message: 'Mot de passe actuel incorrect.')],
            ]);
        }
        $builder->add('newPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'mapped' => false,
            'invalid_message' => 'Les deux mots de passe ne sont pas identiques.',
            'first_options' => [
                'label' => 'Nouveau mot de passe',
                'attr' => ['autocomplete' => 'new-password', 'data-password-rules-target' => 'input', 'data-action' => 'input->password-rules#check'],
            ],
            'second_options' => [
                'label' => 'Confirme le mot de passe',
                'attr' => ['autocomplete' => 'new-password', 'data-password-rules-target' => 'confirm', 'data-action' => 'input->password-rules#check'],
            ],
            'constraints' => [
                new NotBlank(message: 'Choisis un mot de passe.'),
                new AcceptablePassword(username: $options['username']),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['username' => null, 'require_current' => false, 'csrf_token_id' => 'change_password']);
        $resolver->setAllowedTypes('username', ['null', 'string']);
    }
}
