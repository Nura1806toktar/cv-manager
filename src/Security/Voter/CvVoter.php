<?php

namespace App\Security\Voter;

use App\Entity\Cv;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class CvVoter extends Voter
{
    public const VIEW = 'CV_VIEW';
    public const EDIT = 'CV_EDIT';
    public const DELETE = 'CV_DELETE';
    public const PUBLISH = 'CV_PUBLISH';
    public const LIKE = 'CV_LIKE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::PUBLISH, self::LIKE], true)
            && $subject instanceof Cv;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $currentUser = $token->getUser();
        if (!$currentUser instanceof User) {
            return false;
        }

        /** @var Cv $cv */
        $cv = $subject;
        $roles = $currentUser->getRoles();

        // Admin can do everything
        if (in_array('ROLE_ADMIN', $roles, true)) {
            return true;
        }

        // Recruiter: view-only + like (never edit/delete/publish someone else's CV)
        if (in_array('ROLE_RECRUITER', $roles, true)) {
            return match ($attribute) {
                self::VIEW, self::LIKE => true,
                default => false,
            };
        }

        // Candidate: full control, but only over their own CV; never likes
        $isOwner = $cv->getUser()?->getId() === $currentUser->getId();

        return match ($attribute) {
            self::VIEW, self::EDIT, self::DELETE, self::PUBLISH => $isOwner,
            self::LIKE => false,
            default => false,
        };
    }
}
