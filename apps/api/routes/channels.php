<?php

use App\Modules\Servers\Models\Server;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('organizations.{organizationId}', function ($user, $organizationId) {
    return $user->organizations()
        ->whereKey($organizationId)
        ->exists();
});

Broadcast::channel('server.{serverId}.provisioning', function ($user, $serverId) {
    $server = Server::query()
        ->withoutGlobalScope('owned_by_organization')
        ->whereKey($serverId)
        ->first();

    if ($server === null) {
        return false;
    }

    return $user->organizations()
        ->whereKey($server->organization_id)
        ->exists();
});

Broadcast::channel('deployment.{deploymentId}', function ($user, $deploymentId) {
    $deployment = \App\Modules\Deployments\Models\Deployment::query()
        ->withoutGlobalScope('owned_by_organization')
        ->whereKey($deploymentId)
        ->first();

    if ($deployment === null) {
        return false;
    }

    return $user->organizations()
        ->whereKey($deployment->organization_id)
        ->exists();
});

Broadcast::channel('server.{serverId}.sites', function ($user, $serverId) {
    $server = Server::query()
        ->withoutGlobalScope('owned_by_organization')
        ->whereKey($serverId)
        ->first();

    if ($server === null) {
        return false;
    }

    return $user->organizations()
        ->whereKey($server->organization_id)
        ->exists();
});

Broadcast::channel('server.{serverId}.daemons', function ($user, $serverId) {
    $server = Server::query()
        ->withoutGlobalScope('owned_by_organization')
        ->whereKey($serverId)
        ->first();

    if ($server === null) {
        return false;
    }

    return $user->organizations()
        ->whereKey($server->organization_id)
        ->exists();
});

Broadcast::channel('server.{serverId}.logs', function ($user, $serverId) {
    $server = Server::query()
        ->withoutGlobalScope('owned_by_organization')
        ->whereKey($serverId)
        ->first();

    if ($server === null) {
        return false;
    }

    return $user->organizations()
        ->whereKey($server->organization_id)
        ->exists();
});
