<?php

namespace App\Enums\Media;

enum ImageResizeMode: string
{
    /*
     * Mantiene la imagen exactamente
     * en sus dimensiones originales.
     */
    case NONE = 'none';

    /*
     * Reduce manteniendo proporción.
     *
     * Ideal:
     * noticias
     * galerías
     * fotografías
     */
    case SCALE_DOWN = 'scale_down';

    /*
     * Recorta manteniendo un tamaño/proporción.
     *
     * Ideal:
     * heroes
     * banners
     * portadas
     */
    case COVER_DOWN = 'cover_down';
}
