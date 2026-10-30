@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Navegación de Páginas" class="px-4 py-3 bg-obsidian-panel/90 flex flex-col md:flex-row items-center justify-between gap-3 text-xs font-mono">
        <!-- INFORMACIÓN DE REGISTROS -->
        <div class="text-obsidian-muted text-[11px] flex flex-wrap items-center gap-1.5 justify-center md:justify-start">
            <span>Mostrando</span>
            <span class="text-white font-bold">{{ $paginator->firstItem() ?? 0 }}</span>
            <span>a</span>
            <span class="text-white font-bold">{{ $paginator->lastItem() ?? 0 }}</span>
            <span>de</span>
            <span class="text-cyan-400 font-bold">{{ number_format($paginator->total()) }}</span>
            <span>registros</span>
            <span class="text-obsidian-border mx-1 hidden sm:inline">|</span>
            <span class="text-slate-400">Página <strong class="text-white">{{ $paginator->currentPage() }}</strong> de <strong class="text-white">{{ $paginator->lastPage() }}</strong></span>
        </div>

        <!-- CONTROLES DE PAGINACIÓN -->
        <div class="flex items-center gap-1 select-none flex-wrap justify-center">
            {{-- Primera Página --}}
            @if ($paginator->currentPage() > 3)
                <a href="{{ $paginator->url(1) }}" class="px-2 py-1 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-muted hover:text-white hover:border-obsidian-cyan/50 transition text-[11px] flex items-center justify-center" title="Primera página">
                    <span class="material-symbols-outlined text-sm">first_page</span>
                </a>
            @endif

            {{-- Botón Anterior --}}
            @if ($paginator->onFirstPage())
                <span class="px-2.5 py-1 rounded-lg bg-obsidian-panel/40 border border-obsidian-border/40 text-obsidian-muted/40 cursor-not-allowed text-[11px] inline-flex items-center gap-0.5">
                    <span class="material-symbols-outlined text-sm">chevron_left</span>
                    <span class="hidden sm:inline">Anterior</span>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="px-2.5 py-1 rounded-lg bg-obsidian-panel border border-obsidian-border text-cyan-300 hover:bg-cyan-500 hover:text-black transition text-[11px] font-semibold inline-flex items-center gap-0.5 shadow-xs">
                    <span class="material-symbols-outlined text-sm">chevron_left</span>
                    <span class="hidden sm:inline">Anterior</span>
                </a>
            @endif

            {{-- Elementos de Páginas --}}
            @foreach ($elements as $element)
                {{-- Separador de Puntos Suspensivos --}}
                @if (is_string($element))
                    <span class="px-1.5 py-1 text-obsidian-muted/60 text-xs font-mono select-none">{{ $element }}</span>
                @endif

                {{-- Array de Enlaces --}}
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="px-2.5 py-1 rounded-lg bg-cyan-500 text-black font-bold text-[11px] shadow-xs min-w-[28px] text-center" aria-current="page">
                                {{ $page }}
                            </span>
                        @else
                            <a href="{{ $url }}" class="px-2.5 py-1 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-muted hover:text-white hover:border-obsidian-cyan/50 transition text-[11px] min-w-[28px] text-center font-mono">
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Botón Siguiente --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="px-2.5 py-1 rounded-lg bg-obsidian-panel border border-obsidian-border text-cyan-300 hover:bg-cyan-500 hover:text-black transition text-[11px] font-semibold inline-flex items-center gap-0.5 shadow-xs">
                    <span class="hidden sm:inline">Siguiente</span>
                    <span class="material-symbols-outlined text-sm">chevron_right</span>
                </a>
            @else
                <span class="px-2.5 py-1 rounded-lg bg-obsidian-panel/40 border border-obsidian-border/40 text-obsidian-muted/40 cursor-not-allowed text-[11px] inline-flex items-center gap-0.5">
                    <span class="hidden sm:inline">Siguiente</span>
                    <span class="material-symbols-outlined text-sm">chevron_right</span>
                </span>
            @endif

            {{-- Última Página --}}
            @if ($paginator->currentPage() < $paginator->lastPage() - 2)
                <a href="{{ $paginator->url($paginator->lastPage()) }}" class="px-2 py-1 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-muted hover:text-white hover:border-obsidian-cyan/50 transition text-[11px] flex items-center justify-center" title="Última página">
                    <span class="material-symbols-outlined text-sm">last_page</span>
                </a>
            @endif
        </div>
    </nav>
@endif
