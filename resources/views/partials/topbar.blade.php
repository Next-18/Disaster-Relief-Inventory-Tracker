<header class="topbar">
    <div>
        <p>{{ $subtitle ?? '' }}</p>
        <h1>{{ $title ?? '' }}</h1>
    </div>
    <div class="top-actions">
        @if(!empty($showBell))
            <button class="bell" type="button">♧<i></i></button>
        @endif
        <button class="logout" type="button" onclick="confirmLogout()">Sign out</button>
    </div>
</header>
