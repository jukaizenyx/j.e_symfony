<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class AdminUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, ['constraints' => [new NotBlank(), new Length(min: 3, max: 20), new Regex(pattern: '/^[a-zA-Z0-9_]+$/')]])
            ->add('firstName', TextType::class, ['constraints' => [new NotBlank(), new Length(max: 50), new Regex(pattern: '/^[a-zA-Z ]+$/', message: 'Use letters and spaces only.')]])
            ->add('middleName', TextType::class, ['required' => false, 'constraints' => [new Length(max: 50), new Regex(pattern: '/^[a-zA-Z ]*$/', message: 'Use letters and spaces only.')]])
            ->add('lastName', TextType::class, ['constraints' => [new NotBlank(), new Length(max: 50), new Regex(pattern: '/^[a-zA-Z ]+$/', message: 'Use letters and spaces only.')]])
            ->add('email', EmailType::class, ['constraints' => [new NotBlank(), new Email(), new Length(max: 150)]])
            ->add('address', TextareaType::class, ['constraints' => [new NotBlank(), new Length(max: 255)]])
            ->add('phone', TelType::class, ['constraints' => [new NotBlank(), new Length(max: 20)]])
            ->add('accountRole', ChoiceType::class, [
                'mapped' => false,
                'data' => $options['account_role'],
                'choices' => ['Customer' => 'ROLE_USER', 'Staff' => 'ROLE_STAFF', 'Administrator' => 'ROLE_ADMIN'],
                'disabled' => $options['role_locked'],
            ])
            ->add('isActive', CheckboxType::class, ['required' => false, 'label' => 'Account is active'])
            ->add('password', PasswordType::class, [
                'mapped' => false,
                'required' => false,
                'help' => $options['password_required'] ? 'Required for new accounts. Use at least 8 characters with uppercase, lowercase, a number, and a symbol.' : 'Leave blank to keep the current password.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'account_role' => 'ROLE_USER',
            'role_locked' => false,
            'password_required' => false,
        ]);
        $resolver->setAllowedTypes('account_role', 'string');
        $resolver->setAllowedTypes('role_locked', 'bool');
        $resolver->setAllowedTypes('password_required', 'bool');
    }
}
