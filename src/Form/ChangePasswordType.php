<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, ['mapped' => false, 'constraints' => [new NotBlank()]])
            ->add('newPassword', PasswordType::class, ['mapped' => false, 'constraints' => [
                new NotBlank(),
                new Length(min: 8),
                new Regex(pattern: '/[A-Z]/', message: 'Include an uppercase letter.'),
                new Regex(pattern: '/[a-z]/', message: 'Include a lowercase letter.'),
                new Regex(pattern: '/[0-9]/', message: 'Include a number.'),
                new Regex(pattern: '/[\W_]/', message: 'Include a special character.'),
            ]])
            ->add('confirmPassword', PasswordType::class, ['mapped' => false, 'constraints' => [new NotBlank()]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
