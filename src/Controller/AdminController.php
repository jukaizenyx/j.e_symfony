<?php

namespace App\Controller;

use App\Entity\ContactMessage;
use App\Entity\Notification;
use App\Entity\Order;
use App\Entity\User;
use App\Repository\ContactMessageRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin', name: 'app_admin_')]
class AdminController extends AbstractController
{
    private const ORDER_STATUSES = ['pending', 'approved', 'cancelled', 'completed'];

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        OrderRepository $orders,
        ContactMessageRepository $messages,
        ProductRepository $products,
        UserRepository $users,
    ): Response
    {
        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
            'status' => (string) $request->query->get('status', ''),
        ];
        $orderList = $orders->findForManagement($filters['q'], $filters['status']);
        $contactList = $messages->findBy([], ['createdAt' => 'DESC']);
        $statistics = [
            'total' => $orders->count([]),
            'pending' => $orders->countByStatus('pending'),
            'approved' => $orders->countByStatus('approved'),
            'cancelled' => $orders->countByStatus('cancelled'),
            'completed' => $orders->countByStatus('completed'),
            'users' => $users->count([]),
            'staff' => $users->count(['isStaff' => true, 'isAdmin' => false]),
            'records' => $orders->count([]) + $products->count([]) + $messages->count([]),
        ];

        return $this->render('admin/index.html.twig', [
            'orders' => $orderList,
            'messages' => $contactList,
            'statistics' => $statistics,
            'filters' => $filters,
            'unreadCount' => count(array_filter($contactList, static fn (ContactMessage $message): bool => $message->getStatus() === 'Unread')),
        ]);
    }

    #[Route('/orders/{id}', name: 'order_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function showOrder(Order $order): Response
    {
        return $this->render('admin/order_show.html.twig', [
            'order' => $order,
            'backRoute' => 'app_admin_index',
            'statusRoute' => 'app_admin_order_status',
        ]);
    }

    #[Route('/orders/{id}/status', name: 'order_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function updateOrderStatus(
        Order $order,
        Request $request,
        UserRepository $users,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('order_status_'.$order->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $status = (string) $request->request->get('status');
        if (!in_array($status, self::ORDER_STATUSES, true)) {
            $this->addFlash('error', 'Invalid order status.');
        } elseif ($status !== $order->getStatus()) {
            $order->setStatus($status)->setUpdatedAt(new \DateTimeImmutable());
            $customer = $users->find($order->getUserId());
            if ($customer instanceof User) {
                $notification = (new Notification())
                    ->setUser($customer)
                    ->setOrder($order)
                    ->setMessage(sprintf('Your order #%d has been updated to %s.', $order->getId(), ucfirst($status)));
                $entityManager->persist($notification);
            } else {
                $this->addFlash('error', 'Order was updated, but its customer account was not found for notification.');
            }
            $entityManager->flush();
            $this->addFlash('success', 'Order status updated.');
        }

        return $this->redirectToRoute('app_admin_order_show', ['id' => $order->getId()]);
    }

    #[Route('/contact-messages/{id}', name: 'contact_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function showContactMessage(ContactMessage $message): Response
    {
        return $this->render('admin/contact_show.html.twig', ['message' => $message]);
    }

    #[Route('/contact-messages/{id}/status', name: 'contact_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function updateContactStatus(ContactMessage $message, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('contact_status_'.$message->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $status = (string) $request->request->get('status');
        if (!in_array($status, ['Unread', 'Read'], true)) {
            $this->addFlash('error', 'Invalid inquiry status.');
        } else {
            $message->setStatus($status);
            $entityManager->flush();
            $this->addFlash('success', 'Inquiry status updated.');
        }

        return $this->redirectToRoute('app_admin_contact_show', ['id' => $message->getId()]);
    }
}
