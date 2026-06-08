<?php

use Illuminate\Support\Facades\Broadcast;

// Canal privado por usuario — solo el propio usuario puede suscribirse
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Canal privado por empresa — cualquier usuario activo de la empresa
Broadcast::channel('company.{companyId}', function ($user, $companyId) {
    return $user->companies()
        ->wherePivot('company_id', (int) $companyId)
        ->wherePivot('status', 'active')
        ->exists();
});

// Canal de notificaciones de Laravel por defecto
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
