<?php

namespace App\Controller;

use App\Entity\Attribute;
use App\Entity\User;
use App\Entity\UserAttributeValue;
use App\Repository\AttributeRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserAttributeValueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
final class ProfileController extends AbstractController
{
    #[Route('', name: 'profile_index', methods: ['GET'])]
    public function index(AttributeRepository $attributeRepo, UserAttributeValueRepository $uavRepo, ProjectRepository $projectRepo): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $values = $uavRepo->findBy(['user' => $user]);
        $attachedIds = array_map(fn ($v) => $v->getAttribute()->getId(), $values);

        $available = $attributeRepo->createQueryBuilder('a')
            ->andWhere('a.id NOT IN (:ids)')
            ->setParameter('ids', $attachedIds ?: [0])
            ->orderBy('a.name', 'ASC')
            ->getQuery()->getResult();

        $projects = $projectRepo->findBy(['user' => $user], ['id' => 'DESC']);

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'values' => $values,
            'available' => $available,
            'projects' => $projects,
        ]);
    }

    #[Route('/me', name: 'profile_autosave_me', methods: ['POST'])]
    public function autosaveMe(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true) ?? [];

        $field = $data['field'] ?? null;
        $value = $data['value'] ?? null;
        $clientVersion = (int) ($data['version'] ?? 0);

        $allowed = ['firstName', 'lastName', 'location', 'photoUrl'];
        if (!in_array($field, $allowed, true)) {
            return new JsonResponse(['error' => 'Invalid field'], 400);
        }

        if ($clientVersion !== $user->getVersion()) {
            return new JsonResponse(['conflict' => true, 'currentVersion' => $user->getVersion()], 409);
        }

        $setter = 'set'.ucfirst($field);
        $user->$setter($value);

        try {
            $em->flush();
        } catch (OptimisticLockException) {
            return new JsonResponse(['conflict' => true], 409);
        }

        return new JsonResponse(['ok' => true, 'version' => $user->getVersion()]);
    }

    #[Route('/attributes/add', name: 'profile_attribute_add', methods: ['POST'])]
    public function addAttribute(Request $request, AttributeRepository $attributeRepo, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $attribute = $attributeRepo->find((int) $request->request->get('attribute_id'));

        if ($attribute) {
            $value = new UserAttributeValue();
            $value->setUser($user);
            $value->setAttribute($attribute);
            $em->persist($value);
            $em->flush();
        }

        return $this->redirectToRoute('profile_index');
    }

    #[Route('/attributes/{id}/remove', name: 'profile_attribute_remove', methods: ['POST'])]
    public function removeAttribute(UserAttributeValue $value, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($value->getUser()->getId() !== $user->getId() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($value);
        $em->flush();

        return $this->redirectToRoute('profile_index');
    }

    #[Route('/attributes/{id}/autosave', name: 'profile_attribute_autosave', methods: ['POST'])]
    public function autosaveAttribute(UserAttributeValue $value, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($value->getUser()->getId() !== $user->getId() && !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $newValue = $data['value'] ?? null;
        $clientVersion = (int) ($data['version'] ?? 0);

        if ($clientVersion !== $value->getVersion()) {
            return new JsonResponse(['conflict' => true, 'currentVersion' => $value->getVersion()], 409);
        }

        $value->setValue($newValue);

        try {
            $em->flush();
        } catch (OptimisticLockException) {
            return new JsonResponse(['conflict' => true], 409);
        }

        return new JsonResponse(['ok' => true, 'version' => $value->getVersion()]);
    }
}
