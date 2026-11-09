<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Service\CartService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

#[IsGranted('ROLE_USER')]
class CheckoutController extends AbstractController
{
    #[Route('/checkout', name: 'app_checkout', methods: ['GET', 'POST'])]
    public function checkout(Request $request, CartService $cart, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getId() === null) {
            throw $this->createAccessDeniedException();
        }

        $snapshot = $cart->snapshot();
        if ($snapshot['items'] === []) {
            $this->addFlash('error', 'Your cart is empty.');

            return $this->redirectToRoute('app_shop');
        }

        $initialName = trim(implode(' ', array_filter([
            $user->getFirstName(),
            $user->getMiddleName(),
            $user->getLastName(),
        ])));
        $form = $this->createFormBuilder([
            'full_name' => $initialName,
            'email' => $user->getEmail(),
            'phone' => $user->getPhone(),
            'address_type' => 'profile',
        ], ['csrf_protection' => true])
            ->add('full_name', TextType::class, ['constraints' => [new NotBlank()]])
            ->add('email', EmailType::class, ['constraints' => [new NotBlank(), new Email()]])
            ->add('phone', TextType::class, ['constraints' => [new NotBlank(), new Length(max: 50)]])
            ->add('address_type', ChoiceType::class, ['choices' => ['Use profile address' => 'profile', 'Use another address' => 'new'], 'expanded' => true])
            ->add('house', TextType::class, ['required' => false])
            ->add('street', TextType::class, ['required' => false])
            ->add('barangay', TextType::class, ['required' => false])
            ->add('city', TextType::class, ['required' => false])
            ->add('province', TextType::class, ['required' => false])
            ->add('zip', TextType::class, ['required' => false])
            ->add('payment_method', ChoiceType::class, ['choices' => [
                'Cash on Delivery' => 'Cash on Delivery',
                'GCash / QrPH' => 'GCash',
                'Bank Transfer' => 'Bank Transfer',
            ], 'expanded' => true, 'constraints' => [new NotBlank()]])
            ->add('payment_proof', FileType::class, ['required' => false, 'constraints' => [
                new File(maxSize: '5M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp']),
            ]])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $deliveryAddress = $data['address_type'] === 'profile'
                ? trim($user->getAddress())
                : trim(implode(', ', array_map('trim', [
                    $data['house'], $data['street'], $data['barangay'],
                    $data['city'], $data['province'], $data['zip'],
                ])));

            if ($deliveryAddress === '' || ($data['address_type'] === 'new' && in_array('', [
                $data['house'], $data['street'], $data['barangay'],
                $data['city'], $data['province'], $data['zip'],
            ], true))) {
                $form->addError(new FormError('Please provide a complete delivery address.'));
            } elseif ($data['payment_method'] === 'GCash' && !$data['payment_proof']) {
                $form->get('payment_proof')->addError(new FormError('Upload your GCash payment proof.'));
            } else {
                $paymentProofName = null;
                if ($data['payment_method'] === 'GCash' && $data['payment_proof']) {
                    $directory = $this->getParameter('kernel.project_dir').'/public/uploads/payment_proofs';
                    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                        throw new \RuntimeException('Could not create the payment proof directory.');
                    }
                    $paymentProofName = bin2hex(random_bytes(16)).'.'.$data['payment_proof']->guessExtension();
                    $data['payment_proof']->move($directory, $paymentProofName);
                }

                $subtotal = array_sum(array_column($snapshot['items'], 'line_total'));
                $order = (new Order())
                    ->setUserId($user->getId())
                    ->setFullName(trim($data['full_name']))
                    ->setEmail(trim($data['email']))
                    ->setPhone(trim($data['phone']))
                    ->setAddress($deliveryAddress)
                    ->setPaymentMethod($data['payment_method'])
                    ->setPaymentProof($paymentProofName)
                    ->setStatus('pending')
                    ->setTotal(number_format($subtotal + 80, 2, '.', ''));

                try {
                    $entityManager->wrapInTransaction(function () use ($entityManager, $order, $snapshot): void {
                        $entityManager->persist($order);
                        foreach ($snapshot['items'] as $cartItem) {
                            $item = (new OrderItem())
                                ->setOrder($order)
                                ->setProductId((int) $cartItem['product_id'])
                                ->setName((string) $cartItem['name'])
                                ->setSize((string) $cartItem['size'])
                                ->setPrice(number_format((float) $cartItem['price'], 2, '.', ''))
                                ->setQuantity((int) $cartItem['qty']);
                            $entityManager->persist($item);
                        }
                    });
                } catch (\Throwable $exception) {
                    if ($paymentProofName !== null) {
                        $savedFile = $this->getParameter('kernel.project_dir').'/public/uploads/payment_proofs/'.$paymentProofName;
                        if (is_file($savedFile) && !unlink($savedFile)) {
                            throw new \RuntimeException('Could not remove the payment proof after the order failed.', 0, $exception);
                        }
                    }

                    throw $exception;
                }

                $cart->clear();
                $this->addFlash('success', 'Your order has been placed.');

                return $this->redirectToRoute('app_account_order_show', ['id' => $order->getId()]);
            }
        }

        return $this->render('checkout/index.html.twig', [
            'form' => $form,
            'cart' => $snapshot,
            'shippingFee' => 80,
            'savedAddress' => $user->getAddress(),
            'form' => $form->createView(),
        ]);
    }
}
