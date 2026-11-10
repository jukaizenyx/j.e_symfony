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

#[IsGranted('ROLE_STAFF')]
#[Route('/staff/products', name: 'app_staff_product_')]
class StaffProductController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, ProductRepository $products): Response
    {
        $staff = $this->staffUser();
        $search = trim((string) $request->query->get('q', ''));
        $productList = $products->findForManagement($staff, $search);
        $productSizes = [];
        foreach ($productList as $product) {
            $sizes = json_decode($product->getSizes(), true);
            $productSizes[$product->getId()] = is_array($sizes) ? $sizes : [];
        }

        return $this->render('staff/products.html.twig', [
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
                $form->get('sizes')->addError(new FormError('Add at least one size with a non-negative price.'));
            } else {
                $imageFile = $form->get('imageFile')->getData();
                if (!($imageFile instanceof UploadedFile)) {
                    $form->get('imageFile')->addError(new FormError('Choose an image for this product.'));
                } else {
                    $imagePath = $productManager->storeImage($imageFile);
                    $product->setImage($imagePath)->setCreatedBy($this->staffUser());
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

                    return $this->redirectToRoute('app_staff_product_index');
                }
            }
        }

        return $this->render('admin_product/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Add Product',
            'backRoute' => 'app_staff_product_index',
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        int $id,
        Request $request,
        ProductRepository $products,
        EntityManagerInterface $entityManager,
        ProductManager $productManager,
    ): Response {
        $product = $this->ownedProduct($id, $products);
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$productManager->applySizes($product, $form->get('sizes')->getData())) {
                $form->get('sizes')->addError(new FormError('Add at least one size with a non-negative price.'));
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

                return $this->redirectToRoute('app_staff_product_index');
            }
        }

        return $this->render('admin_product/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Edit Product',
            'currentImage' => $product->getImage(),
            'backRoute' => 'app_staff_product_index',
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(
        int $id,
        Request $request,
        ProductRepository $products,
        EntityManagerInterface $entityManager,
        ProductManager $productManager,
    ): Response {
        $product = $this->ownedProduct($id, $products);
        if (!$this->isCsrfTokenValid('delete_staff_product_'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $imagePath = $product->getImage();
        $entityManager->remove($product);
        $entityManager->flush();
        $productManager->removeStoredImage($imagePath);
        $this->addFlash('success', 'Product deleted.');

        return $this->redirectToRoute('app_staff_product_index');
    }

    private function staffUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->isStaff()) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function ownedProduct(int $id, ProductRepository $products): Product
    {
        $product = $products->findOneBy(['id' => $id, 'createdBy' => $this->staffUser()]);
        if (!$product instanceof Product) {
            throw $this->createNotFoundException('Product not found.');
        }

        return $product;
    }
}
