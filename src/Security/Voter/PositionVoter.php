<?php

namespace App\Security\Voter;

use App\Entity\Position;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class PositionVoter extends Voter
{
    public const VIEW = 'POSITION_VIEW';
    public const EDIT = 'POSITION_EDIT';
    public const DELETE = 'POSITION_DELETE';
    public const MANAGE = 'POSITION_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::MANAGE], true)
            && $subject instanceof Position;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $currentUser = $token->getUser();
        if (!$currentUser instanceof User) {
            // Anonymous users may only view public positions
            /** @var Position $position */
            $position = $subject;
            return self::VIEW === $attribute && $position->isPublic();
        }

        $roles = $currentUser->getRoles();

        // Admin has full control
        if (in_array('ROLE_ADMIN', $roles, true)) {
            return true;
        }

        // Any Recruiter can create/edit/delete/manage any position — no ownership concept
        if (in_array('ROLE_RECRUITER', $roles, true)) {
            return true;
        }

        // Candidate: view-only, and only when public (restricted access is filtered separately by access-rule matching)
        /** @var Position $position */
        $position = $subject;

        return match ($attribute) {
            self::VIEW => $position->isPublic(),
            default => false,
        };
    }
}
