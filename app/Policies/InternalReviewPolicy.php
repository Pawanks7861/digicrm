<?php

namespace App\Policies;

use App\Models\InternalReview;
use App\Models\User;

class InternalReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('internal_reviews.view') || $user->can('internal_reviews.approve');
    }

    public function submit(User $user): bool
    {
        return $user->can('internal_reviews.submit');
    }

    public function approve(User $user, InternalReview $review): bool
    {
        return $user->can('internal_reviews.approve') || $user->can('internal_reviews.request_changes');
    }
}
