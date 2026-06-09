<?php

namespace App\Policies;

use App\Models\Image;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ImagePolicy
{
    use HandlesAuthorization;

    public function delete(User $user, Image $image): bool
    {
        return $user->can('products.delete') && ! $image->isArchived();
    }
}
