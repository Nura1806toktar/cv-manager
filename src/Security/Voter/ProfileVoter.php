<?php

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ProfileVoter extends Voter
{
    public const VIEW = 'PROFILE_VIEW';
    public const EDIT = 'PROFILE_EDIT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT], true) && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $currentUser = $token->getUser();
        if (!$currentUser instanceof User) {
            return false;
        }

        /** @var User $targetProfile */
        $targetProfile = $subject;

        // Admin can view/edit any profile
        if (in_array('ROLE_ADMIN', $currentUser->getRoles(), true)) {
            return true;
        }

        // Recruiter can only view (read-only), never edit
        if (in_array('ROLE_RECRUITER', $currentUser->getRoles(), true)) {
            return self::VIEW === $attribute;
        }

        // Candidate can view/edit only their own profile
        return $currentUser->getId() === $targetProfile->getId();
    }
}
