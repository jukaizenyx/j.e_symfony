<?php

namespace App\Controller;

use App\Entity\ContactMessage;
use App\Form\ContactMessageType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('home/index.html.twig', [
            'contactForm' => $this->createForm(ContactMessageType::class, new ContactMessage())->createView(),
        ]);
    }

    #[Route('/contact', name: 'app_contact_create', methods: ['POST'])]
    public function contact(Request $request, EntityManagerInterface $entityManager): Response
    {
        $message = new ContactMessage();
        $form = $this->createForm(ContactMessageType::class, $message);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($message);
            $entityManager->flush();
            $this->addFlash('contact_success', 'Your message has been sent successfully. Thank you for reaching out!');

            return $this->redirectToRoute('app_home', ['_fragment' => 'contact']);
        }

        return $this->render('home/index.html.twig', ['contactForm' => $form->createView()]);
    }
}
