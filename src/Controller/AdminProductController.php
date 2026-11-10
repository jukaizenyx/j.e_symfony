<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\User;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use App\Service\ProductManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/products', name: 'app_admin_product_')]
class AdminProductController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, ProductRepository $products): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $productList = $products->findForManagement(search: $search);
        $productSizes = [];
        foreach ($productList as $product) {
            $sizes = json_decode($product->getSizes(), true);
            $productSizes[$product->getId()] = is_array($sizes) ? $sizes : [];
        }

        return $this->render('admin_product/index.html.twig', [
            'products' => $productList,
            'productSizes' => $productSizes,
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ProductManager $productManager): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$productManager->applySizes($product, $form->get('sizes')->getData())) {
                $form->get('sizes')->addError(new FormError('Sizes must be a non-empty JSON object with non-negative numeric prices.'));
            } else {
                $imageFile = $form->get('imageFile')->getData();
                if (!($imageFile instanceof UploadedFile)) {
                    $form->get('imageFile')->addError(new FormError('Choose an image for this product.'));
                } else {
                    $imagePath = $productManager->storeImage($imageFile);
                    $product->setImage($imagePath);
                    $owner = $this->getUser();
                    if (!$owner instanceof User) {
                        throw $this->createAccessDeniedException();
                    }
                    $product->setCreatedBy($owner);
                    $entityManager->persist($product);
                    try {
                        $entityManager->flush();
                    } catch (\Throwable $exception) {
                        try {
                            $productManager->removeStoredImage($imagePath);
                        } catch (\Throwable $cleanupException) {
                            throw new \RuntimeException('The product could not be saved and its uploaded image could not be removed.', 0, $cleanupException);
                        }
                        throw $exception;
                    }
                    $this->addFlash('success', 'Product added.');

                    return $this->redirectToRoute('app_admin_product_index');
                }
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
    public function edit(Product $product, Request $request, EntityManagerInterface $entityManager, ProductManager $productManager): Response
    {
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$productManager->applySizes($product, $form->get('sizes')->getData())) {
                $form->get('sizes')->addError(new FormError('Sizes must be a non-empty JSON object with non-negative numeric prices.'));
            } else {
                $imageFile = $form->get('imageFile')->getData();
                $imagePath = null;
                if ($imageFile instanceof UploadedFile) {
                    $imagePath = $productManager->storeImage($imageFile);
                    $product->setImage($imagePath);
                }
                try {
                    $entityManager->flush();
                } catch (\Throwable $exception) {
                    if ($imagePath !== null) {
                        try {
                            $productManager->removeStoredImage($imagePath);
                        } catch (\Throwable $cleanupException) {
                            throw new \RuntimeException('The product could not be saved and its uploaded image could not be removed.', 0, $cleanupException);
                        }
                    }
                    throw $exception;
                }
                $this->addFlash('success', 'Product updated.');

                return $this->redirectToRoute('app_admin_product_index');
            }
        }

        return $this->render('admin_product/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Edit product',
            'currentImage' => $product->getImage(),
        ]);
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

}
