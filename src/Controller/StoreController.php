<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Service\ProductCatalog;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StoreController extends AbstractController
{
    #[Route('/shop', name: 'app_shop', methods: ['GET'])]
    public function index(ProductRepository $products, ProductCatalog $catalog, LoggerInterface $logger): Response
    {
        $catalogue = [];
        $clientProducts = [];
        try {
            foreach ($products->findBy([], ['id' => 'DESC']) as $product) {
                $sizes = json_decode($product->getSizes(), true);
                $sizes = is_array($sizes) ? $sizes : [];
                $catalogue[] = ['product' => $product, 'sizes' => $sizes];
                $clientProducts[] = [
                    'id' => $product->getId(),
                    'name' => $product->getName(),
                    'image' => $product->getImage(),
                    'description' => $product->getDescription(),
                    'sizes' => $sizes,
                ];
            }
        } catch (TableNotFoundException|ConnectionException $exception) {
            $logger->warning('Database catalog is unavailable; using the original PHP product catalog.', [
                'exception' => $exception,
            ]);
            foreach ($catalog->all() as $product) {
                $catalogue[] = ['product' => $product, 'sizes' => $product['sizes']];
                $clientProducts[] = $product;
            }
        }

        return $this->render('shop/index.html.twig', [
            'products' => $catalogue,
            'clientProducts' => $clientProducts,
            'usingFallbackCatalog' => $catalogue !== [] && !($catalogue[0]['product'] instanceof Product),
        ]);
    }

    #[Route('/shop/products/{id}', name: 'app_shop_product_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Product $product): Response
    {
        $sizes = json_decode($product->getSizes(), true);

        return $this->render('shop/show.html.twig', [
            'product' => $product,
            'sizes' => is_array($sizes) ? $sizes : [],
        ]);
    }
}
