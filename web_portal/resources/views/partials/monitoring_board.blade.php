    @php
        $snapshotServices = ($snapshotData && isset($snapshotData['services'])) ? collect($snapshotData['services'])->keyBy('letter') : collect();
        $snapshotSites = ($snapshotData && isset($snapshotData['sites'])) ? collect($snapshotData['sites'])->keyBy('letter') : collect();
        $snapshotNetDevices = ($snapshotData && isset($snapshotData['network_devices'])) ? collect($snapshotData['network_devices'])->keyBy('ip') : collect();

        // 1. Servicios Activos vs Caídos
        $activeServices = $services->filter(function($s) use ($snapshotServices) {
            $snap = $snapshotServices->get($s->letter);
            return $snap ? ($snap['is_up'] ?? false) : false;
        });
        $downServices = $services->filter(function($s) use ($snapshotServices) {
            $snap = $snapshotServices->get($s->letter);
            return $snap ? !($snap['is_up'] ?? false) : true;
        });

        // 2. Sedes Activas vs Sin Conexión
        $activeSites = $sites->filter(function($st) use ($snapshotSites) {
            $snap = $snapshotSites->get($st->letter);
            return $snap ? ($snap['is_up'] ?? false) : false;
        });
        $downSites = $sites->filter(function($st) use ($snapshotSites) {
            $snap = $snapshotSites->get($st->letter);
            return $snap ? !($snap['is_up'] ?? false) : true;
        });

        // 3. Dispositivos Sede Valle Seco
        $activeNetDevices = ($networkDevices ?? collect())->map(function($d) use ($snapshotNetDevices) {
            $snap = $snapshotNetDevices->get($d->ip);
            $d->is_up_evaluated = $snap ? ($snap['is_up'] ?? false) : false;
            $d->latency_evaluated = $snap ? ($snap['latency_ms'] ?? 0) : 0;
            return $d;
        });
        $netDevicesOnlineCount = $activeNetDevices->where('is_up_evaluated', true)->count();
    @endphp

    <!-- CUERPO PRINCIPAL (3 COLUMNAS: ACTIVOS, SEDES Y BLOQUE DE CAÍDAS) -->

<div class="{{ ($isDashboard ?? false) ? 'flex flex-col lg:flex-row gap-3 sm:gap-4 lg:h-[580px]' : 'flex-1 p-3 sm:p-4 overflow-hidden flex flex-col lg:flex-row gap-3 sm:gap-4 min-h-0' }}">
        
        <!-- ========================================================================= -->
        <!-- COLUMNA 1: SERVICIOS ACTIVOS (32% ANCHO)                                 -->
        <!-- ========================================================================= -->
        <section class="glass-panel rounded-xl flex flex-col w-full lg:w-[32%] h-full overflow-hidden border border-obsidian-border/80">
            <!-- CABECERA -->
            <div class="p-3.5 border-b border-obsidian-border flex items-center justify-between bg-obsidian-panel/50">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-emerald-400 text-lg">check_circle</span>
                    <h2 class="text-xs font-bold text-white uppercase font-mono tracking-wider">Servicios Activos</h2>
                </div>
                <span id="badge-count-active-services" class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-emerald-950/80 text-emerald-400 border border-emerald-500/40 glow-green">
                    {{ $activeServices->count() }} Operativos
                </span>
            </div>

            <!-- LISTA VERTICAL DE SERVICIOS ACTIVOS (ULTRA-COMPACTA) -->
            <div class="flex-1 overflow-y-auto p-2 space-y-1 custom-scroll" id="active-services-container">
                @forelse($activeServices as $s)
                    @php
                        $sData = $snapshotServices->get($s->letter);
                        $latency = $sData ? ($sData['latency_ms'] ?? 0) : 0;
                        $targetHost = $s->host_ip ?: ($s->web_url ?: '127.0.0.1');
                    @endphp
                    <div class="py-1.5 px-2.5 rounded-lg bg-obsidian-panel/60 hover:bg-obsidian-panel border border-obsidian-border/50 hover:border-emerald-500/50 transition cursor-pointer flex items-center justify-between group item-searchable select-none"
                         data-search="{{ strtolower($s->name . ' ' . $s->type) }}"
                         data-tech-title="{{ $s->name }}"
                         data-tech-type="{{ $s->type }}"
                         @if(Auth::check())
                         data-tech-ip="{{ $targetHost }}"
                         data-tech-port="{{ $s->port ?: ($s->type == 'WEB' ? '80/443' : ($s->type == 'DNS' ? '53' : ($s->type == 'SMTP' ? '25' : ($s->type == 'LDAP' ? '389' : 'ICMP')))) }}"
                         data-tech-protocol="{{ $s->type == 'WEB' ? 'HTTP/HTTPS GET Request' : ($s->type == 'DNS' ? 'DNS Query' : ($s->type == 'SMTP' ? 'SMTP Mail Handshake' : ($s->type == 'LDAP' ? 'LDAP Bind Handshake' : 'ICMP Ping'))) }}"
                         data-tech-latency="{{ $latency > 0 ? $latency . ' ms' : '< 15 ms' }}"
                         data-tech-status="OPERATIVO (200 OK / Response)"
                         data-tech-details="{{ $s->web_url ? 'Endpoint: ' . $s->web_url : 'Verificación por socket de transporte directo.' }}"
                         data-tech-id="{{ $s->id }}"
                         data-tech-kind="service"
                         @else
                         data-tech-auth-required="true"
                         title="DEBE INICIAR SESIÓN PARA VER LOS DATOS"
                         @endif>
                        
                        <div class="flex items-center gap-2 min-w-0">
                            <!-- LED VERDE COMPACTO -->
                            <div class="w-1.5 h-1.5 rounded-full shrink-0 bg-emerald-400 glow-green"></div>

                            @php
                                $svcCert = isset($sslMapByService) ? $sslMapByService->get($s->id) : null;
                                if (!$svcCert && isset($sslMapByDomain) && $s->web_url) {
                                    $parsedDomain = parse_url($s->web_url, PHP_URL_HOST);
                                    if ($parsedDomain) {
                                        $svcCert = $sslMapByDomain->get($parsedDomain);
                                    }
                                }
                                $isHttps = str_starts_with(strtolower($s->web_url ?? ''), 'https://') || ($s->port == 443);
                            @endphp

                            @if($svcCert)
                                @if($svcCert->days_remaining < 0 || $svcCert->last_check_status === 'expired')
                                    <span class="material-symbols-outlined text-[12.5px] text-red-400 shrink-0 animate-pulse" title="SSL EXPIRADO (hace {{ abs($svcCert->days_remaining) }} días) • Emisor: {{ $svcCert->issuer_cn ?: 'N/A' }}">lock_open</span>
                                @elseif($svcCert->last_check_status === 'error')
                                    <span class="material-symbols-outlined text-[12.5px] text-red-400 shrink-0" title="Error de Inspección SSL/TLS">lock_open</span>
                                @elseif($svcCert->days_remaining <= 7)
                                    <span class="material-symbols-outlined text-[12.5px] text-rose-400 shrink-0 animate-pulse" title="SSL CRÍTICO: expira en {{ $svcCert->days_remaining }} días ({{ $svcCert->valid_to ? $svcCert->valid_to->timezone('America/Caracas')->format('d/m/Y') : '' }}) • Emisor: {{ $svcCert->issuer_cn ?: 'N/A' }}">lock_clock</span>
                                @elseif($svcCert->days_remaining <= 30)
                                    <span class="material-symbols-outlined text-[12.5px] text-amber-400 shrink-0" title="SSL POR VENCER: expira en {{ $svcCert->days_remaining }} días ({{ $svcCert->valid_to ? $svcCert->valid_to->timezone('America/Caracas')->format('d/m/Y') : '' }}) • Emisor: {{ $svcCert->issuer_cn ?: 'N/A' }}">lock_clock</span>
                                @else
                                    <span class="material-symbols-outlined text-[12.5px] text-emerald-400 shrink-0" title="SSL VÁLIDO: {{ $svcCert->days_remaining }} días restantes (Vence: {{ $svcCert->valid_to ? $svcCert->valid_to->timezone('America/Caracas')->format('d/m/Y') : '' }}) • Emisor: {{ $svcCert->issuer_cn ?: 'Corporativo' }}">lock</span>
                                @endif
                            @elseif($isHttps)
                                <span class="material-symbols-outlined text-[12.5px] text-cyan-400 shrink-0" title="Servicio Web Seguro HTTPS / TLS">lock</span>
                            @endif

                            <!-- NOMBRE DEL SERVICIO -->
                            <span class="text-[11px] font-semibold text-white group-hover:text-emerald-300 transition-colors truncate">
                                {{ $s->name }}
                            </span>
                        </div>


                        <!-- LATENCIA & BADGE DE PROTOCOLO -->
                        <div class="flex items-center gap-1.5 shrink-0">
                            @if(Auth::check())
                            <span class="text-[9px] font-mono text-emerald-400/90 font-medium">
                                {{ $latency > 0 ? $latency . 'ms' : '<15ms' }}
                            </span>
                            @else
                            <span class="text-amber-400/90 flex items-center justify-center p-0.5" title="DEBE INICIAR SESIÓN PARA VER LOS DATOS">
                                <span class="material-symbols-outlined text-[13px]">lock</span>
                            </span>
                            @endif
                            <span class="px-1 py-0.5 rounded text-[8.5px] font-mono font-bold uppercase bg-obsidian-bg/80 border border-emerald-500/30 text-emerald-300">
                                {{ $s->type }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-xs font-mono text-obsidian-muted">
                        No hay servicios activos reportados en este momento.
                    </div>
                @endforelse
            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- COLUMNA 2: SEDES REGIONALES Y TELEMETRÍA GLOBAL (34% ANCHO)               -->
        <!-- ========================================================================= -->
        <section class="flex flex-col w-full lg:w-[34%] h-full gap-3 overflow-hidden">
            <!-- BLOQUE SUPERIOR: SEDES REGIONALES CONECTADAS -->
            <div class="glass-panel rounded-xl flex-1 flex flex-col overflow-hidden border border-obsidian-border/80">
                <!-- CABECERA -->
                <div class="p-3 border-b border-obsidian-border flex items-center justify-between bg-obsidian-panel/50">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-obsidian-purple text-lg">domain</span>
                        <h2 class="text-xs font-bold text-white uppercase font-mono tracking-wider">Sedes Regionales Conectadas</h2>
                    </div>
                    <span id="badge-count-active-sites" class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-obsidian-purple/20 text-obsidian-purple border border-obsidian-purple/30">
                        {{ $activeSites->count() }} Sedes Online
                    </span>
                </div>

                <!-- LISTA VERTICAL DE SEDES ACTIVAS (COMPACTA Y DESPLEGABLE) -->
                <div class="flex-1 overflow-y-auto p-2 space-y-1.5 custom-scroll" id="active-sites-container">
                    @forelse($activeSites as $site)
                        @php
                            $stData = $snapshotSites->get($site->letter);
                            $latency = $stData ? ($stData['latency_ms'] ?? 0) : 0;
                            $devicesSnapshot = ($stData && isset($stData['devices'])) ? collect($stData['devices'])->keyBy('device_number') : collect();
                            $activeDevices = $site->devices->filter(function($d) {
                                return $d->is_active && $d->name != 'NO CONFIGURADO' && !str_contains(strtoupper($d->name), 'NO CONFIGURADO') && $d->ip != '0.0.0.0';
                            });
                            $cleanAddress = ($site->address && !str_contains(strtoupper($site->address), 'NO CONFIGURADO')) ? $site->address : '';
                            $cleanPhone = ($site->phone_1 && !str_contains(strtoupper($site->phone_1), 'NO CONFIGURADO')) ? $site->phone_1 : '';
                            
                            $stHist = isset($siteHistoryMap[$site->id]) ? $siteHistoryMap[$site->id] : null;
                            $jitter = $stHist ? ($stHist['jitter_ms'] ?? 0) : 0;
                            $loss = $stHist ? ($stHist['packet_loss_pct'] ?? 0) : 0;
                            $minRtt = $stHist ? ($stHist['min_rtt_ms'] ?? 0) : 0;
                            $maxRtt = $stHist ? ($stHist['max_rtt_ms'] ?? 0) : 0;
                        @endphp
                        <div class="rounded-xl bg-obsidian-panel/60 hover:bg-obsidian-panel border border-obsidian-border/60 hover:border-obsidian-purple/50 transition overflow-hidden group item-searchable"
                             data-search="{{ strtolower($site->name . ' ' . $cleanAddress) }}">
                            
                            <!-- ENCABEZADO COMPACTO DE LA SEDE (CLICKEABLE Y CON TOOLTIP AL POSAR) -->
                            <div class="p-2.5 flex items-center justify-between cursor-pointer select-none"
                                 onclick="toggleSiteDetails('site-details-{{ $site->letter }}', this)"
                                     data-tech-title="{{ $site->name }}"
                                 data-tech-type="SEDE REGIONAL"
                                 @if(Auth::check())
                                 data-tech-ip="{{ $site->ip ?: '0.0.0.0' }}"
                                 data-tech-port="Gateway PING / ICMP"
                                 data-tech-protocol="Enlace de Transporte WAN"
                                 data-tech-latency="{{ $latency > 0 ? $latency . ' ms' : '< 20 ms' }}"
                                 data-tech-status="ENLACE PRINCIPAL OPERATIVO"
                                 data-tech-details="{{ $cleanAddress ? 'Ubicación: ' . $cleanAddress : 'Sede Regional Corporativa' }}{{ $cleanPhone ? ' • Contacto: ' . $cleanPhone : '' }}"
                                 data-tech-id="{{ $site->id }}"
                                 data-tech-kind="site"
                                 @else
                                 data-tech-auth-required="true"
                                 title="DEBE INICIAR SESIÓN PARA VER LOS DATOS"
                                 @endif>
                                 
                                <div class="flex items-center space-x-2 min-w-0">
                                    <div class="w-2 h-2 rounded-full shrink-0 bg-emerald-400 glow-green"></div>
                                    <div class="truncate">
                                        <h3 class="text-[11px] font-bold text-white group-hover:text-obsidian-purple transition-colors truncate">
                                            {{ $site->name }}
                                        </h3>
                                        <p class="text-[9px] font-mono text-obsidian-muted truncate">{{ $site->letter == 'A' ? 'Centro de Telecomunicaciones' : 'Enlace Regional Activo' }}</p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-1.5 shrink-0">
                                    @if(Auth::check() && ($jitter > 0 || $loss > 0))
                                    <span class="hidden sm:inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[8.5px] font-mono {{ $loss > 0 ? 'bg-red-950/80 text-red-400 border border-red-500/30' : 'bg-cyan-950/60 text-cyan-300 border border-cyan-500/30' }}" title="Calidad WAN: Jitter {{ $jitter }}ms • Pérdida {{ $loss }}%">
                                        <span>Jitter: {{ $jitter }}ms</span>
                                        @if($loss > 0)
                                            <span class="text-red-400 font-bold">• {{ $loss }}% Pérdida</span>
                                        @endif
                                    </span>
                                    @endif
                                    @if(!Auth::check())
                                    <span class="text-amber-400/90 flex items-center justify-center p-0.5" title="DEBE INICIAR SESIÓN PARA VER LOS DATOS">
                                        <span class="material-symbols-outlined text-[13px]">lock</span>
                                    </span>
                                    @endif
                                    <span class="px-1 py-0.5 rounded text-[8.5px] font-mono font-bold uppercase {{ $site->letter == 'A' ? 'bg-obsidian-cyan/20 text-obsidian-cyan border border-obsidian-cyan/30' : 'bg-obsidian-purple/20 text-obsidian-purple border border-obsidian-purple/30' }}">
                                        {{ $site->letter == 'A' ? 'HUB' : 'SEDE' }}
                                    </span>
                                    <span class="material-symbols-outlined text-obsidian-muted text-sm transition-transform duration-200 chevron-icon">
                                        expand_more
                                    </span>
                                </div>
                            </div>

                            <!-- CONTENIDO DESPLEGABLE CON EQUIPOS EN SITIO (OCULTO POR DEFECTO) -->
                            <div id="site-details-{{ $site->letter }}" class="hidden px-2.5 pb-2.5 pt-1 border-t border-obsidian-border/40 bg-obsidian-bg/40 space-y-2">
                                @if(Auth::check())
                                <!-- METRICAS DE CALIDAD WAN (FASE 5) -->
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-1.5 text-[9px] font-mono bg-obsidian-bg/80 p-2 rounded-lg border border-obsidian-border/40">
                                    <div class="flex flex-col">
                                        <span class="text-[8px] text-obsidian-muted uppercase">Latencia (Avg)</span>
                                        <span class="font-bold text-emerald-400">{{ $latency > 0 ? $latency . ' ms' : '< 15 ms' }}</span>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-[8px] text-obsidian-muted uppercase">Jitter / mdev</span>
                                        <span class="font-bold {{ $jitter > 15 ? 'text-red-400' : ($jitter > 5 ? 'text-amber-400' : 'text-cyan-300') }}">{{ $jitter > 0 ? $jitter . ' ms' : '< 1.0 ms' }}</span>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-[8px] text-obsidian-muted uppercase">Pérdida Paq.</span>
                                        <span class="font-bold {{ $loss > 0 ? 'text-red-400' : 'text-emerald-400' }}">{{ $loss }}%</span>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-[8px] text-obsidian-muted uppercase">Rango RTT</span>
                                        <span class="text-slate-300">{{ $minRtt > 0 ? $minRtt . ' - ' . $maxRtt . ' ms' : '--' }}</span>
                                    </div>
                                </div>

                                <!-- CUADRICULA DE EQUIPOS EN SITIO -->
                                @if($activeDevices->count() > 0)
                                    <div class="space-y-1">
                                        <span class="text-[8.5px] uppercase font-mono tracking-wider text-obsidian-muted block">Equipos en Sitio ({{ $activeDevices->count() }})</span>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                                            @foreach($activeDevices as $dev)
                                                @php
                                                    $dSnap = $devicesSnapshot->get($dev->device_number);
                                                    $devUp = $dSnap ? ($dSnap['is_up'] ?? false) : false;
                                                    $canRemote = Auth::check() && in_array(Auth::user()->role, ['admin', 'operator']);
                                                    $devAcc = strtoupper(trim($dev->access_type ?? 'SIN SOPORTE'));
                                                    $devPort = $dev->access_port ?: ($devAcc === 'SSH' ? 22 : ($devAcc === 'TELNET' ? 23 : ($devAcc === 'WEB' ? 80 : ($devAcc === 'VNC' ? 5900 : ''))));

                                                    $deviceDetailParts = [];
                                                    if ($dev->vendor_data) $deviceDetailParts[] = 'Fabricante / Info: ' . $dev->vendor_data;
                                                    if ($dev->model) $deviceDetailParts[] = 'Modelo: ' . $dev->model;
                                                    if ($dev->serial) $deviceDetailParts[] = 'Serial: ' . $dev->serial;
                                                    if ($dev->ports) $deviceDetailParts[] = 'Puertos: ' . $dev->ports;
                                                    if ($dev->notes) $deviceDetailParts[] = "Notas:\n" . $dev->notes;
                                                    if ($devAcc !== 'SIN SOPORTE') $deviceDetailParts[] = 'Acceso: ' . $devAcc . ($devPort ? ':' . $devPort : '');
                                                    $devDetails = !empty($deviceDetailParts) ? implode("\n", $deviceDetailParts) : 'Dispositivo interno vinculado a la red de ' . $site->name . '.';
                                                @endphp
                                                <div class="bg-obsidian-panel/90 hover:bg-obsidian-panel border border-obsidian-border rounded p-1.5 flex items-center justify-between text-[9px] font-mono cursor-pointer transition hover:border-obsidian-cyan/40"
                                                     data-tech-title="{{ $site->name }} - {{ $dev->name }}"
                                                     data-tech-type="EQUIPO SECUNDARIO"
                                                     data-tech-ip="{{ $dev->ip }}"
                                                     data-tech-port="{{ $devAcc !== 'SIN SOPORTE' ? $devAcc . ':' . $devPort : 'Slot #' . $dev->device_number }}"
                                                     data-tech-protocol="ICMP Echo Ping"
                                                     data-tech-latency="{{ $devUp ? '< 10 ms' : '--' }}"
                                                     data-tech-status="{{ $devUp ? 'ONLINE (Ping Respondido)' : 'OFFLINE (Inaccesible)' }}"
                                                     data-tech-details="{{ $devDetails }}"
                                                     data-tech-id="{{ $site->id }}"
                                                     data-tech-kind="site">
                                                    <div class="flex items-center space-x-1.5 min-w-0 pr-1 truncate">
                                                        <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $devUp ? 'bg-emerald-400 glow-green' : 'bg-red-500' }}"></span>
                                                        <span class="text-white truncate" title="{{ $dev->name }}">{{ $dev->name }}</span>
                                                    </div>

                                                    <!-- BOTÓN DE ACCESO REMOTO SEGÚN PROTOCOLO (SSH / TELNET / WEB / VNC) -->
                                                    @if($devAcc === 'SSH')
                                                        @if($canRemote)
                                                            <button type="button"
                                                                    onclick="event.stopPropagation(); openSshTerminal('{{ $dev->ip }}', {{ $devPort }}, '{{ addslashes($dev->name) }}', '{{ addslashes($site->name) }}')"
                                                                    class="px-1.5 py-0.5 rounded text-[8px] font-mono font-bold bg-emerald-950/90 hover:bg-emerald-500 hover:text-black border border-emerald-500/50 text-emerald-300 transition flex items-center gap-0.5 shrink-0 shadow-sm cursor-pointer"
                                                                    title="Conectar Terminal SSH ({{ $dev->ip }}:{{ $devPort }})">
                                                                <span class="material-symbols-outlined text-[10px]">terminal</span>
                                                                <span>SSH</span>
                                                            </button>
                                                        @else
                                                            <button type="button"
                                                                    onclick="event.stopPropagation(); openAccessModal('SSH', '{{ $dev->ip }}', {{ $devPort }}, '{{ addslashes($dev->name) }}', '{{ addslashes($site->name) }}', false)"
                                                                    class="px-1.5 py-0.5 rounded text-[8px] font-mono bg-obsidian-card/90 hover:bg-amber-950/40 border border-obsidian-border hover:border-amber-500/40 text-obsidian-muted hover:text-amber-300 transition flex items-center gap-0.5 shrink-0 cursor-pointer"
                                                                    title="Debe iniciar sesión para acceder por SSH">
                                                                <span class="material-symbols-outlined text-[10px] text-amber-400/80">lock</span>
                                                                <span>SSH</span>
                                                            </button>
                                                        @endif
                                                    @elseif($devAcc === 'TELNET')
                                                        @if($canRemote)
                                                            <button type="button"
                                                                    onclick="event.stopPropagation(); openTelnetTerminal('{{ $dev->ip }}', {{ $devPort }}, '{{ addslashes($dev->name) }}', '{{ addslashes($site->name) }}')"
                                                                    class="px-1.5 py-0.5 rounded text-[8px] font-mono font-bold bg-cyan-950/90 hover:bg-cyan-500 hover:text-black border border-cyan-500/50 text-cyan-300 transition flex items-center gap-0.5 shrink-0 shadow-sm cursor-pointer"
                                                                    title="Conectar Terminal Telnet ({{ $dev->ip }}:{{ $devPort }})">
                                                                <span class="material-symbols-outlined text-[10px]">terminal</span>
                                                                <span>TELNET</span>
                                                            </button>
                                                        @else
                                                            <button type="button"
                                                                    onclick="event.stopPropagation(); openAccessModal('TELNET', '{{ $dev->ip }}', {{ $devPort }}, '{{ addslashes($dev->name) }}', '{{ addslashes($site->name) }}', false)"
                                                                    class="px-1.5 py-0.5 rounded text-[8px] font-mono bg-obsidian-card/90 hover:bg-amber-950/40 border border-obsidian-border hover:border-amber-500/40 text-obsidian-muted hover:text-amber-300 transition flex items-center gap-0.5 shrink-0 cursor-pointer"
                                                                    title="Debe iniciar sesión para acceder por Telnet">
                                                                <span class="material-symbols-outlined text-[10px] text-amber-400/80">lock</span>
                                                                <span>TELNET</span>
                                                            </button>
                                                        @endif
                                                    @elseif($devAcc === 'WEB')
                                                        @if($canRemote)
                                                            <a href="http://{{ $dev->ip }}:{{ $devPort }}" target="_blank"
                                                               onclick="event.stopPropagation();"
                                                               class="px-1.5 py-0.5 rounded text-[8px] font-mono font-bold bg-blue-950/90 hover:bg-blue-500 hover:text-white border border-blue-500/50 text-blue-300 transition flex items-center gap-0.5 shrink-0 shadow-sm cursor-pointer"
                                                               title="Abrir Panel Web (http://{{ $dev->ip }}:{{ $devPort }})">
                                                                <span class="material-symbols-outlined text-[10px]">language</span>
                                                                <span>WEB</span>
                                                            </a>
                                                        @else
                                                            <button type="button"
                                                                    onclick="event.stopPropagation(); openAccessModal('WEB', '{{ $dev->ip }}', {{ $devPort }}, '{{ addslashes($dev->name) }}', '{{ addslashes($site->name) }}', false)"
                                                                    class="px-1.5 py-0.5 rounded text-[8px] font-mono bg-obsidian-card/90 hover:bg-amber-950/40 border border-obsidian-border hover:border-amber-500/40 text-obsidian-muted hover:text-amber-300 transition flex items-center gap-0.5 shrink-0 cursor-pointer"
                                                                    title="Debe iniciar sesión para acceder al panel web">
                                                                <span class="material-symbols-outlined text-[10px] text-amber-400/80">lock</span>
                                                                <span>WEB</span>
                                                            </button>
                                                        @endif
                                                    @elseif($devAcc === 'VNC')
                                                        @if($canRemote)
                                                            <button type="button"
                                                                    onclick="event.stopPropagation(); openVncViewer('{{ $dev->ip }}', '{{ addslashes($dev->name) }}', '{{ addslashes($site->name) }}')"
                                                                    class="px-1.5 py-0.5 rounded text-[8px] font-mono font-bold bg-purple-950/90 hover:bg-purple-500 hover:text-white border border-purple-500/50 text-purple-300 transition flex items-center gap-0.5 shrink-0 shadow-sm cursor-pointer"
                                                                    title="Conectar Escritorio Remoto VNC ({{ $dev->ip }}:{{ $devPort }})">
                                                                <span class="material-symbols-outlined text-[10px]">desktop_windows</span>
                                                                <span>VNC</span>
                                                            </button>
                                                        @else
                                                            <button type="button"
                                                                    onclick="event.stopPropagation(); openAccessModal('VNC', 5900, '{{ addslashes($dev->name) }}', '{{ addslashes($site->name) }}', false)"
                                                                    class="px-1.5 py-0.5 rounded text-[8px] font-mono bg-obsidian-card/90 hover:bg-amber-950/40 border border-obsidian-border hover:border-amber-500/40 text-obsidian-muted hover:text-amber-300 transition flex items-center gap-0.5 shrink-0 cursor-pointer"
                                                                    title="Debe iniciar sesión para conectar por VNC">
                                                                <span class="material-symbols-outlined text-[10px] text-amber-400/80">lock</span>
                                                                <span>VNC</span>
                                                            </button>
                                                        @endif
                                                    @else
                                                        <span class="px-1 py-0.5 rounded text-[7.5px] font-mono bg-obsidian-card/70 border border-obsidian-border text-obsidian-muted inline-flex items-center gap-0.5" title="Sin soporte de acceso remoto">
                                                            <span class="material-symbols-outlined text-[9px]">power_off</span>
                                                            <span>S/S</span>
                                                        </span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    <p class="text-[9px] font-mono text-obsidian-muted text-center py-1">Sin equipos secundarios registrados</p>
                                @endif
                                @else
                                <div class="p-3 text-center text-xs font-mono text-amber-300 bg-amber-950/20 rounded-lg border border-amber-500/30 space-y-1.5">
                                    <p class="font-bold flex items-center justify-center gap-1.5 text-white">
                                        <span class="material-symbols-outlined text-sm text-amber-400">lock</span>
                                        <span>Acceso Restringido</span>
                                    </p>
                                    <p class="text-[11px] font-sans text-amber-200">Debe iniciar sesión para ver los datos del servicio o la sede en su defecto.</p>
                                </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-xs font-mono text-obsidian-muted">
                            No hay sedes conectadas en este momento.
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- BLOQUE INFERIOR DE TELEMETRÍA: ESTADO GLOBAL & SALUD DE RED -->
            <div class="glass-panel rounded-xl shrink-0 p-3 border border-obsidian-border/80 bg-[#07172b]/95 space-y-2.5 shadow-xl">

                <!-- CABECERA DE TELEMETRÍA -->
                <div class="flex items-center justify-between pb-1.5 border-b border-obsidian-border/60">
                    <div class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-obsidian-cyan text-base">monitor_heart</span>
                        <h3 class="text-[11px] font-bold text-white uppercase font-mono tracking-wider">Telemetría y Estatus Global</h3>
                    </div>
                    @php
                        $gStatus = $latestSnapshot ? $latestSnapshot->global_status : ($downServices->count() == 0 && $downSites->count() == 0 ? 'OPERACIONAL' : 'DEGRADADO');
                        $badgeClass = ($gStatus == 'OPERACIONAL') 
                            ? 'bg-emerald-950/80 text-emerald-400 border-emerald-500/50 glow-green' 
                            : (($gStatus == 'DEGRADADO') 
                                ? 'bg-amber-950/80 text-amber-400 border-amber-500/50' 
                                : 'bg-red-950/80 text-red-400 border-red-500/50 glow-red');
                    @endphp
                    <span id="telemetry-status-badge" class="px-2 py-0.5 rounded-full text-[9px] font-mono font-bold border flex items-center gap-1 {{ $badgeClass }}"
                          data-tech-title="DIAGNÓSTICO DE SALUD"
                          data-tech-type="ESTADO GLOBAL"
                          @if(Auth::check())
                          data-tech-ip="Red Corporativa Nacional"
                          data-tech-protocol="Orquestador Asíncrono Python"
                          data-tech-latency="< 5.0s ciclo"
                          data-tech-status="{{ $gStatus }}"
                          data-tech-details="{{ $gStatus == 'OPERACIONAL' ? '100% de la infraestructura respondiendo.' : ($gStatus == 'DEGRADADO' ? 'Plataforma disponible con incidentes parciales.' : 'Afectación severa de infraestructura.') }}"
                          @else
                          data-tech-auth-required="true"
                          @endif>
                        <span class="w-1.5 h-1.5 rounded-full {{ $gStatus == 'OPERACIONAL' ? 'bg-emerald-400 pulse-dot' : ($gStatus == 'DEGRADADO' ? 'bg-amber-400 pulse-dot' : 'bg-red-400 pulse-dot') }}"></span>
                        {{ $gStatus }}
                    </span>
                </div>

                <!-- TARJETAS DE DISPONIBILIDAD (3 COLUMNAS) -->
                <div class="grid grid-cols-3 gap-1.5">
                    @php
                        $totServ = $activeServices->count() + $downServices->count();
                        $servPct = $totServ > 0 ? round(($activeServices->count() / $totServ) * 100, 1) : 100;
                        
                        $totSites = $activeSites->count() + $downSites->count();
                        $sitesPct = $totSites > 0 ? round(($activeSites->count() / $totSites) * 100, 1) : 100;
                        
                        $totProxies = $latestSnapshot ? $latestSnapshot->proxies_total : \App\Models\MonitoredProxy::where('is_active', 1)->count();
                        $onlProxies = $latestSnapshot ? $latestSnapshot->proxies_online : $totProxies;
                    @endphp
                    <!-- SERVICIOS -->
                    <div class="bg-obsidian-panel/80 border border-obsidian-border/80 rounded-lg p-1.5 text-center cursor-pointer hover:border-obsidian-cyan/40 transition"
                         data-tech-title="DISPONIBILIDAD DE SERVICIOS"
                         data-tech-type="MÉTRICA"
                         @if(Auth::check())
                         data-tech-ip="18 Hosts Registrados"
                         data-tech-protocol="HTTP / LDAP / SMTP / DNS"
                         data-tech-latency="{{ $servPct }}% Up"
                         data-tech-status="{{ $activeServices->count() }} de {{ $totServ }} Operativos"
                         data-tech-details="{{ $downServices->count() }} servicios caídos detectados en el último ciclo de escaneo."
                         @else
                         data-tech-auth-required="true"
                         @endif>
                        <span class="text-[8.5px] uppercase font-mono text-obsidian-muted block truncate">Servicios</span>
                        <div id="metric-services-count" class="mt-0.5 flex items-baseline justify-center gap-1 font-mono">
                            <span class="text-xs font-bold text-white">{{ $activeServices->count() }}</span>
                            <span class="text-[9px] text-obsidian-muted">/ {{ $totServ }}</span>
                        </div>
                        <span id="metric-services-pct" class="text-[8.5px] font-mono font-bold {{ $servPct == 100 ? 'text-emerald-400' : ($servPct >= 70 ? 'text-amber-400' : 'text-red-400') }}">
                            {{ $servPct }}%
                        </span>
                    </div>

                    <!-- SEDES -->
                    <div class="bg-obsidian-panel/80 border border-obsidian-border/80 rounded-lg p-1.5 text-center cursor-pointer hover:border-obsidian-cyan/40 transition"
                         data-tech-title="DISPONIBILIDAD DE SEDES REGIONALES"
                         data-tech-type="MÉTRICA"
                         @if(Auth::check())
                         data-tech-ip="5 Nodos Regionales"
                         data-tech-protocol="ICMP Echo / Enlaces WAN"
                         data-tech-latency="{{ $sitesPct }}% Up"
                         data-tech-status="{{ $activeSites->count() }} de {{ $totSites }} Conectadas"
                         data-tech-details="{{ $downSites->count() }} sedes sin conexión actualmente."
                         @else
                         data-tech-auth-required="true"
                         @endif>
                        <span class="text-[8.5px] uppercase font-mono text-obsidian-muted block truncate">Sedes</span>
                        <div id="metric-sites-count" class="mt-0.5 flex items-baseline justify-center gap-1 font-mono">
                            <span class="text-xs font-bold text-white">{{ $activeSites->count() }}</span>
                            <span class="text-[9px] text-obsidian-muted">/ {{ $totSites }}</span>
                        </div>
                        <span id="metric-sites-pct" class="text-[8.5px] font-mono font-bold {{ $sitesPct == 100 ? 'text-emerald-400' : ($sitesPct >= 70 ? 'text-amber-400' : 'text-red-400') }}">
                            {{ $sitesPct }}%
                        </span>
                    </div>

                    <!-- PROXIES -->
                    <div class="bg-obsidian-panel/80 border border-obsidian-border/80 rounded-lg p-1.5 text-center cursor-pointer hover:border-obsidian-cyan/40 transition"
                         data-tech-title="DISPONIBILIDAD DE PROXIES"
                         data-tech-type="MÉTRICA"
                         @if(Auth::check())
                         data-tech-ip="Salidas PfSense + Directa"
                         data-tech-protocol="HTTP CONNECT (8080)"
                         data-tech-latency="100% Up"
                         data-tech-status="{{ $onlProxies }} de {{ $totProxies }} Operativos"
                         data-tech-details="Todos los túneles proxy corporativos autentican con éxito."
                         @else
                         data-tech-auth-required="true"
                         @endif>
                        <span class="text-[8.5px] uppercase font-mono text-obsidian-muted block truncate">Proxies</span>
                        <div id="metric-proxies-count" class="mt-0.5 flex items-baseline justify-center gap-1 font-mono">
                            <span class="text-xs font-bold text-white">{{ $onlProxies }}</span>
                            <span class="text-[9px] text-obsidian-muted">/ {{ $totProxies }}</span>
                        </div>
                        <span class="text-[8.5px] font-mono font-bold text-emerald-400">100%</span>
                    </div>
                </div>

                <!-- LEYENDA EXPLICATIVA COMPACTA -->
                <div class="pt-1.5 border-t border-obsidian-border/40 flex items-center justify-between text-[8px] font-mono text-obsidian-muted">
                    <div class="flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        <span>100% Operacional</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                        <span>Degradado (&gt;0 fallas)</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span>
                        <span>Crítico (&gt;30% caído)</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- COLUMNA 3: INCIDENTES Y CAÍDAS (BLOQUE SUPERIOR E INFERIOR) (34% ANCHO)   -->
        <!-- ========================================================================= -->
        <section class="flex flex-col w-full lg:flex-1 h-full gap-3 overflow-hidden">
            
            <!-- BLOQUE SUPERIOR: SERVICIOS CAÍDOS (50% ALTURA) -->
            <div class="glass-panel rounded-xl flex-1 flex flex-col overflow-hidden border border-red-500/30 bg-red-950/10">
                <!-- CABECERA -->
                <div class="p-3 border-b border-red-500/30 flex items-center justify-between bg-red-950/40">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-red-400 text-lg">cancel</span>
                        <h2 class="text-xs font-bold text-white uppercase font-mono tracking-wider">Servicios Caídos</h2>
                    </div>
                    <span id="badge-count-down-services" class="px-2 py-0.5 rounded text-[10px] font-mono font-bold {{ $downServices->count() > 0 ? 'bg-red-950 text-red-400 border border-red-500/50 glow-red' : 'bg-emerald-950 text-emerald-400 border border-emerald-500/40' }}">
                        {{ $downServices->count() }} Inactivos
                    </span>
                </div>

                <!-- LISTA DE SERVICIOS CAÍDOS (ULTRA-COMPACTA) -->
                <div class="flex-1 overflow-y-auto p-2 space-y-1 custom-scroll" id="down-services-container">
                    @forelse($downServices as $s)
                        @php
                            $targetHost = $s->host_ip ?: ($s->web_url ?: '127.0.0.1');
                        @endphp
                        <div class="py-1.5 px-2.5 rounded-lg bg-red-950/30 hover:bg-red-950/50 border border-red-500/30 transition cursor-pointer flex items-center justify-between group item-searchable select-none"
                             data-search="{{ strtolower($s->name . ' ' . $s->type) }}"
                             data-tech-title="{{ $s->name }}"
                             data-tech-type="{{ $s->type }}"
                             @if(Auth::check())
                             data-tech-ip="{{ $targetHost }}"
                             data-tech-port="{{ $s->port ?: ($s->type == 'WEB' ? '80/443' : ($s->type == 'DNS' ? '53' : ($s->type == 'SMTP' ? '25' : ($s->type == 'LDAP' ? '389' : 'ICMP')))) }}"
                             data-tech-protocol="{{ $s->type == 'WEB' ? 'HTTP/HTTPS GET' : ($s->type == 'DNS' ? 'DNS Query' : ($s->type == 'SMTP' ? 'SMTP Mail' : ($s->type == 'LDAP' ? 'LDAP Bind' : 'ICMP Ping'))) }}"
                             data-tech-latency="Timeout / Sin respuesta"
                             data-tech-status="APAGADO (Host / Puerto inalcanzable)"
                             data-tech-details="{{ $s->web_url ? 'Endpoint: ' . $s->web_url : 'Sin respuesta de transporte de red.' }}"
                             data-tech-id="{{ $s->id }}"
                             data-tech-kind="service"
                             @else
                             data-tech-auth-required="true"
                             title="DEBE INICIAR SESIÓN PARA VER LOS DATOS"
                             @endif>
                            
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-1.5 h-1.5 rounded-full shrink-0 bg-red-500 glow-red"></div>
                                <span class="text-[11px] font-semibold text-red-200 group-hover:text-red-100 transition-colors truncate">
                                    {{ $s->name }}
                                </span>
                            </div>

                            <div class="flex items-center gap-1.5 shrink-0">
                                @if(Auth::check())
                                <span class="text-[9px] font-mono text-red-400/80 font-medium">Timeout</span>
                                @else
                                <span class="text-amber-400/90 flex items-center justify-center p-0.5" title="DEBE INICIAR SESIÓN PARA VER LOS DATOS">
                                    <span class="material-symbols-outlined text-[13px]">lock</span>
                                </span>
                                @endif
                                <span class="px-1 py-0.5 rounded text-[8.5px] font-mono font-bold uppercase bg-red-950/80 border border-red-500/40 text-red-300">
                                    {{ $s->type }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="h-full flex flex-col items-center justify-center p-4 text-center">
                            <span class="material-symbols-outlined text-emerald-400 text-2xl mb-1">verified</span>
                            <p class="text-xs font-mono text-emerald-400 font-bold">Todos los servicios operando normalmente</p>
                            <p class="text-[10px] font-mono text-obsidian-muted">Sin incidentes de aplicativo registrados</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- BLOQUE INFERIOR: SEDES SIN CONEXIÓN (50% ALTURA) -->
            <div class="glass-panel rounded-xl flex-1 flex flex-col overflow-hidden border border-red-500/30 bg-red-950/10">
                <!-- CABECERA -->
                <div class="p-3 border-b border-red-500/30 flex items-center justify-between bg-red-950/40">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-amber-400 text-lg">signal_disconnected</span>
                        <h2 class="text-xs font-bold text-white uppercase font-mono tracking-wider">Sedes sin Conexión</h2>
                    </div>
                    <span id="badge-count-down-sites" class="px-2 py-0.5 rounded text-[10px] font-mono font-bold {{ $downSites->count() > 0 ? 'bg-red-950 text-red-400 border border-red-500/50 glow-red' : 'bg-emerald-950 text-emerald-400 border border-emerald-500/40' }}">
                        {{ $downSites->count() }} Desconectadas
                    </span>
                </div>

                <!-- LISTA DE SEDES SIN CONEXIÓN (ULTRA-COMPACTA) -->
                <div class="flex-1 overflow-y-auto p-2 space-y-1 custom-scroll" id="down-sites-container">
                    @forelse($downSites as $site)
                        @php
                            $cleanAddress = ($site->address && !str_contains(strtoupper($site->address), 'NO CONFIGURADO')) ? $site->address : '';
                            $cleanPhone = ($site->phone_1 && !str_contains(strtoupper($site->phone_1), 'NO CONFIGURADO')) ? $site->phone_1 : '';
                        @endphp
                        <div class="py-1.5 px-2.5 rounded-lg bg-red-950/30 hover:bg-red-950/50 border border-red-500/30 transition cursor-pointer flex items-center justify-between group item-searchable select-none"
                             data-search="{{ strtolower($site->name . ' ' . $cleanAddress) }}"
                             data-tech-title="{{ $site->name }}"
                             data-tech-type="SEDE REGIONAL"
                             @if(Auth::check())
                             data-tech-ip="{{ $site->ip ?: '0.0.0.0' }}"
                             data-tech-port="Gateway PING / ICMP"
                             data-tech-protocol="Enlace de Transporte WAN"
                             data-tech-latency="100% Packet Loss"
                             data-tech-status="ENLACE WAN CAÍDO"
                             data-tech-details="{{ $cleanAddress ? 'Ubicación: ' . $cleanAddress : 'Sede Regional Corporativa' }}{{ $cleanPhone ? ' • Contacto: ' . $cleanPhone : '' }}"
                             data-tech-id="{{ $site->id }}"
                             data-tech-kind="site"
                             @else
                             data-tech-auth-required="true"
                             title="DEBE INICIAR SESIÓN PARA VER LOS DATOS"
                             @endif>
                            
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-1.5 h-1.5 rounded-full shrink-0 bg-red-500 glow-red"></div>
                                <span class="text-[11px] font-semibold text-red-200 group-hover:text-red-100 transition-colors truncate">
                                    {{ $site->name }}
                                </span>
                            </div>

                            <div class="flex items-center gap-1.5 shrink-0">
                                @if(!Auth::check())
                                <span class="text-amber-400/90 flex items-center justify-center p-0.5" title="DEBE INICIAR SESIÓN PARA VER LOS DATOS">
                                    <span class="material-symbols-outlined text-[13px]">lock</span>
                                </span>
                                @endif
                                <span class="px-1 py-0.5 rounded text-[8.5px] font-mono font-bold uppercase bg-red-950/80 border border-red-500/40 text-red-300">
                                    OFFLINE
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="h-full flex flex-col items-center justify-center p-4 text-center">
                            <span class="material-symbols-outlined text-emerald-400 text-2xl mb-1">wifi_tethering</span>
                            <p class="text-xs font-mono text-emerald-400 font-bold">Todos los enlaces regionales conectados</p>
                            <p class="text-[10px] font-mono text-obsidian-muted">Comunicación WAN 100% operativa</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

</div>

@if($isDashboard ?? false)
<!-- ========================================================================= -->
<!-- NIVEL INFERIOR: 3 COLUMNAS DE AUDITORÍA, ALERTAS Y GITOPS                 -->
<!-- ========================================================================= -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 mt-3 sm:mt-4">

    <!-- ========================================================================= -->
    <!-- COLUMNA 1: CERTIFICADOS SSL/TLS                                           -->
    <!-- ========================================================================= -->
    @php
        $expiringList = isset($expiringSslCerts) ? $expiringSslCerts : collect();
        $hasExpiring = $expiringList->count() > 0;
    @endphp
    <div class="glass-panel rounded-xl p-3 border {{ $hasExpiring ? 'border-amber-500/40 bg-amber-950/10' : 'border-obsidian-border/80 bg-[#07172b]/95' }} flex flex-col justify-between shadow-md min-h-[170px]">
        <div>
            <div class="flex items-center justify-between pb-1.5 border-b border-obsidian-border/50">
                <div class="flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-base {{ $hasExpiring ? 'text-amber-400 animate-pulse' : 'text-cyan-400' }}">lock_clock</span>
                    <h3 class="text-[11px] font-bold text-white uppercase font-mono tracking-wider">Certificados SSL/TLS</h3>
                </div>
                @if(Auth::check())
                    <a href="{{ route('admin.ssl.index') }}" class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold {{ $hasExpiring ? 'bg-amber-950/90 text-amber-300 border border-amber-500/40 hover:bg-amber-800' : 'bg-emerald-950/80 text-emerald-400 border border-emerald-500/30 hover:bg-emerald-900' }} transition" title="{{ $hasExpiring ? $expiringList->count() . ' certificado(s) por vencer' : 'Ver consola SSL' }}">
                        {{ $hasExpiring ? $expiringList->count() . ' por Vencer →' : 'Vigentes (>30d) →' }}
                    </a>
                @else
                    <span class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold {{ $hasExpiring ? 'bg-amber-950/90 text-amber-300 border border-amber-500/40' : 'bg-emerald-950/80 text-emerald-400 border border-emerald-500/30' }}">
                        {{ $hasExpiring ? $expiringList->count() . ' por Vencer' : 'Vigentes (>30d)' }}
                    </span>
                @endif
            </div>

            <div class="mt-2">
                @if($hasExpiring)
                    <div class="space-y-1 max-h-24 overflow-y-auto custom-scroll">
                        @foreach($expiringList->take(3) as $c)
                            <div class="flex items-center justify-between py-1 px-1.5 rounded bg-obsidian-panel/60 border border-obsidian-border/50 text-[10px] font-mono" title="Dominio: {{ $c->domain }} • Emisor: {{ $c->issuer_cn ?: 'N/A' }} • Vence: {{ $c->valid_to ? $c->valid_to->timezone('America/Caracas')->format('d/m/Y') : 'N/A' }}">
                                <span class="truncate max-w-[150px] text-gray-200">{{ $c->domain }}</span>
                                <span class="font-bold {{ $c->days_remaining < 0 ? 'text-red-400' : ($c->days_remaining <= 7 ? 'text-rose-400' : 'text-amber-400') }}">
                                    {{ $c->days_remaining < 0 ? 'Expirado' : $c->days_remaining . 'd' }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-[10px] font-mono text-obsidian-muted flex items-center gap-1.5 py-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        Cadenas criptográficas e integridad HTTPS en regla.
                    </p>
                @endif
            </div>
        </div>

        <div class="pt-1.5 border-t border-obsidian-border/40 flex items-center justify-between text-[8px] font-mono text-obsidian-muted">
            <span>Auditoría X.509</span>
            <span class="text-cyan-400/80">TLS 1.2 / 1.3</span>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- COLUMNA 2: ALERTAS ACTIVAS ⛈️ TORMENTA                                   -->
    <!-- ========================================================================= -->
    @php
        $alertsCount = isset($activeAlertsList) ? $activeAlertsList->count() : 0;
        $hasActiveAlerts = $alertsCount > 0;
        $hasStorm = isset($stormSuppressedCount) && $stormSuppressedCount > 0;
    @endphp
    <div class="glass-panel rounded-xl p-3 border {{ $hasActiveAlerts ? 'border-amber-500/40 bg-amber-950/10' : 'border-obsidian-border/80 bg-[#07172b]/95' }} flex flex-col justify-between shadow-md min-h-[170px]">
        <div>
            <div class="flex items-center justify-between pb-1.5 border-b border-obsidian-border/50">
                <div class="flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-base {{ $hasActiveAlerts ? 'text-amber-400 animate-pulse' : 'text-cyan-400' }}">notifications_active</span>
                    <h3 class="text-[11px] font-bold text-white uppercase font-mono tracking-wider">Alertas Activas</h3>
                    @if($hasStorm)
                        <span class="px-1 py-0.2 rounded bg-purple-950 border border-purple-500/40 text-purple-300 font-mono text-[8.5px]" title="Control de Tormentas Activo">⛈️ Tormenta</span>
                    @endif
                </div>
                <div>
                    @if($hasActiveAlerts)
                        <a href="{{ route('admin.alerts.index', ['tab' => 'active']) }}" class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold bg-amber-950/90 text-amber-300 border border-amber-500/40 hover:bg-amber-800 transition" title="Ver consola completa de incidentes">
                            {{ $alertsCount }} Activas →
                        </a>
                    @else
                        <a href="{{ route('admin.alerts.index') }}" class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold bg-emerald-950/80 text-emerald-400 border border-emerald-500/30 hover:bg-emerald-900 transition" title="Sin alarmas activas">
                            0 Incidentes →
                        </a>
                    @endif
                </div>
            </div>

            <div class="mt-2">
                @if($hasActiveAlerts)
                    <div class="space-y-1 max-h-24 overflow-y-auto custom-scroll">
                        @foreach($activeAlertsList->take(3) as $al)
                            @php
                                $alColors = [
                                    'emergency' => 'text-fuchsia-400 border-fuchsia-500/30 bg-fuchsia-950/40',
                                    'critical' => 'text-red-400 border-red-500/30 bg-red-950/40',
                                    'warning' => 'text-amber-400 border-amber-500/30 bg-amber-950/40',
                                    'info' => 'text-sky-400 border-sky-500/30 bg-sky-950/40',
                                ];
                                $alCol = $alColors[$al->severity] ?? 'text-slate-300 border-slate-700 bg-slate-900/40';
                            @endphp
                            <div class="flex items-center justify-between py-1 px-1.5 rounded border text-[10px] font-mono {{ $alCol }}" title="{{ $al->message }}">
                                <div class="flex items-center gap-1.5 min-w-0 flex-1 mr-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $al->severity === 'critical' || $al->severity === 'emergency' ? 'bg-red-500 animate-ping' : 'bg-amber-400' }}"></span>
                                    <span class="truncate font-bold text-white text-[10px]">{{ $al->entity_name }}</span>
                                    <span class="inline-flex items-center gap-0.5 px-1 py-0.2 rounded text-[7.5px] font-mono font-semibold shrink-0 {{ $al->condition_badge_class }}" title="{{ $al->condition_label }}">
                                        <span class="material-symbols-outlined text-[9px] leading-none">{{ $al->condition_icon }}</span>
                                        <span>{{ $al->condition_short_label }}</span>
                                    </span>
                                </div>
                                <div class="flex items-center gap-1 shrink-0">
                                    <span class="text-[9px] font-bold">{{ $al->duration_formatted }}</span>
                                    @if(Auth::check() && $al->status === 'firing')
                                        <form action="{{ route('admin.alerts.ack', $al->id) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="px-1 py-0.2 rounded bg-sky-900/80 hover:bg-sky-500 text-sky-200 hover:text-black transition text-[8.5px]" title="Reconocer Alerta">ACK</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-[10px] font-mono text-obsidian-muted flex items-center gap-1.5 py-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        Infraestructura operando sin alarmas activas.
                    </p>
                @endif
            </div>
        </div>

        <div class="pt-1.5 border-t border-obsidian-border/40 flex items-center justify-between text-[8px] font-mono text-obsidian-muted">
            <span>Motor de Correlación</span>
            <span class="text-amber-400/80">Anti-Flapping</span>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- COLUMNA 3: RESPALDOS & GITOPS                                             -->
    <!-- ========================================================================= -->
    @php
        $configChangesList = isset($recentConfigChanges) ? $recentConfigChanges : collect();
        $hasRecentChanges = $configChangesList->count() > 0;
        $totBackups = isset($totalConfigBackupsCount) ? $totalConfigBackupsCount : 0;
    @endphp
    <div class="glass-panel rounded-xl p-3 border border-obsidian-border/80 bg-[#07172b]/95 flex flex-col justify-between shadow-md min-h-[170px]">
        <div>
            <div class="flex items-center justify-between pb-1.5 border-b border-obsidian-border/50">
                <div class="flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-base text-cyan-400">settings_backup_restore</span>
                    <h3 class="text-[11px] font-bold text-white uppercase font-mono tracking-wider">Respaldos &amp; GitOps</h3>
                </div>
                <div>
                    @if(Auth::check())
                        <a href="{{ route('admin.configs.index') }}" class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold bg-cyan-950/80 text-cyan-300 border border-cyan-500/40 hover:bg-cyan-800 transition" title="Ver consola de respaldos y diffs">
                            {{ $totBackups }} Versiones →
                        </a>
                    @else
                        <span class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold bg-cyan-950/80 text-cyan-300 border border-cyan-500/30" title="Versiones de configuración resguardadas">
                            {{ $totBackups }} Versiones
                        </span>
                    @endif
                </div>
            </div>

            <div class="mt-2">
                @if($hasRecentChanges)
                    <div class="space-y-1 max-h-24 overflow-y-auto custom-scroll">
                        @foreach($configChangesList->take(3) as $cchg)
                            <div class="flex items-center justify-between py-1 px-1.5 rounded bg-obsidian-panel/60 border border-obsidian-border/50 text-[10px] font-mono" title="{{ $cchg->diff_summary }}">
                                <div class="flex items-center gap-1 truncate max-w-[150px]">
                                    <span class="material-symbols-outlined text-xs {{ $cchg->change_type === 'modified' ? 'text-amber-400' : 'text-emerald-400' }}">
                                        {{ $cchg->change_type === 'modified' ? 'difference' : 'flag' }}
                                    </span>
                                    <span class="truncate font-bold text-white">{{ $cchg->configuration?->resolved_name ?? 'Dispositivo' }}</span>
                                </div>
                                <div class="flex items-center gap-1 shrink-0">
                                    @if($cchg->change_type === 'modified')
                                        <span class="text-[9px] text-emerald-400 font-bold">+{{ $cchg->lines_added }}</span>
                                        <span class="text-[9px] text-red-400 font-bold">-{{ $cchg->lines_removed }}</span>
                                    @else
                                        <span class="text-[9px] text-cyan-300 font-mono">Base</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-[10px] font-mono text-obsidian-muted flex items-center gap-1.5 py-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        Configuraciones de switches y routers sincronizadas.
                    </p>
                @endif
            </div>
        </div>

        <div class="pt-1.5 border-t border-obsidian-border/40 flex items-center justify-between text-[8px] font-mono text-obsidian-muted">
            <span>Auditoría GitOps</span>
            <span class="text-cyan-400/80">Diff SHA-256</span>
        </div>
    </div>

</div>
@endif

<!-- ========================================================================= -->
<!-- ========================================================================= -->
<!-- VENTANA EMERGENTE HUD FLOTANTE (TOOLTIP DE DATOS TÉCNICOS & HISTÓRICO)    -->
<!-- ========================================================================= -->
<div id="tech-tooltip" class="glass-panel rounded-2xl p-4 border border-amber-500/50 shadow-2xl w-80 text-xs font-mono text-white bg-[#051424]/98 backdrop-blur-2xl">

    @if(Auth::check())
    <!-- BLOQUE: DATOS TÉCNICOS COMPLETOS (SOLO USUARIOS AUTENTICADOS) -->
    <div id="tt-authenticated-block">
        <!-- CABECERA (CONSERVADA INTACTA) -->
        <div class="flex items-center justify-between border-b border-obsidian-border pb-1.5 mb-1.5">
            <div class="flex items-center gap-1.5">
                <span class="material-symbols-outlined text-obsidian-cyan text-sm" id="tt-icon">terminal</span>
                <span class="font-bold text-white truncate max-w-[170px]" id="tt-title">DATOS TÉCNICOS</span>
            </div>
            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase bg-obsidian-cyan/20 text-obsidian-cyan border border-obsidian-cyan/30" id="tt-type">
                PROTOCOL
            </span>
        </div>

        <!-- DATOS TÉCNICOS (TODOS CONSERVADOS INTACTOS) -->
        <div class="space-y-1 text-[11px]">
            <div class="flex justify-between py-0.5 border-b border-obsidian-border/40">
                <span class="text-obsidian-muted">Destino / IP:</span>
                <span class="text-obsidian-cyan font-semibold truncate max-w-[160px]" id="tt-ip">10.0.0.1</span>
            </div>
            <div class="flex justify-between py-0.5 border-b border-obsidian-border/40">
                <span class="text-obsidian-muted">Puerto / Socket:</span>
                <span class="text-white truncate max-w-[160px]" id="tt-port">80 / 443</span>
            </div>
            <div class="flex justify-between py-0.5 border-b border-obsidian-border/40">
                <span class="text-obsidian-muted">Protocolo / Check:</span>
                <span class="text-obsidian-purple font-medium truncate max-w-[160px]" id="tt-protocol">HTTP GET</span>
            </div>
            <div class="flex justify-between py-0.5 border-b border-obsidian-border/40">
                <span class="text-obsidian-muted">Latencia Actual:</span>
                <span class="text-emerald-400 font-bold" id="tt-latency">12.4 ms</span>
            </div>
            <div class="flex justify-between py-0.5">
                <span class="text-obsidian-muted">Estado Reportado:</span>
                <span class="font-bold text-emerald-400 truncate max-w-[160px]" id="tt-status">OPERATIVO</span>
            </div>
        </div>

        <!-- CUADRO CON GRÁFICO HISTÓRICO TEMPORAL DE LATENCIA & DISPONIBILIDAD (24 HORAS) -->
        <div id="tt-chart-container" class="mt-2 pt-1.5 border-t border-obsidian-border/60">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[9px] uppercase font-bold text-obsidian-cyan tracking-wider flex items-center gap-1">
                    <span class="material-symbols-outlined text-[12px]">ssid_chart</span>
                    Histórico (Últimas 24 Horas)
                </span>
                <span id="tt-uptime-badge" class="px-1.5 py-0.2 rounded text-[8.5px] font-bold font-mono bg-emerald-950/90 text-emerald-400 border border-emerald-500/40">
                    100% Up
                </span>
            </div>

            <div class="h-16 w-full relative bg-[#020b14]/70 rounded border border-obsidian-border/50 p-1 flex items-center justify-center">
                <canvas id="tt-canvas" class="w-full h-full"></canvas>
                <span id="tt-no-chart" class="text-[9px] text-obsidian-muted hidden">Sin histórico suficiente</span>
            </div>

            <div class="flex justify-between text-[9px] font-mono text-obsidian-muted mt-1 px-0.5">
                <span>Mín: <strong id="tt-stat-min" class="text-white">--</strong></span>
                <span>Prom: <strong id="tt-stat-avg" class="text-emerald-400">--</strong></span>
                <span>Máx: <strong id="tt-stat-max" class="text-amber-400">--</strong></span>
            </div>
        </div>

        <!-- DETALLES TÉCNICOS ADICIONALES (CONSERVADOS INTACTOS) -->
        <div class="mt-1.5 pt-1.5 border-t border-obsidian-border/60 text-[10px] text-obsidian-muted leading-tight whitespace-pre-line break-words max-h-52 overflow-y-auto scrollbar-thin" id="tt-details">
            Verificación asíncrona de socket en tiempo real.
        </div>
    </div>
    @else
    <!-- BLOQUE: ALERTA DE AUTENTICACIÓN REQUERIDA (USUARIO NO REGISTRADO / GUEST) -->
    <div id="tt-auth-required-block" class="flex flex-col items-center text-center space-y-3">
        <div class="w-12 h-12 rounded-2xl bg-amber-950/90 border border-amber-500/50 text-amber-400 flex items-center justify-center shadow-lg shadow-amber-950">
            <span class="material-symbols-outlined text-2xl">lock</span>
        </div>
        <div class="space-y-1.5 w-full">
            <div class="text-[10px] uppercase font-bold text-obsidian-muted font-mono tracking-wider truncate max-w-[260px] mx-auto" id="tt-auth-target-name">
                Servicio o Sede
            </div>
            <h3 class="text-xs font-black text-amber-400 uppercase tracking-wide font-mono bg-amber-950/60 border border-amber-500/40 py-2 px-2.5 rounded-xl shadow-inner">
                DEBE INICIAR SESIÓN PARA VER LOS DATOS
            </h3>
            <p class="text-[11px] font-sans text-amber-200/90 leading-relaxed font-normal pt-1">
                Debe iniciar sesión para ver los datos del servicio o la sede en su defecto.
            </p>
        </div>
        <a href="{{ route('login') }}" class="w-full py-2.5 px-4 rounded-xl bg-obsidian-cyan hover:bg-cyan-300 text-black font-black text-xs font-mono flex items-center justify-center gap-2 transition shadow-lg shadow-cyan-500/30">
            <span class="material-symbols-outlined text-base">login</span>
            <span>INICIAR SESIÓN</span>
        </a>
    </div>
    @endif

</div>

<!-- ========================================================================= -->
<!-- MODAL AVISO DE INICIO DE SESIÓN PARA ASISTENTE IA                         -->
<!-- ========================================================================= -->
<div id="modal-ai-login-prompt" class="fixed inset-0 z-[100000] bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="glass-panel max-w-md w-full rounded-2xl p-6 border border-cyan-500/40 shadow-2xl space-y-4 font-mono">
        <div class="flex items-center justify-between border-b border-obsidian-border pb-3">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-cyan-950/80 border border-cyan-500/40 text-obsidian-cyan flex items-center justify-center shadow-lg shadow-cyan-950">
                    <span class="material-symbols-outlined text-xl">smart_toy</span>
                </div>
                <div>
                    <h3 class="text-xs font-bold text-white uppercase tracking-wider">Asistente IA Corporativo</h3>
                    <p class="text-[10px] text-obsidian-cyan">Monitor Valle Seco</p>
                </div>
            </div>
            <button onclick="closeAiLoginPromptModal()" class="text-obsidian-muted hover:text-white text-2xl leading-none">&times;</button>
        </div>

        <div class="space-y-3 text-xs">
            <div class="p-3.5 rounded-xl bg-cyan-950/30 border border-cyan-500/30 text-cyan-300 text-[11px] space-y-1.5">
                <p class="font-bold flex items-center gap-1.5 text-white uppercase tracking-wide text-xs">
                    <span class="material-symbols-outlined text-sm text-cyan-400">lock</span>
                    <span>DEBE INICIAR SESIÓN PARA VER LOS DATOS</span>
                </p>
                <p class="leading-relaxed font-sans text-cyan-200">
                    Debe iniciar sesión para ver los datos del servicio o la sede en su defecto.
                </p>
                <p class="leading-relaxed text-gray-300 text-[10.5px]">
                    El acceso al asistente virtual inteligente <strong>Monitor Valle Seco</strong> está restringido exclusivamente a usuarios autenticados de la Sede Valle Seco.
                </p>
            </div>
            <p class="text-[10px] text-obsidian-muted leading-relaxed">
                🔒 Por favor, inicia sesión con tus credenciales corporativas LDAP o con tu cuenta asignada por el Administrador para interactuar con el asistente virtual.
            </p>
        </div>

        <div class="pt-3 border-t border-obsidian-border flex items-center justify-end gap-2">
            <button type="button" onclick="closeAiLoginPromptModal()" class="px-4 py-2 rounded-lg bg-obsidian-panel text-obsidian-muted hover:text-white text-xs">
                Cancelar
            </button>
            <a href="{{ route('login') }}" class="px-4 py-2 rounded-lg bg-obsidian-cyan text-black font-bold text-xs flex items-center gap-1.5 hover:bg-cyan-300 transition shadow-lg shadow-cyan-500/20">
                <span class="material-symbols-outlined text-sm">login</span>
                Iniciar Sesión
            </a>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- VENTANA EMERGENTE TIPO CHAT: ASISTENTE IA MONITOR VALLE SECO              -->
<!-- ========================================================================= -->
<div id="modal-ai-chat" class="fixed inset-0 z-[100000] bg-black/75 backdrop-blur-sm hidden items-center justify-center p-2 sm:p-4">
    <div class="glass-panel w-full max-w-2xl h-[90vh] max-h-[700px] rounded-2xl border border-obsidian-cyan/50 flex flex-col overflow-hidden shadow-2xl bg-[#040d1a]/95">
        
        <!-- CABECERA DEL CHAT -->
        <div class="p-3.5 px-5 border-b border-obsidian-border/80 flex items-center justify-between bg-[#061527]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-obsidian-cyan/15 border border-obsidian-cyan/40 flex items-center justify-center text-obsidian-cyan shadow-md shadow-cyan-950">
                    <span class="material-symbols-outlined text-2xl">smart_toy</span>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-white font-mono tracking-wide">Monitor Valle Seco</h3>
                        <span class="px-2 py-0.5 rounded text-[9px] font-mono bg-cyan-950 text-cyan-400 border border-cyan-500/30">IA Local Qwen 3B</span>
                    </div>
                    <p class="text-[11px] text-emerald-400 font-mono flex items-center gap-1.5 mt-0.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        En Línea • Asistente de Infraestructura & Soporte
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-1">
                <button onclick="clearAiChatConversation()" title="Limpiar historial" class="p-2 rounded-lg text-obsidian-muted hover:text-red-400 hover:bg-white/5 transition flex items-center justify-center">
                    <span class="material-symbols-outlined text-lg">delete_sweep</span>
                </button>
                <button onclick="closeAiChatModal()" title="Cerrar ventana" class="p-2 rounded-lg text-obsidian-muted hover:text-white hover:bg-white/5 transition flex items-center justify-center">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>
        </div>

        <!-- CUERPO DE MENSAJES CON SCROLL -->
        <div id="ai-chat-messages-container" class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-4 font-sans text-xs scrollbar-thin">
            <!-- MENSAJE DE BIENVENIDA INICIAL -->
            <div class="flex gap-3 items-start">
                <div class="w-8 h-8 rounded-xl bg-cyan-950/80 border border-cyan-500/30 flex items-center justify-center text-cyan-400 shrink-0 mt-0.5">
                    <span class="material-symbols-outlined text-base">smart_toy</span>
                </div>
                <div class="bg-[#081b33] border border-obsidian-border/80 rounded-2xl rounded-tl-sm p-4 max-w-[88%] text-gray-200 leading-relaxed space-y-2">
                    <p class="font-bold text-obsidian-cyan text-xs">
                        ¡Hola @auth {{ Auth::user()->name }} @else Colega @endauth!
                    </p>
                    <p>
                        Soy <strong>Monitor Valle Seco</strong>, el asistente virtual corporativo de la <strong>Sede Valle Seco</strong>. Estoy sincronizado con los servidores, servicios y políticas técnicas de nuestra infraestructura.
                    </p>
                    <p class="text-obsidian-muted text-[11px]">
                        Puedes consultarme sobre diagnóstico de red, administración de servidores Linux, DNS BIND9, Proxy Squid, Zimbra, comandos de terminal o soporte técnico general.
                    </p>
                    <div class="pt-2 flex flex-wrap gap-1.5 font-mono text-[10px]">
                        <button type="button" onclick="sendQuickPrompt('¿Cuáles son los comandos clave para diagnosticar DNS en Linux?')" class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-cyan-950/60 border border-obsidian-border hover:border-cyan-500/40 text-cyan-300 transition">
                            🔍 Diagnóstico DNS
                        </button>
                        <button type="button" onclick="sendQuickPrompt('Explícame cómo verificar el estado de los servicios en systemd')" class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-cyan-950/60 border border-obsidian-border hover:border-cyan-500/40 text-cyan-300 transition">
                            ⚙️ Chequeo systemd
                        </button>
                        <button type="button" onclick="sendQuickPrompt('¿Quién eres y qué puedes hacer en Sede Valle Seco?')" class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-cyan-950/60 border border-obsidian-border hover:border-cyan-500/40 text-cyan-300 transition">
                            🤖 Identidad y alcance
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- INDICADOR DE PROCESAMIENTO / PENSANDO -->
        <div id="ai-chat-typing-indicator" class="hidden px-5 py-2 text-[11px] text-obsidian-cyan font-mono flex items-center gap-2 bg-[#061527]/70 border-t border-obsidian-border/50">
            <span class="material-symbols-outlined text-sm animate-spin">progress_activity</span>
            <span>Monitor Valle Seco está pensando y redactando tu respuesta...</span>
        </div>

        <!-- BARRA INFERIOR DE ENTRADA -->
        <div class="p-3 sm:p-4 bg-[#061527] border-t border-obsidian-border/80">
            <form id="ai-chat-form" onsubmit="submitAiChat(event)" class="flex items-center gap-2">
                <div class="flex-1 relative">
                    <input type="text" id="ai-chat-input-text" placeholder="Escribe tu consulta técnica aquí..." autocomplete="off" class="w-full bg-[#040d1a] border border-obsidian-border rounded-xl pl-4 pr-3 py-2.5 text-xs text-white placeholder-obsidian-muted focus:outline-none focus:border-obsidian-cyan font-mono transition">
                </div>
                <button type="submit" id="btn-submit-ai-chat" class="px-4 py-2.5 rounded-xl bg-obsidian-cyan text-black font-bold flex items-center justify-center gap-1.5 transition hover:bg-cyan-300 hover:shadow-lg hover:shadow-cyan-500/30 shrink-0 text-xs font-mono">
                    <span>Enviar</span>
                    <span class="material-symbols-outlined text-base">send</span>
                </button>
            </form>
            <div class="flex items-center justify-between text-[10px] text-obsidian-muted mt-2 px-1 font-mono">
                <span>Usuario: <strong class="text-white">@auth {{ Auth::user()->name }} ({{ strtoupper(Auth::user()->role) }}) @else Invitado @endauth</strong></span>
                <span>Enter para enviar • Motor Local</span>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL AVISO DE AUTENTICACIÓN PARA VER DATOS DE SERVICIO O SEDE            -->
<!-- ========================================================================= -->
<div id="modal-item-auth-prompt" class="fixed inset-0 z-[100000] bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4" onclick="if(event.target === this) closeItemAuthModal()">
    <div class="glass-panel max-w-md w-full rounded-2xl p-6 border border-amber-500/40 shadow-2xl space-y-4 font-mono">
        <div class="flex items-center justify-between border-b border-obsidian-border pb-3">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-amber-950/80 border border-amber-500/40 text-amber-400 flex items-center justify-center shadow-lg shadow-amber-950">
                    <span class="material-symbols-outlined text-xl">lock</span>
                </div>
                <div>
                    <h3 class="text-xs font-black text-amber-400 uppercase tracking-wider font-mono">DEBE INICIAR SESIÓN PARA VER LOS DATOS</h3>
                    <p class="text-[10.5px] text-white/80 truncate max-w-[240px]" id="item-auth-prompt-target">Servicio / Sede de Red</p>
                </div>
            </div>
            <button type="button" onclick="closeItemAuthModal()" class="text-obsidian-muted hover:text-white text-2xl leading-none">&times;</button>
        </div>

        <div class="space-y-3 text-xs">
            <div class="p-3.5 rounded-xl bg-amber-950/30 border border-amber-500/30 text-amber-300 text-[11px] space-y-2">
                <p class="font-bold flex items-center gap-1.5 text-white">
                    <span class="material-symbols-outlined text-sm text-amber-400">shield_person</span>
                    <span>Autenticación Requerida</span>
                </p>
                <p class="leading-relaxed font-sans text-amber-200">
                    Debe iniciar sesión para ver los datos del servicio o la sede en su defecto.
                </p>
            </div>
            <p class="text-[10px] text-obsidian-muted leading-relaxed">
                🔒 El acceso a direcciones IP, puertos de servicio, métricas de latencia, consolas de diagnóstico y telemetría histórica está reservado exclusivamente para usuarios autorizados.
            </p>
        </div>

        <div class="pt-3 border-t border-obsidian-border flex items-center justify-end gap-2">
            <button type="button" onclick="closeItemAuthModal()" class="px-4 py-2 rounded-lg bg-obsidian-panel text-obsidian-muted hover:text-white text-xs">
                Cerrar
            </button>
            <a href="{{ route('login') }}" class="px-4 py-2 rounded-lg bg-obsidian-cyan text-black font-bold text-xs flex items-center gap-1.5 hover:bg-cyan-300 transition shadow-lg shadow-cyan-500/20">
                <span class="material-symbols-outlined text-sm">login</span>
                <span>Iniciar Sesión</span>
            </a>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL AVISO DE INICIO DE SESIÓN REQUERIDO (USUARIO NO LOGUEADO)           -->
<!-- ========================================================================= -->
<div id="modal-access-login-prompt" class="fixed inset-0 z-[100000] bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4" onclick="if(event.target === this) closeAccessPromptModal()">
    <div class="glass-panel max-w-md w-full rounded-2xl p-6 border border-amber-500/40 shadow-2xl space-y-4 font-mono">
        <div class="flex items-center justify-between border-b border-obsidian-border pb-3">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-amber-950/80 border border-amber-500/40 text-amber-400 flex items-center justify-center shadow-lg shadow-amber-950">
                    <span class="material-symbols-outlined text-xl">lock</span>
                </div>
                <div>
                    <h3 class="text-xs font-black text-amber-400 uppercase tracking-wider font-mono">DEBE INICIAR SESIÓN PARA VER LOS DATOS</h3>
                    <p class="text-[10px] text-obsidian-muted" id="access-prompt-service">Consola de Red y Monitoreo</p>
                </div>
            </div>
            <button onclick="closeAccessPromptModal()" class="text-obsidian-muted hover:text-white text-2xl leading-none">&times;</button>
        </div>

        <div class="space-y-3 text-xs">
            <div class="p-3.5 rounded-xl bg-amber-950/30 border border-amber-500/30 text-amber-300 text-[11px] space-y-2">
                <p class="font-bold flex items-center gap-1.5 text-white uppercase tracking-wide text-xs">
                    <span class="material-symbols-outlined text-sm text-amber-400">shield_person</span>
                    <span>DEBE INICIAR SESIÓN PARA VER LOS DATOS</span>
                </p>
                <p class="leading-relaxed font-sans text-amber-200">
                    Debe iniciar sesión para ver los datos del servicio o la sede en su defecto.
                </p>
                <p class="leading-relaxed text-[10.5px]">
                    Para acceder <span id="access-prompt-action">al servicio</span> del equipo <strong id="access-prompt-device" class="text-white"></strong> en <strong id="access-prompt-site" class="text-white"></strong>, debe iniciar sesión con una cuenta autorizada de <strong>Operador</strong> o <strong>Administrador</strong>.
                </p>
            </div>
            <p class="text-[10px] text-obsidian-muted leading-relaxed">
                🔒 El acceso a consolas remotas (VNC, Telnet) y paneles de configuración web está reservado exclusivamente al personal técnico autorizado para labores de soporte y monitoreo.
            </p>
        </div>

        <div class="pt-3 border-t border-obsidian-border flex items-center justify-end gap-2">
            <button type="button" onclick="closeAccessPromptModal()" class="px-4 py-2 rounded-lg bg-obsidian-panel text-obsidian-muted hover:text-white text-xs">
                Cancelar
            </button>
            <a href="{{ route('login') }}" class="px-4 py-2 rounded-lg bg-obsidian-cyan text-black font-bold text-xs flex items-center gap-1.5 hover:bg-cyan-300 transition shadow-lg shadow-cyan-500/20">
                <span class="material-symbols-outlined text-sm">login</span>
                Iniciar Sesión
            </a>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL LANZADOR DE SESIÓN VNC (USUARIO CON ROL ADMINISTRADOR U OPERADOR)   -->
<!-- ========================================================================= -->
<div id="modal-vnc-session" class="fixed inset-0 z-[100000] bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="glass-panel max-w-lg w-full rounded-2xl p-6 border border-cyan-500/40 shadow-2xl space-y-4 font-mono">
        <div class="flex items-center justify-between border-b border-obsidian-border pb-3">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-cyan-950/80 border border-cyan-500/40 text-cyan-300 flex items-center justify-center shadow-lg shadow-cyan-950">
                    <span class="material-symbols-outlined text-xl">desktop_windows</span>
                </div>
                <div>
                    <h3 class="text-xs font-bold text-white uppercase tracking-wider">Escritorio Remoto VNC</h3>
                    <p class="text-[10px] text-obsidian-cyan">Conexión Gráfica en Tiempo Real (RFB)</p>
                </div>
            </div>
            <button onclick="closeVncSessionModal()" class="text-obsidian-muted hover:text-white text-2xl leading-none">&times;</button>
        </div>

        <div class="space-y-3 text-xs">
            <div class="p-3.5 rounded-xl bg-cyan-950/40 border border-cyan-500/40 text-white text-[11px] space-y-1.5">
                <div class="flex justify-between">
                    <span class="text-obsidian-muted">Equipo Destino:</span>
                    <strong id="vnc-session-device" class="text-cyan-300"></strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-obsidian-muted">Sede / Ubicación:</span>
                    <span id="vnc-session-site" class="text-white"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-obsidian-muted">Dirección IP:</span>
                    <code id="vnc-session-ip" class="text-obsidian-cyan font-bold"></code>
                </div>
                <div class="flex justify-between">
                    <span class="text-obsidian-muted">Puerto de Servicio:</span>
                    <span class="text-white font-mono">5900 (VNC RFB)</span>
                </div>
            </div>

            <div class="p-3 rounded-lg bg-obsidian-panel/60 border border-obsidian-border text-[11px] text-obsidian-muted space-y-1">
                <p class="text-white font-semibold flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm text-obsidian-cyan">verified_user</span>
                    <span>Acceso Habilitado</span>
                </p>
                <p>
                    Sesión autorizada para <strong class="text-white">{{ Auth::user() ? Auth::user()->name : 'Operador' }}</strong> (Rol: <code class="text-obsidian-cyan uppercase">{{ Auth::user() ? Auth::user()->role : 'operador' }}</code>).
                </p>
            </div>
        </div>

        <div class="pt-3 border-t border-obsidian-border flex items-center justify-between">
            <a id="vnc-native-link" href="#" class="text-[11px] text-obsidian-muted hover:text-cyan-300 transition flex items-center gap-1">
                <span class="material-symbols-outlined text-sm">open_in_new</span>
                <span>Visor Local (vnc://)</span>
            </a>
            <div class="flex items-center gap-2">
                <button type="button" onclick="closeVncSessionModal()" class="px-4 py-2 rounded-lg bg-obsidian-panel text-obsidian-muted hover:text-white text-xs">
                    Cerrar
                </button>
                <button id="btn-launch-vnc-web" type="button" onclick="launchWebVnc()" class="px-4 py-2 rounded-lg bg-obsidian-cyan text-black font-bold text-xs flex items-center gap-1.5 hover:bg-cyan-300 transition shadow-lg shadow-cyan-500/20 cursor-pointer">
                    <span class="material-symbols-outlined text-sm">tv</span>
                    <span>Abrir Visor Web</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL LANZADOR DE TERMINAL TELNET (USUARIO CON ROL ADMIN U OPERADOR)      -->
<!-- ========================================================================= -->
<div id="modal-telnet-session" class="fixed inset-0 z-[100000] bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="glass-panel max-w-lg w-full rounded-2xl p-6 border border-cyan-500/40 shadow-2xl space-y-4 font-mono">
        <div class="flex items-center justify-between border-b border-obsidian-border pb-3">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-cyan-950/80 border border-cyan-500/40 text-cyan-300 flex items-center justify-center shadow-lg shadow-cyan-950">
                    <span class="material-symbols-outlined text-xl">terminal</span>
                </div>
                <div>
                    <h3 class="text-xs font-bold text-white uppercase tracking-wider">Terminal Telnet CLI</h3>
                    <p class="text-[10px] text-obsidian-cyan">Consola de Red en Tiempo Real (RFC 854)</p>
                </div>
            </div>
            <button onclick="closeTelnetSessionModal()" class="text-obsidian-muted hover:text-white text-2xl leading-none">&times;</button>
        </div>

        <div class="space-y-3 text-xs">
            <div class="p-3.5 rounded-xl bg-cyan-950/40 border border-cyan-500/40 text-white text-[11px] space-y-1.5">
                <div class="flex justify-between">
                    <span class="text-obsidian-muted">Equipo Destino:</span>
                    <strong id="telnet-session-device" class="text-cyan-300"></strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-obsidian-muted">Sede / Ubicación:</span>
                    <span id="telnet-session-site" class="text-white"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-obsidian-muted">Dirección IP:</span>
                    <code id="telnet-session-ip" class="text-obsidian-cyan font-bold"></code>
                </div>
                <div class="flex justify-between">
                    <span class="text-obsidian-muted">Puerto de Servicio:</span>
                    <span id="telnet-session-port" class="text-white font-mono">23 (Telnet RFC 854)</span>
                </div>
            </div>

            <div class="p-3 rounded-lg bg-obsidian-panel/60 border border-obsidian-border text-[11px] text-obsidian-muted space-y-1">
                <p class="text-white font-semibold flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm text-obsidian-cyan">verified_user</span>
                    <span>Acceso Habilitado</span>
                </p>
                <p>
                    Sesión autorizada para <strong class="text-white">{{ Auth::user() ? Auth::user()->name : 'Operador' }}</strong> (Rol: <code class="text-obsidian-cyan uppercase">{{ Auth::user() ? Auth::user()->role : 'operador' }}</code>).
                </p>
            </div>
        </div>

        <div class="pt-3 border-t border-obsidian-border flex items-center justify-between">
            <a id="telnet-native-link" href="#" class="text-[11px] text-obsidian-muted hover:text-cyan-300 transition flex items-center gap-1">
                <span class="material-symbols-outlined text-sm">open_in_new</span>
                <span>Cliente Local (telnet://)</span>
            </a>
            <div class="flex items-center gap-2">
                <button type="button" onclick="closeTelnetSessionModal()" class="px-4 py-2 rounded-lg bg-obsidian-panel text-obsidian-muted hover:text-white text-xs">
                    Cerrar
                </button>
                <button id="btn-launch-telnet-web" type="button" onclick="launchWebTelnet()" class="px-4 py-2 rounded-lg bg-obsidian-cyan text-black font-bold text-xs flex items-center gap-1.5 hover:bg-cyan-300 transition shadow-lg shadow-cyan-500/20 cursor-pointer">
                    <span class="material-symbols-outlined text-sm">terminal</span>
                    <span>Abrir Terminal Web</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL LANZADOR DE PANEL WEB (USUARIO CON ROL ADMIN U OPERADOR)           -->
<!-- ========================================================================= -->
<div id="modal-web-session" class="fixed inset-0 z-[100000] bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="glass-panel max-w-lg w-full rounded-2xl p-6 border border-cyan-500/40 shadow-2xl space-y-4 font-mono">
        <div class="flex items-center justify-between border-b border-obsidian-border pb-3">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-cyan-950/80 border border-cyan-500/40 text-cyan-300 flex items-center justify-center shadow-lg shadow-cyan-950">
                    <span class="material-symbols-outlined text-xl">language</span>
                </div>
                <div>
                    <h3 class="text-xs font-bold text-white uppercase tracking-wider">Panel Web Administrativo</h3>
                    <p class="text-[10px] text-obsidian-cyan">Interfaz de Configuración del Dispositivo</p>
                </div>
            </div>
            <button onclick="closeWebSessionModal()" class="text-obsidian-muted hover:text-white text-2xl leading-none">&times;</button>
        </div>

        <div class="space-y-3 text-xs">
            <div class="p-3.5 rounded-xl bg-cyan-950/40 border border-cyan-500/40 text-white text-[11px] space-y-1.5">
                <div class="flex justify-between">
                    <span class="text-obsidian-muted">Equipo Destino:</span>
                    <strong id="web-session-device" class="text-cyan-300"></strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-obsidian-muted">Sede / Ubicación:</span>
                    <span id="web-session-site" class="text-white"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-obsidian-muted">Dirección IP:</span>
                    <code id="web-session-ip" class="text-obsidian-cyan font-bold"></code>
                </div>
                <div class="flex justify-between">
                    <span class="text-obsidian-muted">Puerto / Protocolo:</span>
                    <span id="web-session-port" class="text-white font-mono">80 (HTTP)</span>
                </div>
                <div class="flex justify-between items-center pt-1 border-t border-cyan-500/20">
                    <span class="text-obsidian-muted">URL Directa:</span>
                    <a id="web-session-url" href="#" target="_blank" class="text-obsidian-cyan hover:underline truncate max-w-[280px]"></a>
                </div>
            </div>

            <div class="p-3 rounded-lg bg-obsidian-panel/60 border border-obsidian-border text-[11px] text-obsidian-muted space-y-1">
                <p class="text-white font-semibold flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm text-obsidian-cyan">verified_user</span>
                    <span>Acceso Habilitado</span>
                </p>
                <p>
                    Sesión autorizada para <strong class="text-white">{{ Auth::user() ? Auth::user()->name : 'Operador' }}</strong> (Rol: <code class="text-obsidian-cyan uppercase">{{ Auth::user() ? Auth::user()->role : 'operador' }}</code>).
                </p>
            </div>
        </div>

        <div class="pt-3 border-t border-obsidian-border flex items-center justify-end gap-2">
            <button type="button" onclick="closeWebSessionModal()" class="px-4 py-2 rounded-lg bg-obsidian-panel text-obsidian-muted hover:text-white text-xs">
                Cerrar
            </button>
            <button id="btn-launch-web-admin" type="button" class="px-4 py-2 rounded-lg bg-obsidian-cyan text-black font-bold text-xs flex items-center gap-1.5 hover:bg-cyan-300 transition shadow-lg shadow-cyan-500/20 cursor-pointer">
                <span class="material-symbols-outlined text-sm">open_in_new</span>
                <span>Abrir Panel Web</span>
            </button>
        </div>
    </div>
</div>

<script>
    function openSshTerminal(ip, port = 22, name = '', site = '') {
        const url = `/admin/ssh/terminal?ip=${encodeURIComponent(ip)}&port=${port}&name=${encodeURIComponent(name)}&site=${encodeURIComponent(site)}`;
        const w = 1100;
        const h = 700;
        const left = (screen.width/2)-(w/2);
        const top = (screen.height/2)-(h/2);
        window.open(url, `ssh_${ip.replace(/\./g, '_')}`, `width=${w},height=${h},top=${top},left=${left},resizable=yes,scrollbars=no,status=no`);
    }

    async function openTelnetTerminal(ip, port = 23, name = '', site = '') {
        try {
            const res = await fetch("{{ route('admin.telnet.session') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ ip, port, name, site })
            });
            const data = await res.json();
            if (data.success && data.viewer_url) {
                const w = 1100;
                const h = 700;
                const left = (screen.width/2)-(w/2);
                const top = (screen.height/2)-(h/2);
                window.open(data.viewer_url, `telnet_${ip.replace(/\./g, '_')}`, `width=${w},height=${h},top=${top},left=${left},resizable=yes,scrollbars=no,status=no`);
            } else {
                alert('No se pudo inicializar la sesión Telnet: ' + (data.message || 'Error desconocido'));
            }
        } catch (err) {
            console.error('Error Telnet:', err);
            alert('Error de comunicación con el proxy Telnet.');
        }
    }

    async function openVncViewer(ip, name = '', site = '') {
        try {
            const res = await fetch("{{ route('admin.vnc.session') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ ip, name, site })
            });
            const data = await res.json();
            if (data.success && data.viewer_url) {
                const w = 1280;
                const h = 800;
                const left = (screen.width/2)-(w/2);
                const top = (screen.height/2)-(h/2);
                window.open(data.viewer_url, `vnc_${ip.replace(/\./g, '_')}`, `width=${w},height=${h},top=${top},left=${left},resizable=yes,scrollbars=no,status=no`);
            } else {
                alert('No se pudo inicializar la sesión VNC: ' + (data.message || 'Error desconocido'));
            }
        } catch (err) {
            console.error('Error VNC:', err);
            alert('Error de comunicación con el servicio VNC.');
        }
    }

    function openAccessModal(type, ip, port, name, siteName, isAuth) {
        var tooltip = document.getElementById('tech-tooltip');
        if (tooltip) {
            tooltip.classList.remove('show');
        }

        type = (type || 'TELNET').toUpperCase();
        port = port || (type === 'SSH' ? 22 : (type === 'TELNET' ? 23 : (type === 'WEB' ? 80 : 5900)));

        if (!isAuth) {
            const svcTitle = type === 'SSH' ? 'Terminal SSH Cifrada' : (type === 'TELNET' ? 'Terminal Telnet CLI' : (type === 'WEB' ? 'Panel Web Administrativo' : 'Control Remoto VNC'));
            const actionText = type === 'SSH' ? 'a la terminal interactiva SSH' : (type === 'TELNET' ? 'a la consola de comandos Telnet' : (type === 'WEB' ? 'al panel de administración web' : 'al escritorio remoto VNC'));
            
            const pSvc = document.getElementById('access-prompt-service');
            if (pSvc) pSvc.innerText = svcTitle;
            const pAct = document.getElementById('access-prompt-action');
            if (pAct) pAct.innerText = actionText;
            const pDev = document.getElementById('access-prompt-device');
            if (pDev) pDev.innerText = name;
            const pSite = document.getElementById('access-prompt-site');
            if (pSite) pSite.innerText = siteName;

            const m = document.getElementById('modal-access-login-prompt');
            if (m) {
                m.classList.remove('hidden');
                m.classList.add('flex');
            }
            return;
        }

        if (type === 'SSH') {
            openSshTerminal(ip, port, name, siteName);
        } else if (type === 'TELNET') {
            openTelnetTerminal(ip, port, name, siteName);
        } else if (type === 'WEB') {
            const proto = (port === 443 || port === 8443) ? 'https' : 'http';
            const url = proto + '://' + ip + ((port === 80 || port === 443) ? '' : ':' + port);
            window.open(url, '_blank');
        } else if (type === 'VNC') {
            openVncViewer(ip, name, siteName);
        }
    }

    function openVncModal(ip, name, siteName, isAuth) {
        openAccessModal('VNC', ip, 5900, name, siteName, isAuth);
    }

    function showItemAuthModal(title) {
        var tooltip = document.getElementById('tech-tooltip');
        if (tooltip) tooltip.classList.remove('show');
        var m = document.getElementById('modal-item-auth-prompt');
        var t = document.getElementById('item-auth-prompt-target');
        if (t && title) t.innerText = title;
        if (m) {
            m.classList.remove('hidden');
            m.classList.add('flex');
        }
    }

    function closeItemAuthModal() {
        var m = document.getElementById('modal-item-auth-prompt');
        if (m) {
            m.classList.remove('flex');
            m.classList.add('hidden');
        }
    }

    window.showItemAuthModal = showItemAuthModal;
    window.closeItemAuthModal = closeItemAuthModal;
    window.showAuthRequiredModal = showItemAuthModal;
    window.closeAuthRequiredModal = closeItemAuthModal;

    function closeAccessPromptModal() {
        const m = document.getElementById('modal-access-login-prompt');
        if (m) {
            m.classList.remove('flex');
            m.classList.add('hidden');
        }
    }

    function closeVncPromptModal() {
        closeAccessPromptModal();
    }

    function closeVncSessionModal() {
        const m = document.getElementById('modal-vnc-session');
        if (m) {
            m.classList.remove('flex');
            m.classList.add('hidden');
        }
    }

    function closeTelnetSessionModal() {
        const m = document.getElementById('modal-telnet-session');
        if (m) {
            m.classList.remove('flex');
            m.classList.add('hidden');
        }
    }

    function closeWebSessionModal() {
        const m = document.getElementById('modal-web-session');
        if (m) {
            m.classList.remove('flex');
            m.classList.add('hidden');
        }
    }

    async function launchWebVnc() {
        if (!window._currentVncTarget) return;

        const btn = document.getElementById('btn-launch-vnc-web');
        const originalHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">progress_activity</span> <span>Iniciando...</span>';
        }

        try {
            const res = await fetch("{{ route('admin.vnc.session') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    ip: window._currentVncTarget.ip,
                    name: window._currentVncTarget.name,
                    site: window._currentVncTarget.site
                })
            });

            const data = await res.json();
            if (data.success && data.viewer_url) {
                closeVncSessionModal();
                window.open(data.viewer_url, '_blank', 'width=1280,height=800,menubar=no,status=no,toolbar=no');
            } else {
                alert('No se pudo inicializar la sesión VNC: ' + (data.message || 'Error desconocido'));
            }
        } catch (err) {
            console.error('Error iniciando sesión VNC:', err);
            alert('Error de red al intentar conectar con el proxy VNC.');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
    }

    async function launchWebTelnet() {
        if (!window._currentTelnetTarget) return;

        const btn = document.getElementById('btn-launch-telnet-web');
        const originalHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">progress_activity</span> <span>Iniciando...</span>';
        }

        try {
            const res = await fetch("{{ route('admin.telnet.session') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    ip: window._currentTelnetTarget.ip,
                    port: window._currentTelnetTarget.port,
                    name: window._currentTelnetTarget.name,
                    site: window._currentTelnetTarget.site
                })
            });

            const data = await res.json();
            if (data.success && data.viewer_url) {
                closeTelnetSessionModal();
                window.open(data.viewer_url, '_blank', 'width=1280,height=800,menubar=no,status=no,toolbar=no');
            } else {
                alert('No se pudo inicializar la sesión Telnet: ' + (data.message || 'Error desconocido'));
            }
        } catch (err) {
            console.error('Error iniciando sesión Telnet:', err);
            alert('Error de red al intentar conectar con el proxy Telnet.');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
    }

    // =========================================================================
    // GESTIÓN DEL ASISTENTE VIRTUAL IA (MONITOR VALLE SECO)
    // =========================================================================
    let aiChatHistory = [];
    const isUserAuthenticated = {{ Auth::check() ? 'true' : 'false' }};

    function handleAiChatClick() {
        if (!isUserAuthenticated) {
            const promptModal = document.getElementById('modal-ai-login-prompt');
            if (promptModal) {
                promptModal.classList.remove('hidden');
                promptModal.classList.add('flex');
            }
        } else {
            const chatModal = document.getElementById('modal-ai-chat');
            if (chatModal) {
                chatModal.classList.remove('hidden');
                chatModal.classList.add('flex');
                setTimeout(() => {
                    const input = document.getElementById('ai-chat-input-text');
                    if (input) input.focus();
                }, 100);
            }
        }
    }

    function closeAiLoginPromptModal() {
        const promptModal = document.getElementById('modal-ai-login-prompt');
        if (promptModal) {
            promptModal.classList.add('hidden');
            promptModal.classList.remove('flex');
        }
    }

    function closeAiChatModal() {
        const chatModal = document.getElementById('modal-ai-chat');
        if (chatModal) {
            chatModal.classList.add('hidden');
            chatModal.classList.remove('flex');
        }
    }

    function clearAiChatConversation() {
        aiChatHistory = [];
        const container = document.getElementById('ai-chat-messages-container');
        if (container) {
            container.innerHTML = `
                <div class="flex gap-3 items-start">
                    <div class="w-8 h-8 rounded-xl bg-cyan-950/80 border border-cyan-500/30 flex items-center justify-center text-cyan-400 shrink-0 mt-0.5">
                        <span class="material-symbols-outlined text-base">smart_toy</span>
                    </div>
                    <div class="bg-[#081b33] border border-obsidian-border/80 rounded-2xl rounded-tl-sm p-4 max-w-[88%] text-gray-200 leading-relaxed space-y-2">
                        <p class="font-bold text-obsidian-cyan text-xs">Conversación reiniciada</p>
                        <p>Historial limpiado correctamente. ¿Sobre qué tema de infraestructura o soporte deseas consultar ahora?</p>
                    </div>
                </div>
            `;
        }
    }

    function sendQuickPrompt(promptText) {
        const input = document.getElementById('ai-chat-input-text');
        if (input) {
            input.value = promptText;
            submitAiChat(new Event('submit'));
        }
    }

    function formatAiMarkdown(text) {
        if (!text) return '';
        let escaped = text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;");

        // Bloques de código con opción de copia
        escaped = escaped.replace(/```([a-zA-Z0-9_-]*)\n([\s\S]*?)```/g, function(match, lang, code) {
            const rawCode = code.trim();
            return `<div class="my-2.5 rounded-xl bg-[#020914] border border-cyan-500/30 overflow-hidden shadow-inner font-mono text-[11px]">
                <div class="flex items-center justify-between px-3 py-1.5 bg-cyan-950/40 border-b border-cyan-500/20 text-[10px] text-cyan-400">
                    <span class="uppercase font-bold tracking-wider">${lang || 'TERMINAL'}</span>
                    <button type="button" onclick="navigator.clipboard.writeText(decodeURIComponent('${encodeURIComponent(rawCode)}')); this.innerText='¡Copiado!'; setTimeout(()=>this.innerText='Copiar', 2000)" class="text-[10px] text-obsidian-cyan hover:text-white transition">Copiar</button>
                </div>
                <pre class="p-3 overflow-x-auto text-cyan-300 leading-relaxed"><code>${rawCode}</code></pre>
            </div>`;
        });

        // Código en línea
        escaped = escaped.replace(/`([^`]+)`/g, '<code class="bg-cyan-950/80 text-cyan-300 px-1.5 py-0.5 rounded border border-cyan-500/30 font-mono text-[11px]">$1</code>');

        // Negrita
        escaped = escaped.replace(/\*\*([^*]+)\*\*/g, '<strong class="text-white font-bold">$1</strong>');

        // Cursiva
        escaped = escaped.replace(/\*([^*]+)\*/g, '<em class="text-cyan-200/90">$1</em>');

        // Saltos de línea
        escaped = escaped.replace(/\n/g, '<br/>');

        return escaped;
    }

    async function submitAiChat(e) {
        if (e && e.preventDefault) e.preventDefault();

        const input = document.getElementById('ai-chat-input-text');
        const sendBtn = document.getElementById('btn-submit-ai-chat');
        const container = document.getElementById('ai-chat-messages-container');
        const typingIndicator = document.getElementById('ai-chat-typing-indicator');

        if (!input) return;
        const messageText = input.value.trim();
        if (!messageText) return;

        // 1. Renderizar burbuja del usuario
        const userHtml = `
            <div class="flex justify-end">
                <div class="bg-gradient-to-r from-cyan-950 to-cyan-900 border border-cyan-500/40 rounded-2xl rounded-tr-sm p-3.5 max-w-[85%] text-white shadow-lg">
                    <p class="leading-relaxed text-xs font-sans">${messageText.replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/\n/g, "<br/>")}</p>
                    <span class="block text-right text-[9px] text-cyan-300/60 font-mono mt-1">${new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', userHtml);
        container.scrollTop = container.scrollHeight;

        input.value = '';
        input.disabled = true;
        if (sendBtn) sendBtn.disabled = true;
        if (typingIndicator) typingIndicator.classList.remove('hidden');

        try {
            const res = await fetch("{{ route('ai.chat') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    message: messageText,
                    history: aiChatHistory.slice(-8)
                })
            });

            const data = await res.json();

            if (res.status === 401 || data.require_login) {
                closeAiChatModal();
                closeAiLoginPromptModal();
                const promptModal = document.getElementById('modal-ai-login-prompt');
                if (promptModal) {
                    promptModal.classList.remove('hidden');
                    promptModal.classList.add('flex');
                }
                return;
            }

            if (data.success && data.reply) {
                aiChatHistory.push({ role: 'user', content: messageText });
                aiChatHistory.push({ role: 'assistant', content: data.reply });

                const botFormatted = formatAiMarkdown(data.reply);
                const botHtml = `
                    <div class="flex gap-3 items-start">
                        <div class="w-8 h-8 rounded-xl bg-cyan-950/80 border border-cyan-500/30 flex items-center justify-center text-cyan-400 shrink-0 mt-0.5 shadow-md">
                            <span class="material-symbols-outlined text-base">smart_toy</span>
                        </div>
                        <div class="bg-[#081b33] border border-obsidian-border/80 rounded-2xl rounded-tl-sm p-4 max-w-[88%] text-gray-200 leading-relaxed text-xs">
                            ${botFormatted}
                            <span class="block text-[9px] text-obsidian-muted font-mono mt-2">${new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})} • Monitor Valle Seco</span>
                        </div>
                    </div>
                `;
                container.insertAdjacentHTML('beforeend', botHtml);
            } else {
                const errorMsg = data.error || 'Ocurrió un error inesperado al procesar la respuesta con la IA.';
                const errHtml = `
                    <div class="flex gap-3 items-start">
                        <div class="w-8 h-8 rounded-xl bg-red-950/80 border border-red-500/40 flex items-center justify-center text-red-400 shrink-0 mt-0.5">
                            <span class="material-symbols-outlined text-base">error</span>
                        </div>
                        <div class="bg-red-950/30 border border-red-500/30 rounded-2xl rounded-tl-sm p-3.5 max-w-[85%] text-red-300 leading-relaxed text-xs">
                            <p class="font-bold text-red-200">Aviso del Sistema</p>
                            <p>${errorMsg}</p>
                        </div>
                    </div>
                `;
                container.insertAdjacentHTML('beforeend', errHtml);
            }
        } catch (err) {
            console.error('Error comunicando con el asistente IA:', err);
            const netErrHtml = `
                <div class="flex gap-3 items-start">
                    <div class="w-8 h-8 rounded-xl bg-red-950/80 border border-red-500/40 flex items-center justify-center text-red-400 shrink-0 mt-0.5">
                        <span class="material-symbols-outlined text-base">cloud_off</span>
                    </div>
                    <div class="bg-red-950/30 border border-red-500/30 rounded-2xl rounded-tl-sm p-3.5 max-w-[85%] text-red-300 leading-relaxed text-xs">
                        <p class="font-bold text-red-200">Falla de Conexión</p>
                        <p>No se pudo establecer comunicación con el servidor web o el servicio de IA local. Por favor verifica tu conexión.</p>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', netErrHtml);
        } finally {
            if (typingIndicator) typingIndicator.classList.add('hidden');
            input.disabled = false;
            if (sendBtn) sendBtn.disabled = false;
            input.focus();
            container.scrollTop = container.scrollHeight;
        }
    }
</script>

@push('scripts')
<script>
    window._isAuth = {{ Auth::check() ? 'true' : 'false' }};
    window._userCanRemote = {{ (Auth::check() && in_array(Auth::user()->role, ['admin', 'operator'])) ? 'true' : 'false' }};
    window._userCanVnc = window._userCanRemote;
</script>
<script id="monit-payload" type="application/json">{"s":@json(Auth::check() ? $serviceHistoryMap : []),"t":@json(Auth::check() ? $siteHistoryMap : []),"d":@json(Auth::check() ? $deviceHistoryMap : [])}</script>
<script src="{{ asset('js/monitoring-app.min.js') }}?v={{ time() }}" defer></script>
@endpush
