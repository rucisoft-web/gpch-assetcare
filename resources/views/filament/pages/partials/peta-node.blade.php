@php
    $kids = $tree['children']->get($node->id, collect());
    $total = $tree['total'][$node->id] ?? 0;
    $isSel = $selectedId === $node->id;
@endphp
@if ($total > 0 || ! $hideEmpty)
<li>
    @if ($kids->isNotEmpty())
        <details @if (in_array($node->id, $open, true) || $depth === 0) open @endif>
            <summary class="pa-row {{ $isSel ? 'is-sel' : '' }}">
                <button type="button" wire:click="pickLocation({{ $node->id }})" class="pa-name">{{ $node->name }}</button>
                <span class="pa-count {{ $total ? '' : 'nol' }}">{{ $total }}</span>
            </summary>
            <ul class="pa-tree">
                @foreach ($kids as $child)
                    @include('filament.pages.partials.peta-node', ['node' => $child, 'depth' => $depth + 1])
                @endforeach
            </ul>
        </details>
    @else
        <div class="pa-row pa-leaf {{ $isSel ? 'is-sel' : '' }}">
            <button type="button" wire:click="pickLocation({{ $node->id }})" class="pa-name">{{ $node->name }}</button>
            <span class="pa-count {{ $total ? '' : 'nol' }}">{{ $total }}</span>
        </div>
    @endif
</li>
@endif
