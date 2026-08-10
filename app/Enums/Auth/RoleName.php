<?php

namespace App\Enums\Auth;

enum RoleName: string
{
    case SUPER_ADMIN = 'super_admin';
    case DIRECTOR = 'director';
    case RESPONSABLE_PROGRAMAS = 'responsable_programas';
    case TECNICO = 'tecnico';
}
