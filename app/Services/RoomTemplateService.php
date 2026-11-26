<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetLabel;
use App\Models\AssetQuantityGroup;
use App\Models\GenerationBatch;
use App\Models\GenerationResult;
use App\Models\Location;
use App\Models\RoomAssetSlot;
use App\Models\RoomAssetTemplate;
use App\Models\RoomException;
use App\Models\SlotAssignment;
use App\Models\User;
use App\Support\Sequence;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pembuatan aset untuk banyak kamar (bagian 24–25).
 * pilih template → pilih kamar → preview selisih → cocokkan aset lama → buat kandidat (draft) → verifikasi fisik → aktifkan.
 * Kunci slot (property_id, room_id, item_key, unit_position) + constraint unik pemetaan aktif mencegah unit ganda.
 */
class RoomTemplateService
{
    /** @return array<int,array> preview per kamar, kunci = room_id */
    public function preview(RoomAssetTemplate $template, array $roomIds, bool $allowTypeMismatch = false): array
    {
        $template->loadMissing('items.category');
        $rooms = Location::whereIn('id', $roomIds)->whereIn('location_type', ['room', 'venue'])->orderBy('sort_key')->get();
        $out = [];
        foreach ($rooms as $room) {
            $out[$room->id] = $this->previewRoom($template, $room, $allowTypeMismatch);
        }

        return $out;
    }

    public function previewRoom(RoomAssetTemplate $template, Location $room, bool $allowTypeMismatch = false): array
    {
        $typeOk = (int) $room->room_type_id === (int) $template->room_type_id;
        $slots = RoomAssetSlot::where('room_id', $room->id)->with('activeAssignment.asset', 'activeAssignment.quantityGroup')->get()
            ->keyBy(fn ($s) => $s->item_key.'#'.$s->unit_position);
        $exceptions = RoomException::where('room_id', $room->id)->get()->keyBy('item_key');
        $assignedAssetIds = SlotAssignment::whereNotNull('active_asset_key')->pluck('asset_id')->all();
        $roomAssets = Asset::where('location_id', $room->id)->whereIn('lifecycle_status', ['draft', 'active'])
            ->whereNotIn('id', $assignedAssetIds)->get();

        $rows = [];
        $items = $template->items->map(fn ($i) => [
            'item_key' => $i->item_key, 'category_id' => $i->category_id, 'category' => $i->category?->name, 'name' => $i->standard_name,
            'mode' => $i->mode, 'unit' => $i->unit, 'qty' => (int) $i->quantity, 'positions' => $i->positionLabels(),
            'department_id' => $i->default_department_id, 'criticality' => $i->default_criticality, 'specification' => $i->specification, 'pm' => $i->pm_interval_days,
        ])->keyBy('item_key')->all();
        // tambahan khusus kamar (pengecualian 'added' dengan item_key baru)
        foreach ($exceptions->where('type', 'added') as $ex) {
            if (! isset($items[$ex->item_key])) {
                $items[$ex->item_key] = ['item_key' => $ex->item_key, 'category_id' => $ex->substitute_category_id, 'category' => $ex->substituteCategory?->name, 'name' => $ex->substitute_name ?: $ex->item_key, 'mode' => 'individual', 'unit' => 'unit', 'qty' => 0, 'positions' => [], 'department_id' => null, 'criticality' => null, 'specification' => null, 'pm' => null];
            }
        }

        foreach ($items as $key => $it) {
            $ex = $exceptions->get($key);
            $qty = $it['qty'];
            $note = null;
            if ($ex) {
                $note = RoomException::TYPES[$ex->type].': '.$ex->reason;
                $qty = match ($ex->type) {
                    'not_available' => 0,
                    'quantity' => (int) $ex->quantity,
                    'added' => $qty + max(1, (int) $ex->quantity),
                    default => $qty,
                };
                if ($ex->type === 'substitute') {
                    $it['name'] = $ex->substitute_name ?: $it['name'];
                    $it['category_id'] = $ex->substitute_category_id ?: $it['category_id'];
                }
            }
            if ($it['mode'] === 'quantity') {
                $slot = $slots->get($key.'#1');
                $mapped = $slot?->activeAssignment?->quantity_group_id;
                $rows[] = $this->row($it, 1, null, $mapped ? 'mapped' : ($qty > 0 ? 'create' : 'not_available'), $slot, [], $note, $qty);

                continue;
            }
            $usedCandidates = [];
            for ($pos = 1; $pos <= max($qty, 0); $pos++) {
                $slot = $slots->get($key.'#'.$pos);
                $label = $it['positions'][$pos] ?? ($qty > 1 ? 'Unit '.$pos : null);
                if ($slot?->activeAssignment) {
                    $rows[] = $this->row($it, $pos, $label, 'mapped', $slot, [], $note);

                    continue;
                }
                // kandidat aset lama: kategori sama di kamar ini, belum terpetakan — hanya saran
                $cands = $roomAssets->where('category_id', $it['category_id'])->reject(fn ($a) => in_array($a->id, $usedCandidates, true))
                    ->map(fn ($a) => ['id' => $a->id, 'label' => $a->asset_code.' · '.$a->name.($a->serial_number ? ' (SN '.$a->serial_number.')' : '')])->values()->all();
                $rows[] = $this->row($it, $pos, $label, $cands ? 'candidate' : 'create', $slot, $cands, $note);
            }
            // slot berlebih (mis. jumlah template dikurangi): tidak dihapus, hanya dilaporkan
            foreach ($slots->filter(fn ($s) => $s->item_key === $key && $s->unit_position > max($qty, 0) && $s->activeAssignment) as $s) {
                $rows[] = $this->row($it, $s->unit_position, $s->position_label, 'excess', $s, [], 'Melebihi standar — tidak dihapus/dipindah otomatis');
            }
        }

        $count = fn ($state) => count(array_filter($rows, fn ($r) => $r['state'] === $state));
        $individualStandard = count(array_filter($rows, fn ($r) => $r['mode'] === 'individual' && $r['state'] !== 'excess'));

        return [
            'room_id' => $room->id, 'room' => $room->full_path ?: $room->name, 'room_ref' => $room->room_ref ?: $room->code,
            'type_ok' => $typeOk, 'allowed' => $typeOk || $allowTypeMismatch, 'rows' => $rows,
            'counts' => ['standard' => $individualStandard, 'mapped' => $count('mapped'), 'candidate' => $count('candidate'), 'create' => $count('create'), 'excess' => $count('excess'), 'not_available' => $count('not_available')],
            'signature' => md5(json_encode(array_map(fn ($r) => [$r['item_key'], $r['unit_position'], $r['state'], $r['mapped_id'], array_column($r['candidates'], 'id')], $rows))),
        ];
    }

    protected function row(array $it, int $pos, ?string $label, string $state, ?RoomAssetSlot $slot, array $cands, ?string $note, ?int $qty = null): array
    {
        $assign = $slot?->activeAssignment;

        return [
            'item_key' => $it['item_key'], 'unit_position' => $pos, 'position_label' => $label, 'name' => $it['name'],
            'category_id' => $it['category_id'], 'category' => $it['category'], 'mode' => $it['mode'], 'unit' => $it['unit'],
            'expected_qty' => $it['mode'] === 'quantity' ? $qty : null, 'state' => $state, 'slot_id' => $slot?->id,
            'mapped_id' => $assign?->asset_id ?? $assign?->quantity_group_id,
            'mapped_label' => $assign?->asset ? $assign->asset->asset_code.' · '.$assign->asset->name : ($assign?->quantityGroup ? $assign->quantityGroup->group_code : null),
            'candidates' => $cands, 'note' => $note,
            'department_id' => $it['department_id'], 'criticality' => $it['criticality'], 'specification' => $it['specification'], 'pm' => $it['pm'],
        ];
    }

    public function createBatch(RoomAssetTemplate $template, array $roomIds, array $preview, User $user, ?string $idempotencyKey = null): GenerationBatch
    {
        if ($template->status !== 'published') {
            throw WorkflowException::invalid('Hanya template berstatus published yang dapat diterapkan.');
        }
        $key = $idempotencyKey ?: (string) Str::uuid();
        if ($existing = GenerationBatch::where('idempotency_key', $key)->first()) {
            return $existing;
        }

        return GenerationBatch::create([
            'number' => Sequence::next('GEN', $template->property_id), 'template_id' => $template->id, 'template_version' => $template->version,
            'idempotency_key' => $key, 'room_ids' => array_values(array_map('intval', $roomIds)),
            'preview_snapshot' => array_map(fn ($p) => ['signature' => $p['signature'], 'allowed' => $p['allowed'], 'counts' => $p['counts']], $preview),
            'status' => 'previewed', 'created_by' => $user->id,
        ]);
    }

    /**
     * Commit atomik per kamar. Retry hanya memproses kamar yang belum berhasil.
     *
     * @param  array<string,int|null>  $decisions  "roomId|item_key|pos" => asset_id (pencocokan disetujui), null = buat baru
     */
    public function commit(GenerationBatch $batch, array $decisions, User $user, bool $allowTypeMismatch = false): GenerationBatch
    {
        if (! $user->hasPermission('generation.run')) {
            throw WorkflowException::forbidden('Pembuatan massal hanya untuk Asset Controller.');
        }
        if (in_array($batch->status, ['completed', 'cancelled'], true)) {
            return $batch;
        }
        $batch->update(['status' => 'committing', 'match_decisions' => $decisions ?: $batch->match_decisions]);
        $template = $batch->template()->with('items.category')->first();
        $done = $batch->results()->where('status', 'ok')->pluck('room_id')->all();

        foreach ($batch->room_ids as $roomId) {
            if (in_array($roomId, $done, true)) {
                continue;
            }
            try {
                $res = DB::transaction(fn () => $this->commitRoom($batch, $template, (int) $roomId, $decisions, $user, $allowTypeMismatch), 3);
            } catch (Throwable $e) {
                report($e);
                $res = ['status' => 'failed', 'message' => mb_substr($e->getMessage(), 0, 500)];
            }
            GenerationResult::updateOrCreate(['batch_id' => $batch->id, 'room_id' => $roomId], $res + ['created_count' => 0, 'matched_count' => 0, 'skipped_count' => 0]);
        }

        $results = $batch->results()->get();
        $status = $results->every(fn ($r) => $r->status === 'ok') ? 'completed' : ($results->contains('status', 'ok') ? 'partial' : 'failed');
        $batch->update([
            'status' => $status, 'committed_at' => now(),
            'created_assets' => $results->sum('created_count'), 'matched_assets' => $results->sum('matched_count'),
        ]);

        return $batch->refresh();
    }

    protected function commitRoom(GenerationBatch $batch, RoomAssetTemplate $template, int $roomId, array $decisions, User $user, bool $allowTypeMismatch): array
    {
        $room = Location::whereKey($roomId)->lockForUpdate()->first(); // mengunci kamar: batch bersamaan antre di sini
        if (! $room) {
            return ['status' => 'failed', 'message' => 'Kamar tidak ditemukan.'];
        }
        $fresh = $this->previewRoom($template, $room, $allowTypeMismatch);
        if (! $fresh['allowed']) {
            return ['status' => 'skipped', 'message' => 'Tipe kamar berbeda dari template; perlu pengecualian yang disetujui.'];
        }
        $snap = $batch->preview_snapshot[$roomId]['signature'] ?? $batch->preview_snapshot[(string) $roomId]['signature'] ?? null;
        if ($snap !== $fresh['signature']) {
            return ['status' => 'conflict', 'message' => 'Keadaan slot/pemetaan kamar berubah sejak preview. Buat preview baru.', 'detail' => $fresh['counts']];
        }

        $created = $matched = $skipped = 0;
        $detail = [];
        $usedMatch = [];
        foreach ($fresh['rows'] as $r) {
            if (in_array($r['state'], ['mapped', 'excess', 'not_available'], true)) {
                $skipped++;

                continue;
            }
            $slot = RoomAssetSlot::firstOrCreate(
                ['property_id' => $room->property_id, 'room_id' => $room->id, 'item_key' => $r['item_key'], 'unit_position' => $r['unit_position']],
                ['position_label' => $r['position_label'], 'category_id' => $r['category_id'], 'standard_name' => $r['name'], 'mode' => $r['mode'], 'expected_qty' => $r['expected_qty'], 'template_id' => $template->id, 'status' => 'empty'],
            );
            $slot->fill(['template_id' => $template->id, 'expected_qty' => $r['expected_qty']])->save();
            if ($slot->activeAssignment()->exists()) {
                $skipped++;

                continue;
            }

            if ($r['mode'] === 'quantity') {
                $group = AssetQuantityGroup::create([
                    'location_id' => $room->id, 'category_id' => $r['category_id'], 'name' => $r['name'].' · '.($room->room_ref ?: $room->name),
                    'unit' => $r['unit'] ?: 'pcs', 'expected_qty' => $r['expected_qty'], 'on_hand' => null, 'status' => 'draft', 'generation_batch_id' => $batch->id,
                ]);
                AssetLabel::issueFor(null, $group);
                $this->assign($slot, null, $group, 'generated', $batch, $user);
                $created++;
                $detail[] = $group->group_code;

                continue;
            }

            $decisionKey = $room->id.'|'.$r['item_key'].'|'.$r['unit_position'];
            $matchId = $decisions[$decisionKey] ?? null;
            if ($matchId && in_array((int) $matchId, array_column($r['candidates'], 'id'), true)) {
                if (in_array((int) $matchId, $usedMatch, true)) {
                    throw WorkflowException::invalid('Satu aset lama dipilih untuk dua slot pada kamar '.($room->room_ref ?: $room->name).'.');
                }
                $usedMatch[] = (int) $matchId;
                $asset = Asset::whereKey($matchId)->lockForUpdate()->first();
                $this->assign($slot, $asset, null, 'matched', $batch, $user);
                $matched++;
                $detail[] = $asset->asset_code.' (dicocokkan)';

                continue;
            }

            // unit baru: draft & belum terverifikasi; seri, nilai, garansi, kondisi, foto TIDAK disalin
            $asset = new Asset([
                'property_id' => $room->property_id,
                'name' => $r['name'].($r['position_label'] ? ' '.$r['position_label'] : ''),
                'category_id' => $r['category_id'], 'location_id' => $room->id, 'department_id' => $r['department_id'],
                'criticality' => $r['criticality'], 'specification' => $r['specification'], 'pm_interval_days' => $r['pm'],
                'lifecycle_status' => 'draft', 'verification_status' => 'unverified', 'operational_status' => 'serviceable',
                'ownership' => 'milik', 'generation_batch_id' => $batch->id, 'migration_source' => 'Template '.$template->code.' v'.$template->version,
            ]);
            $asset->auditReason = 'Dibuat dari batch '.$batch->number;
            $asset->save();
            AssetLabel::issueFor($asset);
            $this->assign($slot, $asset, null, 'generated', $batch, $user);
            $created++;
            $detail[] = $asset->asset_code;
        }

        return ['status' => 'ok', 'created_count' => $created, 'matched_count' => $matched, 'skipped_count' => $skipped, 'message' => null, 'detail' => $detail];
    }

    public function assign(RoomAssetSlot $slot, ?Asset $asset, ?AssetQuantityGroup $group, string $reason, ?GenerationBatch $batch, User $user): SlotAssignment
    {
        $a = SlotAssignment::create([
            'slot_id' => $slot->id, 'asset_id' => $asset?->id, 'quantity_group_id' => $group?->id, 'assigned_at' => now(),
            'assign_reason' => $reason, 'generation_batch_id' => $batch?->id, 'actor_id' => $user->id,
            'active_slot_key' => $slot->id, 'active_asset_key' => $asset?->id,
        ]);
        $slot->update(['status' => 'filled']);

        return $a;
    }

    public function release(SlotAssignment $a, string $reason): void
    {
        $a->update(['released_at' => now(), 'release_reason' => $reason, 'active_slot_key' => null, 'active_asset_key' => null]);
        $a->slot?->update(['status' => 'empty']);
    }

    /** Ringkasan selisih versi template (preview revisi tanpa mengubah aset aktual). */
    public function diff(RoomAssetTemplate $old, RoomAssetTemplate $new): Collection
    {
        $o = $old->items->keyBy('item_key');
        $n = $new->items->keyBy('item_key');

        return $o->keys()->merge($n->keys())->unique()->map(function ($k) use ($o, $n) {
            $a = $o->get($k);
            $b = $n->get($k);
            $change = ! $a ? 'ditambah' : (! $b ? 'dihapus dari template' : ((int) $a->quantity !== (int) $b->quantity || $a->mode !== $b->mode || $a->standard_name !== $b->standard_name ? 'berubah' : 'sama'));

            return ['item_key' => $k, 'old' => $a ? $a->quantity.' × '.$a->standard_name : '-', 'new' => $b ? $b->quantity.' × '.$b->standard_name : '-', 'change' => $change];
        })->values();
    }
}
