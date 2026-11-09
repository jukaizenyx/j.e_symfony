<?php

namespace App\Service;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Service\ProductCatalog;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class CartService
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ProductRepository $products,
        private readonly ProductCatalog $catalog,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{ok: bool, items: list<array<string, mixed>>, count: int, subtotal: float}
     */
    public function snapshot(): array
    {
        $session = $this->requestStack->getSession();
        $cart = $session->get('cart', []);
        $items = [];
        $count = 0;
        $subtotal = 0.0;

        foreach ($cart as $key => $entry) {
            $product = $this->findProduct((int) ($entry['product_id'] ?? 0));
            $size = (string) ($entry['size'] ?? '');
            $sizes = $product ? $product['sizes'] : [];

            if (!$product || !isset($sizes[$size])) {
                unset($cart[$key]);
                continue;
            }

            $quantity = max(1, min(20, (int) ($entry['qty'] ?? 1)));
            $price = (float) $sizes[$size];
            $lineTotal = $price * $quantity;
            $count += $quantity;
            $subtotal += $lineTotal;
            $items[] = [
                'key' => $key,
                'product_id' => $product['id'],
                'name' => $product['name'],
                'image' => $product['image'],
                'size' => $size,
                'price' => $price,
                'qty' => $quantity,
                'line_total' => $lineTotal,
            ];
        }

        $session->set('cart', $cart);

        return ['ok' => true, 'items' => $items, 'count' => $count, 'subtotal' => $subtotal];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function add(int $productId, string $size, int $quantity): array
    {
        $product = $this->findProduct($productId);
        if (!$product) {
            return ['ok' => false, 'error' => 'Product not found.'];
        }

        if (!isset($product['sizes'][$size])) {
            return ['ok' => false, 'error' => 'Invalid size selected.'];
        }

        $cart = $this->requestStack->getSession()->get('cart', []);
        $key = $productId . '_' . $size;
        $cart[$key] = [
            'product_id' => $productId,
            'size' => $size,
            'qty' => min(20, (int) ($cart[$key]['qty'] ?? 0) + max(1, min(20, $quantity))),
        ];
        $this->requestStack->getSession()->set('cart', $cart);

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function update(int $productId, string $size, int $quantity): array
    {
        $cart = $this->requestStack->getSession()->get('cart', []);
        $key = $productId . '_' . $size;
        if (!isset($cart[$key])) {
            return ['ok' => false, 'error' => 'Item not in cart.'];
        }

        if ($quantity <= 0) {
            unset($cart[$key]);
        } else {
            $cart[$key]['qty'] = min(20, $quantity);
        }
        $this->requestStack->getSession()->set('cart', $cart);

        return ['ok' => true];
    }

    public function remove(int $productId, string $size): void
    {
        $cart = $this->requestStack->getSession()->get('cart', []);
        unset($cart[$productId . '_' . $size]);
        $this->requestStack->getSession()->set('cart', $cart);
    }

    public function clear(): void
    {
        $this->requestStack->getSession()->set('cart', []);
    }

    /**
     * @return array{id: int, name: string, image: string, sizes: array<string, int|float>}|null
     */
    private function findProduct(int $productId): ?array
    {
        try {
            $product = $this->products->find($productId);
        } catch (TableNotFoundException|ConnectionException $exception) {
            $this->logger->warning('Database catalog is unavailable; resolving the cart item from the original PHP product catalog.', [
                'product_id' => $productId,
                'exception' => $exception,
            ]);

            return $this->catalog->find($productId);
        }

        if (!$product instanceof Product) {
            return null;
        }

        $sizes = json_decode($product->getSizes(), true);

        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'image' => $product->getImage(),
            'sizes' => is_array($sizes) ? $sizes : [],
        ];
    }
}
