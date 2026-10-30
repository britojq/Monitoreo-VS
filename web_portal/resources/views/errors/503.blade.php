<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="30">
    <title>Mantenimiento Programado • Sistema de Monitoreo</title>
    
    <style>
        *, ::after, ::before { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #030712;
            color: #f3f4f6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .container {
            max-width: 38rem;
            width: 100%;
            background: rgba(17, 24, 39, 0.85);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 1rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
            overflow: hidden;
            backdrop-filter: blur(12px);
        }
        .header-bar {
            background: linear-gradient(90deg, rgba(30, 58, 138, 0.8), rgba(15, 23, 42, 0.9));
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(59, 130, 246, 0.25);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .header-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.875rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            color: #93c5fd;
            text-transform: uppercase;
        }
        .led-indicator {
            width: 0.625rem;
            height: 0.625rem;
            border-radius: 9999px;
            background-color: #3b82f6;
            box-shadow: 0 0 10px #3b82f6;
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.9); }
        }
        .status-tag {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.75rem;
            padding: 0.25rem 0.625rem;
            background: rgba(59, 130, 246, 0.15);
            border: 1px solid rgba(59, 130, 246, 0.35);
            border-radius: 0.375rem;
            color: #bfdbfe;
        }
        .content {
            padding: 2.25rem 2rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        .hero {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }
        .icon-box {
            flex-shrink: 0;
            width: 4rem;
            height: 4rem;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.2), rgba(30, 58, 138, 0.35));
            border: 1px solid rgba(59, 130, 246, 0.4);
            border-radius: 0.875rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #60a5fa;
        }
        .icon-box svg {
            width: 2.25rem;
            height: 2.25rem;
        }
        .title-group h1 {
            font-size: 1.35rem;
            font-weight: 700;
            color: #ffffff;
            line-height: 1.3;
        }
        .title-group p {
            font-size: 0.875rem;
            color: #94a3b8;
            margin-top: 0.25rem;
        }
        .message-card {
            background: rgba(15, 23, 42, 0.65);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 0.75rem;
            padding: 1.25rem;
            line-height: 1.6;
            font-size: 0.925rem;
            color: #cbd5e1;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
        }
        .info-item {
            background: rgba(15, 23, 42, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
        }
        .info-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            margin-bottom: 0.25rem;
        }
        .info-value {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.825rem;
            color: #e2e8f0;
            font-weight: 500;
        }
        .footer {
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            padding: 1rem 1.5rem;
            background: rgba(10, 15, 30, 0.5);
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.75rem;
            color: #64748b;
        }
        .refresh-hint {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-bar">
            <div class="header-title">
                <span class="led-indicator" title="Mantenimiento en curso"></span>
                <span>Centro de Operaciones & Monitoreo</span>
            </div>
            <div class="status-tag">HTTP 503 • SERVICE UNAVAILABLE</div>
        </div>

        <div class="content">
            <div class="hero">
                <div class="icon-box">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                </div>
                <div class="title-group">
                    <h1>Mantenimiento Programado del Servidor</h1>
                    <p>Actualización técnica y sincronización de arquitectura en curso</p>
                </div>
            </div>

            <div class="message-card">
                La plataforma web de monitoreo se encuentra temporalmente en modo de mantenimiento programado mientras se ejecutan tareas de actualización, sincronización de base de datos y certificación de integridad. El acceso de usuarios se restablecerá de forma automática tan pronto culmine el procedimiento.
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Estado de Plataforma</div>
                    <div class="info-value">Mantenimiento Activo</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Soporte Técnico</div>
                    <div class="info-value">División ATIT Valle Seco</div>
                </div>
            </div>
        </div>

        <div class="footer">
            <span>Infraestructura Corporativa • Monitoreo Valle Seco</span>
            <div class="refresh-hint">
                <span>Comprobación automática cada 30s</span>
            </div>
        </div>
    </div>
</body>
</html>
