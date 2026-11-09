<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/products', name: 'app_admin_product_')]
class AdminProductController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(ProductRepository $products): Response
    {
        $productList = $products->findBy([], ['id' => 'DESC']);
        $productSizes = [];
        foreach ($productList as $product) {
            $sizes = json_decode($product->getSizes(), true);
            $productSizes[$product->getId()] = is_array($sizes) ? $sizes : [];
        }

        return $this->render('admin_product/index.html.twig', [
            'products' => $productList,
            'productSizes' => $productSizes,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $product->setSizes($this->encodeSizes($form->get('sizes')->getData()));
            if (!$this->validSizes($product->getSizes())) {
                $form->get('sizes')->addError(new FormError('Sizes must be a non-empty JSON object with non-negative numeric prices.'));
            } else {
                $sizes = json_decode($product->getSizes(), true);
                $product->setPrice(number_format((float) reset($sizes), 2, '.', ''));
                $entityManager->persist($product);
                $entityManager->flush();
                $this->addFlash('success', 'Product added.');

                return $this->redirectToRoute('app_admin_product_index');
            }
        }

        return $this->render('admin_product/form.html.twig', ['form' => $form->createView(), 'title' => 'Add product']);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Product $product): Response
    {
        $sizes = json_decode($product->getSizes(), true);

        return $this->render('admin_product/show.html.twig', [
            'product' => $product,
            'sizes' => is_array($sizes) ? $sizes : [],
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Product $product, Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $product->setSizes($this->encodeSizes($form->get('sizes')->getData()));
            if (!$this->validSizes($product->getSizes())) {
                $form->get('sizes')->addError(new FormError('Sizes must be a non-empty JSON object with non-negative numeric prices.'));
            } else {
                $sizes = json_decode($product->getSizes(), true);
                $product->setPrice(number_format((float) reset($sizes), 2, '.', ''));
                $entityManager->flush();
                $this->addFlash('success', 'Product updated.');

                return $this->redirectToRoute('app_admin_product_index');
            }
        }

        return $this->render('admin_product/form.html.twig', ['form' => $form->createView(), 'title' => 'Edit product']);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Product $product, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete_product_'.$product->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $entityManager->remove($product);
        $entityManager->flush();
        $this->addFlash('success', 'Product deleted.');

        return $this->redirectToRoute('app_admin_product_index');
    }

    private function validSizes(string $sizes): bool
    {
        $decoded = json_decode($sizes, true);
        if (!is_array($decoded) || $decoded === [] || array_is_list($decoded)) {
            return false;
        }

        foreach ($decoded as $size => $price) {
            if (!is_string($size) || trim($size) === '' || !is_numeric($price) || (float) $price < 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array{size?: string, price?: int|float|string|null}> $rows
     */
    private function encodeSizes(array $rows): string
    {
        $sizes = [];
        foreach ($rows as $row) {
            $size = trim((string) ($row['size'] ?? ''));
            $price = $row['price'] ?? null;
            if ($size !== '' && $price !== null && $price !== '') {
                $sizes[$size] = (float) $price;
            }
        }

        return json_encode($sizes, JSON_THROW_ON_ERROR);
    }
}
