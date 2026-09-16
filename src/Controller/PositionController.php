<?php

namespace App\Controller;

use App\Entity\Position;
use App\Entity\PositionAttribute;
use App\Form\PositionFormType;
use App\Repository\AttributeRepository;
use App\Repository\PositionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/positions')]
final class PositionController extends AbstractController
{
    #[Route('', name: 'position_index', methods: ['GET'])]
    public function index(Request $request, PositionRepository $repo): Response
    {
        $qb = $repo->createQueryBuilder('p')->orderBy('p.createdAt', 'DESC');

        $user = $this->getUser();
        $isStaff = $user && ($this->isGranted('ROLE_RECRUITER') || $this->isGranted('ROLE_ADMIN'));

        if (!$isStaff) {
            $qb->andWhere('p.isPublic = true');
        }

        $search = trim((string) $request->query->get('q', ''));
        if ('' !== $search) {
            $qb->andWhere('p.title LIKE :q')->setParameter('q', '%'.$search.'%');
        }

        return $this->render('position/index.html.twig', [
            'positions' => $qb->getQuery()->getResult(),
            'search' => $search,
            'isStaff' => $isStaff,
        ]);
    }

    #[Route('/new', name: 'position_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_RECRUITER')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $position = new Position();
        $position->setCreatedBy($this->getUser());

        $form = $this->createForm(PositionFormType::class, $position);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($position);
            $em->flush();
            $this->addFlash('success', 'Position created.');
            return $this->redirectToRoute('position_attributes', ['id' => $position->getId()]);
        }

        return $this->render('position/form.html.twig', ['form' => $form, 'position' => null]);
    }

    #[Route('/{id}/edit', name: 'position_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_RECRUITER')]
    public function edit(Position $position, Request $request, EntityManagerInterface $em): Response
    {
        $submittedVersion = $request->request->all('position')['_version'] ?? null;

        $form = $this->createForm(PositionFormType::class, $position);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (null !== $submittedVersion && (int) $submittedVersion !== $position->getVersion()) {
                $this->addFlash('error', 'Позиция басқа адаммен өзгертілді. Қайта көріңіз.');
                return $this->redirectToRoute('position_edit', ['id' => $position->getId()]);
            }

            $position->touch();

            try {
                $em->flush();
            } catch (OptimisticLockException) {
                $this->addFlash('error', 'Қайшылық: позиция өзгертілген. Қайта көріңіз.');
                return $this->redirectToRoute('position_edit', ['id' => $position->getId()]);
            }

            $this->addFlash('success', 'Position updated.');
            return $this->redirectToRoute('position_index');
        }

        return $this->render('position/form.html.twig', ['form' => $form, 'position' => $position]);
    }

    #[Route('/{id}/duplicate', name: 'position_duplicate', methods: ['POST'])]
    #[IsGranted('ROLE_RECRUITER')]
    public function duplicate(Position $position, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('duplicate-position', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $copy = new Position();
        $copy->setCreatedBy($this->getUser());
        $copy->setTitle($position->getTitle().' (copy)');
        $copy->setShortDescription($position->getShortDescription());
        $copy->setIsPublic($position->isPublic());
        $copy->setMaxProjects($position->getMaxProjects());

        foreach ($position->getPositionAttributes() as $pa) {
            $newPa = new PositionAttribute();
            $newPa->setAttribute($pa->getAttribute());
            $newPa->setSortOrder($pa->getSortOrder());
            $copy->addPositionAttribute($newPa);
        }

        foreach ($position->getProjectTags() as $tag) {
            $copy->addProjectTag($tag);
        }

        $em->persist($copy);
        $em->flush();

        $this->addFlash('success', 'Position duplicated.');
        return $this->redirectToRoute('position_edit', ['id' => $copy->getId()]);
    }

    #[Route('/bulk-delete', name: 'position_bulk_delete', methods: ['POST'])]
    #[IsGranted('ROLE_RECRUITER')]
    public function bulkDelete(Request $request, PositionRepository $repo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('bulk-delete-position', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $ids = $request->request->all('ids');
        foreach ($ids as $id) {
            $position = $repo->find((int) $id);
            if ($position) {
                $em->remove($position);
            }
        }
        $em->flush();

        $this->addFlash('success', count($ids).' position(s) deleted.');
        return $this->redirectToRoute('position_index');
    }

    #[Route('/{id}/attributes', name: 'position_attributes', methods: ['GET'])]
    #[IsGranted('ROLE_RECRUITER')]
    public function attributes(Position $position, AttributeRepository $attributeRepo): Response
    {
        $attachedIds = array_map(
            fn ($pa) => $pa->getAttribute()->getId(),
            $position->getPositionAttributes()->toArray()
        );

        $available = $attributeRepo->createQueryBuilder('a')
            ->andWhere('a.id NOT IN (:ids)')
            ->setParameter('ids', $attachedIds ?: [0])
            ->orderBy('a.name', 'ASC')
            ->getQuery()->getResult();

        return $this->render('position/attributes.html.twig', [
            'position' => $position,
            'available' => $available,
        ]);
    }

    #[Route('/{id}/attributes/add', name: 'position_attribute_add', methods: ['POST'])]
    #[IsGranted('ROLE_RECRUITER')]
    public function addAttribute(Position $position, Request $request, AttributeRepository $attributeRepo, EntityManagerInterface $em): Response
    {
        $attribute = $attributeRepo->find((int) $request->request->get('attribute_id'));
        if ($attribute) {
            $pa = new PositionAttribute();
            $pa->setAttribute($attribute);
            $pa->setSortOrder($position->getPositionAttributes()->count());
            $position->addPositionAttribute($pa);
            $em->flush();
        }

        return $this->redirectToRoute('position_attributes', ['id' => $position->getId()]);
    }

    #[Route('/attributes/{paId}/remove', name: 'position_attribute_remove', methods: ['POST'])]
    #[IsGranted('ROLE_RECRUITER')]
    public function removeAttribute(int $paId, EntityManagerInterface $em): Response
    {
        $pa = $em->getRepository(PositionAttribute::class)->find($paId);
        if ($pa) {
            $positionId = $pa->getPosition()->getId();
            $em->remove($pa);
            $em->flush();
            return $this->redirectToRoute('position_attributes', ['id' => $positionId]);
        }

        return $this->redirectToRoute('position_index');
    }
}
