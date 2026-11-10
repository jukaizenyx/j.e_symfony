<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\User;
use App\Form\ProfileType;
use App\Form\ChangePasswordType;
use App\Repository\NotificationRepository;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[IsGranted('ROLE_USER')]
#[Route('/account', name: 'app_account_')]
class AccountController extends AbstractController
{
    #[Route('/profile', name: 'profile', methods: ['GET', 'POST'])]
    public function profile(
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $users,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $existing = $users->findOneBy(['email' => $user->getEmail()]);
            if ($existing && $existing->getId() !== $user->getId()) {
                $form->get('email')->addError(new FormError('This email is already in use.'));
            } else {
                $user->setMiddleName($user->getMiddleName() === '' ? null : $user->getMiddleName());
                $entityManager->flush();
                $this->addFlash('success', 'Your profile has been updated.');

                return $this->redirectToRoute('app_account_profile');
            }
        }

        return $this->render('account/profile.html.twig', ['form' => $form->createView()]);
    }

    #[Route('/settings', name: 'settings', methods: ['GET'])]
    public function settings(): Response
    {
        return $this->render('account/settings.html.twig');
    }

    #[Route('/password', name: 'password', methods: ['GET', 'POST'])]
    public function changePassword(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $currentPassword = (string) $form->get('currentPassword')->getData();
            $newPassword = (string) $form->get('newPassword')->getData();
            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $form->get('currentPassword')->addError(new FormError('The current password is incorrect.'));
            } elseif ($newPassword !== $form->get('confirmPassword')->getData()) {
                $form->get('confirmPassword')->addError(new FormError('The new passwords do not match.'));
            } else {
                $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
                $entityManager->flush();
                $this->addFlash('success', 'Your password has been changed.');

                return $this->redirectToRoute('app_account_settings');
            }
        }

        return $this->render('account/change_password.html.twig', ['form' => $form->createView()]);
    }

    #[Route('/orders', name: 'orders', methods: ['GET'])]
    public function orders(
        OrderRepository $orders,
        NotificationRepository $notifications,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getId() === null) {
            throw $this->createAccessDeniedException();
        }

        $orderList = $orders->findBy(['userId' => $user->getId()], ['createdAt' => 'DESC']);
        $userNotifications = $notifications->findBy(['user' => $user], ['createdAt' => 'DESC'], 20);
        $hasUnread = false;
        foreach ($userNotifications as $notification) {
            if (!$notification->isRead()) {
                $notification->setIsRead(true);
                $hasUnread = true;
            }
        }
        if ($hasUnread) {
            $entityManager->flush();
        }

        return $this->render('account/orders.html.twig', [
            'orders' => $orderList,
            'notifications' => $userNotifications,
        ]);
    }

    #[Route('/orders/{id}', name: 'order_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function showOrder(int $id, OrderRepository $orders): Response
    {
        $user = $this->getUser();
        $order = $orders->findOneBy(['id' => $id, 'userId' => $user instanceof User ? $user->getId() : null]);
        if (!$order instanceof Order) {
            throw $this->createNotFoundException('Order not found.');
        }

        return $this->render('account/order_show.html.twig', ['order' => $order]);
    }

    #[Route('/orders/{id}/cancel', name: 'order_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancelOrder(int $id, Request $request, OrderRepository $orders, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$this->isCsrfTokenValid('cancel_order_'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid request token.');
        }

        $order = $orders->findOneBy(['id' => $id, 'userId' => $user->getId()]);
        if (!$order instanceof Order) {
            throw $this->createNotFoundException('Order not found.');
        }
        if ($order->getStatus() !== 'pending') {
            $this->addFlash('error', 'This order can no longer be cancelled.');
        } else {
            $order->setStatus('cancelled')->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();
            $this->addFlash('success', 'Your order has been cancelled.');
        }

        return $this->redirectToRoute('app_account_orders');
    }
}
