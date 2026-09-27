@extends('layouts.admin')

@section('pageTitle', 'Search | Relief Tracker')
@section('title', 'Search results')
@section('subtitle', 'ADMIN PORTAL / SEARCH')

@section('content')
    @php
        $resultGroups = [
            ['title' => 'Beneficiaries', 'items' => $beneficiaries, 'url' => route('admin.beneficiaries', ['search' => $term])],
            ['title' => 'Inventory', 'items' => $inventoryItems, 'url' => route('admin.inventory', ['search' => $term])],
            ['title' => 'Relief packages', 'items' => $packages, 'url' => route('admin.packages', ['search' => $term])],
            ['title' => 'Distributions', 'items' => $distributions, 'url' => route('admin.distribution', ['search' => $term])],
        ];
        $resultCount = collect($resultGroups)->sum(fn ($group) => $group['items']->count());
    @endphp

    <section class="global-search-summary">
        <div>
            <h2>Results for <span>“{{ $term }}”</span></h2>
            <p>{{ $resultCount }} {{ $resultCount === 1 ? 'match' : 'matches' }} found across your records. Up to 8 results are shown per section.</p>
        </div>
        <a class="action-btn" href="{{ route('dashboard') }}">Back to dashboard</a>
    </section>

    @if($resultCount === 0)
        <section class="panel global-search-empty">
            <span aria-hidden="true">⌕</span>
            <h2>No matching records</h2>
            <p>Check the spelling or try a beneficiary name, item, package, or distribution status.</p>
        </section>
    @else
        <div class="global-search-groups">
            @foreach($resultGroups as $group)
                @if($group['items']->isNotEmpty())
                    <section class="panel global-search-group">
                        <div class="global-search-group-heading">
                            <h2>{{ $group['title'] }}</h2>
                            <span>{{ $group['items']->count() }}{{ $group['items']->count() === 8 ? '+' : '' }}</span>
                        </div>
                        <div class="global-search-results">
                            @foreach($group['items'] as $item)
                                <a href="{{ $group['url'] }}" class="global-search-result">
                                    <span class="global-search-result-main">
                                        @if($item instanceof \App\Models\Beneficiary)
                                            <strong>{{ $item->full_name }}</strong>
                                            <small>{{ $item->beneficiary_no }} · {{ $item->address ?: 'No address listed' }}</small>
                                        @elseif($item instanceof \App\Models\InventoryItem)
                                            <strong>{{ $item->item_name }}</strong>
                                            <small>{{ $item->category }} · {{ number_format($item->quantity) }} {{ $item->unit }} in stock</small>
                                        @elseif($item instanceof \App\Models\ReliefPackage)
                                            <strong>{{ $item->package_name }}</strong>
                                            <small>{{ $item->category }} · {{ $item->status }}</small>
                                        @else
                                            <strong>{{ $item->beneficiary?->full_name ?? 'Unknown beneficiary' }} · {{ $item->reliefPackage?->package_name ?? 'Unknown package' }}</strong>
                                            <small>{{ $item->date_released?->format('M d, Y') }} · {{ $item->status }}</small>
                                        @endif
                                    </span>
                                    <span class="global-search-result-arrow" aria-hidden="true">→</span>
                                </a>
                            @endforeach
                        </div>
                        <a class="global-search-view-all" href="{{ $group['url'] }}">View matching {{ strtolower($group['title']) }} <span aria-hidden="true">→</span></a>
                    </section>
                @endif
            @endforeach
        </div>
    @endif
@endsection
