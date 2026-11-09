<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationType;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $users,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $form = $this->createForm(RegistrationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            if ($data['password'] !== $data['confirm_password']) {
                $form->get('confirm_password')->addError(new FormError('Passwords do not match.'));
            } elseif ($users->findOneBy(['username' => $data['username']])) {
                $form->get('username')->addError(new FormError('This username is already registered.'));
            } elseif ($users->findOneBy(['email' => $data['email']])) {
                $form->get('email')->addError(new FormError('This email is already registered.'));
            } else {
                $user = (new User())
                    ->setUsername($data['username'])
                    ->setFirstName($data['first_name'])
                    ->setMiddleName($data['middle_name'] !== '' ? $data['middle_name'] : null)
                    ->setLastName($data['last_name'])
                    ->setEmail($data['email'])
                    ->setAddress($data['address'])
                    ->setPhone($data['phone']);
                $user->setPassword($passwordHasher->hashPassword($user, $data['password']));
                $entityManager->persist($user);

                try {
                    $entityManager->flush();
                    $this->addFlash('success', 'Account created successfully. You can now log in.');

                    return $this->redirectToRoute('app_login');
                } catch (UniqueConstraintViolationException) {
                    $form->addError(new FormError('That username or email is already registered.'));
                }
            }
        }

        return $this->render('registration/register.html.twig', ['form' => $form->createView()]);
    }
}
