@props(['name' => 'chart'])
<svg {{ $attributes->merge(['class' => 'icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('bell') <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/> @break
        @case('download') <path d="M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5"/> @break
        @case('filter') <path d="M4 6h16M7 12h10"/><circle cx="12" cy="18" r="1.75" fill="currentColor" stroke="none"/> @break
        @case('clipboard') <rect x="5" y="4" width="14" height="17" rx="2"/><rect x="9" y="2" width="6" height="4" rx="1"/><path d="m8 13 3 3 5-6"/> @break
        @case('graduation') <path d="m2 9 10-5 10 5-10 5L2 9Zm4 2v6c4 3 8 3 12 0v-6M22 9v7"/> @break
        @case('calendar') <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18m-13 5 2 2 5-4"/> @break
        @case('trend') <path d="m3 17 6-6 4 4 8-10m-6 0h6v6"/> @break
        @case('line-chart') <path d="M3 4v16h18M6 15l5-5 4 3 6-7"/> @break
        @case('people') <circle cx="12" cy="7" r="3"/><path d="M6 20v-2a5 5 0 0 1 5-5h2a5 5 0 0 1 5 5v2M5.5 5A2.8 2.8 0 0 0 5 10.5M18.5 5a2.8 2.8 0 0 1 .5 5.5M5 13a4 4 0 0 0-4 4v1m18-5a4 4 0 0 1 4 4v1"/> @break
        @case('layers') <path d="m12 3 9 5-9 5-9-5 9-5Zm-9 9 9 5 9-5M3 16l9 5 9-5"/> @break
        @case('performance') <path d="m3 11 6-5 4 3 7-6m-4 0h4v4"/><path d="M3 15h3v6H3zm5-3h3v9H8zm5 2h3v7h-3zm5-5h3v12h-3z" fill="currentColor" stroke="none"/> @break
        @case('star') <path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9L12 3Z"/> @break
        @case('info') <circle cx="12" cy="12" r="9"/><path d="M12 11v5m0-9h.01"/> @break
        @case('alert') <path d="M10.3 4.5 2.7 18a2 2 0 0 0 1.7 3h15.2a2 2 0 0 0 1.7-3L13.7 4.5a2 2 0 0 0-3.4 0ZM12 9v4m0 4h.01"/> @break
        @case('arrow') <path d="M5 12h14m-5-5 5 5-5 5"/> @break
        @case('chevron') <path d="m9 5 7 7-7 7"/> @break
        @case('chevron-down') <path d="m6 9 6 6 6-6"/> @break
        @case('close') <path d="m6 6 12 12M6 18 18 6"/> @break
        @case('search') <circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/> @break
        @case('eye') <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/> @break
        @case('reset') <path d="M3 10a9 9 0 1 1 2 8M3 4v6h6"/> @break
        @case('check') <path d="m5 12 4 4L19 6"/> @break
        @case('clock') <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/> @break
        @case('shield') <path d="M12 3 3 7v6c0 4 9 8 9 8s9-4 9-8V7l-9-4Z"/><path d="m8 12 3 3 5-6"/> @break
        @case('sort') <path d="m8 4-3 3h6L8 4Zm8 16 3-3h-6l3 3ZM8 7v13m8-16v13"/> @break
        @default <path d="M4 20V10m7 10V4m7 16v-8M2 21h20"/>
    @endswitch
</svg>
