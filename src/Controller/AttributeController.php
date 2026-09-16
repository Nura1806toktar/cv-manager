<?php

namespace App\Controller;

use App\Entity\Attribute;
use App\Entity\AttributeCategory;
use App\Form\AttributeFormType;
use App\Repository\AttributeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/attributes')]
#[IsGranted('ROLE_RECRUITER')]
final class AttributeController extends AbstractController
{
    #[Route('', name: 'attribute_index', methods: ['GET'])]
    public function index(Request $request, AttributeRepository $repo): Response
    {
        $qb = $repo->createQueryBuilder('a')->orderBy('a.id', 'DESC');

        $search = trim((string) $request->query->get('q', ''));
        if ('' !== $search) {
            $qb->andWhere('a.name LIKE :q')->setParameter('q', $search.'%');
        }

        $category = $request->query->get('category');
        if ($category && AttributeCategory::tryFrom($category)) {
            $qb->andWhere('a.category = :cat')->setParameter('cat', AttributeCategory::from($category));
        }

        $attributes = $qb->getQuery()->getResult();

        return $this->render('attribute/index.html.twig', [
            'attributes' => $attributes,
            'categories' => AttributeCategory::cases(),
            'search' => $search,
            'selectedCategory' => $category,
        ]);
    }

    #[Route('/new', name: 'attribute_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $attribute = new Attribute();
        $form = $this->createForm(AttributeFormType::class, $attribute);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($attribute);
            $em->flush();
            $this->addFlash('success', 'Attribute created.');
            return $this->redirectToRoute('attribute_index');
        }

        return $this->render('attribute/form.html.twig', ['form' => $form, 'attribute' => null]);
    }

    #[Route('/{id}/edit', name: 'attribute_edit', methods: ['GET', 'POST'])]
    public function edit(Attribute $attribute, Request $request, EntityManagerInterface $em): Response
    {
        $submittedVersion = $request->request->all('attribute')['_version'] ?? null;

        $form = $this->createForm(AttributeFormType::class, $attribute);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (null !== $submittedVersion && (int) $submittedVersion !== $attribute->getVersion()) {
                $this->addFlash('error', 'Бұл атрибутты басқа адам өзгертіп үлгерді. Деректерді жаңартып, қайта көріңіз.');
                return $this->redirectToRoute('attribute_edit', ['id' => $attribute->getId()]);
            }

            try {
                $em->flush();
            } catch (OptimisticLockException) {
                $this->addFlash('error', 'Қайшылық: бұл жазба өзгертілген. Қайта көріңіз.');
                return $this->redirectToRoute('attribute_edit', ['id' => $attribute->getId()]);
            }

            $this->addFlash('success', 'Attribute updated.');
            return $this->redirectToRoute('attribute_index');
        }

        return $this->render('attribute/form.html.twig', ['form' => $form, 'attribute' => $attribute]);
    }

    #[Route('/bulk-delete', name: 'attribute_bulk_delete', methods: ['POST'])]
    public function bulkDelete(Request $request, AttributeRepository $repo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('bulk-delete-attribute', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $ids = $request->request->all('ids');
        foreach ($ids as $id) {
            $attribute = $repo->find((int) $id);
            if ($attribute) {
                $em->remove($attribute);
            }
        }
        $em->flush();

        $this->addFlash('success', count($ids).' attribute(s) deleted.');
        return $this->redirectToRoute('attribute_index');
    }
}
