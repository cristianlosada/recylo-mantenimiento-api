<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Bitácora {{ $project->code }}</title>
    <style>
        @page { margin: 1.5cm 1.8cm 1.8cm 1.8cm; }
        * { box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #1e293b;
            background: #fff;
            margin: 0; padding: 0;
        }
        a { color: inherit; text-decoration: none; }

        /* Header */
        .header-table { width: 100%; border-collapse: collapse; border-bottom: 3px solid #4338ca; padding-bottom: 10px; margin-bottom: 14px; }
        .header-table td { padding: 0; vertical-align: middle; }
        .logo-cell { width: 110px; }
        .logo-cell img { max-height: 46px; max-width: 100px; }
        .logo-placeholder { display: inline-block; width: 44px; height: 44px; background: #4338ca; border-radius: 6px; text-align: center; line-height: 44px; color: #fff; font-size: 20px; font-weight: bold; }
        .title-cell { padding-left: 10px; }
        .doc-type { font-size: 17px; font-weight: bold; color: #4338ca; letter-spacing: 0.3px; }
        .company-sub { font-size: 10px; color: #64748b; margin-top: 1px; }
        .meta-cell { text-align: right; font-size: 9px; color: #64748b; }
        .meta-cell .code-box { display: inline-block; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 4px; padding: 2px 7px; font-weight: bold; color: #1d4ed8; font-size: 11px; margin-bottom: 3px; }

        /* Project info */
        .project-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 14px; margin-bottom: 14px; }
        .project-card-title { font-size: 13px; font-weight: bold; color: #1e293b; margin-bottom: 6px; }
        .info-grid { width: 100%; border-collapse: collapse; }
        .info-grid td { padding: 2px 10px 2px 0; vertical-align: top; font-size: 9.5px; }
        .info-label { color: #64748b; font-weight: bold; width: 100px; }

        /* Section header */
        .section-header { background: #4338ca; color: #fff; font-size: 10px; font-weight: bold; padding: 5px 10px; border-radius: 4px; margin: 14px 0 8px 0; letter-spacing: 0.3px; }

        /* Log table */
        .log-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .log-table th { background: #eff6ff; color: #1e40af; font-size: 9px; font-weight: bold; padding: 5px 7px; text-align: left; border-bottom: 2px solid #bfdbfe; }
        .log-table td { font-size: 9.5px; padding: 6px 7px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        .log-table tr:nth-child(even) td { background: #f8fafc; }
        .badge { display: inline-block; padding: 1px 6px; border-radius: 10px; font-size: 8.5px; font-weight: bold; }
        .badge-registered { background: #f1f5f9; color: #475569; }
        .badge-reviewed { background: #fef9c3; color: #854d0e; }
        .badge-validated { background: #dcfce7; color: #166534; }

        /* Summary */
        .summary-box { border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 14px; margin-top: 14px; }
        .summary-title { font-size: 11px; font-weight: bold; color: #1e293b; margin-bottom: 6px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 3px; font-size: 9.5px; }
        .summary-label { color: #64748b; }
        .summary-value { font-weight: bold; color: #1e293b; }

        /* Footer */
        .footer { margin-top: 20px; padding-top: 8px; border-top: 1px solid #e2e8f0; font-size: 8.5px; color: #94a3b8; display: flex; justify-content: space-between; }
    </style>
</head>
<body>

<!-- Header -->
<table class="header-table">
    <tr>
        <td class="logo-cell">
            @if($logoBase64)
                <img src="{{ $logoBase64 }}" alt="Logo">
            @else
                <span class="logo-placeholder">Q</span>
            @endif
        </td>
        <td class="title-cell">
            <div class="doc-type">Bitácora de Proyecto</div>
            <div class="company-sub">{{ $project->company->name ?? 'Recylo CMMS' }}</div>
        </td>
        <td class="meta-cell">
            <div class="code-box">{{ $project->code }}</div><br>
            <div>Generado: {{ now()->format('d/m/Y H:i') }}</div>
        </td>
    </tr>
</table>

<!-- Project Info -->
<div class="project-card">
    <div class="project-card-title">{{ $project->name }}</div>
    <table class="info-grid">
        <tr>
            <td class="info-label">Tipo</td>
            <td>{{ $project->type?->name ?? '—' }}</td>
            <td class="info-label">Estado</td>
            <td>{{ $project->status?->name ?? '—' }}</td>
            <td class="info-label">Líder</td>
            <td>{{ $project->leader?->full_name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="info-label">Inicio planificado</td>
            <td>{{ $project->planned_start_date ? \Carbon\Carbon::parse($project->planned_start_date)->format('d/m/Y') : '—' }}</td>
            <td class="info-label">Fin planificado</td>
            <td>{{ $project->planned_end_date ? \Carbon\Carbon::parse($project->planned_end_date)->format('d/m/Y') : '—' }}</td>
            <td class="info-label">Avance</td>
            <td>{{ number_format($project->progress_percent ?? 0, 1) }}%</td>
        </tr>
        @if($filters['date_from'] ?? null)
        <tr>
            <td class="info-label">Período filtrado</td>
            <td colspan="5">{{ $filters['date_from'] }} al {{ $filters['date_to'] ?? now()->format('Y-m-d') }}</td>
        </tr>
        @endif
    </table>
</div>

<!-- Logs -->
<div class="section-header">Registros de Bitácora ({{ count($logs) }})</div>

@if(count($logs) === 0)
    <p style="color:#94a3b8; font-size:10px; text-align:center; padding:20px;">No hay registros de bitácora para los filtros seleccionados.</p>
@else
<table class="log-table">
    <thead>
        <tr>
            <th style="width:70px">Fecha</th>
            <th style="width:90px">Persona</th>
            <th style="width:70px">Fase</th>
            <th style="width:35px">Horas</th>
            <th>Actividad</th>
            <th>Resultado del día</th>
            <th style="width:45px">Avance%</th>
            <th style="width:55px">Estado</th>
        </tr>
    </thead>
    <tbody>
        @foreach($logs as $log)
        <tr>
            <td>{{ \Carbon\Carbon::parse($log->log_date)->format('d/m/Y') }}</td>
            <td>{{ $log->user?->full_name ?? '—' }}</td>
            <td>{{ $log->phase?->name ?? '—' }}</td>
            <td style="text-align:center; font-weight:bold;">{{ number_format($log->hours_worked, 1) }}</td>
            <td>{{ $log->activity_description }}</td>
            <td>{{ $log->result_description }}</td>
            <td style="text-align:center;">
                @if($log->progress_reported !== null)
                    {{ number_format($log->progress_reported, 0) }}%
                @else
                    —
                @endif
            </td>
            <td>
                <span class="badge badge-{{ $log->status?->code ?? 'registered' }}">
                    {{ $log->status?->name ?? 'Registrado' }}
                </span>
            </td>
        </tr>
        @if($log->findings)
        <tr>
            <td colspan="8" style="padding: 2px 7px 6px 7px; font-size:9px; color:#64748b; background:#fafafa; border-bottom: 1px solid #f1f5f9;">
                <strong>Novedades:</strong> {{ $log->findings }}
            </td>
        </tr>
        @endif
        @endforeach
    </tbody>
</table>
@endif

<!-- Summary -->
<div class="summary-box">
    <div class="summary-title">Resumen del período</div>
    <table style="width:100%; border-collapse:collapse;">
        <tr>
            <td style="width:25%; padding:3px 0; font-size:9.5px;"><span style="color:#64748b;">Total registros:</span> <strong>{{ count($logs) }}</strong></td>
            <td style="width:25%; padding:3px 0; font-size:9.5px;"><span style="color:#64748b;">Total horas:</span> <strong>{{ number_format($totalHours, 1) }}h</strong></td>
            <td style="width:25%; padding:3px 0; font-size:9.5px;"><span style="color:#64748b;">Personas involucradas:</span> <strong>{{ $uniquePersons }}</strong></td>
            @if($totalLaborCost > 0)
            <td style="width:25%; padding:3px 0; font-size:9.5px;"><span style="color:#64748b;">Costo MO:</span> <strong>${{ number_format($totalLaborCost, 0, ',', '.') }}</strong></td>
            @endif
        </tr>
    </table>
</div>

<!-- Footer -->
<div class="footer">
    <span>{{ $project->code }} — Bitácora de Proyecto</span>
    <span>Recylo CMMS © {{ now()->year }}</span>
</div>

</body>
</html>
