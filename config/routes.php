<?php

declare(strict_types=1);

use Cva\Controllers\Admin\AuthController;
use Cva\Controllers\Admin\AcademyContentController;
use Cva\Controllers\Admin\CoachesController;
use Cva\Controllers\Admin\AuditLogController;
use Cva\Controllers\Admin\DashboardController;
use Cva\Controllers\Admin\EventsController;
use Cva\Controllers\Admin\GalleryController;
use Cva\Controllers\Admin\MembersController;
use Cva\Controllers\Admin\ProgramsController;
use Cva\Controllers\Admin\RegistrationsController;
use Cva\Controllers\Admin\StaffAccountsController;
use Cva\Controllers\Public\GalleryMediaController;
use Cva\Controllers\Public\HomeController;
use Cva\Controllers\Public\RegistrationController;

// Each route declares an HTTP method, a path, and whether admin access is required.
return [
    'GET /' => [
        'controller' => HomeController::class,
        'action' => 'index',
        'admin' => false,
    ],
    'GET /registration' => [
        'controller' => RegistrationController::class,
        'action' => 'showForm',
        'admin' => false,
    ],
    'POST /registration' => [
        'controller' => RegistrationController::class,
        'action' => 'submit',
        'admin' => false,
    ],
    'GET /registration/received' => [
        'controller' => RegistrationController::class,
        'action' => 'received',
        'admin' => false,
    ],
    'GET /gallery/media' => [
        'controller' => GalleryMediaController::class,
        'action' => 'show',
        'admin' => false,
    ],
    'GET /admin/login' => [
        'controller' => AuthController::class,
        'action' => 'showLogin',
        'admin' => false,
    ],
    'POST /admin/login' => [
        'controller' => AuthController::class,
        'action' => 'login',
        'admin' => false,
    ],
    'GET /admin' => [
        'controller' => DashboardController::class,
        'action' => 'index',
        'admin' => true,
    ],
    'GET /admin/users' => [
        'controller' => StaffAccountsController::class,
        'action' => 'index',
        'admin' => true,
        'access' => 'administrator',
    ],
    'POST /admin/users/save' => [
        'controller' => StaffAccountsController::class,
        'action' => 'save',
        'admin' => true,
        'access' => 'administrator',
    ],
    'GET /admin/academy' => [
        'controller' => AcademyContentController::class,
        'action' => 'index',
        'admin' => true,
    ],
    'POST /admin/academy' => [
        'controller' => AcademyContentController::class,
        'action' => 'save',
        'admin' => true,
    ],
    'GET /admin/registrations' => [
        'controller' => RegistrationsController::class,
        'action' => 'index',
        'admin' => true,
    ],
    'GET /admin/registrations/view' => [
        'controller' => RegistrationsController::class,
        'action' => 'show',
        'admin' => true,
    ],
    'POST /admin/registrations/review' => [
        'controller' => RegistrationsController::class,
        'action' => 'prepareReview',
        'admin' => true,
    ],
    'POST /admin/registrations/review/confirm' => [
        'controller' => RegistrationsController::class,
        'action' => 'confirmReview',
        'admin' => true,
    ],
    'POST /admin/registrations/review/cancel' => [
        'controller' => RegistrationsController::class,
        'action' => 'cancelReview',
        'admin' => true,
    ],
    'GET /admin/players' => [
        'controller' => MembersController::class,
        'action' => 'index',
        'admin' => true,
    ],
    'GET /admin/players/edit' => [
        'controller' => MembersController::class,
        'action' => 'edit',
        'admin' => true,
    ],
    'POST /admin/players/update' => [
        'controller' => MembersController::class,
        'action' => 'update',
        'admin' => true,
    ],
    'GET /admin/gallery' => [
        'controller' => GalleryController::class,
        'action' => 'index',
        'admin' => true,
    ],
    'GET /admin/programs' => [
        'controller' => ProgramsController::class,
        'action' => 'index',
        'admin' => true,
    ],
    'GET /admin/programs/edit' => [
        'controller' => ProgramsController::class,
        'action' => 'edit',
        'admin' => true,
    ],
    'POST /admin/programs/save' => [
        'controller' => ProgramsController::class,
        'action' => 'save',
        'admin' => true,
    ],
    'GET /admin/events' => [
        'controller' => EventsController::class,
        'action' => 'index',
        'admin' => true,
    ],
    'GET /admin/events/edit' => [
        'controller' => EventsController::class,
        'action' => 'edit',
        'admin' => true,
    ],
    'POST /admin/events/save' => [
        'controller' => EventsController::class,
        'action' => 'save',
        'admin' => true,
    ],
    'GET /admin/coaches' => [
        'controller' => CoachesController::class,
        'action' => 'index',
        'admin' => true,
    ],
    'GET /admin/coaches/edit' => [
        'controller' => CoachesController::class,
        'action' => 'edit',
        'admin' => true,
    ],
    'POST /admin/coaches/save' => [
        'controller' => CoachesController::class,
        'action' => 'save',
        'admin' => true,
    ],
    'GET /admin/gallery/media' => [
        'controller' => GalleryMediaController::class,
        'action' => 'preview',
        'admin' => true,
    ],
    'POST /admin/gallery/upload' => [
        'controller' => GalleryController::class,
        'action' => 'upload',
        'admin' => true,
    ],
    'POST /admin/gallery/update' => [
        'controller' => GalleryController::class,
        'action' => 'update',
        'admin' => true,
    ],
    'GET /admin/audit-logs' => [
        'controller' => AuditLogController::class,
        'action' => 'index',
        'admin' => true,
        'access' => 'administrator',
    ],
    'POST /admin/logout' => [
        'controller' => AuthController::class,
        'action' => 'logout',
        'admin' => true,
    ],
];
