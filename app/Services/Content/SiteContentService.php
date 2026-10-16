<?php

namespace App\Services\Content;

use App\Models\Content\SiteMedia;
use App\Models\Content\SiteSection;
use App\Models\Content\SiteSectionItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SiteContentService
{
    /** @param array<string, mixed> $data */
    public function updateSection(SiteSection $section, array $data, int $userId): SiteSection
    {
        return DB::transaction(function () use ($section, $data, $userId): SiteSection {
            $section = SiteSection::query()->lockForUpdate()->findOrFail($section->id);
            $mediaIds = array_filter([$data['desktop_media_id'] ?? null, $data['mobile_media_id'] ?? null]);
            $media = SiteMedia::query()->whereKey($mediaIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach (['desktop', 'mobile'] as $profile) {
                $field = $profile.'_media_id';
                if (! empty($data[$field]) && $media->get($data[$field])?->profile !== $profile) {
                    throw ValidationException::withMessages([$field => 'La imagen ya no está disponible o no corresponde al formato.']);
                }
            }
            if (array_key_exists('options', $data)) {
                $data['options'] = array_replace($section->options, $data['options']);
            }
            $section->fill($data);
            $section->updated_by = $userId;
            $section->save();

            return $section->load(['items', 'desktopMedia', 'mobileMedia']);
        });
    }

    /** @param array<string, mixed> $data */
    public function saveItem(SiteSection $section, ?SiteSectionItem $item, array $data, int $userId): SiteSectionItem
    {
        return DB::transaction(function () use ($section, $item, $data, $userId): SiteSectionItem {
            $section = SiteSection::query()->lockForUpdate()->findOrFail($section->id);
            abort_unless($section->type === 'pillars', 422, 'Este bloque no admite elementos institucionales.');
            if ($item !== null) {
                $item = $section->items()->findOrFail($item->id);
            }
            if (isset($data['key']) && $section->items()->where('key', $data['key'])->when($item, fn (Builder $query): Builder => $query->whereKeyNot($item->id))->exists()) {
                throw ValidationException::withMessages(['key' => 'Ya existe un elemento con esta clave en el bloque.']);
            }
            $item ??= $section->items()->make();
            $item->fill($data);
            $item->updated_by = $userId;
            $item->save();
            $section->updated_by = $userId;
            $section->save();

            return $item;
        });
    }

    public function deleteItem(SiteSection $section, SiteSectionItem $item, int $userId): void
    {
        DB::transaction(function () use ($section, $item, $userId): void {
            $section = SiteSection::query()->lockForUpdate()->findOrFail($section->id);
            abort_unless($section->type === 'pillars', 422, 'Este bloque no admite elementos institucionales.');
            $section->items()->findOrFail($item->id)->delete();
            $section->updated_by = $userId;
            $section->save();
        });
    }
}
