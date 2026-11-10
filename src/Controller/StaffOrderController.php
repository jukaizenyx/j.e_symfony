<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\Order;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_STAFF')]
#[Route('/staff/orders', name: 'app_staff_order_')]
class StaffOrderController extends AbstractController
{
    private const ORDER_STATUSES = ['pending', 'approved', 'cancelled', 'completed'];

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, OrderRepository $orders): Response
    {
        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
            'status' => (string) $request->query->get('status', ''),
        ];

        return $this->render('staff/orders.html.twig', [
            'orders' => $orders->findForManagement($filters['q'], $filters['status']),
            'filters' => $filters,
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Order $order): Response
    {
        return $this->render('admin/order_show.html.twig', [
            'order' => $order,
            'backRoute' => 'app_staff_order_index',
            'statusRoute' => 'app_staff_order_status',
        ]);
    }

    #[Route('/{id}/status', name: 'status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function updateStatus(
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

        return $this->redirectToRoute('app_staff_order_show', ['id' => $order->getId()]);
    }
}
