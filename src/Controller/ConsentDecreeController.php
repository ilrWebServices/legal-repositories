<?php

namespace App\Controller;

use App\Entity\ConsentDecree;
use App\Form\ConsentDecreeType;
use App\Repository\ConsentDecreeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/consentdecree")
 */
class ConsentDecreeController extends AbstractController
{
    /**
     * @Route("/", name="consent_decree_index", methods={"GET"})
     */
    public function index(ConsentDecreeRepository $consentDecreeRepository): Response
    {
        return $this->render('consent_decree/index.html.twig', [
            'consent_decrees' => $consentDecreeRepository->findAll(),
        ]);
    }

    /**
     * @Route("/new", name="consent_decree_new", methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        $consentDecree = new ConsentDecree();
        $form = $this->createForm(ConsentDecreeType::class, $consentDecree);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($consentDecree);
            $entityManager->flush();

            return $this->redirectToRoute('consent_decree_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('consent_decree/new.html.twig', [
            'consent_decree' => $consentDecree,
            'form' => $form,
        ]);
    }

    /**
     * @Route("/{id}", name="consent_decree_show", methods={"GET"})
     */
    public function show(ConsentDecree $consentDecree): Response
    {
        return $this->render('consent_decree/show.html.twig', [
            'consent_decree' => $consentDecree,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="consent_decree_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, ConsentDecree $consentDecree): Response
    {
        $form = $this->createForm(ConsentDecreeType::class, $consentDecree);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('consent_decree_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('consent_decree/edit.html.twig', [
            'consent_decree' => $consentDecree,
            'form' => $form,
        ]);
    }

    /**
     * @Route("/{id}", name="consent_decree_delete", methods={"POST"})
     */
    public function delete(Request $request, ConsentDecree $consentDecree): Response
    {
        if ($this->isCsrfTokenValid('delete'.$consentDecree->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($consentDecree);
            $entityManager->flush();
        }

        return $this->redirectToRoute('consent_decree_index', [], Response::HTTP_SEE_OTHER);
    }
}
