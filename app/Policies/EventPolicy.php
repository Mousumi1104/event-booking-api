<?php

namespace App\Policies;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EventPolicy
{
    public function view(User $user, Event $event): Response
    {
        if ($event->status === EventStatus::Published || $event->isOwnedBy($user)) {
            return Response::allow();
        }

        // a 404 instead of 403, so other users can't find out a draft exists
        return Response::denyAsNotFound();
    }

    public function update(User $user, Event $event): bool
    {
        return $event->isOwnedBy($user);
    }

    public function delete(User $user, Event $event): bool
    {
        return $event->isOwnedBy($user);
    }
}
