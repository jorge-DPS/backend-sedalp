<?php

namespace Database\Seeders;

use App\Models\Content\SitePage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SiteContentSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach ($this->pages() as $key => $definition) {
                $page = SitePage::firstOrCreate(['key' => $key], ['name' => $definition['name']]);
                foreach ($definition['sections'] as $position => $data) {
                    $page->sections()->firstOrCreate(['key' => $data['key']], [...$data, 'position' => $position]);
                }
            }
            $pillars = SitePage::where('key', 'inicio')->firstOrFail()->sections()->where('key', 'pilares')->firstOrFail();
            foreach ($this->pillars() as $position => $data) {
                $pillars->items()->firstOrCreate(['key' => $data['key']], [...$data, 'position' => $position]);
            }
        });
    }

    /** @return array<string, array{name: string, sections: list<array<string, mixed>>}> */
    private function pages(): array
    {
        return [
            'inicio' => ['name' => 'Inicio', 'sections' => [
                ['key' => 'hero', 'type' => 'hero', 'eyebrow' => 'Servicio Departamental de Autonomías',
                    'title' => 'Construimos autonomía,',
                    'description' => 'Fortalecemos la gestión autonómica y acompañamos a los gobiernos autónomos del departamento de La Paz mediante asistencia técnica, información y herramientas para su desarrollo.',
                    'image_alt' => 'Vista de la ciudad de La Paz, el Illimani y el teleférico junto a ciudadanos paceños',
                    'options' => ['highlighted_text' => 'promovemos desarrollo', 'button_label' => 'Conócenos más', 'button_to' => '#institucional']],
                ['key' => 'pilares', 'type' => 'pillars', 'eyebrow' => 'Pilares Institucionales',
                    'title' => 'Bases de nuestra gestión departamental',
                    'description' => 'Acompañamos a los gobiernos autónomos municipales e indígena originario campesinos mediante asistencia técnica, planificación y herramientas estratégicas.'],
                ['key' => 'asistencia-tecnica', 'type' => 'content', 'eyebrow' => 'Acompañamiento Territorial', 'title' => 'Asistencia Técnica',
                    'description' => 'Brindamos acompañamiento técnico especializado a gobiernos municipales y regionales en planificación, gestión financiera, institucional y ejecución de proyectos para una administración autónoma eficiente y transparente.',
                    'options' => ['button_label' => 'Ver todos los servicios', 'button_to' => '/asistencia-tecnica']],
                ['key' => 'normativa', 'type' => 'content', 'eyebrow' => 'Marco Jurídico', 'title' => 'Normativa',
                    'description' => 'Accede a la normativa vigente que regula la autonomía municipal, departamental e indígena originaria campesina en el marco legal del Estado Plurinacional de Bolivia.'],
                ['key' => 'comunicacion', 'type' => 'content', 'eyebrow' => 'Actualidad Departamental', 'title' => 'Comunicación',
                    'description' => 'Noticias, actividades y avances institucionales que conectan a SEDALP con los municipios y regiones del departamento de La Paz.'],
                ['key' => 'simred', 'type' => 'content', 'eyebrow' => 'Geoinformación y Territorio', 'title' => 'SIMRED — Sistema de Información Municipal y Regional',
                    'description' => 'Plataforma digital interactiva que integra y visualiza datos territoriales, socioeconómicos y de gestión de los 87 municipios y 20 provincias de La Paz para una planificación técnica informada.',
                    'options' => ['button_label' => 'Ingresar a SIMRED', 'button_to' => '/simred']],
            ]],
            'asistencia-tecnica' => ['name' => 'Asistencia Técnica', 'sections' => [
                ['key' => 'hero', 'type' => 'hero', 'eyebrow' => 'SEDALP', 'title' => 'Asistencia Técnica',
                    'description' => 'Información y materiales orientados al fortalecimiento de capacidades y al acompañamiento técnico de los gobiernos autónomos del departamento de La Paz.'],
                ['key' => 'materiales', 'type' => 'content', 'eyebrow' => 'Recursos institucionales', 'title' => 'Material de Asistencia Técnica',
                    'description' => 'Explora los materiales desarrollados como parte de las acciones de asistencia y fortalecimiento institucional.'],
            ]],
            'comunicacion' => ['name' => 'Comunicación', 'sections' => [
                ['key' => 'hero', 'type' => 'hero', 'eyebrow' => 'SEDALP', 'title' => 'Comunicación',
                    'description' => 'Conoce las noticias, actividades, asistencia técnica y principales acciones institucionales desarrolladas por el Servicio Departamental de Autonomías de La Paz.'],
                ['key' => 'noticias', 'type' => 'content', 'eyebrow' => 'Comunicación institucional', 'title' => 'Últimas noticias'],
            ]],
            'simred' => ['name' => 'SIMRED', 'sections' => [
                ['key' => 'hero', 'type' => 'hero', 'eyebrow' => 'Información territorial', 'title' => 'SIMRED',
                    'subtitle' => 'Sistema de Información Municipal y Regional',
                    'description' => 'Consulta información territorial de las regiones y municipios del departamento de La Paz mediante el visor cartográfico institucional.'],
                ['key' => 'visor', 'type' => 'content', 'eyebrow' => 'Información geográfica', 'title' => 'Visor territorial',
                    'description' => 'Explora el territorio del departamento de La Paz y consulta información relacionada con sus regiones y municipios.'],
            ]],
        ];
    }

    /** @return list<array{key: string, title: string, description: string, icon_key: string}> */
    private function pillars(): array
    {
        return [
            ['key' => 'mision', 'title' => 'Misión', 'description' => 'Fortalecer la gestión autonómica de los gobiernos autónomos del departamento de La Paz mediante asistencia técnica, acompañamiento institucional y generación de herramientas que contribuyan al ejercicio efectivo de sus competencias.', 'icon_key' => 'mission'],
            ['key' => 'vision', 'title' => 'Visión', 'description' => 'Consolidarnos como una institución referente en el fortalecimiento de las autonomías, promoviendo una gestión pública articulada, eficiente y orientada al desarrollo integral del departamento de La Paz.', 'icon_key' => 'vision'],
            ['key' => 'objetivo-general', 'title' => 'Objetivo General', 'description' => 'Impulsar el fortalecimiento institucional y el desarrollo autonómico mediante procesos de asistencia técnica, coordinación y generación de información para los gobiernos autónomos.', 'icon_key' => 'objective'],
        ];
    }
}
