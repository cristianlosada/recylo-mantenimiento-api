<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Hoja de Vida - {{ $asset->code }}</title>
    <style>
        @page {
            margin: 2cm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #5b7939;
            padding-bottom: 15px;
        }
        .header h1 {
            color: #5b7939;
            font-size: 24px;
            margin: 0 0 5px 0;
        }
        .header .subtitle {
            color: #64748b;
            font-size: 14px;
        }
        .company-info {
            background: #f1f5f9;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            background: #5b7939;
            color: white;
            padding: 8px 12px;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .info-grid {
            display: table;
            width: 100%;
            border-collapse: collapse;
        }
        .info-row {
            display: table-row;
        }
        .info-label {
            display: table-cell;
            font-weight: bold;
            padding: 6px 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            width: 30%;
        }
        .info-value {
            display: table-cell;
            padding: 6px 10px;
            border: 1px solid #e2e8f0;
        }
        .qr-section {
            text-align: center;
            margin-top: 30px;
            padding: 20px;
            border: 2px dashed #cbd5e1;
        }
        .qr-section img {
            max-width: 200px;
            height: auto;
        }
        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 9px;
            color: #94a3b8;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
        }
        .specs-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .specs-table th,
        .specs-table td {
            border: 1px solid #e2e8f0;
            padding: 6px 8px;
            text-align: left;
        }
        .specs-table th {
            background: #f8fafc;
            font-weight: bold;
        }
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
        }
        .badge-active {
            background: #dcfce7;
            color: #166534;
        }
        .badge-inactive {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>HOJA DE VIDA DEL ACTIVO</h1>
        <div class="subtitle">{{ $asset->code }} - {{ $asset->name }}</div>
    </div>

    <div class="company-info">
        <strong>{{ $asset->company->name }}</strong><br>
        Sitio: {{ $asset->companySite->name ?? 'N/A' }}<br>
        Generado: {{ now()->format('d/m/Y H:i') }}
    </div>

    <!-- Información General -->
    <div class="section">
        <div class="section-title">INFORMACIÓN GENERAL</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Código</div>
                <div class="info-value">{{ $asset->code }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Nombre</div>
                <div class="info-value">{{ $asset->name }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Categoría</div>
                <div class="info-value">{{ $asset->category->name ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Estado</div>
                <div class="info-value">{{ $asset->status->name ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Prioridad</div>
                <div class="info-value">{{ $asset->priority->name ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Estado Activo</div>
                <div class="info-value">
                    <span class="badge {{ $asset->is_active ? 'badge-active' : 'badge-inactive' }}">
                        {{ $asset->is_active ? 'ACTIVO' : 'INACTIVO' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Descripción -->
    @if($asset->description)
    <div class="section">
        <div class="section-title">DESCRIPCIÓN</div>
        <div style="padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0;">
            {{ $asset->description }}
        </div>
    </div>
    @endif

    <!-- Ubicación -->
    <div class="section">
        <div class="section-title">UBICACIÓN</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Sitio</div>
                <div class="info-value">{{ $asset->companySite->name ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Ruta Completa</div>
                <div class="info-value">{{ $asset->location_path ?? 'N/A' }}</div>
            </div>
            @if($asset->location_details)
            <div class="info-row">
                <div class="info-label">Detalles</div>
                <div class="info-value">{{ $asset->location_details }}</div>
            </div>
            @endif
        </div>
    </div>

    <!-- Información Técnica -->
    @if($asset->brand || $asset->model || $asset->serial_number || $asset->manufacturer)
    <div class="section">
        <div class="section-title">INFORMACIÓN TÉCNICA</div>
        <div class="info-grid">
            @if($asset->manufacturer)
            <div class="info-row">
                <div class="info-label">Fabricante</div>
                <div class="info-value">{{ $asset->manufacturer }}</div>
            </div>
            @endif
            @if($asset->brand)
            <div class="info-row">
                <div class="info-label">Marca</div>
                <div class="info-value">{{ $asset->brand }}</div>
            </div>
            @endif
            @if($asset->model)
            <div class="info-row">
                <div class="info-label">Modelo</div>
                <div class="info-value">{{ $asset->model }}</div>
            </div>
            @endif
            @if($asset->serial_number)
            <div class="info-row">
                <div class="info-label">Número de Serie</div>
                <div class="info-value">{{ $asset->serial_number }}</div>
            </div>
            @endif
            @if($asset->manufacturing_date)
            <div class="info-row">
                <div class="info-label">Fecha de Fabricación</div>
                <div class="info-value">{{ \Carbon\Carbon::parse($asset->manufacturing_date)->format('d/m/Y') }}</div>
            </div>
            @endif
            @if($asset->installation_date)
            <div class="info-row">
                <div class="info-label">Fecha de Instalación</div>
                <div class="info-value">{{ \Carbon\Carbon::parse($asset->installation_date)->format('d/m/Y') }}</div>
            </div>
            @endif
            @if($asset->warranty_expiration)
            <div class="info-row">
                <div class="info-label">Vencimiento Garantía</div>
                <div class="info-value">{{ \Carbon\Carbon::parse($asset->warranty_expiration)->format('d/m/Y') }}</div>
            </div>
            @endif
        </div>
    </div>
    @endif

    <!-- Especificaciones Técnicas -->
    @if($asset->specifications && $asset->specifications->count() > 0)
    <div class="section">
        <div class="section-title">ESPECIFICACIONES TÉCNICAS</div>
        <table class="specs-table">
            <thead>
                <tr>
                    <th style="width: 30%;">Nombre</th>
                    <th style="width: 40%;">Valor</th>
                    <th style="width: 15%;">Unidad</th>
                    <th style="width: 15%;">Categoría</th>
                </tr>
            </thead>
            <tbody>
                @foreach($asset->specifications as $spec)
                <tr>
                    <td>{{ $spec->name }}</td>
                    <td>{{ $spec->value }}</td>
                    <td>{{ $spec->unit ?? '-' }}</td>
                    <td>{{ $spec->category ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Código QR -->
    @if($qrBase64)
    <div class="qr-section">
        <div style="font-weight: bold; margin-bottom: 10px; color: #5b7939;">CÓDIGO QR</div>
        <img src="{{ $qrBase64 }}" alt="QR Code" style="max-width: 200px; height: auto;">
        <div style="margin-top: 10px; font-size: 10px; color: #64748b;">
            Escanea este código para acceder a la información del activo y crear solicitudes de servicio
        </div>
    </div>
    @endif

    <div class="footer">
        Documento generado por {{ config('app.name') }} - {{ now()->format('d/m/Y H:i:s') }}
    </div>
</body>
</html>
