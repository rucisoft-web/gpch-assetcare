<?php

namespace Tests\Feature;

use App\Enums\Condition;
use App\Enums\IdentificationSource;
use App\Enums\PmFrequency;
use App\Enums\RequestState;
use App\Enums\VerificationStatus;
use App\Enums\WorkOrderState;
use App\Enums\WorkType;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetTemplate;
use App\Models\Location;
use App\Models\MaintenanceRequest;
use App\Models\PreventivePlan;
use App\Models\WorkOrder;
use App\Services\AssetIntake;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class DomainTest extends TestCase
{
    use RefreshDatabase;

    protected function asset(array $attributes = []): Asset
    {
        return Asset::create($attributes + [
            'name' => 'AHU Lobby',
            'asset_category_id' => AssetCategory::where('code', 'HVAC')->value('id'),
            'location_id' => Location::firstOrCreate(['name' => 'Gedung A'])->id,
        ]);
    }

    public function test_asset_gets_code_qr_token_and_computed_scores(): void
    {
        $this->useCompany($this->company());
        $asset = $this->asset(['physical_condition' => 30, 'maintenance_compliance' => 30, 'safety_impact' => 5, 'guest_impact' => 5, 'revenue_impact' => 5, 'operational_impact' => 5, 'replacement_difficulty' => 5]);

        $this->assertMatchesRegularExpression('#^AST/\d{4}/00001$#', $asset->code);
        $this->assertSame(40, strlen($asset->qr_token));
        // 30×25% + 30×20% + 80×55% (komponen lain bawaan 80) = 57,5.
        $this->assertSame(57.5, $asset->health_score);
        $this->assertSame(Condition::Poor, $asset->condition);
        $this->assertSame('critical', $asset->criticality->value);
        $this->assertSame(42.5, $asset->risk_score);
        $this->assertSame('high', $asset->priority->value);
        $this->assertSame(15.0, $asset->expected_life_years); // dari kategori HVAC

        $this->assertMatchesRegularExpression('#/00002$#', $this->asset()->code);
    }

    public function test_data_is_isolated_per_company(): void
    {
        $a = $this->company('Hotel A');
        $b = $this->company('Hotel B');

        $this->useCompany($a);
        $assetA = $this->asset();

        $this->useCompany($b);
        $this->assertSame(0, Asset::count());
        $this->assertNull(Asset::find($assetA->id));

        // Tidak boleh merujuk lokasi milik perusahaan lain.
        $this->expectException(LogicException::class);
        $this->asset(['location_id' => $assetA->location_id]);
    }

    public function test_request_to_work_order_and_work_order_states(): void
    {
        $this->useCompany($this->company());
        $asset = $this->asset();

        $request = MaintenanceRequest::create(['asset_id' => $asset->id, 'request_type' => 'emergency', 'priority' => 'urgent', 'description' => 'Bocor']);
        $this->assertStringStartsWith('MR/', $request->number);

        $request->verify();
        $wo = $request->createWorkOrder();

        $this->assertSame(RequestState::Converted, $request->fresh()->state);
        $this->assertSame(WorkType::Emergency, $wo->work_type);
        $this->assertSame($wo->id, $request->fresh()->work_order_id);

        $wo->update(['sparepart_cost' => 100000, 'vendor_cost' => 250000, 'other_cost' => 5000]);
        $this->assertEquals(355000, $wo->fresh()->total_cost);

        $this->assertFalse($wo->can('close'));
        $wo->transition('start');
        $this->assertNotNull($wo->started_at);
        $wo->transition('done');
        $this->assertNotNull($wo->completed_at);
        $wo->transition('close');
        $this->assertSame(WorkOrderState::Closed, $wo->fresh()->state);

        $this->expectException(LogicException::class);
        $wo->transition('start');
    }

    public function test_preventive_plan_issues_work_order_and_advances_due_date(): void
    {
        $this->useCompany($this->company());
        $plan = PreventivePlan::create(['name' => 'PM AHU', 'asset_id' => $this->asset()->id, 'frequency' => PmFrequency::Quarterly, 'next_due_date' => '2026-01-31']);

        $this->assertTrue($plan->isOverdue());
        $wo = $plan->issueWorkOrder();

        $this->assertSame(WorkType::Preventive, $wo->work_type);
        $this->assertSame('2026-04-30', $plan->fresh()->next_due_date->toDateString());
        $this->assertSame(1, PreventivePlan::overdue()->count());
    }

    public function test_intake_from_template_copy_and_duplicate(): void
    {
        $this->useCompany($this->company());
        $cat = AssetCategory::first();
        $rooms = collect(['101', '102', '103'])->map(fn ($n) => Location::create(['name' => "Kamar {$n}"]));

        $template = AssetTemplate::create(['name' => 'Kamar', 'location_type' => 'room']);
        $template->lines()->create(['name' => 'AC', 'asset_category_id' => $cat->id, 'quantity' => 1]);
        $template->lines()->create(['name' => 'Kursi', 'asset_category_id' => $cat->id, 'quantity' => 2]);

        $intake = app(AssetIntake::class);
        $this->assertCount(6, $intake->fromTemplate($template, [$rooms[0]->id, $rooms[1]->id]));
        $this->assertCount(0, $intake->fromTemplate($template, [$rooms[0]->id]), 'yang sudah ada dilewati');
        $this->assertSame(['AC', 'Kursi #1', 'Kursi #2'], Asset::where('location_id', $rooms[0]->id)->orderBy('id')->pluck('name')->all());
        $this->assertSame(VerificationStatus::Expected, Asset::first()->verification_status);

        $this->assertCount(3, $intake->copyLocation($rooms[0]->id, [$rooms[2]->id]));
        $this->assertSame(IdentificationSource::Copied, Asset::where('location_id', $rooms[2]->id)->first()->identification_source);

        $dup = $intake->duplicate(Asset::first(), [$rooms[1]->id, $rooms[2]->id]);
        $this->assertCount(2, $dup);
        $this->assertSame(IdentificationSource::Duplicated, $dup->first()->identification_source);

        $asset = Asset::first();
        $asset->markVerification(VerificationStatus::Additional);
        $this->assertSame(IdentificationSource::Additional, $asset->fresh()->identification_source);
    }

    public function test_room_types_generate_assets_per_location_and_templates_can_be_duplicated(): void
    {
        $this->useCompany($this->company());
        $cat = AssetCategory::first();

        $standard = AssetTemplate::create(['name' => 'Kamar Standard', 'location_type' => 'room']);
        $standard->lines()->create(['name' => 'AC 1 PK', 'asset_category_id' => $cat->id, 'quantity' => 1]);
        $standard->lines()->create(['name' => 'TV 32"', 'asset_category_id' => $cat->id, 'quantity' => 1]);

        $suite = app(AssetIntake::class)->duplicateTemplate($standard, 'Junior Suite');
        $this->assertSame(['AC 1 PK', 'TV 32"'], $suite->lines()->pluck('name')->all());
        $this->assertSame(2, $standard->lines()->count(), 'templat asal tidak berubah');
        $suite->lines()->where('name', 'AC 1 PK')->update(['quantity' => 2]);

        $r201 = Location::create(['name' => 'Kamar 201', 'asset_template_id' => $standard->id]);
        $r202 = Location::create(['name' => 'Kamar 202', 'asset_template_id' => $standard->id]);
        $r1101 = Location::create(['name' => 'Kamar 1101', 'asset_template_id' => $suite->id]);
        $lobby = Location::create(['name' => 'Lobby']);

        $intake = app(AssetIntake::class);
        $created = $intake->fromLinkedTemplates([$r201->id, $r202->id, $r1101->id, $lobby->id]);

        $this->assertCount(2 + 2 + 3, $created);
        $this->assertSame(['AC 1 PK #1', 'AC 1 PK #2', 'TV 32"'], Asset::where('location_id', $r1101->id)->orderBy('id')->pluck('name')->all());
        $this->assertSame(0, Asset::where('location_id', $lobby->id)->count(), 'lokasi tanpa jenis ruang dilewati');
        $this->assertCount(0, $intake->fromLinkedTemplates([$r201->id]), 'yang sudah ada dilewati');
        $this->assertSame([$r201->id, $r202->id], $standard->locations()->orderBy('id')->pluck('id')->all());

        // Jenis ruang tidak boleh merujuk templat perusahaan lain.
        $this->useCompany($this->company('Hotel Lain'));
        $this->expectException(LogicException::class);
        Location::create(['name' => 'Kamar X', 'asset_template_id' => $standard->id]);
    }

    public function test_location_complete_name_follows_parent(): void
    {
        $this->useCompany($this->company());
        $tower = Location::create(['name' => 'Gedung']);
        $floor = Location::create(['name' => 'Lantai 2', 'parent_id' => $tower->id]);
        $room = Location::create(['name' => 'Kamar 201', 'parent_id' => $floor->id]);

        $this->assertSame('Gedung / Lantai 2 / Kamar 201', $room->complete_name);

        $tower->update(['name' => 'Tower Utama']);
        $this->assertSame('Tower Utama / Lantai 2 / Kamar 201', $room->fresh()->complete_name);
    }
}
