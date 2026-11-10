<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\AdminUserType;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/users', name: 'app_admin_user_')]
class AdminUserController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, UserRepository $users): Response
    {
        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
            'role' => (string) $request->query->get('role', ''),
            'status' => (string) $request->query->get('status', ''),
        ];

        return $this->render('admin/users.html.twig', [
            'users' => $users->findForManagement($filters['q'], $filters['role'], $filters['status']),
            'filters' => $filters,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $user = new User();
        $form = $this->createForm(AdminUserType::class, $user, [
            'password_required' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $role = (string) $form->get('accountRole')->getData();
            $password = (string) $form->get('password')->getData();
            if ($users->findOneBy(['username' => $user->getUsername()])) {
                $form->get('username')->addError(new FormError('This username is already in use.'));
            } elseif ($users->findOneBy(['email' => $user->getEmail()])) {
                $form->get('email')->addError(new FormError('This email is already in use.'));
            } elseif (!$this->validPassword($password)) {
                $form->get('password')->addError(new FormError('Use at least 8 characters with uppercase, lowercase, a number, and a symbol.'));
            } else {
                $this->applyRole($user, $role);
                $user->setPassword($passwordHasher->hashPassword($user, $password));
                $entityManager->persist($user);

                try {
                    $entityManager->flush();
                    $this->addFlash('success', 'User account created.');

                    return $this->redirectToRoute('app_admin_user_index');
                } catch (UniqueConstraintViolationException) {
                    $form->addError(new FormError('That username or email is already in use.'));
                }
            }
        }

        return $this->render('admin/user_form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Create User Account',
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        User $user,
        Request $request,
        UserRepository $users,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $form = $this->createForm(AdminUserType::class, $user, [
            'account_role' => $this->roleFor($user),
            'role_locked' => $this->isCurrentUser($user),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $role = $this->isCurrentUser($user) ? 'ROLE_ADMIN' : (string) $form->get('accountRole')->getData();
            $password = trim((string) $form->get('password')->getData());
            $losesAdminAccess = $user->isAdmin() && ($role !== 'ROLE_ADMIN' || !$user->isActive());
            $usernameOwner = $users->findOneBy(['username' => $user->getUsername()]);
            $emailOwner = $users->findOneBy(['email' => $user->getEmail()]);

            if ($usernameOwner instanceof User && $usernameOwner->getId() !== $user->getId()) {
                $form->get('username')->addError(new FormError('This username is already in use.'));
            } elseif ($emailOwner instanceof User && $emailOwner->getId() !== $user->getId()) {
                $form->get('email')->addError(new FormError('This email is already in use.'));
            } elseif ($this->isCurrentUser($user) && !$user->isActive()) {
                $form->get('isActive')->addError(new FormError('You cannot disable your own administrator account.'));
            } elseif ($losesAdminAccess && $users->countActiveAdministrators($user) === 0) {
                $form->get('accountRole')->addError(new FormError('At least one active administrator must remain.'));
            } elseif ($password !== '' && !$this->validPassword($password)) {
                $form->get('password')->addError(new FormError('Use at least 8 characters with uppercase, lowercase, a number, and a symbol.'));
            } else {
                $this->applyRole($user, $role);
                if ($password !== '') {
                    $user->setPassword($passwordHasher->hashPassword($user, $password));
                }
                $user->setMiddleName($user->getMiddleName() === '' ? null : $user->getMiddleName());

                try {
                    $entityManager->flush();
                    $this->addFlash('success', 'User account updated.');

                    return $this->redirectToRoute('app_admin_user_index');
                } catch (UniqueConstraintViolationException) {
                    $form->addError(new FormError('That username or email is already in use.'));
                }
            }
        }

        return $this->render('admin/user_form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Edit User Account',
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(
        User $user,
        Request $request,
        UserRepository $users,
        OrderRepository $orders,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('delete_user_'.$user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        if ($this->isCurrentUser($user)) {
            $this->addFlash('error', 'You cannot delete your own account.');
        } elseif ($user->isAdmin() && $user->isActive() && $users->countActiveAdministrators($user) === 0) {
            $this->addFlash('error', 'At least one active administrator must remain.');
        } elseif ($orders->count(['userId' => $user->getId()]) > 0) {
            $this->addFlash('error', 'This account has order history and cannot be deleted. Disable it instead to preserve records.');
        } else {
            $entityManager->remove($user);
            $entityManager->flush();
            $this->addFlash('success', 'User account deleted.');
        }

        return $this->redirectToRoute('app_admin_user_index');
    }

    private function roleFor(User $user): string
    {
        if ($user->isAdmin()) {
            return 'ROLE_ADMIN';
        }

        return $user->isStaff() ? 'ROLE_STAFF' : 'ROLE_USER';
    }

    private function isCurrentUser(User $user): bool
    {
        $currentUser = $this->getUser();

        return $currentUser instanceof User && $currentUser->getId() === $user->getId();
    }

    private function applyRole(User $user, string $role): void
    {
        $user->setIsAdmin($role === 'ROLE_ADMIN');
        $user->setIsStaff($role === 'ROLE_STAFF');
    }

    private function validPassword(string $password): bool
    {
        return strlen($password) >= 8
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[0-9]/', $password) === 1
            && preg_match('/[\W_]/', $password) === 1;
    }
}
