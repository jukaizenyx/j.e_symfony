<?php

namespace App\Service;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Psr\Log\LoggerInterface;

class StoreCatalog
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductCatalog $fallbackCatalog,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{products: list<array{id: int, name: string, image: string, description: string, sizes: array<string, int|float>}>, usingFallback: bool}
     */
    public function listing(): array
    {
        try {
            $products = $this->products->findBy([], ['id' => 'DESC']);
            if ($products !== []) {
                return [
                    'products' => array_map($this->formatProduct(...), $products),
                    'usingFallback' => false,
                ];
            }

            return ['products' => $this->fallbackCatalog->all(), 'usingFallback' => true];
        } catch (TableNotFoundException|ConnectionException $exception) {
            $this->logger->warning('Database catalog is unavailable; using the original PHP product catalog.', [
                'exception' => $exception,
            ]);

            return ['products' => $this->fallbackCatalog->all(), 'usingFallback' => true];
        }
    }

    /**
     * @return array{id: int, name: string, image: string, description: string, sizes: array<string, int|float>}|null
     */
    public function find(int $id): ?array
    {
        try {
            $product = $this->products->find($id);
            if ($product instanceof Product) {
                return $this->formatProduct($product);
            }

            if ($this->products->count([]) > 0) {
                return null;
            }

            return $this->fallbackCatalog->find($id);
        } catch (TableNotFoundException|ConnectionException $exception) {
            $this->logger->warning('Database catalog is unavailable; resolving the product from the original PHP product catalog.', [
                'product_id' => $id,
                'exception' => $exception,
            ]);

            return $this->fallbackCatalog->find($id);
        }
    }

    /**
     * @return array{id: int, name: string, image: string, description: string, sizes: array<string, int|float>}
     */
    private function formatProduct(Product $product): array
    {
        $id = $product->getId();
        if ($id === null) {
            throw new \LogicException('A persisted catalog product must have an identifier.');
        }

        $sizes = json_decode($product->getSizes(), true);

        return [
            'id' => $id,
            'name' => $product->getName(),
            'image' => $product->getImage(),
            'description' => $product->getDescription(),
            'sizes' => is_array($sizes) ? $sizes : [],
        ];
    }
}
