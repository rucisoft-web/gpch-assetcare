<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Ukuran label umum (roll & pita laminasi) bisa dicetak tanpa teks keluar dari label. */
class LabelProfileTest extends TestCase
{
    public function test_common_label_sizes_render_to_actual_size(): void
    {
        foreach (['roll-40x30' => [40, 30], 'roll-50x25' => [50, 25], 'roll-60x40' => [60, 40], 'tape-24' => [60, 24], 'tape-18' => [50, 18]] as $key => [$w, $h]) {
            $p = config('assetcare.label_profiles.'.$key);
            $this->assertNotNull($p, $key);
            $this->assertSame([$w, $h], [$p['page_w'], $p['page_h']], $key);
            $this->assertLessThanOrEqual($h, $p['qr_mm'], $key);

            $html = view('labels.pdf', ['batch' => (object) ['number' => 'UJI'], 'p' => $p + ['key' => $key], 'guides' => false, 'test' => true,
                'sheets' => collect([1 => collect([['qr' => 'data:image/png;base64,', 'item' => (object) [
                    'cell_no' => 1, 'sequence' => 1, 'code_snapshot' => 'MB-08-819-TV-01', 'name_snapshot' => 'TV kamar', 'location_snapshot' => 'Kamar 819',
                ]]])])])->render();

            $this->assertStringContainsString('TV-01', $html, $key);
            // label pendek: tanpa merek & catatan kaki; lembar roll: tanpa petunjuk kalibrasi A4
            $this->assertSame($h >= 28, str_contains($html, 'Scan untuk info'), $key);
            $this->assertStringNotContainsString('LEMBAR UJI', $html, $key);
        }
    }
}
