<?php

namespace App\Controller;

use App\Entity\Cv;
use App\Entity\CvStatus;
use App\Entity\Position;
use App\Entity\User;
use App\Entity\UserAttributeValue;
use App\Repository\AttributeRepository;
use App\Repository\CvRepository;
use App\Security\Voter\CvVoter;
use App\Service\CvGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/cv')]
final class CvController extends AbstractController
{
    #[Route('/create/{positionId}', name: 'cv_create', methods: ['POST'])]
    #[IsGranted('ROLE_CANDIDATE')]
    public function create(int $positionId, EntityManagerInterface $em, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $position = $em->getRepository(Position::class)->find($positionId);
        if (!$position) {
            throw $this->createNotFoundException();
        }

        $existing = $em->getRepository(Cv::class)->findOneBy(['user' => $user, 'position' => $position]);
        if ($existing) {
            return $this->redirectToRoute('cv_show', ['id' => $existing->getId()]);
        }

        $cv = new Cv();
        $cv->setUser($user);
        $cv->setPosition($position);
        $em->persist($cv);
        $em->flush();

        return $this->redirectToRoute('cv_show', ['id' => $cv->getId()]);
    }

    #[Route('/{id}', name: 'cv_show', methods: ['GET'])]
    public function show(Cv $cv, CvGenerator $generator): Response
    {
        $this->denyAccessUnlessGranted(CvVoter::VIEW, $cv);

        $data = $generator->generate($cv);
        $isOwner = $this->getUser() instanceof User && $this->getUser()->getId() === $cv->getUser()->getId();
        $canEdit = $this->isGranted(CvVoter::EDIT, $cv);
        $canPublish = $this->isGranted(CvVoter::PUBLISH, $cv) && $generator->isReadyToPublish($cv);

        return $this->render('cv/show.html.twig', [
            'cv' => $cv,
            'attributes' => $data['attributes'],
            'projects' => $data['projects'],
            'isOwner' => $isOwner,
            'canEdit' => $canEdit,
            'canPublish' => $canPublish,
        ]);
    }

    #[Route('/{id}/publish', name: 'cv_publish', methods: ['POST'])]
    public function publish(Cv $cv, CvGenerator $generator, EntityManagerInterface $em, Request $request): Response
    {
        $this->denyAccessUnlessGranted(CvVoter::PUBLISH, $cv);

        if (!$this->isCsrfTokenValid('publish-cv-'.$cv->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if (!$generator->isReadyToPublish($cv)) {
            $this->addFlash('error', 'Барлық өрісті толтырыңыз.');
            return $this->redirectToRoute('cv_show', ['id' => $cv->getId()]);
        }

        $cv->setStatus(CvStatus::PUBLISHED);
        $em->flush();

        $this->addFlash('success', 'CV published.');
        return $this->redirectToRoute('cv_show', ['id' => $cv->getId()]);
    }

    #[Route('/{id}/delete', name: 'cv_delete', methods: ['POST'])]
    public function delete(Cv $cv, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(CvVoter::DELETE, $cv);

        if ($this->isCsrfTokenValid('delete-cv-'.$cv->getId(), $request->request->get('_token'))) {
            $em->remove($cv);
            $em->flush();
        }

        return $this->redirectToRoute('profile_index');
    }

    #[Route('/{id}/attribute/{attributeId}/autosave', name: 'cv_attribute_autosave', methods: ['POST'])]
    public function autosaveAttribute(
        Cv $cv,
        int $attributeId,
        Request $request,
        AttributeRepository $attributeRepo,
        EntityManagerInterface $em,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(CvVoter::EDIT, $cv);

        $attribute = $attributeRepo->find($attributeId);
        if (!$attribute) {
            return new JsonResponse(['error' => 'Attribute not found'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $newValue = $data['value'] ?? null;
        $clientVersion = (int) ($data['version'] ?? 0);

        $uav = $em->getRepository(UserAttributeValue::class)->findOneBy([
            'user' => $cv->getUser(),
            'attribute' => $attribute,
        ]);

        if (!$uav) {
            // Attribute not yet in profile — create it now (per spec: editing in CV adds it to the profile)
            $uav = new UserAttributeValue();
            $uav->setUser($cv->getUser());
            $uav->setAttribute($attribute);
            $em->persist($uav);
        } elseif ($clientVersion !== $uav->getVersion()) {
            return new JsonResponse(['conflict' => true, 'currentVersion' => $uav->getVersion()], 409);
        }

        $uav->setValue($newValue);

        try {
            $em->flush();
        } catch (OptimisticLockException) {
            return new JsonResponse(['conflict' => true], 409);
        }

        return new JsonResponse(['ok' => true, 'version' => $uav->getVersion(), 'valueId' => $uav->getId()]);
    }
}
