<?php

namespace Database\Seeders\Territory;

/**
 * Transcripción literal de nombres y asignaciones de «regiones divididas.docx».
 * La capitalización original se conserva (encabezados de región/provincia en mayúsculas).
 * No contiene códigos oficiales ni fechas de vigencia: el documento no los proporciona.
 * En la fuente Copacabana aparece bajo LOS ANDES, y así se conserva aquí.
 */
final class SimredTerritoryCatalog
{
    /** @return array<string, array<string, list<string>>> */
    public static function regions(): array
    {
        return [
            'REGIÓN METROPOLITANA' => [
                'MURILLO' => [
                    'Nuestra Señora de La Paz',
                    'El Alto',
                    'Achocalla',
                    'Mecapaca',
                    'Palca',
                ],
                'LOS ANDES' => [
                    'Laja',
                    'Pucarani',
                ],
                'INGAVI' => [
                    'Viacha',
                ],
            ],
            'REGIÓN VALLES INTERANDINOS SUR' => [
                'LOAYZA' => [
                    'Malla',
                    'Sapahaqui',
                    'Cairoma',
                    'Yaco',
                    'Luribay',
                ],
                'INQUISIVI' => [
                    'Cajuata',
                    'Ichoca',
                    'Quime',
                    'Colquiri',
                    'Inquisivi',
                    'Villa Libertad Licoma',
                ],
            ],
            'REGIÓN VALLES INTERANDINOS NORTE' => [
                'BAUTISTA SAAVEDRA' => [
                    'Charazani',
                    'Curva',
                ],
                'MUÑECAS' => [
                    'Aucapata',
                    'Ayata',
                    'Chuma',
                ],
                'LARECAJA' => [
                    'Combaya',
                    'Quiabaya',
                    'Sorata',
                    'Tacacoma',
                ],
                'CAMACHO' => [
                    'Mocomoco',
                ],
                'FRANZ TAMAYO' => [
                    'Pelechuco',
                ],
            ],
            'REGIÓN YUNGAS' => [
                'SUD YUNGAS' => [
                    'Chulumani',
                    'Irupana',
                    'Yanacachi',
                    'Palos Blancos',
                    'La Asunta',
                ],
                'NOR YUNGAS' => [
                    'Coroico',
                    'Coripata',
                ],
            ],
            'REGIÓN ALTIPLANO SUR' => [
                'INGAVI' => [
                    'San Andrés de Machaca',
                    'Jesús de Machaca',
                ],
                'GUALBERTO VILLARROEL' => [
                    'San Pedro de Curahuara',
                    'Chacarilla',
                    'Papel Pampa',
                ],
                'PACAJES' => [
                    'Corocoro',
                    'Caquiaviri',
                    'Calacoto',
                    'Comanche',
                    'Charaña',
                    'Waldo Ballivián',
                    'Nazacara de Pacajes',
                    'Santiago de Callapa',
                ],
                'JOSÉ MANUEL PANDO' => [
                    'Santiago de Machaca',
                    'Catacora',
                ],
                'AROMA' => [
                    'Sica Sica',
                    'Umala',
                    'Ayo Ayo',
                    'Calamarca',
                    'Patacamaya',
                    'Colquencha',
                    'Collana',
                ],
            ],
            'REGIÓN ALTIPLANO NORTE' => [
                'OMASUYOS' => [
                    'Achacachi',
                    'Ancoraimes',
                    'Chua Cocani',
                    'Huarina',
                    'Huatajata',
                    'Santiago de Huata',
                ],
                'CAMACHO' => [
                    'Escoma',
                    'Humanata',
                    'Puerto Acosta',
                    'Puerto Carabuco',
                ],
                'INGAVI' => [
                    'Desaguadero',
                    'Puerto Guaqui',
                    'Taraco',
                    'Tiahuanacu',
                ],
                'LOS ANDES' => [
                    'Batallas',
                    'Puerto Peréz',
                    'Copacabana',
                ],
                'MANCO KAPAC' => [
                    'San Pedro de Tiquina',
                    'Tito Yupanqui',
                ],
            ],
            'REGIÓN AMAZÓNICA' => [
                'ABEL ITURRALDE' => [
                    'Ixiamas',
                    'San Buenaventura',
                ],
                'CARANAVI' => [
                    'Alto Beni',
                    'Caranavi',
                ],
                'FRANZ TAMAYO' => [
                    'Apolo',
                ],
                'LARECAJA' => [
                    'Guanay',
                    'Mapiri',
                    'Teoponte',
                    'Tipuani',
                ],
            ],
        ];
    }

    /** @return list<string> */
    public static function provinces(): array
    {
        $names = [];

        foreach (self::regions() as $provinces) {
            foreach (array_keys($provinces) as $province) {
                $names[$province] = true;
            }
        }

        return array_keys($names);
    }
}
