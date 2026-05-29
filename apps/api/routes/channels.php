<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('organizations.{organizationId}', function ($user, $organizationId) {
    return $user->organizations()
        ->whereKey($organizationId)
        ->exists();
});
