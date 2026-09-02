<?php

namespace App\Enums\Auth;

enum PermissionName: string
{
    // Personal
    case STAFF_VIEW = 'staff.view';
    case STAFF_CREATE = 'staff.create';
    case STAFF_UPDATE = 'staff.update';
    case STAFF_STATUS_UPDATE = 'staff.status.update';
    case STAFF_DELETE = 'staff.delete';

    // Cargos
    case POSITIONS_VIEW = 'positions.view';
    case POSITIONS_CREATE = 'positions.create';
    case POSITIONS_UPDATE = 'positions.update';
    case POSITIONS_DELETE = 'positions.delete';

    // Profesiones
    case PROFESSIONS_VIEW = 'professions.view';
    case PROFESSIONS_CREATE = 'professions.create';
    case PROFESSIONS_UPDATE = 'professions.update';
    case PROFESSIONS_DELETE = 'professions.delete';

    // Usuarios
    case USERS_VIEW = 'users.view';
    case USERS_CREATE = 'users.create';
    case USERS_UPDATE = 'users.update';
    case USERS_STATUS_UPDATE = 'users.status.update';
    case USERS_DELETE = 'users.delete';
    case USERS_TRASH_VIEW = 'users.trash.view';
    case USERS_RESTORE = 'users.restore';

    // Roles
    case ROLES_VIEW = 'roles.view';
    case ROLES_CREATE = 'roles.create';
    case ROLES_UPDATE = 'roles.update';
    case ROLES_DELETE = 'roles.delete';
    case ROLES_ASSIGN = 'roles.assign';

    // Permisos
    case PERMISSIONS_VIEW = 'permissions.view';

    // Regiones
    case REGIONS_VIEW = 'regions.view';
    case REGIONS_CREATE = 'regions.create';
    case REGIONS_UPDATE = 'regions.update';
    case REGIONS_DELETE = 'regions.delete';

    // Provincias
    case PROVINCES_VIEW = 'provinces.view';
    case PROVINCES_CREATE = 'provinces.create';
    case PROVINCES_UPDATE = 'provinces.update';
    case PROVINCES_DELETE = 'provinces.delete';

    // Municipios
    case MUNICIPALITIES_VIEW = 'municipalities.view';
    case MUNICIPALITIES_CREATE = 'municipalities.create';
    case MUNICIPALITIES_UPDATE = 'municipalities.update';
    case MUNICIPALITIES_DELETE = 'municipalities.delete';

    // Asignaciones regionales
    case REGION_ASSIGNMENTS_VIEW = 'region_assignments.view';
    case REGION_ASSIGNMENTS_CREATE = 'region_assignments.create';
    case REGION_ASSIGNMENTS_UPDATE = 'region_assignments.update';

    // Asistencias técnicas
    case TECHNICAL_ASSISTANCES_VIEW = 'technical_assistances.view';
    case TECHNICAL_ASSISTANCES_CREATE = 'technical_assistances.create';
    case TECHNICAL_ASSISTANCES_UPDATE = 'technical_assistances.update';
    case TECHNICAL_ASSISTANCES_DELETE = 'technical_assistances.delete';

    // Unidades organizacionales
    case ORGANIZATIONAL_UNITS_VIEW = 'organizational_units.view';
    case ORGANIZATIONAL_UNITS_CREATE = 'organizational_units.create';
    case ORGANIZATIONAL_UNITS_UPDATE = 'organizational_units.update';
    case ORGANIZATIONAL_UNITS_DELETE = 'organizational_units.delete';

    // Noticias
    case NEWS_VIEW = 'news.view';
    case NEWS_CREATE = 'news.create';
    case NEWS_UPDATE = 'news.update';
    case NEWS_DELETE = 'news.delete';
    case NEWS_PUBLISH = 'news.publish';

    // Papelera de noticias
    case NEWS_TRASH_VIEW = 'news.trash.view';
    case NEWS_RESTORE = 'news.restore';
    case NEWS_FORCE_DELETE = 'news.force_delete';
}
