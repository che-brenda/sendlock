@props([
    'size' => 300,          // donut diameter in px
    'title' => 'Top Risk Reasons',
    'segments' => null,     // real data: array of ['label','value','color']; null = demo mode
    'total' => null,        // real data: total scans analysed (centre figure)
])

@php
    $id = 'riskchart-'.\Illuminate\Support\Str::random(8);
    $real = is_array($segments);
    $empty = $real && count($segments) === 0;
@endphp

{{--
    Animated "Top Risk Reasons" donut. Two modes:
      • demo (no `segments`): cycles between live-looking result sets (landing page).
      • data-driven (`segments` + `total`): renders an org's real breakdown derived
        from EmailScan history, animating the segments in and counting up to `total`.
    Self-contained SVG + a scoped inline script — no chart library, no Alpine.
--}}
<div id="{{ $id }}"
     {{ $attributes->merge(['class' => 'rounded-2xl border border-slate-200 bg-white p-6 shadow-sm']) }}>

    <div class="mb-4 flex items-center justify-between">
        <h3 class="text-base font-semibold text-slate-800">{{ $title }}</h3>
        <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-600">
            <span class="relative flex h-2 w-2">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-rose-400 opacity-75"></span>
                <span class="relative inline-flex h-2 w-2 rounded-full bg-rose-500"></span>
            </span>
            LIVE
        </span>
    </div>

    @if($empty)
        <div class="flex flex-col items-center justify-center gap-2 py-10 text-center" style="min-height: {{ $size }}px;">
            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-500">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
            </span>
            <p class="text-sm font-medium text-slate-600">No flagged sends yet</p>
            <p class="text-xs text-slate-400">Your outbound traffic looks clean. Risk reasons will appear here as scans are run.</p>
        </div>
    @else
        <div class="flex flex-col items-center gap-6 sm:flex-row sm:items-center sm:gap-8">
            <!-- Donut -->
            <div class="relative shrink-0" style="width: {{ $size }}px; height: {{ $size }}px;">
                <svg viewBox="0 0 120 120" width="{{ $size }}" height="{{ $size }}">
                    <circle cx="60" cy="60" r="54" fill="none" stroke="#eef2f7" stroke-width="13"></circle>
                    <g class="rc-segments" transform="rotate(-90 60 60)"></g>
                </svg>
                <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
                    <span class="rc-count text-3xl font-extrabold tracking-tight text-slate-900">0</span>
                    <span class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Scans analysed</span>
                </div>
            </div>

            <!-- Legend -->
            <ul class="rc-legend w-full space-y-2.5 text-sm"></ul>
        </div>
    @endif
</div>

@unless($empty)
<script>
(function () {
    var root = document.getElementById('{{ $id }}');
    if (!root || root.dataset.rcInit) return;
    root.dataset.rcInit = '1';

    var segG = root.querySelector('.rc-segments');
    if (!segG) return;

    var C = 2 * Math.PI * 54;
    var SVGNS = 'http://www.w3.org/2000/svg';
    var legend = root.querySelector('.rc-legend');
    var countEl = root.querySelector('.rc-count');

    var real = @json($real);
    var realSegments = @json($real ? array_values($segments) : []);
    var realTotal = @json($real ? (int) ($total ?? 0) : 0);

    var demoCats = [
        { k: 'Similar Domain', c: '#6366f1' }, { k: 'New Domain', c: '#ef4444' },
        { k: 'No Prior Communication', c: '#22c55e' }, { k: 'Low Reputation', c: '#06b6d4' },
        { k: 'Impersonation', c: '#f59e0b' }, { k: 'Others', c: '#94a3b8' }
    ];
    var demoSets = [[42, 20, 14, 10, 9, 5], [30, 30, 12, 8, 15, 5], [26, 18, 22, 14, 15, 5], [38, 23, 11, 12, 11, 5]];

    var cats = real ? realSegments.map(function (s) { return { k: s.label, c: s.color }; }) : demoCats;

    var circles = [], legendVals = [];
    cats.forEach(function (cat) {
        var el = document.createElementNS(SVGNS, 'circle');
        el.setAttribute('cx', 60); el.setAttribute('cy', 60); el.setAttribute('r', 54);
        el.setAttribute('fill', 'none'); el.setAttribute('stroke', cat.c); el.setAttribute('stroke-width', 13);
        el.style.strokeDasharray = '0 ' + C;
        el.style.transition = 'stroke-dasharray .9s cubic-bezier(.4,0,.2,1), stroke-dashoffset .9s cubic-bezier(.4,0,.2,1)';
        segG.appendChild(el); circles.push(el);

        var li = document.createElement('li');
        li.className = 'flex items-center gap-2.5';
        li.innerHTML =
            '<span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background:' + cat.c + '"></span>' +
            '<span class="flex-1 text-slate-600">' + cat.k + '</span>' +
            '<span class="rc-val font-semibold text-slate-900 tabular-nums">0%</span>';
        legend.appendChild(li); legendVals.push(li.querySelector('.rc-val'));
    });

    function render(vals) {
        var total = vals.reduce(function (a, b) { return a + b; }, 0) || 1;
        var cum = 0;
        vals.forEach(function (v, i) {
            var len = (v / total) * C;
            circles[i].style.strokeDasharray = len + ' ' + C;
            circles[i].style.strokeDashoffset = (-cum);
            cum += len;
            tween(legendVals[i], Math.round((v / total) * 100) + '%');
        });
    }

    function tween(el, to) {
        var toNum = parseInt(to, 10) || 0, from = parseInt(el.textContent, 10) || 0, start = null, dur = 800;
        var suffix = /%$/.test(to) ? '%' : '';
        function step(ts) {
            if (!start) start = ts;
            var p = Math.min((ts - start) / dur, 1);
            el.textContent = Math.round(from + (toNum - from) * p) + suffix;
            if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }

    if (real) {
        // Grow the segments in from zero, then count the centre figure up.
        requestAnimationFrame(function () { render(realSegments.map(function (s) { return s.value; })); });
        tween(countEl, String(realTotal));
    } else {
        var n = 12000 + Math.floor(Math.random() * 3000);
        function bump() { n += Math.floor(Math.random() * 70) + 20; countEl.textContent = n.toLocaleString(); }
        var idx = 0; render(demoSets[0]); bump();
        setInterval(function () { idx = (idx + 1) % demoSets.length; render(demoSets[idx]); }, 3600);
        setInterval(bump, 2100);
    }
})();
</script>
@endunless
