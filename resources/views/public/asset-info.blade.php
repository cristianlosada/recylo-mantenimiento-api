<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $asset->name }} - {{ $asset->company->name }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        .header .company {
            font-size: 14px;
            opacity: 0.9;
        }
        .asset-card {
            padding: 30px 20px;
        }
        .asset-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .asset-code {
            display: inline-block;
            background: #eff6ff;
            color: #2563eb;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 10px;
        }
        .asset-name {
            font-size: 22px;
            color: #1e293b;
            margin-bottom: 5px;
        }
        .asset-category {
            color: #64748b;
            font-size: 14px;
        }
        .info-section {
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .info-section h3 {
            color: #2563eb;
            font-size: 16px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        .info-section h3::before {
            content: '';
            width: 4px;
            height: 20px;
            background: #2563eb;
            margin-right: 10px;
            border-radius: 2px;
        }
        .info-row {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #475569;
            width: 40%;
            font-size: 14px;
        }
        .info-value {
            color: #1e293b;
            flex: 1;
            font-size: 14px;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-active {
            background: #dcfce7;
            color: #166534;
        }
        .badge-inactive {
            background: #fee2e2;
            color: #991b1b;
        }
        .cta-section {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            padding: 30px 20px;
            text-align: center;
            border-top: 2px solid #e0f2fe;
        }
        .cta-title {
            font-size: 18px;
            color: #1e293b;
            margin-bottom: 10px;
        }
        .cta-subtitle {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            padding: 14px 32px;
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.5);
        }
        .btn:active {
            transform: translateY(0);
        }
        .footer {
            text-align: center;
            padding: 20px;
            color: #94a3b8;
            font-size: 12px;
        }
        .alert {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px;
            border-radius: 8px;
        }
        .alert-title {
            font-weight: 600;
            color: #92400e;
            margin-bottom: 5px;
        }
        .alert-text {
            color: #78350f;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Información del Activo</h1>
            <div class="company">{{ $asset->company->name }}</div>
        </div>

        <div class="asset-card">
            <div class="asset-header">
                <div class="asset-code">{{ $asset->code }}</div>
                <h2 class="asset-name">{{ $asset->name }}</h2>
                @if($asset->category)
                <div class="asset-category">{{ $asset->category->name }}</div>
                @endif
            </div>

            <div class="info-section">
                <h3>Información General</h3>
                <div class="info-row">
                    <div class="info-label">Estado:</div>
                    <div class="info-value">
                        <span class="badge {{ $asset->is_active ? 'badge-active' : 'badge-inactive' }}">
                            {{ $asset->is_active ? 'Activo' : 'Inactivo' }}
                        </span>
                    </div>
                </div>
                @if($asset->status)
                <div class="info-row">
                    <div class="info-label">Condición:</div>
                    <div class="info-value">{{ $asset->status->name }}</div>
                </div>
                @endif
                @if($asset->priority)
                <div class="info-row">
                    <div class="info-label">Prioridad:</div>
                    <div class="info-value">{{ $asset->priority->name }}</div>
                </div>
                @endif
            </div>

            @if($asset->description)
            <div class="info-section">
                <h3>Descripción</h3>
                <p style="color: #475569; line-height: 1.6;">{{ $asset->description }}</p>
            </div>
            @endif

            <div class="info-section">
                <h3>Ubicación</h3>
                @if($asset->companySite)
                <div class="info-row">
                    <div class="info-label">Sitio:</div>
                    <div class="info-value">{{ $asset->companySite->name }}</div>
                </div>
                @endif
                @if($asset->location_path)
                <div class="info-row">
                    <div class="info-label">Ruta:</div>
                    <div class="info-value">{{ $asset->location_path }}</div>
                </div>
                @endif
                @if($asset->location_details)
                <div class="info-row">
                    <div class="info-label">Detalles:</div>
                    <div class="info-value">{{ $asset->location_details }}</div>
                </div>
                @endif
            </div>

            @if($asset->brand || $asset->model || $asset->serial_number)
            <div class="info-section">
                <h3>Información Técnica</h3>
                @if($asset->brand)
                <div class="info-row">
                    <div class="info-label">Marca:</div>
                    <div class="info-value">{{ $asset->brand }}</div>
                </div>
                @endif
                @if($asset->model)
                <div class="info-row">
                    <div class="info-label">Modelo:</div>
                    <div class="info-value">{{ $asset->model }}</div>
                </div>
                @endif
                @if($asset->serial_number)
                <div class="info-row">
                    <div class="info-label">Serie:</div>
                    <div class="info-value">{{ $asset->serial_number }}</div>
                </div>
                @endif
            </div>
            @endif
        </div>

        @if($asset->is_active)
        <div class="cta-section">
            <div class="cta-title">¿Necesitas reportar un problema?</div>
            <div class="cta-subtitle">Crea una solicitud de servicio de forma rápida y sencilla</div>
            <a href="{{ config('app.frontend_url', config('app.url')) }}/public/work-request/{{ $asset->code }}" class="btn">
                📝 Crear Solicitud de Servicio
            </a>
        </div>
        @else
        <div class="alert">
            <div class="alert-title">⚠️ Activo Inactivo</div>
            <div class="alert-text">Este activo está marcado como inactivo. No es posible crear solicitudes en este momento.</div>
        </div>
        @endif

        <div class="footer">
            {{ config('app.name') }} © {{ date('Y') }}
        </div>
    </div>
</body>
</html>
