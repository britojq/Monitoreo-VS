@extends('layouts.admin')

@section('page_title', 'Registrar Nuevo Activo de TI')

@section('admin_content')
<div class="max-w-4xl mx-auto space-y-4">
    <!-- NAVEGACIÓN -->
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.devices.index') }}" class="px-2.5 py-1 rounded-xl bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-cyan-500 hover:text-black font-mono text-xs font-bold transition flex items-center gap-1 group">
            <span class="material-symbols-outlined text-sm group-hover:-translate-x-0.5 transition-transform">arrow_back</span>
            <span>Volver a Equipos &amp; Activos</span>
        </a>
        <span class="text-obsidian-border font-mono text-xs">/</span>
        <span class="font-mono text-xs text-obsidian-muted">Alta Manual de Activo</span>
    </div>

    <!-- FORMULARIO -->
    <div class="p-6 rounded-2xl bg-obsidian-panel/95 border border-obsidian-border shadow-xl">
        <div class="flex items-center gap-3 pb-4 mb-5 border-b border-obsidian-border">
            <span class="p-2.5 rounded-xl bg-cyan-500/10 border border-cyan-500/30 text-cyan-400">
                <span class="material-symbols-outlined text-2xl">add_box</span>
            </span>
            <div>
                <h1 class="text-lg font-black text-white font-mono">Alta Manual de Activo de TI</h1>
                <p class="text-xs text-obsidian-muted font-mono">Asistente guiado de registro para computadoras, periféricos e infraestructura de red</p>
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

        <form method="POST" action="{{ route('admin.inventory.assets.store') }}" class="space-y-5">
            @csrf

            <!-- PASO 1: SELECCIÓN DE CATEGORÍA DEL ACTIVO -->
            <div class="p-4 rounded-2xl bg-[#040b15] border border-cyan-500/30">
                <label class="block text-xs font-mono uppercase font-bold text-cyan-400 mb-2.5 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">category</span>
                    Paso 1: ¿Qué tipo de activo desea registrar?
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <!-- OPCIÓN 1: COMPUTADORA (PC / LAPTOP) -->
                    <label id="lblCategoryPc" class="cursor-pointer p-4 rounded-xl border-2 border-cyan-500 bg-cyan-500/10 flex items-start gap-3 transition">
                        <input type="radio" name="asset_category" value="pc" checked class="mt-1 text-cyan-500 focus:ring-cyan-500" onchange="onCategoryChanged('pc')">
                        <div>
                            <div class="font-bold text-white text-sm flex items-center gap-2">
                                <span class="material-symbols-outlined text-cyan-400">desktop_windows</span>
                                Computadora (PC / Laptop)
                            </div>
                            <div class="text-xs text-obsidian-muted mt-1">
                                Estaciones de trabajo, computadoras de escritorio o portátiles. Permite incluir periféricos de puesto.
                            </div>
                        </div>
                    </label>

                    <!-- OPCIÓN 2: DISPOSITIVO DE RED -->
                    <label id="lblCategoryNet" class="cursor-pointer p-4 rounded-xl border-2 border-obsidian-border bg-obsidian-panel/50 hover:border-purple-500/50 flex items-start gap-3 transition">
                        <input type="radio" name="asset_category" value="network_device" class="mt-1 text-purple-500 focus:ring-purple-500" onchange="onCategoryChanged('network_device')">
                        <div>
                            <div class="font-bold text-white text-sm flex items-center gap-2">
                                <span class="material-symbols-outlined text-purple-400">hub</span>
                                Dispositivo de Red / Infraestructura
                            </div>
                            <div class="text-xs text-obsidian-muted mt-1">
                                Routers, Switches, Teléfonos IP, Impresoras de red o Servidores.
                            </div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- PASO 2: PREGUNTA CONDICIONAL PARA COMPUTADORAS (PERIFÉRICOS DE PUESTO) -->
            <div id="pcPeripheralsBox" class="p-4 rounded-2xl bg-cyan-950/20 border border-cyan-500/40 space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <div class="text-sm font-bold text-white flex items-center gap-2">
                            <span class="material-symbols-outlined text-cyan-400">keyboard</span>
                            Periféricos del Puesto de Trabajo
                        </div>
                        <p class="text-xs text-obsidian-muted mt-0.5">
                            ¿Desea registrar solo la CPU / Equipo base, o incluir también el Monitor, Teclado, Mouse y Protector de Voltaje?
                        </p>
                    </div>
                    <label class="cursor-pointer inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border border-cyan-500/50 bg-cyan-500/10 text-cyan-300 font-mono text-xs font-bold hover:bg-cyan-500 hover:text-black transition shrink-0">
                        <input type="checkbox" name="include_peripherals" id="includePeripheralsCheck" value="1" class="w-4 h-4 rounded text-cyan-500 bg-[#040b15] border-obsidian-border" onchange="togglePeripheralsFields(this.checked)">
                        <span>Incluir Periféricos de Puesto</span>
                    </label>
                </div>

                <!-- FORMULARIO DE 4 PERIFÉRICOS (DESPLEGABLE) -->
                <div id="peripheralsFieldsSection" class="hidden pt-3 border-t border-cyan-500/20 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <!-- 1. MONITOR -->
                    <div class="p-3 rounded-xl bg-[#040b15] border border-obsidian-border space-y-2">
                        <div class="text-xs font-bold text-white flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-cyan-400 text-sm">monitor</span>
                            Monitor
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-obsidian-muted mb-0.5">Marca</label>
                            <input type="text" name="monitor_brand" placeholder="Ej. HP, AOC, Lenovo" class="w-full px-2.5 py-1.5 bg-black border border-obsidian-border rounded-lg text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-obsidian-muted mb-0.5">Modelo</label>
                            <input type="text" name="monitor_model" placeholder="Ej. HP V193b" class="w-full px-2.5 py-1.5 bg-black border border-obsidian-border rounded-lg text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-obsidian-muted mb-0.5">Serial</label>
                            <input type="text" name="monitor_serial" placeholder="Serial de fábrica" class="w-full px-2.5 py-1.5 bg-black border border-obsidian-border rounded-lg text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                        </div>
                    </div>

                    <!-- 2. TECLADO -->
                    <div class="p-3 rounded-xl bg-[#040b15] border border-obsidian-border space-y-2">
                        <div class="text-xs font-bold text-white flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-cyan-400 text-sm">keyboard</span>
                            Teclado
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-obsidian-muted mb-0.5">Marca</label>
                            <input type="text" name="keyboard_brand" placeholder="Ej. Genius, Dell, HP" class="w-full px-2.5 py-1.5 bg-black border border-obsidian-border rounded-lg text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-obsidian-muted mb-0.5">Serial</label>
                            <input type="text" name="keyboard_serial" placeholder="Serial de fábrica" class="w-full px-2.5 py-1.5 bg-black border border-obsidian-border rounded-lg text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                        </div>
                    </div>

                    <!-- 3. MOUSE -->
                    <div class="p-3 rounded-xl bg-[#040b15] border border-obsidian-border space-y-2">
                        <div class="text-xs font-bold text-white flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-cyan-400 text-sm">mouse</span>
                            Mouse / Ratón
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-obsidian-muted mb-0.5">Marca</label>
                            <input type="text" name="mouse_brand" placeholder="Ej. Genius, Logitech" class="w-full px-2.5 py-1.5 bg-black border border-obsidian-border rounded-lg text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-obsidian-muted mb-0.5">Serial</label>
                            <input type="text" name="mouse_serial" placeholder="Serial de fábrica" class="w-full px-2.5 py-1.5 bg-black border border-obsidian-border rounded-lg text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                        </div>
                    </div>

                    <!-- 4. PROTECTOR DE VOLTAJE -->
                    <div class="p-3 rounded-xl bg-[#040b15] border border-obsidian-border space-y-2">
                        <div class="text-xs font-bold text-white flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-cyan-400 text-sm">bolt</span>
                            Protector de Voltaje / Regulador
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-obsidian-muted mb-0.5">Marca</label>
                            <input type="text" name="surge_protector_brand" placeholder="Ej. Marsriva, CDP, APC" class="w-full px-2.5 py-1.5 bg-black border border-obsidian-border rounded-lg text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-obsidian-muted mb-0.5">Serial</label>
                            <input type="text" name="surge_protector_serial" placeholder="Serial de fábrica" class="w-full px-2.5 py-1.5 bg-black border border-obsidian-border rounded-lg text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECCIÓN: IDENTIFICACIÓN PRINCIPAL -->
            <div class="space-y-3 pt-2">
                <h3 class="text-xs font-mono uppercase font-bold text-cyan-400 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">badge</span>
                    Identificación Principal
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-mono text-cyan-400 mb-1 font-semibold uppercase">Nombre Etiqueta (Identidad Operativa) <span class="text-cyan-400">*</span></label>
                        <input type="text" name="name" required value="{{ old('name') }}" placeholder="Ej. JEFE DE OFICINA, TAQUILLA 01, ROUTER-WAN" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Código / Tag Patrimonial</label>
                        <input type="text" name="asset_tag" value="{{ old('asset_tag') }}" placeholder="Ej. IT-VS-0045" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>

                    <!-- SELECTOR ADAPTATIVO DE TIPO -->
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Tipo de Activo <span class="text-cyan-400">*</span></label>
                        <select name="type" id="typeSelector" required class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                            <!-- Opciones para PC -->
                            <optgroup id="pcTypeGroup" label="Computadoras">
                                <option value="desktop" {{ old('type') === 'desktop' ? 'selected' : '' }}>Desktop / Computadora de Escritorio</option>
                                <option value="laptop" {{ old('type') === 'laptop' ? 'selected' : '' }}>Laptop / Computadora Portátil</option>
                            </optgroup>
                            <!-- Opciones para Dispositivos de Red -->
                            <optgroup id="netTypeGroup" label="Dispositivos de Red & Infraestructura" class="hidden">
                                <option value="router" {{ old('type') === 'router' ? 'selected' : '' }}>Router Gateway</option>
                                <option value="switch" {{ old('type') === 'switch' ? 'selected' : '' }}>Switch de Red</option>
                                <option value="ip_phone" {{ old('type') === 'ip_phone' ? 'selected' : '' }}>Teléfono IP</option>
                                <option value="printer" {{ old('type') === 'printer' ? 'selected' : '' }}>Impresora de Red</option>
                                <option value="server" {{ old('type') === 'server' ? 'selected' : '' }}>Servidor Físico / Virtual</option>
                                <option value="access_point" {{ old('type') === 'access_point' ? 'selected' : '' }}>Punto de Acceso WiFi (AP)</option>
                                <option value="firewall" {{ old('type') === 'firewall' ? 'selected' : '' }}>Firewall / Seguridad</option>
                            </optgroup>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Marca / Fabricante</label>
                        <input type="text" name="brand" value="{{ old('brand') }}" placeholder="Ej. Lenovo, HP, Dell, Cisco" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Modelo Comercial</label>
                        <input type="text" name="model" value="{{ old('model') }}" placeholder="Ej. ThinkCentre M910s, Catalyst 2960" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Número de Serie de Fábrica</label>
                        <input type="text" name="serial_number" value="{{ old('serial_number') }}" placeholder="Ej. MJ09RSFL" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>
                </div>
            </div>

            <!-- SECCIÓN ADICIONAL PARA DISPOSITIVOS DE RED (PUERTOS Y ACCESO REMOTO) -->
            <div id="netDeviceFieldsSection" class="hidden space-y-3.5 pt-3 border-t border-purple-500/30 bg-purple-950/10 p-4 rounded-xl">
                <div class="flex items-center justify-between border-b border-purple-500/20 pb-2">
                    <h3 class="text-xs font-mono uppercase font-bold text-purple-400 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-sm">settings_ethernet</span>
                        Parámetros Técnicos &amp; Acceso Remoto
                    </h3>
                    <span class="text-[9.5px] font-mono px-2 py-0.5 rounded bg-purple-950 text-purple-300 border border-purple-500/30">NOC &amp; ITAM Parity</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Puertos de Red</label>
                        <input type="text" name="ports" value="{{ old('ports') }}" placeholder="Ej. 24x GE, 4x SFP ó 1x GE" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-purple-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Tipo de Acceso Remoto</label>
                        <select name="access_type" id="create-access-type" onchange="handleAccessChange(this.value)" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-purple-500 focus:outline-none">
                            <option value="SIN SOPORTE">SIN SOPORTE</option>
                            <option value="SSH">SSH</option>
                            <option value="TELNET">TELNET</option>
                            <option value="WEB">WEB / HTTP(S)</option>
                            <option value="VNC">VNC</option>
                            <option value="SSH+VNC">Dual: SSH (22) + VNC (5900)</option>
                        </select>
                    </div>
                    <div id="create-single-port-wrapper">
                        <label class="block text-xs font-mono text-obsidian-muted mb-1" id="create-access-port-label">Puerto de Acceso</label>
                        <input type="number" name="access_port" id="create-access-port" value="{{ old('access_port', 22) }}" min="1" max="65535" placeholder="Ej. 22, 23, 80" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-purple-500 focus:outline-none">
                    </div>
                </div>

                <!-- PUERTOS DUALES DEDICADOS (SSH + VNC) -->
                <div id="create-dual-ports-wrapper" class="hidden grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-mono text-emerald-400 mb-1 font-semibold uppercase">Puerto SSH</label>
                        <input type="number" name="ssh_port" id="create-ssh-port" value="{{ old('ssh_port', 22) }}" min="1" max="65535" class="w-full px-3 py-2 bg-[#040b15] border border-emerald-500/40 rounded-xl text-xs font-mono text-white focus:border-emerald-400 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-mono text-purple-400 mb-1 font-semibold uppercase">Puerto VNC</label>
                        <input type="number" name="vnc_port" id="create-vnc-port" value="{{ old('vnc_port', 5900) }}" min="1" max="65535" class="w-full px-3 py-2 bg-[#040b15] border border-purple-500/40 rounded-xl text-xs font-mono text-white focus:border-purple-400 focus:outline-none">
                    </div>
                </div>

                <!-- CREDENCIALES VNC -->
                <div id="create-vnc-wrapper" class="hidden p-3 rounded-xl bg-[#020610] border border-purple-500/20 space-y-2 font-mono">
                    <div class="flex items-center justify-between border-b border-purple-500/20 pb-1.5">
                        <div class="flex items-center gap-1.5 text-xs font-bold text-purple-400">
                            <span class="material-symbols-outlined text-sm">desktop_windows</span>
                            <span>Contraseña VNC (Cifrado AES-256)</span>
                        </div>
                        <span class="text-[9px] px-2 py-0.2 rounded bg-purple-500/10 text-purple-300 border border-purple-500/30">Auto-login noVNC</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <div>
                            <label class="block text-[10.5px] text-slate-300 mb-0.5">Contraseña VNC</label>
                            <div class="relative">
                                <input type="password" name="vnc_password" placeholder="Contraseña de escritorio remoto" class="w-full px-2.5 py-1.5 rounded-lg bg-[#040b15] border border-obsidian-border text-white text-xs font-mono focus:border-purple-400 focus:outline-none pr-7">
                                <button type="button" onclick="togglePasswordVisibility(this)" class="absolute right-1.5 top-1.5 text-slate-400 hover:text-white" tabindex="-1">
                                    <span class="material-symbols-outlined text-xs">visibility</span>
                                </button>
                            </div>
                        </div>
                        <div class="flex items-end pb-1.5">
                            <p class="text-[10px] text-slate-400 leading-tight">
                                Se guardará cifrada para inicio de sesión directo en la consola web noVNC.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- CREDENCIALES SSH -->
                <div id="create-ssh-wrapper" class="p-3 rounded-xl bg-[#020610] border border-emerald-500/20 space-y-2 font-mono">
                    <div class="flex items-center justify-between border-b border-emerald-500/20 pb-1.5">
                        <div class="flex items-center gap-1.5 text-xs font-bold text-emerald-400">
                            <span class="material-symbols-outlined text-sm">key</span>
                            <span>Credenciales de Gestión SSH (Cifrado AES-256)</span>
                        </div>
                        <span class="text-[9px] px-2 py-0.2 rounded bg-emerald-500/10 text-emerald-300 border border-emerald-500/30">Terminal SSH</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <div>
                            <label class="block text-[10.5px] text-slate-300 mb-0.5">Usuario SSH</label>
                            <input type="text" name="ssh_username" value="{{ old('ssh_username') }}" placeholder="admin / cisco" class="w-full px-2.5 py-1.5 rounded-lg bg-[#040b15] border border-obsidian-border text-white text-xs font-mono focus:border-emerald-400 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[10.5px] text-slate-300 mb-0.5">Contraseña</label>
                            <div class="relative">
                                <input type="password" name="ssh_password" placeholder="Contraseña SSH" class="w-full px-2.5 py-1.5 rounded-lg bg-[#040b15] border border-obsidian-border text-white text-xs font-mono focus:border-emerald-400 focus:outline-none pr-7">
                                <button type="button" onclick="togglePasswordVisibility(this)" class="absolute right-1.5 top-1.5 text-slate-400 hover:text-white" tabindex="-1">
                                    <span class="material-symbols-outlined text-xs">visibility</span>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10.5px] text-slate-300 mb-0.5">Enable Secret</label>
                            <div class="relative">
                                <input type="password" name="ssh_enable_secret" placeholder="Clave Cisco" class="w-full px-2.5 py-1.5 rounded-lg bg-[#040b15] border border-obsidian-border text-white text-xs font-mono focus:border-emerald-400 focus:outline-none pr-7">
                                <button type="button" onclick="togglePasswordVisibility(this)" class="absolute right-1.5 top-1.5 text-slate-400 hover:text-white" tabindex="-1">
                                    <span class="material-symbols-outlined text-xs">visibility</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECCIÓN: UBICACIÓN Y ESTADO -->
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
                                <option value="{{ $s->id }}" {{ old('monitored_site_id') == $s->id ? 'selected' : '' }}>
                                    {{ $s->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Ubicación Detallada</label>
                        <input type="text" name="location_detail" value="{{ old('location_detail') }}" placeholder="Ej. Piso 2, Oficina de Finanzas, Puesto 4" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Estado Inicial <span class="text-cyan-400">*</span></label>
                        <select name="status" required class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                            <option value="in_stock" {{ old('status') === 'in_stock' ? 'selected' : '' }}>En Stock (Disponible)</option>
                            <option value="assigned" {{ old('status', 'assigned') === 'assigned' ? 'selected' : '' }}>Asignado (En Uso)</option>
                            <option value="maintenance" {{ old('status') === 'maintenance' ? 'selected' : '' }}>En Mantenimiento</option>
                            <option value="damaged" {{ old('status') === 'damaged' ? 'selected' : '' }}>Dañado / Para Baja</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- SECCIÓN: IDENTIDAD DE RED -->
            <div class="space-y-3 pt-3 border-t border-obsidian-border">
                <h3 class="text-xs font-mono uppercase font-bold text-cyan-400 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">lan</span>
                    Direccionamiento de Red
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Dirección IP</label>
                        <input type="text" name="ip_address" value="{{ old('ip_address') }}" placeholder="Ej. 192.168.107.155" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Dirección MAC</label>
                        <input type="text" name="mac_address" value="{{ old('mac_address') }}" placeholder="Ej. 18:66:DA:55:66:21" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>
                </div>
            </div>

            <!-- SECCIÓN: RESPONSABLE & ASIGNACIÓN (ACTA UINF-4.05 / LDAP) -->
            <div class="space-y-3 pt-3 border-t border-obsidian-border">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-mono uppercase font-bold text-cyan-400 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-sm">badge</span>
                        Responsable &amp; Asignación (Acta UINF-4.05)
                    </h3>
                    <span class="text-[9.5px] font-mono px-2 py-0.5 rounded bg-cyan-950 text-cyan-300 border border-cyan-500/30">Opcional</span>
                </div>

                <x-ldap-user-picker 
                    id="create-asset-picker" 
                    name="custodian" 
                    label="Buscar Trabajador en Directorio Activo LDAP:" 
                    :show-details="true" 
                    :can-release="false" 
                    theme="cyan" 
                />
            </div>

            <!-- SECCIÓN: OBSERVACIONES -->
            <div class="space-y-3 pt-3 border-t border-obsidian-border">
                <label class="block text-xs font-mono text-obsidian-muted mb-1">Notas u Observaciones del Activo</label>
                <textarea name="notes" rows="3" placeholder="Detalles de procedencia, configuración o historial..." class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">{{ old('notes') }}</textarea>
            </div>

            <!-- BOTONES -->
            <div class="flex items-center justify-end gap-2 pt-4 border-t border-obsidian-border">
                <a href="{{ route('admin.devices.index') }}" class="px-4 py-2 rounded-xl border border-obsidian-border text-obsidian-muted hover:text-white font-mono text-xs transition">
                    Cancelar
                </a>
                <button type="submit" class="px-5 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-black font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-lg shadow-cyan-500/20">
                    <span class="material-symbols-outlined text-base">save</span>
                    <span>Guardar Activo</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function onCategoryChanged(category) {
        const lblPc = document.getElementById('lblCategoryPc');
        const lblNet = document.getElementById('lblCategoryNet');
        const pcPeripheralsBox = document.getElementById('pcPeripheralsBox');
        const pcTypeGroup = document.getElementById('pcTypeGroup');
        const netTypeGroup = document.getElementById('netTypeGroup');
        const netDeviceFieldsSection = document.getElementById('netDeviceFieldsSection');
        const typeSelector = document.getElementById('typeSelector');

        if (category === 'pc') {
            lblPc.className = 'cursor-pointer p-4 rounded-xl border-2 border-cyan-500 bg-cyan-500/10 flex items-start gap-3 transition';
            lblNet.className = 'cursor-pointer p-4 rounded-xl border-2 border-obsidian-border bg-obsidian-panel/50 hover:border-purple-500/50 flex items-start gap-3 transition';
            pcPeripheralsBox.classList.remove('hidden');
            pcTypeGroup.classList.remove('hidden');
            netTypeGroup.classList.add('hidden');
            netDeviceFieldsSection.classList.add('hidden');
            typeSelector.value = 'desktop';
        } else {
            lblNet.className = 'cursor-pointer p-4 rounded-xl border-2 border-purple-500 bg-purple-500/10 flex items-start gap-3 transition';
            lblPc.className = 'cursor-pointer p-4 rounded-xl border-2 border-obsidian-border bg-obsidian-panel/50 hover:border-cyan-500/50 flex items-start gap-3 transition';
            pcPeripheralsBox.classList.add('hidden');
            pcTypeGroup.classList.add('hidden');
            netTypeGroup.classList.remove('hidden');
            netDeviceFieldsSection.classList.remove('hidden');
            typeSelector.value = 'switch';
            // Desmarcar periféricos si cambia a red
            const check = document.getElementById('includePeripheralsCheck');
            if (check) {
                check.checked = false;
                togglePeripheralsFields(false);
            }
        }
    }

    function togglePeripheralsFields(show) {
        const sec = document.getElementById('peripheralsFieldsSection');
        if (show) {
            sec.classList.remove('hidden');
        } else {
            sec.classList.add('hidden');
        }
    }

    function handleAccessChange(val) {
        const singlePortWrapper = document.getElementById('create-single-port-wrapper');
        const dualPortsWrapper = document.getElementById('create-dual-ports-wrapper');
        const portInput = document.getElementById('create-access-port');
        const portLabel = document.getElementById('create-access-port-label');
        const vncWrapper = document.getElementById('create-vnc-wrapper');
        const sshWrapper = document.getElementById('create-ssh-wrapper');

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
                if (portInput) portInput.value = 22;
                if (portLabel) portLabel.textContent = 'Puerto SSH';
                if (vncWrapper) vncWrapper.classList.add('hidden');
                if (sshWrapper) sshWrapper.classList.remove('hidden');
            } else if (upper === 'TELNET') {
                if (portInput) portInput.value = 23;
                if (portLabel) portLabel.textContent = 'Puerto Telnet';
                if (vncWrapper) vncWrapper.classList.add('hidden');
                if (sshWrapper) sshWrapper.classList.remove('hidden');
            } else if (upper === 'WEB') {
                if (portInput) portInput.value = 80;
                if (portLabel) portLabel.textContent = 'Puerto Web (HTTP/S)';
                if (vncWrapper) vncWrapper.classList.add('hidden');
                if (sshWrapper) sshWrapper.classList.add('hidden');
            } else if (upper === 'VNC') {
                if (portInput) portInput.value = 5900;
                if (portLabel) portLabel.textContent = 'Puerto VNC';
                if (vncWrapper) vncWrapper.classList.remove('hidden');
                if (sshWrapper) sshWrapper.classList.add('hidden');
            } else {
                if (portInput) portInput.value = '';
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
</script>
@endsection
