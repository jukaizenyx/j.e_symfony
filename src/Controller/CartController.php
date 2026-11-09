<?php

namespace App\Controller;

use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CartController extends AbstractController
{
    #[Route('/cart', name: 'app_cart', methods: ['GET'])]
    public function index(CartService $cart): Response
    {
        return $this->render('cart/index.html.twig', ['cart' => $cart->snapshot()]);
    }

    #[Route('/cart/items', name: 'app_cart_snapshot', methods: ['GET'])]
    public function snapshot(CartService $cart): JsonResponse
    {
        return $this->json($cart->snapshot());
    }

    #[Route('/cart/items/{action}', name: 'app_cart_action', requirements: ['action' => 'add|update|remove'], methods: ['POST'])]
    public function mutate(
        string $action,
        Request $request,
        CartService $cart,
    ): Response {
        $data = $request->getPayload()->all();
        if (!$this->isCsrfTokenValid('cart_action', (string) ($data['_token'] ?? ''))) {
            return $this->json(['ok' => false, 'error' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        $productId = filter_var($data['product_id'] ?? null, FILTER_VALIDATE_INT);
        $size = trim((string) ($data['size'] ?? ''));
        $quantity = filter_var($data['qty'] ?? 1, FILTER_VALIDATE_INT);
        if ($productId === false || $productId <= 0 || $size === '' || $quantity === false) {
            return $this->json(['ok' => false, 'error' => 'Invalid cart item.'], Response::HTTP_BAD_REQUEST);
        }

        $result = match ($action) {
            'add' => $cart->add($productId, $size, $quantity),
            'update' => $cart->update($productId, $size, $quantity),
            'remove' => $this->removeItem($cart, $productId, $size),
        };

        if (!$result['ok']) {
            return $this->json($result, Response::HTTP_BAD_REQUEST);
        }

        $snapshot = $cart->snapshot();
        if ($request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'application/json')) {
            return $this->json($snapshot);
        }

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/cart', name: 'app_cart_add_from_shop', methods: ['POST'])]
    public function addFromShop(Request $request, CartService $cart): Response
    {
        if (!$this->isCsrfTokenValid('cart_action', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $productId = filter_var($request->request->get('product_id'), FILTER_VALIDATE_INT);
        $size = (string) $request->request->get('size');
        $quantity = filter_var($request->request->get('qty', 1), FILTER_VALIDATE_INT);
        $result = $productId !== false && $productId > 0 && $quantity !== false
            ? $cart->add($productId, $size, $quantity)
            : ['ok' => false, 'error' => 'Invalid cart item.'];

        if (!$result['ok']) {
            $this->addFlash('error', $result['error']);
        } else {
            $this->addFlash('success', 'Product added to your cart.');
        }

        return $this->redirectToRoute('app_shop');
    }

    /**
     * @return array{ok: true}
     */
    private function removeItem(CartService $cart, int $productId, string $size): array
    {
        $cart->remove($productId, $size);

        return ['ok' => true];
    }
}
