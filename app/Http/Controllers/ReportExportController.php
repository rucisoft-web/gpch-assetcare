<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use App\Support\Fmt;
use Illuminate\Http\Request;

class ReportExportController extends Controller
{
    public function export(Request $request, string $report, ReportService $reports)
    {
        $u = $request->user();
        abort_unless($u->hasPermission('report.export'), 403);
        abort_unless(array_key_exists($report, $reports->available($u)), 403);
        $tz = config('assetcare.display_timezone');
        $from = $request->date('from')?->toDateString() ?? now($tz)->subDays(29)->toDateString();
        $to = $request->date('to')?->toDateString() ?? now($tz)->toDateString();
        $data = $reports->build($report, $u, $from, $to);
        $name = 'assetcare-'.$report.'-'.$from.'_'.$to.'.csv';

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            fputcsv($out, $data['headers'], ';');
            foreach ($data['rows'] as $row) {
                // nilai teks dinetralkan dari formula injection; angka tetap angka
                fputcsv($out, array_map(fn ($v) => is_int($v) || is_float($v) ? str_replace('.', ',', (string) $v) : Fmt::csvSafe($v), $row), ';');
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
