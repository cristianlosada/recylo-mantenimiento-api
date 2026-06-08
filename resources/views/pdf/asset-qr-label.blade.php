<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Etiqueta QR - {{ $asset->code }}</title>
    <style>
        @page {
            margin: 0;
            size: 10cm 10cm;
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 1cm;
            text-align: center;
        }
        .qr-label {
            border: 3px solid #2563eb;
            padding: 15px;
            background: white;
            height: 8cm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .header {
            background: #2563eb;
            color: white;
            padding: 10px;
            margin: -15px -15px 15px -15px;
        }
        .company-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 3px;
        }
        .label-title {
            font-size: 12px;
            opacity: 0.9;
        }
        .qr-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 10px 0;
        }
        .qr-container img {
            max-width: 4.5cm;
            height: auto;
        }
        .asset-info {
            background: #f1f5f9;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
        }
        .asset-code {
            font-size: 18px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 5px;
        }
        .asset-name {
            font-size: 13px;
            color: #334155;
            margin-bottom: 3px;
        }
        .asset-category {
            font-size: 11px;
            color: #64748b;
        }
        .footer {
            font-size: 9px;
            color: #94a3b8;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 2px solid #e2e8f0;
        }
        .instruction {
            font-size: 10px;
            color: #475569;
            font-weight: bold;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="qr-label">
        <div class="header">
            <div class="company-name">{{ $asset->company->name }}</div>
            <div class="label-title">CÓDIGO QR DEL ACTIVO</div>
        </div>

        <div class="qr-container">
            <img src="{{ $qrCodeDataUri }}" alt="QR Code">
        </div>

        <div class="asset-info">
            <div class="asset-code">{{ $asset->code }}</div>
            <div class="asset-name">{{ $asset->name }}</div>
            @if($asset->category)
            <div class="asset-category">{{ $asset->category->name }}</div>
            @endif
        </div>

        <div class="instruction">
            📱 Escanea para reportar fallas o solicitar servicio
        </div>

        <div class="footer">
            {{ $asset->companySite->name ?? 'N/A' }} | {{ now()->format('m/Y') }}
        </div>
    </div>
</body>
</html>
