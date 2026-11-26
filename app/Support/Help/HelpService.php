<?php

namespace App\Support\Help;

use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Throwable;

/** Bantuan kontekstual: isi kurasi (content.php) + daftar field yang dibaca langsung dari form, sehingga selalu sinkron dengan aplikasi. */
class HelpService
{
    private ?array $content = null;

    public function content(): array
    {
        return $this->content ??= require __DIR__.'/content.php';
    }

    /** Nama route Filament → kunci bantuan, mis. filament.admin.resources.assets.edit → assets; filament.admin.pages.laporan → pages.laporan */
    public function keyFor(?string $route): string
    {
        $route = (string) $route;
        if (preg_match('/^filament\.admin\.resources\.([a-z0-9\-]+)\./', $route, $m)) {
            return $m[1];
        }
        if (preg_match('/^filament\.admin\.pages\.([a-z0-9\-]+)/', $route, $m)) {
            return 'pages.'.$m[1];
        }

        return 'pages.beranda';
    }

    public function exists(string $key): bool
    {
        return isset($this->content()[$key]) || $this->resourceFor($key) !== null;
    }

    public function resourceFor(string $key): ?string
    {
        foreach (Filament::getPanel('admin')->getResources() as $r) {
            if ($r::getSlug() === $key) {
                return $r;
            }
        }

        return null;
    }

    public function pageFor(string $key): ?string
    {
        if (! str_starts_with($key, 'pages.')) {
            return null;
        }
        foreach (Filament::getPanel('admin')->getPages() as $p) {
            if ($p::getSlug() === substr($key, 6)) {
                return $p;
            }
        }

        return null;
    }

    /** @return array{title:string, konsep:array, langkah:array, tips:array, shots:array, sections:array, url:?string} */
    public function build(string $key): array
    {
        $c = $this->content()[$key] ?? [];
        $resource = $this->resourceFor($key);
        $page = $this->pageFor($key);
        $title = $c['title'] ?? ($resource ? $resource::getPluralModelLabel() : ($page ? $page::getNavigationLabel() : 'Bantuan'));
        $fields = $this->fields($resource, $page);
        $explain = $c['fields'] ?? [];
        $sections = [];
        foreach ($fields as $f) {
            $desc = $explain[$f['name']] ?? null;
            if (! $desc && ! $f['help'] && ! $f['label']) {
                continue;
            }
            $sections[$f['section'] ?: 'Isian'][] = $f + ['desc' => $desc];
        }
        // field yang dijelaskan tapi tidak terbaca dari form utama (mis. isian di modal)
        $seen = array_column($fields, 'name');
        foreach ($explain as $name => $desc) {
            if (! in_array($name, $seen, true)) {
                $sections['Isian lain (formulir aksi)'][] = ['name' => $name, 'label' => ucfirst(str_replace('_', ' ', $name)), 'required' => null, 'help' => null, 'options' => null, 'desc' => $desc];
            }
        }
        $shots = array_values(array_filter($c['shots'] ?? [], fn ($s) => is_file(public_path('help/'.$s[0]))));

        return [
            'key' => $key, 'title' => $title, 'konsep' => $c['konsep'] ?? [], 'langkah' => $c['langkah'] ?? [], 'tips' => $c['tips'] ?? [],
            'shots' => $shots, 'sections' => $sections,
            'url' => $resource ? rescue(fn () => $resource::getUrl('index'), null, false) : ($page ? rescue(fn () => $page::getUrl(), null, false) : null),
        ];
    }

    /** Baca field dari form resource / halaman (termasuk yang tersembunyi kondisional). */
    public function fields(?string $resource, ?string $page): array
    {
        try {
            if ($resource) {
                $pages = $resource::getPages();
                $pageCls = ($pages['create'] ?? $pages['edit'] ?? $pages['index'])->getPage();
                $schema = $resource::form(Schema::make(app($pageCls))->statePath('data'));
            } elseif ($page && method_exists($page, 'form')) {
                $lw = app($page);
                $schema = $lw->form(Schema::make($lw)->statePath('data'));
            } else {
                return [];
            }
        } catch (Throwable) {
            return [];
        }
        $rows = [];
        $seen = [];
        $walk = function (array $components, ?string $section) use (&$walk, &$rows, &$seen) {
            foreach ($components as $c) {
                if (! is_object($c)) {
                    continue;
                }
                if ($c instanceof Field) {
                    $name = $c->getName();
                    if (! isset($seen[$name])) {
                        $seen[$name] = true;
                        $rows[] = [
                            'name' => $name,
                            'label' => rescue(fn () => strip_tags((string) $c->getLabel()), null, false),
                            'section' => $section,
                            'required' => rescue(fn () => $c->isRequired(), null, false),
                            'help' => rescue(fn () => ($h = $c->getHelperText()) ? strip_tags((string) $h) : null, null, false),
                            'options' => method_exists($c, 'getOptions') ? rescue(function () use ($c) {
                                $o = $c->getOptions();

                                return is_array($o) && $o && count($o) <= 12 && ! is_array(reset($o)) ? array_values(array_map(fn ($v) => strip_tags((string) $v), $o)) : null;
                            }, null, false) : null,
                        ];
                    }
                }
                $sec = $section;
                if ($c instanceof Section) {
                    $sec = rescue(fn () => strip_tags((string) $c->getHeading()), $section, false) ?: $section;
                }
                $kids = [];
                if (method_exists($c, 'getChildComponents')) {
                    $kids = rescue(fn () => $c->getChildComponents(), [], false);
                }
                if (method_exists($c, 'getChildSchemas')) {
                    foreach (rescue(fn () => $c->getChildSchemas(withHidden: true), [], false) as $cs) {
                        $kids = array_merge($kids, rescue(fn () => $cs->getComponents(withHidden: true), [], false));
                    }
                }
                if ($kids) {
                    $walk($kids, $sec);
                }
            }
        };
        $walk(rescue(fn () => $schema->getComponents(withHidden: true), [], false), null);

        return $rows;
    }
}
