<?php

namespace App\Http\Controllers;

use App\Support\Help\HelpService;
use Filament\Facades\Filament;

/** Isi panel Bantuan (dimuat saat tombol Bantuan diklik). */
class HelpController extends Controller
{
    public function show(\Illuminate\Http\Request $request, string $key, HelpService $help)
    {
        abort_unless(preg_match('/^(pages\.)?[a-z0-9\-]+$/', $key) && $help->exists($key), 404);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return response()->view($request->ajax() ? 'help.panel' : 'help.page', ['h' => $help->build($key)])->header('Cache-Control', 'private, max-age=0');
    }
}
