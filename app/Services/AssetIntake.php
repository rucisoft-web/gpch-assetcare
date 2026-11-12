<?php

namespace App\Services;

use App\Enums\IdentificationSource;
use App\Enums\VerificationStatus;
use App\Models\Asset;
use App\Models\AssetTemplate;
use App\Models\Location;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Identifikasi aset awal: bangkitkan dari templat, salin struktur lokasi, duplikasi aset.
 * Aset hasilnya berstatus "Diharapkan" sampai diverifikasi tim lapangan.
 */
class AssetIntake
{
    /** @param array<int> $locationIds @return Collection<int, Asset> */
    public function fromTemplate(AssetTemplate $template, array $locationIds, ?int $surveyId = null, bool $skipExisting = true): Collection
    {
        $created = collect();
        $lines = $template->lines()->get();

        DB::transaction(function () use ($lines, $locationIds, $surveyId, $skipExisting, $created) {
            foreach (Location::whereIn('id', $locationIds)->get() as $location) {
                foreach ($lines as $line) {
                    $qty = max($line->quantity, 1);

                    for ($i = 1; $i <= $qty; $i++) {
                        $name = $qty === 1 ? $line->name : "{$line->name} #{$i}";

                        if ($skipExisting && $this->exists($location->id, $line->asset_category_id, $name)) {
                            continue;
                        }

                        $created->push(Asset::create([
                            'name' => $name,
                            'asset_category_id' => $line->asset_category_id,
                            'location_id' => $location->id,
                            'department_id' => $line->department_id,
                            'brand' => $line->brand,
                            'model' => $line->model,
                            'expected_life_years' => $line->expected_life_years,
                            'verification_status' => VerificationStatus::Expected,
                            'identification_source' => IdentificationSource::Template,
                            'asset_survey_id' => $surveyId,
                        ]));
                    }
                }
            }
        });

        return $created;
    }

    /**
     * Bangkitkan aset untuk setiap lokasi sesuai jenis ruangnya (templat yang ditautkan ke lokasi).
     * Lokasi tanpa templat dilewati.
     *
     * @param  array<int>  $locationIds
     * @return Collection<int, Asset>
     */
    public function fromLinkedTemplates(array $locationIds, ?int $surveyId = null, bool $skipExisting = true): Collection
    {
        $created = collect();

        Location::whereIn('id', $locationIds)->whereNotNull('asset_template_id')->with('template')->get()
            ->groupBy('asset_template_id')
            ->each(function ($locations) use ($surveyId, $skipExisting, $created) {
                $template = $locations->first()->template;

                if ($template) {
                    $created->push(...$this->fromTemplate($template, $locations->pluck('id')->all(), $surveyId, $skipExisting));
                }
            });

        return $created;
    }

    /** Salin templat beserta seluruh barisnya, misalnya untuk membuat varian jenis kamar baru. */
    public function duplicateTemplate(AssetTemplate $template, string $name): AssetTemplate
    {
        return DB::transaction(function () use ($template, $name) {
            // Ambil kolom tabel saja; record dari tabel Filament bisa membawa atribut agregat (lines_count, dsb.).
            $copy = AssetTemplate::create(['name' => $name] + $template->only(['company_id', 'location_type', 'is_active', 'note']));

            foreach ($template->lines()->get() as $line) {
                $copy->lines()->create($line->replicate(['asset_template_id'])->getAttributes());
            }

            return $copy;
        });
    }

    /** @param array<int> $targetIds @return Collection<int, Asset> */
    public function copyLocation(int $sourceId, array $targetIds, ?int $surveyId = null, bool $skipExisting = true): Collection
    {
        $sources = Asset::where('location_id', $sourceId)->where('is_active', true)->get();

        if ($sources->isEmpty()) {
            throw new InvalidArgumentException('Lokasi sumber tidak memiliki aset aktif.');
        }

        $created = collect();

        DB::transaction(function () use ($sources, $sourceId, $targetIds, $surveyId, $skipExisting, $created) {
            foreach (array_diff($targetIds, [$sourceId]) as $targetId) {
                foreach ($sources as $source) {
                    if ($skipExisting && $this->exists($targetId, $source->asset_category_id, $source->name)) {
                        continue;
                    }

                    $created->push($this->cloneTo($source, (int) $targetId, IdentificationSource::Copied, $surveyId));
                }
            }
        });

        return $created;
    }

    /** @param array<int> $targetIds @return Collection<int, Asset> */
    public function duplicate(Asset $asset, array $targetIds, ?int $surveyId = null): Collection
    {
        return DB::transaction(fn () => collect($targetIds)->map(fn ($id) => $this->cloneTo($asset, (int) $id, IdentificationSource::Duplicated, $surveyId)));
    }

    protected function cloneTo(Asset $source, int $locationId, IdentificationSource $origin, ?int $surveyId): Asset
    {
        return Asset::create([
            'name' => $source->name,
            'asset_category_id' => $source->asset_category_id,
            'location_id' => $locationId,
            'department_id' => $source->department_id,
            'brand' => $source->brand,
            'model' => $source->model,
            'expected_life_years' => $source->expected_life_years,
            'verification_status' => VerificationStatus::Expected,
            'identification_source' => $origin,
            'asset_survey_id' => $surveyId,
        ]);
    }

    protected function exists(int $locationId, int $categoryId, string $name): bool
    {
        return Asset::where('location_id', $locationId)
            ->where('asset_category_id', $categoryId)
            ->where('name', $name)
            ->where('is_active', true)
            ->exists();
    }
}
