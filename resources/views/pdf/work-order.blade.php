<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Orden {{ $workOrder->code }}</title>
    <style>
        @page { margin-top:1.5cm; margin-right:1.8cm; margin-bottom:1.8cm; margin-left:1.8cm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size:10px; line-height:1.4; color:#1e293b; background:#fff; margin:0; padding:0; }
        a { color: inherit; text-decoration: none; }

        .header-table { width:100%; border-collapse:collapse; border-bottom:3px solid #1d4ed8; padding-bottom:10px; margin-bottom:14px; }
        .header-table td { padding:0; vertical-align:middle; }
        .logo-cell { width:110px; }
        .logo-cell img { max-height:46px; max-width:100px; }
        .logo-placeholder { display:inline-block; width:44px; height:44px; background:#1d4ed8; border-radius:6px; text-align:center; line-height:44px; color:#fff; font-size:20px; font-weight:bold; }
        .title-cell { padding-left:10px; }
        .doc-type { font-size:17px; font-weight:bold; color:#1d4ed8; letter-spacing:0.3px; }
        .company-sub { font-size:10px; color:#64748b; margin-top:1px; }
        .code-cell { text-align:right; }
        .code-badge { display:inline-block; background:#1d4ed8; color:#fff; padding:6px 14px; border-radius:5px; font-size:12px; font-weight:bold; letter-spacing:0.8px; }
        .gen-date { font-size:8px; color:#94a3b8; margin-top:4px; }

        .wo-title { font-size:13px; font-weight:bold; color:#1e293b; margin-bottom:12px; padding:7px 10px 7px 13px; background:#f1f5f9; border-left:4px solid #1d4ed8; }

        .status-table { width:100%; border-collapse:collapse; margin-bottom:14px; border:1px solid #e2e8f0; }
        .status-table td { padding:7px 11px; vertical-align:top; border-right:1px solid #e2e8f0; }
        .status-table td:last-child { border-right:none; }
        .s-lbl { font-size:7.5px; text-transform:uppercase; letter-spacing:0.5px; color:#94a3b8; font-weight:bold; margin-bottom:3px; }
        .s-val { font-size:11px; font-weight:bold; color:#1e293b; }

        .badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:9px; font-weight:bold; }
        .b-pending    { background:#fef9c3; color:#854d0e; }
        .b-scheduled  { background:#e0f2fe; color:#0369a1; }
        .b-in_progress{ background:#dbeafe; color:#1e40af; }
        .b-paused     { background:#f3e8ff; color:#7e22ce; }
        .b-completed  { background:#dcfce7; color:#166534; }
        .b-validated  { background:#f0fdf4; color:#15803d; }
        .b-cancelled  { background:#f1f5f9; color:#475569; }
        .b-emergency  { background:#fee2e2; color:#991b1b; }

        .p-critical { color:#dc2626; }
        .p-high     { color:#ea580c; }
        .p-medium   { color:#ca8a04; }
        .p-low      { color:#16a34a; }

        .sec { margin-bottom:12px; }
        .sec-head { background:#1d4ed8; color:#fff; font-size:9px; font-weight:bold; text-transform:uppercase; letter-spacing:0.5px; padding:4px 10px; }
        .sec-head-gray { background:#475569; color:#fff; font-size:9px; font-weight:bold; text-transform:uppercase; letter-spacing:0.5px; padding:4px 10px; }

        .info-table { width:100%; border-collapse:collapse; }
        .info-table td { border:1px solid #e2e8f0; border-top:none; padding:5px 10px; vertical-align:middle; color:#1e293b; font-size:10px; }
        .info-table .lbl { background:#f8fafc; font-weight:bold; color:#475569; font-size:9px; white-space:nowrap; }

        .dates-table { width:100%; border-collapse:collapse; table-layout:fixed; }
        .dates-table td { border:1px solid #e2e8f0; border-top:none; padding:5px 10px; vertical-align:middle; color:#1e293b; font-size:10px; white-space:nowrap; }
        .dates-table .lbl { background:#f8fafc; font-weight:bold; color:#475569; font-size:9px; width:22%; }
        .dates-table .val { width:28%; }

        .two-col-table { width:100%; border-collapse:separate; border-spacing:0; margin-bottom:12px; }
        .two-col-table > tbody > tr > td { vertical-align:top; padding:0; }
        .col-l { width:49%; padding-right:4px; }
        .col-r { width:51%; padding-left:4px; }

        .cost-table { width:100%; border-collapse:collapse; }
        .cost-table th { background:#f1f5f9; border:1px solid #e2e8f0; border-top:none; padding:4px 8px; font-size:8px; color:#64748b; text-transform:uppercase; text-align:left; }
        .cost-table td { border:1px solid #e2e8f0; border-top:none; padding:5px 8px; vertical-align:middle; color:#1e293b; font-size:10px; }
        .cost-table tr:nth-child(even) td { background:#f8fafc; }
        .cost-highlight { font-weight:bold; color:#1d4ed8; }

        .chk-table { width:100%; border-collapse:collapse; }
        .chk-table th { background:#f1f5f9; border:1px solid #e2e8f0; border-top:none; padding:4px 8px; font-size:8px; color:#64748b; text-transform:uppercase; text-align:left; }
        .chk-table td { border:1px solid #e2e8f0; border-top:none; padding:5px 8px; vertical-align:middle; color:#1e293b; }
        .chk-table tr:nth-child(even) td { background:#f8fafc; }
        .chk-box { display:inline-block; width:12px; height:12px; border:1.5px solid #94a3b8; border-radius:2px; text-align:center; line-height:11px; font-size:8px; }
        .chk-box.done { background:#dcfce7; border-color:#16a34a; color:#16a34a; }

        .team-table { width:100%; border-collapse:collapse; }
        .team-table td { border:1px solid #e2e8f0; border-top:none; padding:5px 8px; vertical-align:middle; font-size:10px; }
        .team-table tr:nth-child(even) td { background:#f8fafc; }

        .timelog-table { width:100%; border-collapse:collapse; }
        .timelog-table th { background:#f1f5f9; border:1px solid #e2e8f0; border-top:none; padding:4px 8px; font-size:8px; color:#64748b; text-transform:uppercase; text-align:left; }
        .timelog-table td { border:1px solid #e2e8f0; border-top:none; padding:5px 8px; vertical-align:middle; color:#1e293b; font-size:9px; }

        .desc-body { border:1px solid #e2e8f0; border-top:none; padding:8px 10px; font-size:10px; color:#334155; line-height:1.5; }

        .b-ok     { background:#dcfce7; color:#166534; }
        .b-breach { background:#fee2e2; color:#991b1b; }

        .footer { position:fixed; bottom:0; left:0; right:0; border-top:1px solid #e2e8f0; padding:4px 0 2px; font-size:8px; color:#94a3b8; text-align:center; background:#fff; }
    </style>
</head>
<body>

@php
    $tz = 'America/Bogota';
    $statusLabels = [
        'pending'     => 'Pendiente',
        'scheduled'   => 'Programada',
        'in_progress' => 'En progreso',
        'paused'      => 'Pausada',
        'completed'   => 'Completada',
        'validated'   => 'Validada',
        'cancelled'   => 'Cancelada',
    ];
    $priorityLabels = ['critical'=>'Crítica','high'=>'Alta','medium'=>'Media','low'=>'Baja'];
    $typeLabels = [
        'corrective'  => 'Correctivo',
        'preventive'  => 'Preventivo',
        'predictive'  => 'Predictivo',
        'inspection'  => 'Inspección',
        'improvement' => 'Mejora',
        'emergency'   => 'Emergencia',
        'other'       => 'Otro',
    ];
    $fmtDate = fn($d) => $d ? $d->setTimezone($tz)->format('d/m/Y H:i') : '—';
@endphp

{{-- HEADER --}}
<table class="header-table">
    <tr>
        <td class="logo-cell">
            @if(!empty($logoBase64))
                <img src="{{ $logoBase64 }}" alt="Logo">
            @else
                <span class="logo-placeholder">Q</span>
            @endif
        </td>
        <td class="title-cell">
            <div class="doc-type">ORDEN DE TRABAJO</div>
            <div class="company-sub">{{ $workOrder->company->trade_name ?? $workOrder->company->legal_name ?? '' }}</div>
        </td>
        <td class="code-cell">
            <div class="code-badge">{{ $workOrder->code }}</div>
            <div class="gen-date">Generado: {{ now($tz)->format('d/m/Y H:i') }}</div>
        </td>
    </tr>
</table>

{{-- TÍTULO --}}
<div class="wo-title">{{ $workOrder->title }}</div>

{{-- BADGES: ESTADO / PRIORIDAD / TIPO --}}
<table class="status-table">
    <tr>
        <td>
            <div class="s-lbl">Estado</div>
            <div class="s-val">
                <span class="badge b-{{ $workOrder->status }}">
                    {{ $statusLabels[$workOrder->status] ?? $workOrder->status }}
                </span>
                @if($workOrder->is_emergency)
                    &nbsp;<span class="badge b-emergency">Emergencia</span>
                @endif
            </div>
        </td>
        <td>
            <div class="s-lbl">Prioridad</div>
            <div class="s-val p-{{ $workOrder->priority }}">{{ $priorityLabels[$workOrder->priority] ?? $workOrder->priority }}</div>
        </td>
        <td>
            <div class="s-lbl">Tipo</div>
            <div class="s-val">{{ $typeLabels[$workOrder->work_order_type] ?? $workOrder->work_order_type }}</div>
        </td>
        <td>
            <div class="s-lbl">Creada</div>
            <div class="s-val" style="font-size:9px;">{{ $workOrder->created_at->setTimezone($tz)->format('d/m/Y H:i') }}</div>
        </td>
    </tr>
</table>

{{-- DESCRIPCIÓN --}}
@if($workOrder->description)
<div class="sec">
    <div class="sec-head">Descripción</div>
    <div class="desc-body">{{ $workOrder->description }}</div>
</div>
@endif

{{-- ACTIVO + PROGRAMACIÓN --}}
<table class="two-col-table">
    <tr>
        <td class="col-l">
            <div class="sec-head">Activo</div>
            @if($workOrder->asset)
            <table class="info-table">
                <tr><td class="lbl">Código</td><td><strong>{{ $workOrder->asset->code }}</strong></td></tr>
                <tr><td class="lbl">Nombre</td><td>{{ $workOrder->asset->name }}</td></tr>
                @if($workOrder->asset->category)
                <tr><td class="lbl">Categoría</td><td>{{ $workOrder->asset->category->name }}</td></tr>
                @endif
                @if($workOrder->asset->companySite)
                <tr><td class="lbl">Sede</td><td>{{ $workOrder->asset->companySite->name }}</td></tr>
                @endif
                @if($workOrder->requires_shutdown)
                <tr><td class="lbl">Parada</td><td style="color:#dc2626; font-weight:bold;">Requiere parada de equipo</td></tr>
                @endif
            </table>
            @else
            <div class="desc-body" style="color:#94a3b8; font-style:italic;">Sin activo asociado</div>
            @endif
        </td>
        <td class="col-r">
            <div class="sec-head">Programación</div>
            <table class="info-table">
                <tr>
                    <td class="lbl">Inicio programado</td>
                    <td>{{ $workOrder->scheduled_start ? $workOrder->scheduled_start->setTimezone($tz)->format('d/m/Y H:i') : '—' }}</td>
                </tr>
                <tr>
                    <td class="lbl">Fin programado</td>
                    <td>{{ $workOrder->scheduled_end ? $workOrder->scheduled_end->setTimezone($tz)->format('d/m/Y H:i') : '—' }}</td>
                </tr>
                <tr>
                    <td class="lbl">Duración estimada</td>
                    <td>{{ $workOrder->estimated_duration_hours ? $workOrder->estimated_duration_hours . ' h' : '—' }}</td>
                </tr>
                @if($workOrder->actual_start)
                <tr>
                    <td class="lbl">Inicio real</td>
                    <td>{{ $workOrder->actual_start->setTimezone($tz)->format('d/m/Y H:i') }}</td>
                </tr>
                @endif
                @if($workOrder->actual_end)
                <tr>
                    <td class="lbl">Fin real</td>
                    <td>{{ $workOrder->actual_end->setTimezone($tz)->format('d/m/Y H:i') }}</td>
                </tr>
                @endif
                @if($workOrder->actual_duration_hours)
                <tr>
                    <td class="lbl">Duración real</td>
                    <td>{{ $workOrder->actual_duration_hours }} h</td>
                </tr>
                @endif
                @if($workOrder->downtime_hours)
                <tr>
                    <td class="lbl">Tiempo de parada</td>
                    <td>{{ $workOrder->downtime_hours }} h</td>
                </tr>
                @endif
            </table>
        </td>
    </tr>
</table>

{{-- ASIGNACIÓN --}}
@if($workOrder->assignedTo)
<div class="sec">
    <div class="sec-head">Asignación</div>
    <table class="dates-table">
        <tr>
            <td class="lbl">Técnico asignado</td>
            <td class="val"><strong>{{ $workOrder->assignedTo->first_name }} {{ $workOrder->assignedTo->last_name }}</strong></td>
            <td class="lbl">Fecha asignación</td>
            <td class="val">{{ $workOrder->assigned_at ? $workOrder->assigned_at->setTimezone($tz)->format('d/m/Y H:i') : '—' }}</td>
        </tr>
        @if($workOrder->assignedBy)
        <tr>
            <td class="lbl">Asignado por</td>
            <td class="val">{{ $workOrder->assignedBy->first_name }} {{ $workOrder->assignedBy->last_name }}</td>
            <td class="lbl"></td><td class="val"></td>
        </tr>
        @endif
    </table>
</div>
@endif

{{-- EQUIPO DE TRABAJO --}}
@if($workOrder->assignments && $workOrder->assignments->count() > 0)
<div class="sec">
    <div class="sec-head">Equipo de trabajo</div>
    <table class="team-table">
        @foreach($workOrder->assignments as $assignment)
        <tr>
            <td style="width:40%;">{{ $assignment->user->first_name ?? '' }} {{ $assignment->user->last_name ?? '' }}</td>
            <td style="color:#64748b; font-size:9px;">{{ $assignment->user->email ?? '' }}</td>
            <td style="width:25%; font-size:9px; color:#64748b; text-align:right;">{{ $assignment->assigned_at ? $assignment->assigned_at->setTimezone($tz)->format('d/m/Y H:i') : '' }}</td>
        </tr>
        @endforeach
    </table>
</div>
@endif

{{-- COSTOS --}}
<div class="sec">
    <div class="sec-head">Costos</div>
    <table class="cost-table">
        <thead>
            <tr>
                <th></th>
                <th>Mano de obra</th>
                <th>Materiales</th>
                <th>Otros</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="lbl" style="background:#f8fafc; font-weight:bold; color:#475569; font-size:9px;">Estimado</td>
                <td>$ {{ number_format($workOrder->estimated_labor_cost ?? 0, 0, ',', '.') }}</td>
                <td>$ {{ number_format($workOrder->estimated_material_cost ?? 0, 0, ',', '.') }}</td>
                <td>$ {{ number_format($workOrder->estimated_other_cost ?? 0, 0, ',', '.') }}</td>
                <td class="cost-highlight">$ {{ number_format(($workOrder->estimated_labor_cost ?? 0) + ($workOrder->estimated_material_cost ?? 0) + ($workOrder->estimated_other_cost ?? 0), 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="lbl" style="background:#f8fafc; font-weight:bold; color:#475569; font-size:9px;">Real</td>
                <td>$ {{ number_format($workOrder->actual_labor_cost ?? 0, 0, ',', '.') }}</td>
                <td>$ {{ number_format($workOrder->actual_material_cost ?? 0, 0, ',', '.') }}</td>
                <td>$ {{ number_format($workOrder->actual_other_cost ?? 0, 0, ',', '.') }}</td>
                <td class="cost-highlight">$ {{ number_format(($workOrder->actual_labor_cost ?? 0) + ($workOrder->actual_material_cost ?? 0) + ($workOrder->actual_other_cost ?? 0), 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>
</div>

{{-- CHECKLIST --}}
@if($workOrder->checklistItems && $workOrder->checklistItems->count() > 0)
@php $checked = $workOrder->checklistItems->where('is_checked', true)->count(); $total = $workOrder->checklistItems->count(); @endphp
<div class="sec">
    <div class="sec-head">Checklist &nbsp;<span style="font-weight:normal; opacity:.75;">{{ $checked }}/{{ $total }} completados</span></div>
    <table class="chk-table">
        <thead>
            <tr>
                <th style="width:5%; text-align:center;">✓</th>
                <th>Ítem</th>
                <th style="width:20%; text-align:center;">Verificado por</th>
                <th style="width:18%; text-align:center;">Fecha</th>
            </tr>
        </thead>
        <tbody>
            @foreach($workOrder->checklistItems->sortBy('display_order') as $item)
            <tr>
                <td style="text-align:center;">
                    <span class="chk-box {{ $item->is_checked ? 'done' : '' }}">{{ $item->is_checked ? '✓' : '' }}</span>
                </td>
                <td>
                    {{ $item->item_text }}
                    @if($item->notes)
                        <div style="color:#64748b; font-size:8.5px; font-style:italic; margin-top:1px;">{{ $item->notes }}</div>
                    @endif
                </td>
                <td style="text-align:center; font-size:9px;">
                    {{ $item->checkedBy ? $item->checkedBy->first_name . ' ' . $item->checkedBy->last_name : '' }}
                </td>
                <td style="text-align:center; font-size:9px;">
                    {{ $item->checked_at ? $item->checked_at->setTimezone($tz)->format('d/m/Y H:i') : '' }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- REGISTROS DE TIEMPO --}}
@if($workOrder->timeLogs && $workOrder->timeLogs->count() > 0)
<div class="sec">
    <div class="sec-head">Registros de tiempo</div>
    <table class="timelog-table">
        <thead>
            <tr>
                <th>Técnico</th>
                <th>Inicio</th>
                <th>Fin</th>
                <th style="width:12%; text-align:right;">Duración</th>
            </tr>
        </thead>
        <tbody>
            @foreach($workOrder->timeLogs as $log)
            <tr>
                <td>{{ $log->user ? $log->user->first_name . ' ' . $log->user->last_name : '—' }}</td>
                <td>{{ $log->start_time ? \Carbon\Carbon::parse($log->start_time)->setTimezone($tz)->format('d/m/Y H:i') : '—' }}</td>
                <td>{{ $log->end_time ? \Carbon\Carbon::parse($log->end_time)->setTimezone($tz)->format('d/m/Y H:i') : 'En curso' }}</td>
                <td style="text-align:right;">{{ $log->duration_hours ? $log->duration_hours . ' h' : '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- COMPLETADO / VALIDADO --}}
@if(in_array($workOrder->status, ['completed', 'validated']))
<div class="sec">
    <div class="sec-head" style="background:#166534;">Completado</div>
    <table class="dates-table">
        @if($workOrder->completedBy)
        <tr>
            <td class="lbl">Completado por</td>
            <td class="val">{{ $workOrder->completedBy->first_name }} {{ $workOrder->completedBy->last_name }}</td>
            <td class="lbl">Fecha</td>
            <td class="val">{{ $workOrder->completed_at ? $workOrder->completed_at->setTimezone($tz)->format('d/m/Y H:i') : '—' }}</td>
        </tr>
        @endif
        @if($workOrder->completion_notes)
        <tr>
            <td class="lbl">Notas</td>
            <td colspan="3" style="white-space:normal;">{{ $workOrder->completion_notes }}</td>
        </tr>
        @endif
    </table>
    @if($workOrder->status === 'validated' && $workOrder->validatedBy)
    <table class="dates-table" style="border-top:1px solid #e2e8f0;">
        <tr>
            <td class="lbl">Validado por</td>
            <td class="val">{{ $workOrder->validatedBy->first_name }} {{ $workOrder->validatedBy->last_name }}</td>
            <td class="lbl">Fecha validación</td>
            <td class="val">{{ $workOrder->validated_at ? $workOrder->validated_at->setTimezone($tz)->format('d/m/Y H:i') : '—' }}</td>
        </tr>
        @if($workOrder->validation_notes)
        <tr>
            <td class="lbl">Notas validación</td>
            <td colspan="3" style="white-space:normal;">{{ $workOrder->validation_notes }}</td>
        </tr>
        @endif
    </table>
    @endif
</div>
@endif

{{-- CANCELADO --}}
@if($workOrder->status === 'cancelled')
<div class="sec">
    <div class="sec-head" style="background:#991b1b;">Cancelado</div>
    <table class="dates-table">
        @if($workOrder->cancelledBy)
        <tr>
            <td class="lbl">Cancelado por</td>
            <td class="val">{{ $workOrder->cancelledBy->first_name }} {{ $workOrder->cancelledBy->last_name }}</td>
            <td class="lbl">Fecha</td>
            <td class="val">{{ $workOrder->cancelled_at ? $workOrder->cancelled_at->setTimezone($tz)->format('d/m/Y H:i') : '—' }}</td>
        </tr>
        @endif
        @if($workOrder->cancellation_reason)
        <tr>
            <td class="lbl">Motivo</td>
            <td colspan="3" style="white-space:normal;">{{ $workOrder->cancellation_reason }}</td>
        </tr>
        @endif
    </table>
</div>
@endif

{{-- SLA --}}
@if($workOrder->sla_deadline)
<div class="sec">
    <div class="sec-head-gray">SLA</div>
    <table class="dates-table">
        <tr>
            <td class="lbl">Límite SLA</td>
            <td class="val">{{ $workOrder->sla_deadline->setTimezone($tz)->format('d/m/Y H:i') }}</td>
            <td class="lbl">Estado SLA</td>
            <td class="val">
                @if($workOrder->sla_breached)
                    <span class="badge b-breach">Incumplido</span>
                    @if($workOrder->sla_breach_reason)
                        &nbsp;<span style="color:#64748b;">{{ $workOrder->sla_breach_reason }}</span>
                    @endif
                @else
                    <span class="badge b-ok">A tiempo</span>
                @endif
            </td>
        </tr>
    </table>
</div>
@endif

{{-- SOLICITUD VINCULADA --}}
@if($workOrder->workRequest)
<div class="sec">
    <div class="sec-head-gray">Solicitud de trabajo vinculada</div>
    <div style="border:1px solid #e2e8f0; border-top:none; padding:7px 10px; font-size:10px;">
        <strong style="color:#1d4ed8;">{{ $workOrder->workRequest->code }}</strong>
        @if($workOrder->workRequest->title)
            &nbsp;–&nbsp;{{ $workOrder->workRequest->title }}
        @endif
    </div>
</div>
@endif

{{-- FOOTER --}}
<div class="footer">
    {{ $workOrder->company->trade_name ?? $workOrder->company->legal_name ?? config('app.name') }}
    &nbsp;&mdash;&nbsp;
    {{ $workOrder->code }}
    &nbsp;&mdash;&nbsp;
    Generado el {{ now($tz)->format('d/m/Y H:i:s') }}
</div>

</body>
</html>
