<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\Department;
use App\Models\RoomTemplateItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Lengkapi isian aset kamar yang masih kosong dari template kamar & kategori (per jenis aset).
 * Hanya mengisi kolom KOSONG: departemen, unit penanggung jawab, spesifikasi standar, kritikalitas, interval PM.
 * Nilai yang sudah diisi orang tidak pernah ditimpa. Jumlah aset tidak berubah.
 */
class CompleteRoomAssets extends Command
{
    protected $signature = 'assetcare:complete-room-assets {--dry-run : Tampilkan ringkasan tanpa menyimpan}';

    protected $description = 'Lengkapi isian kosong aset kamar (departemen, spesifikasi, kritikalitas, interval PM) dari template & kategori';

    public function handle(): int
    {
        $items = RoomTemplateItem::query()->get()->keyBy(fn ($i) => $i->template_id.'|'.$i->item_key);
        // departemen paling umum per kategori di template: dipakai untuk aset tambahan yang tidak punya baris template
        $deptByCategory = RoomTemplateItem::query()->whereNotNull('default_department_id')
            ->select('category_id', 'default_department_id', DB::raw('count(*) n'))->groupBy('category_id', 'default_department_id')
            ->orderByDesc('n')->get()->unique('category_id')->pluck('default_department_id', 'category_id');
        $deptNames = Department::withoutGlobalScopes()->pluck('name', 'id');

        $assets = Asset::withoutGlobalScopes()->with(['category', 'slotAssignment.slot'])
            ->whereHas('location', fn ($q) => $q->withoutGlobalScopes()->where('location_type', 'room'))->get();

        $stats = ['department_id' => 0, 'custodian_unit' => 0, 'specification' => 0, 'criticality' => 0, 'pm_interval_days' => 0];
        $touched = 0;
        foreach ($assets as $a) {
            $slot = $a->slotAssignment?->slot;
            $it = $slot ? $items->get($slot->template_id.'|'.$slot->item_key) : null;
            $fill = [];
            if (blank($a->department_id) && ($d = $it?->default_department_id ?? $deptByCategory[$a->category_id] ?? null)) {
                $fill['department_id'] = $d;
            }
            $dept = $fill['department_id'] ?? $a->department_id;
            if (blank($a->custodian_id) && blank($a->custodian_unit) && $dept && isset($deptNames[$dept])) {
                $fill['custodian_unit'] = $deptNames[$dept];
            }
            if (blank($a->specification) && filled($it?->specification)) {
                $fill['specification'] = $it->specification;
            }
            if (blank($a->criticality) && ($c = $it?->default_criticality ?? $a->category?->default_criticality)) {
                $fill['criticality'] = $c;
            }
            if (blank($a->pm_interval_days) && ($pm = $it?->pm_interval_days ?? $a->category?->default_pm_interval_days)) {
                $fill['pm_interval_days'] = (int) $pm;
            }
            if (! $fill) {
                continue;
            }
            $touched++;
            foreach (array_keys($fill) as $k) {
                $stats[$k]++;
            }
            if (! $this->option('dry-run')) {
                $a->forceFill($fill)->save();
            }
        }

        $labels = ['department_id' => 'Departemen', 'custodian_unit' => 'Unit penanggung jawab', 'specification' => 'Spesifikasi standar', 'criticality' => 'Kritikalitas', 'pm_interval_days' => 'Interval PM'];
        $this->table(['Isian', 'Aset diisi'], collect($stats)->map(fn ($n, $k) => [$labels[$k], $n])->values()->all());
        $this->info(($this->option('dry-run') ? '[uji] ' : '').$touched.' dari '.$assets->count().' aset kamar dilengkapi.');

        return self::SUCCESS;
    }
}
