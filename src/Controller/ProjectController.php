<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\ProjectTag;
use App\Entity\User;
use App\Repository\ProjectTagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile/projects')]
#[IsGranted('ROLE_USER')]
final class ProjectController extends AbstractController
{
    #[Route('/new', name: 'project_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, ProjectTagRepository $tagRepo): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $project = new Project();
            $project->setUser($user);
            $this->applyRequest($project, $request, $em, $tagRepo);
            $em->persist($project);
            $em->flush();

            return $this->redirectToRoute('profile_index');
        }

        return $this->render('project/form.html.twig', ['project' => null]);
    }

    #[Route('/{id}/edit', name: 'project_edit', methods: ['GET', 'POST'])]
    public function edit(Project $project, Request $request, EntityManagerInterface $em, ProjectTagRepository $tagRepo): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($project->getUser()->getId() !== $user->getId() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        if ($request->isMethod('POST')) {
            $submittedVersion = (int) $request->request->get('_version');
            if ($submittedVersion !== $project->getVersion()) {
                $this->addFlash('error', 'Жоба басқа жерде өзгертілді. Қайта көріңіз.');
                return $this->redirectToRoute('project_edit', ['id' => $project->getId()]);
            }

            $this->applyRequest($project, $request, $em, $tagRepo);
            $em->flush();

            return $this->redirectToRoute('profile_index');
        }

        return $this->render('project/form.html.twig', ['project' => $project]);
    }

    #[Route('/{id}/delete', name: 'project_delete', methods: ['POST'])]
    public function delete(Project $project, Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($project->getUser()->getId() !== $user->getId() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete-project-'.$project->getId(), $request->request->get('_token'))) {
            $em->remove($project);
            $em->flush();
        }

        return $this->redirectToRoute('profile_index');
    }

    private function applyRequest(Project $project, Request $request, EntityManagerInterface $em, ProjectTagRepository $tagRepo): void
    {
        $project->setName($request->request->get('name'));
        $project->setDescription($request->request->get('description'));

        $start = $request->request->get('periodStart');
        $end = $request->request->get('periodEnd');
        $project->setPeriodStart($start ? new \DateTimeImmutable($start) : null);
        $project->setPeriodEnd($end ? new \DateTimeImmutable($end) : null);

        foreach ($project->getTags()->toArray() as $existingTag) {
            $project->removeTag($existingTag);
        }

        $tagsRaw = trim((string) $request->request->get('tags', ''));
        if ('' !== $tagsRaw) {
            foreach (array_filter(array_map('trim', explode(',', $tagsRaw))) as $tagName) {
                $tag = $tagRepo->findOneBy(['name' => $tagName]);
                if (!$tag) {
                    $tag = new ProjectTag();
                    $tag->setName($tagName);
                    $em->persist($tag);
                }
                $project->addTag($tag);
            }
        }
    }
}
