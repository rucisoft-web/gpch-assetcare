<?php

namespace App\Http\Controllers;

use App\Models\LabelPrintBatch;
use App\Services\LabelService;
use Illuminate\Http\Request;

class LabelPdfController extends Controller
{
    public function show(Request $request, LabelPrintBatch $batch, LabelService $labels)
    {
        abort_unless($request->user()->hasPermission('label.print'), 403);
        $guides = $request->boolean('guides');
        $pdf = $labels->pdf($batch, $guides);
        if (! $guides) {
            $labels->markDownloaded($batch);
        }

        return $pdf->stream($batch->number.($guides ? '-uji' : '').'.pdf');
    }

    public function test(Request $request, string $profile, LabelService $labels)
    {
        abort_unless($request->user()->hasPermission('label.print'), 403);
        abort_unless(config('assetcare.label_profiles.'.$profile), 404);

        return $labels->testSheet($profile)->stream('lembar-uji-'.$profile.'.pdf');
    }
}
