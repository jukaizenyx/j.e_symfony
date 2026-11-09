<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, ['constraints' => [
                new NotBlank(),
                new Length(min: 3, max: 20),
                new Regex(pattern: '/^[a-zA-Z0-9_]+$/', message: 'Use only letters, numbers, and underscores.'),
            ]])
            ->add('first_name', TextType::class, ['constraints' => [new NotBlank(), new Length(max: 50), new Regex(pattern: '/^[a-zA-Z ]+$/')]])
            ->add('middle_name', TextType::class, ['required' => false, 'constraints' => [new Length(max: 50), new Regex(pattern: '/^[a-zA-Z ]*$/')]])
            ->add('last_name', TextType::class, ['constraints' => [new NotBlank(), new Length(max: 50), new Regex(pattern: '/^[a-zA-Z ]+$/')]])
            ->add('email', EmailType::class, ['constraints' => [new NotBlank(), new Email(), new Length(max: 150)]])
            ->add('address', TextType::class, ['constraints' => [new NotBlank(), new Length(max: 255)]])
            ->add('phone', TelType::class, ['constraints' => [new NotBlank(), new Regex(pattern: '/^[0-9]{10,15}$/', message: 'Enter 10 to 15 digits.')]])
            ->add('password', PasswordType::class, ['constraints' => [
                new NotBlank(),
                new Length(min: 8),
                new Regex(pattern: '/[A-Z]/', message: 'Include an uppercase letter.'),
                new Regex(pattern: '/[a-z]/', message: 'Include a lowercase letter.'),
                new Regex(pattern: '/[0-9]/', message: 'Include a number.'),
                new Regex(pattern: '/[\W_]/', message: 'Include a special character.'),
            ]])
            ->add('confirm_password', PasswordType::class, ['constraints' => [new NotBlank()]])
            ->add('terms', CheckboxType::class, ['constraints' => [new \Symfony\Component\Validator\Constraints\IsTrue(message: 'You must agree to the Terms and Conditions.')]])
            ->add('submit', SubmitType::class, ['label' => 'Register']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
