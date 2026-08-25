<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Media disk
    |--------------------------------------------------------------------------
    |
    | Actualmente almacenamos las imágenes físicamente
    | en storage/app/public.
    |
    | En el futuro podría cambiarse a S3, R2, etc.
    | sin modificar ImageService.
    |
    */

    'disk' => env('MEDIA_DISK', 'public'),

];
