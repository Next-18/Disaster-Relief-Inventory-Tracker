<header class="topbar">
    <div>
        <p>{{ $subtitle ?? '' }}</p>
        <h1>{{ $title ?? '' }}</h1>
    </div>
    <div class="top-actions">
        @if(!empty($showBell))
            <button class="bell" type="button">♧<i></i></button>
        @endif
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="logout" type="submit">Sign out</button>
        </form>
    </div>
</header>
