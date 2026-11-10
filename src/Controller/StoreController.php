<?php

namespace App\Controller;

use App\Service\StoreCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StoreController extends AbstractController
{
    #[Route('/shop', name: 'app_shop', methods: ['GET'])]
    public function index(StoreCatalog $catalog): Response
    {
        $listing = $catalog->listing();
        $products = array_map(static fn (array $product): array => [
            'product' => $product,
            'sizes' => $product['sizes'],
        ], $listing['products']);

        return $this->render('shop/index.html.twig', [
            'products' => $products,
            'clientProducts' => $listing['products'],
            'usingFallbackCatalog' => $listing['usingFallback'],
        ]);
    }

    #[Route('/shop/products/{id}', name: 'app_shop_product_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, StoreCatalog $catalog): Response
    {
        $product = $catalog->find($id);
        if ($product === null) {
            throw $this->createNotFoundException('Product not found.');
        }

        return $this->render('shop/show.html.twig', [
            'product' => $product,
            'sizes' => $product['sizes'],
        ]);
    }
}
