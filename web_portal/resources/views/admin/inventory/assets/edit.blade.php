@extends('layouts.admin')

@section('page_title', 'Editar Activo • ' . $asset->name)

@section('admin_content')
<div class="max-w-4xl mx-auto space-y-4">
    <!-- NAVEGACIÓN -->
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.inventory.assets.show', $asset->id) }}" class="px-2.5 py-1 rounded-xl bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-cyan-500 hover:text-black font-mono text-xs font-bold transition flex items-center gap-1 group">
            <span class="material-symbols-outlined text-sm group-hover:-translate-x-0.5 transition-transform">arrow_back</span>
            <span>Volver a Ficha Técnica</span>
        </a>
        <span class="text-obsidian-border font-mono text-xs">/</span>
        <span class="font-mono text-xs text-obsidian-muted">Editar Activo</span>
    </div>

    <!-- FORMULARIO -->
    <div class="p-6 rounded-2xl bg-obsidian-panel/95 border border-obsidian-border shadow-xl">
        <div class="flex items-center gap-3 pb-4 mb-5 border-b border-obsidian-border">
            <span class="p-2.5 rounded-xl bg-cyan-500/10 border border-cyan-500/30 text-cyan-400">
                <span class="material-symbols-outlined text-2xl">edit_note</span>
            </span>
            <div>
                <h1 class="text-lg font-black text-white font-mono">Editar Metadatos de Activo</h1>
                <p class="text-xs text-obsidian-muted font-mono">Actualiza tag patrimonial, responsable asignado, sede física o notas operativas</p>
            </div>
        </div>

        @if(isset($errors) && (is_object($errors) ? (method_exists($errors, 'any') && $errors->any()) : !empty($errors)))
            <div class="p-3 mb-4 rounded-xl bg-red-950/40 border border-red-500/50 text-red-300 text-xs font-mono">
                <ul class="list-disc list-inside space-y-1">
                    @php $allErrors = (is_object($errors) && method_exists($errors, 'all')) ? $errors->all() : (is_array($errors) ? $errors : []); @endphp
                    @foreach($allErrors as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.inventory.assets.update', $asset->id) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <!-- SECCIÓN 1: IDENTIFICACIÓN PRINCIPAL -->
            <div class="space-y-3">
                <h3 class="text-xs font-mono uppercase font-bold text-cyan-400 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">badge</span>
                    Identificación Principal
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-mono text-cyan-400 mb-1 font-semibold uppercase">Nombre Etiqueta (Identidad Operativa) <span class="text-cyan-400">*</span></label>
                        <input type="text" name="name" required value="{{ old('name', $asset->name) }}" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Código / Tag Patrimonial</label>
                        <input type="text" name="asset_tag" value="{{ old('asset_tag', $asset->asset_tag) }}" placeholder="Ej. IT-VS-0045" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Tipo de Activo <span class="text-cyan-400">*</span></label>
                        <select name="type" id="asset_type_select" required class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none" onchange="togglePcFields(this.value)">
                            <optgroup label="Equipos de Cómputo">
                                <option value="desktop" {{ old('type', $asset->type) === 'desktop' ? 'selected' : '' }}>Desktop / Computadora de Escritorio</option>
                                <option value="laptop" {{ old('type', $asset->type) === 'laptop' ? 'selected' : '' }}>Laptop / Computadora Portátil</option>
                                <option value="server" {{ old('type', $asset->type) === 'server' ? 'selected' : '' }}>Servidor Físico</option>
                            </optgroup>
                            <optgroup label="Dispositivos de Red e Infraestructura">
                                <option value="router" {{ old('type', $asset->type) === 'router' ? 'selected' : '' }}>Router / Enrutador</option>
                                <option value="switch" {{ old('type', $asset->type) === 'switch' ? 'selected' : '' }}>Switch de Red</option>
                                <option value="firewall" {{ old('type', $asset->type) === 'firewall' ? 'selected' : '' }}>Firewall / Seguridad</option>
                                <option value="access_point" {{ old('type', $asset->type) === 'access_point' ? 'selected' : '' }}>Access Point Wi-Fi</option>
                                <option value="ip_phone" {{ old('type', $asset->type) === 'ip_phone' ? 'selected' : '' }}>Teléfono IP</option>
                                <option value="ups" {{ old('type', $asset->type) === 'ups' ? 'selected' : '' }}>UPS / Respaldo Eléctrico</option>
                                <option value="printer" {{ old('type', $asset->type) === 'printer' ? 'selected' : '' }}>Impresora / Multifuncional</option>
                                <option value="network" {{ old('type', $asset->type) === 'network' ? 'selected' : '' }}>Dispositivo de Red Genérico</option>
                            </optgroup>
                            <optgroup label="Otros">
                                <option value="peripheral" {{ old('type', $asset->type) === 'peripheral' ? 'selected' : '' }}>Periférico</option>
                            </optgroup>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Sistema Operativo</label>
                        <input type="text" name="operating_system" value="{{ old('operating_system', $asset->operating_system) }}" placeholder="Ej. Windows 11 Pro / Debian 12" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Marca / Fabricante</label>
                        <input type="text" name="brand" value="{{ old('brand', $asset->brand) }}" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Modelo Comercial</label>
                        <input type="text" name="model" value="{{ old('model', $asset->model) }}" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Número de Serie de Fábrica</label>
                        <input type="text" name="serial_number" value="{{ old('serial_number', $asset->serial_number) }}" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>
                </div>
            </div>

            <!-- SECCIÓN 2: UBICACIÓN Y ESTADO -->
            <div class="space-y-3 pt-3 border-t border-obsidian-border">
                <h3 class="text-xs font-mono uppercase font-bold text-cyan-400 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">location_on</span>
                    Ubicación y Estado Operativo
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Sede Física</label>
                        <select name="monitored_site_id" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                            <option value="">Sin Asignar</option>
                            @foreach($sites as $s)
                                <option value="{{ $s->id }}" {{ old('monitored_site_id', $asset->monitored_site_id) == $s->id ? 'selected' : '' }}>
                                    {{ $s->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Ubicación Detallada</label>
                        <input type="text" name="location_detail" value="{{ old('location_detail', $asset->location_detail) }}" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Estado Operativo <span class="text-cyan-400">*</span></label>
                        <select name="status" required class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                            <option value="in_stock" {{ old('status', $asset->status) === 'in_stock' ? 'selected' : '' }}>En Stock (Disponible)</option>
                            <option value="assigned" {{ old('status', $asset->status) === 'assigned' ? 'selected' : '' }}>Asignado (En Uso)</option>
                            <option value="maintenance" {{ old('status', $asset->status) === 'maintenance' ? 'selected' : '' }}>En Mantenimiento</option>
                            <option value="unauthorized" {{ old('status', $asset->status) === 'unauthorized' ? 'selected' : '' }}>Descubierto (GLPI)</option>
                            <option value="damaged" {{ old('status', $asset->status) === 'damaged' ? 'selected' : '' }}>Dañado / Para Baja</option>
                            <option value="disposed" {{ old('status', $asset->status) === 'disposed' ? 'selected' : '' }}>Desincorporado</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- SECCIÓN 3: DIRECCIONAMIENTO DE RED -->
            <div class="space-y-3 pt-3 border-t border-obsidian-border">
                <h3 class="text-xs font-mono uppercase font-bold text-cyan-400 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">lan</span>
                    Direccionamiento de Red
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Dirección IP</label>
                        <input type="text" name="ip_address" value="{{ old('ip_address', $asset->ip_address) }}" placeholder="Ej. 192.168.107.155" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Dirección MAC</label>
                        <input type="text" name="mac_address" value="{{ old('mac_address', $asset->mac_address) }}" placeholder="Ej. 18:66:DA:55:66:21" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>
                </div>
            </div>

            <!-- SECCIÓN 4: PARÁMETROS TÉCNICOS & ACCESO REMOTO -->
            <div class="space-y-3.5 pt-3 border-t border-cyan-500/30 bg-[#030d1c]/80 p-4 rounded-2xl border border-cyan-500/20">
                <div class="flex items-center justify-between border-b border-cyan-500/20 pb-2">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-cyan-400 text-lg">settings_remote</span>
                        <h3 class="text-xs font-mono uppercase font-bold text-cyan-300">Parámetros Técnicos &amp; Acceso Remoto</h3>
                    </div>
                    <span class="text-[9.5px] font-mono px-2 py-0.5 rounded bg-cyan-950 text-cyan-300 border border-cyan-500/30">NOC &amp; ITAM Parity</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Puertos de Red</label>
                        <input type="text" name="ports" value="{{ old('ports', $asset->ports) }}" placeholder="Ej. 24x GE, 4x SFP" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Protocolo de Acceso</label>
                        <select name="access_type" id="edit-access-type" onchange="handleAccessChange(this.value)" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                            <option value="SIN SOPORTE" {{ old('access_type', $asset->access_type) === 'SIN SOPORTE' ? 'selected' : '' }}>SIN SOPORTE</option>
                            <option value="SSH" {{ old('access_type', $asset->access_type) === 'SSH' ? 'selected' : '' }}>Terminal SSH</option>
                            <option value="TELNET" {{ old('access_type', $asset->access_type) === 'TELNET' ? 'selected' : '' }}>Terminal Telnet</option>
                            <option value="WEB" {{ old('access_type', $asset->access_type) === 'WEB' ? 'selected' : '' }}>Interfaz Web (HTTP/HTTPS)</option>
                            <option value="VNC" {{ old('access_type', $asset->access_type) === 'VNC' ? 'selected' : '' }}>Escritorio Remoto VNC</option>
                            <option value="SSH+VNC" {{ old('access_type', $asset->access_type) === 'SSH+VNC' ? 'selected' : '' }}>Dual: SSH (22) + VNC (5900)</option>
                        </select>
                    </div>

                    <div id="edit-single-port-wrapper">
                        <label class="block text-xs font-mono text-obsidian-muted mb-1" id="edit-access-port-label">Puerto de Conexión</label>
                        <input type="number" name="access_port" id="edit-access-port" value="{{ old('access_port', $asset->access_port ?? 22) }}" min="1" max="65535" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>
                </div>

                <!-- PUERTOS DUALES DEDICADOS (SSH + VNC) -->
                <div id="edit-dual-ports-wrapper" class="hidden grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-mono text-emerald-400 mb-1 font-semibold uppercase">Puerto SSH</label>
                        <input type="number" name="ssh_port" id="edit-ssh-port" value="{{ old('ssh_port', $asset->ssh_port ?? 22) }}" min="1" max="65535" class="w-full px-3 py-2 bg-[#040b15] border border-emerald-500/40 rounded-xl text-xs font-mono text-white focus:border-emerald-400 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-mono text-purple-400 mb-1 font-semibold uppercase">Puerto VNC</label>
                        <input type="number" name="vnc_port" id="edit-vnc-port" value="{{ old('vnc_port', $asset->vnc_port ?? 5900) }}" min="1" max="65535" class="w-full px-3 py-2 bg-[#040b15] border border-purple-500/40 rounded-xl text-xs font-mono text-white focus:border-purple-400 focus:outline-none">
                    </div>
                </div>

                <!-- CREDENCIALES VNC -->
                <div id="edit-vnc-wrapper" class="hidden p-3 rounded-xl bg-[#020610] border border-purple-500/20 space-y-2 font-mono">
                    <div class="flex items-center justify-between border-b border-purple-500/20 pb-1.5">
                        <div class="flex items-center gap-1.5 text-xs font-bold text-purple-400">
                            <span class="material-symbols-outlined text-sm">desktop_windows</span>
                            <span>Contraseña VNC (Cifrado AES-256)</span>
                        </div>
                        @if($asset->has_vnc_credentials)
                            <span class="inline-flex items-center gap-1 text-[10px] font-mono px-2 py-0.5 rounded bg-purple-500/20 text-purple-400 border border-purple-500/40">
                                <span class="material-symbols-outlined text-xs">verified_user</span> Clave Configurada
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-[10px] font-mono px-2 py-0.5 rounded bg-slate-500/20 text-slate-400 border border-slate-500/30">
                                Sin Clave Guardada
                            </span>
                        @endif
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <div>
                            <label class="block text-[10.5px] text-slate-300 mb-0.5">Nueva Contraseña VNC (dejar vacío para conservar)</label>
                            <div class="relative">
                                <input type="password" name="vnc_password" placeholder="••••••••" class="w-full px-2.5 py-1.5 rounded-lg bg-[#040b15] border border-obsidian-border text-white text-xs font-mono focus:border-purple-400 focus:outline-none pr-7">
                                <button type="button" onclick="togglePasswordVisibility(this)" class="absolute right-1.5 top-1.5 text-slate-400 hover:text-white" tabindex="-1">
                                    <span class="material-symbols-outlined text-xs">visibility</span>
                                </button>
                            </div>
                        </div>
                        @if($asset->has_vnc_credentials)
                            <div class="flex items-center pt-4">
                                <label class="inline-flex items-center gap-1.5 text-[11px] text-red-300 hover:text-red-200 cursor-pointer">
                                    <input type="checkbox" name="clear_vnc_credentials" value="1" class="rounded bg-black border-red-500 text-red-500 focus:ring-0">
                                    <span>Eliminar contraseña VNC guardada</span>
                                </label>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- CREDENCIALES SSH -->
                <div id="edit-ssh-wrapper" class="p-3 rounded-xl bg-[#020610] border border-emerald-500/20 space-y-2 font-mono">
                    <div class="flex items-center justify-between border-b border-emerald-500/20 pb-1.5">
                        <div class="flex items-center gap-1.5 text-xs font-bold text-emerald-400">
                            <span class="material-symbols-outlined text-sm">key</span>
                            <span>Credenciales de Gestión SSH (Cifrado AES-256)</span>
                        </div>
                        @if($asset->has_ssh_credentials)
                            <span class="inline-flex items-center gap-1 text-[10px] font-mono px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-400 border border-emerald-500/40">
                                <span class="material-symbols-outlined text-xs">verified_user</span> Credenciales Activas
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-[10px] font-mono px-2 py-0.5 rounded bg-slate-500/20 text-slate-400 border border-slate-500/30">
                                Sin Credenciales Guardadas
                            </span>
                        @endif
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <div>
                            <label class="block text-[10.5px] text-slate-300 mb-0.5">Usuario SSH</label>
                            <input type="text" name="ssh_username" value="{{ old('ssh_username', $asset->ssh_username) }}" placeholder="admin / cisco" class="w-full px-2.5 py-1.5 rounded-lg bg-[#040b15] border border-obsidian-border text-white text-xs font-mono focus:border-emerald-400 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[10.5px] text-slate-300 mb-0.5">Nueva Contraseña SSH</label>
                            <div class="relative">
                                <input type="password" name="ssh_password" placeholder="Dejar vacío para conservar" class="w-full px-2.5 py-1.5 rounded-lg bg-[#040b15] border border-obsidian-border text-white text-xs font-mono focus:border-emerald-400 focus:outline-none pr-7">
                                <button type="button" onclick="togglePasswordVisibility(this)" class="absolute right-1.5 top-1.5 text-slate-400 hover:text-white" tabindex="-1">
                                    <span class="material-symbols-outlined text-xs">visibility</span>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10.5px] text-slate-300 mb-0.5">Nuevo Enable Secret</label>
                            <div class="relative">
                                <input type="password" name="ssh_enable_secret" placeholder="Dejar vacío para conservar" class="w-full px-2.5 py-1.5 rounded-lg bg-[#040b15] border border-obsidian-border text-white text-xs font-mono focus:border-emerald-400 focus:outline-none pr-7">
                                <button type="button" onclick="togglePasswordVisibility(this)" class="absolute right-1.5 top-1.5 text-slate-400 hover:text-white" tabindex="-1">
                                    <span class="material-symbols-outlined text-xs">visibility</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    @if($asset->has_ssh_credentials)
                        <div class="pt-1">
                            <label class="inline-flex items-center gap-1.5 text-[11px] text-red-300 hover:text-red-200 cursor-pointer">
                                <input type="checkbox" name="clear_ssh_credentials" value="1" class="rounded bg-black border-red-500 text-red-500 focus:ring-0">
                                <span>Eliminar credenciales SSH almacenadas</span>
                            </label>
                        </div>
                    @endif
                </div>
            </div>

            <!-- SECCIÓN 5: RESPONSABLE & ASIGNACIÓN (ACTA UINF-4.05 / LDAP) -->
            <div class="space-y-3 pt-3 border-t border-obsidian-border">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-mono uppercase font-bold text-cyan-400 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-sm">badge</span>
                        Responsable &amp; Asignación (Acta UINF-4.05)
                    </h3>
                    <span class="text-[9.5px] font-mono px-2 py-0.5 rounded {{ $asset->custodian_name ? 'bg-emerald-950 text-emerald-300 border border-emerald-500/40' : 'bg-slate-800 text-slate-400 border border-slate-700' }}">
                        {{ $asset->custodian_name ? 'Asignado' : 'En Stock / Sin Asignación' }}
                    </span>
                </div>

                <x-ldap-user-picker 
                    id="edit-asset-picker" 
                    name="custodian" 
                    label="Buscar Trabajador en Directorio Activo LDAP:" 
                    :show-details="true" 
                    :can-release="true" 
                    :current-name="$asset->custodian_name ?? ''" 
                    :current-id-number="$asset->custodian_id_number ?? ''" 
                    :current-department="$asset->custodian_department ?? ''" 
                    :current-user-id="$asset->current_user_id ?? ''" 
                    theme="cyan" 
                />
            </div>

            @php
                $mon = $asset->peripherals->firstWhere('type', 'monitor');
                $kbd = $asset->peripherals->firstWhere('type', 'keyboard');
                $mse = $asset->peripherals->firstWhere('type', 'mouse');
                $pwr = $asset->peripherals->firstWhere('type', 'surge_protector') ?? $asset->peripherals->firstWhere('type', 'ups');
            @endphp

            <!-- SECCIÓN: PERIFÉRICOS DEL PUESTO DE TRABAJO (SOLO PC) -->
            <div id="pc_peripherals_section" class="space-y-3 pt-3 border-t border-obsidian-border" style="{{ in_array($asset->type, ['desktop', 'laptop', 'server']) ? '' : 'display:none;' }}">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-mono uppercase font-bold text-cyan-400 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-sm">devices</span>
                        Periféricos del Puesto de Trabajo (Monitor, Teclado, Mouse, Protector)
                    </h3>
                    <span class="text-[11px] font-mono text-obsidian-muted">Actualiza o asocia periféricos al equipo</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <!-- MONITOR -->
                    <div class="p-3 rounded-xl bg-obsidian-darker/60 border border-obsidian-border/80 space-y-2">
                        <div class="flex items-center gap-1.5 text-xs font-mono text-white font-semibold">
                            <span class="material-symbols-outlined text-cyan-400 text-sm">desktop_windows</span>
                            <span>Monitor</span>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-obsidian-muted mb-0.5">Marca / Modelo</label>
                            <input type="text" name="monitor_brand" value="{{ old('monitor_brand', $mon ? trim($mon->brand . ' ' . $mon->model) : '') }}" placeholder="Ej. Dell 22 pulgadas" class="w-full px-2.5 py-1.5 bg-[#040b15] border border-obsidian-border rounded-lg text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-obsidian-muted mb-0.5">Serial</label>
                            <input type="text" name="monitor_serial" value="{{ old('monitor_serial', $mon?->serial_number) }}" placeholder="Ej. CN-0XYZ12" class="w-full px-2.5 py-1.5 bg-[#040b15] border border-obsidian-border rounded-lg text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                        </div>
                    </div>

                    <!-- TECLADO -->
                    <div class="p-3 rounded-xl bg-obsidian-darker/60 border border-obsidian-border/80 space-y-2">
                        <div class="flex items-center gap-1.5 text-xs font-mono text-white font-semibold">
                            <span class="material-symbols-outlined text-cyan-400 text-sm">keyboard</span>
                            <span>Teclado</span>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-obsidian-muted mb-0.5">Marca</label>
                            <input type="text" name="keyboard_brand" value="{{ old('keyboard_brand', $kbd?->brand) }}" placeholder="Ej. Logitech / Genius" class="w-full px-2.5 py-1.5 bg-[#040b15] border border-obsidian-border rounded-lg text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-obsidian-muted mb-0.5">Serial</label>
                            <input type="text" name="keyboard_serial" value="{{ old('keyboard_serial', $kbd?->serial_number) }}" placeholder="Serial (opcional)" class="w-full px-2.5 py-1.5 bg-[#040b15] border border-obsidian-border rounded-lg text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                        </div>
                    </div>

                    <!-- MOUSE -->
                    <div class="p-3 rounded-xl bg-obsidian-darker/60 border border-obsidian-border/80 space-y-2">
                        <div class="flex items-center gap-1.5 text-xs font-mono text-white font-semibold">
                            <span class="material-symbols-outlined text-cyan-400 text-sm">mouse</span>
                            <span>Mouse</span>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-obsidian-muted mb-0.5">Marca</label>
                            <input type="text" name="mouse_brand" value="{{ old('mouse_brand', $mse?->brand) }}" placeholder="Ej. HP / Logitech" class="w-full px-2.5 py-1.5 bg-[#040b15] border border-obsidian-border rounded-lg text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-obsidian-muted mb-0.5">Serial</label>
                            <input type="text" name="mouse_serial" value="{{ old('mouse_serial', $mse?->serial_number) }}" placeholder="Serial (opcional)" class="w-full px-2.5 py-1.5 bg-[#040b15] border border-obsidian-border rounded-lg text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                        </div>
                    </div>

                    <!-- PROTECTOR / UPS -->
                    <div class="p-3 rounded-xl bg-obsidian-darker/60 border border-obsidian-border/80 space-y-2">
                        <div class="flex items-center gap-1.5 text-xs font-mono text-white font-semibold">
                            <span class="material-symbols-outlined text-cyan-400 text-sm">electric_bolt</span>
                            <span>Protector / Mini-UPS</span>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-obsidian-muted mb-0.5">Marca</label>
                            <input type="text" name="surge_protector_brand" value="{{ old('surge_protector_brand', $pwr?->brand) }}" placeholder="Ej. Forza / APC" class="w-full px-2.5 py-1.5 bg-[#040b15] border border-obsidian-border rounded-lg text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-obsidian-muted mb-0.5">Serial</label>
                            <input type="text" name="surge_protector_serial" value="{{ old('surge_protector_serial', $pwr?->serial_number) }}" placeholder="Serial (opcional)" class="w-full px-2.5 py-1.5 bg-[#040b15] border border-obsidian-border rounded-lg text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECCIÓN 4: OBSERVACIONES -->
            <div class="space-y-3 pt-3 border-t border-obsidian-border">
                <label class="block text-xs font-mono text-obsidian-muted mb-1">Notas u Observaciones del Activo</label>
                <textarea name="notes" rows="3" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">{{ old('notes', $asset->notes) }}</textarea>
            </div>

            <!-- BOTONES -->
            <div class="flex items-center justify-end gap-2 pt-4 border-t border-obsidian-border">
                <a href="{{ route('admin.inventory.assets.show', $asset->id) }}" class="px-4 py-2 rounded-xl border border-obsidian-border text-obsidian-muted hover:text-white font-mono text-xs transition">
                    Cancelar
                </a>
                <button type="submit" class="px-5 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-black font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-lg shadow-cyan-500/20">
                    <span class="material-symbols-outlined text-base">save</span>
                    <span>Actualizar Cambios</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function togglePcFields(val) {
    const sec = document.getElementById('pc_peripherals_section');
    if (sec) {
        sec.style.display = (val === 'desktop' || val === 'laptop' || val === 'server') ? '' : 'none';
    }
}

function handleAccessChange(val) {
    const singlePortWrapper = document.getElementById('edit-single-port-wrapper');
    const dualPortsWrapper = document.getElementById('edit-dual-ports-wrapper');
    const portInput = document.getElementById('edit-access-port');
    const portLabel = document.getElementById('edit-access-port-label');
    const vncWrapper = document.getElementById('edit-vnc-wrapper');
    const sshWrapper = document.getElementById('edit-ssh-wrapper');

    const upper = (val || '').toUpperCase();

    if (upper === 'SSH+VNC') {
        if (singlePortWrapper) singlePortWrapper.classList.add('hidden');
        if (dualPortsWrapper) dualPortsWrapper.classList.remove('hidden');
        if (vncWrapper) vncWrapper.classList.remove('hidden');
        if (sshWrapper) sshWrapper.classList.remove('hidden');
    } else {
        if (singlePortWrapper) singlePortWrapper.classList.remove('hidden');
        if (dualPortsWrapper) dualPortsWrapper.classList.add('hidden');

        if (upper === 'SSH') {
            if (portLabel) portLabel.textContent = 'Puerto SSH';
            if (vncWrapper) vncWrapper.classList.add('hidden');
            if (sshWrapper) sshWrapper.classList.remove('hidden');
        } else if (upper === 'TELNET') {
            if (portLabel) portLabel.textContent = 'Puerto Telnet';
            if (vncWrapper) vncWrapper.classList.add('hidden');
            if (sshWrapper) sshWrapper.classList.remove('hidden');
        } else if (upper === 'WEB') {
            if (portLabel) portLabel.textContent = 'Puerto Web (HTTP/S)';
            if (vncWrapper) vncWrapper.classList.add('hidden');
            if (sshWrapper) sshWrapper.classList.add('hidden');
        } else if (upper === 'VNC') {
            if (portLabel) portLabel.textContent = 'Puerto VNC';
            if (vncWrapper) vncWrapper.classList.remove('hidden');
            if (sshWrapper) sshWrapper.classList.add('hidden');
        } else {
            if (portLabel) portLabel.textContent = 'Puerto de Acceso';
            if (vncWrapper) vncWrapper.classList.add('hidden');
            if (sshWrapper) sshWrapper.classList.add('hidden');
        }
    }
}

function togglePasswordVisibility(btn) {
    const input = btn.parentElement.querySelector('input');
    const icon = btn.querySelector('.material-symbols-outlined');
    if (input.type === 'password') {
        input.type = 'text';
        icon.textContent = 'visibility_off';
    } else {
        input.type = 'password';
        icon.textContent = 'visibility';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const accType = document.getElementById('edit-access-type');
    if (accType) {
        handleAccessChange(accType.value);
    }
});
</script>
@endsection
