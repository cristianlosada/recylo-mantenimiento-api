<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Solicitud {{ $workRequest->code }}</title>
    <style>
        @page {
            margin-top: 1.5cm;
            margin-right: 1.8cm;
            margin-bottom: 1.8cm;
            margin-left: 1.8cm;
        }

        * { box-sizing: border-box; }

        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #1e293b;
            background: #fff;
            margin: 0;
            padding: 0;
        }

        a { color: inherit; text-decoration: none; }

        /* ── HEADER ── */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 3px solid #4a6741;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .header-table td { padding: 0; vertical-align: middle; }
        .logo-cell { width: 110px; }
        .logo-cell img { max-height: 46px; max-width: 100px; }
        .logo-placeholder {
            display: inline-block;
            width: 44px; height: 44px;
            background: #4a6741;
            border-radius: 6px;
            text-align: center;
            line-height: 44px;
            color: #fff;
            font-size: 20px;
            font-weight: bold;
        }
        .title-cell { padding-left: 10px; }
        .doc-type {
            font-size: 17px;
            font-weight: bold;
            color: #4a6741;
            letter-spacing: 0.3px;
        }
        .company-sub {
            font-size: 10px;
            color: #64748b;
            margin-top: 1px;
        }
        .code-cell { text-align: right; }
        .code-badge {
            display: inline-block;
            background: #4a6741;
            color: #fff;
            padding: 6px 14px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 0.8px;
        }
        .gen-date {
            font-size: 8px;
            color: #94a3b8;
            margin-top: 4px;
        }

        /* ── REQUEST TITLE ── */
        .req-title {
            font-size: 13px;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 12px;
            padding: 7px 10px 7px 13px;
            background: #f1f5f9;
            border-left: 4px solid #4a6741;
        }

        /* ── STATUS ROW ── */
        .status-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            border: 1px solid #e2e8f0;
        }
        .status-table td {
            padding: 7px 11px;
            vertical-align: top;
            border-right: 1px solid #e2e8f0;
            width: 25%;
        }
        .status-table td:last-child { border-right: none; }
        .s-lbl {
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #94a3b8;
            font-weight: bold;
            margin-bottom: 3px;
        }
        .s-val { font-size: 11px; font-weight: bold; color: #1e293b; }

        /* ── BADGES ── */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: bold;
        }
        .b-pending      { background: #fef9c3; color: #854d0e; }
        .b-under_review { background: #dbeafe; color: #1e40af; }
        .b-approved     { background: #dcfce7; color: #166534; }
        .b-rejected     { background: #fee2e2; color: #991b1b; }
        .b-completed    { background: #f0fdf4; color: #15803d; }
        .b-cancelled    { background: #f1f5f9; color: #475569; }
        .b-ok           { background: #dcfce7; color: #166534; }
        .b-breach       { background: #fee2e2; color: #991b1b; }

        .p-critical { color: #dc2626; }
        .p-high     { color: #ea580c; }
        .p-medium   { color: #ca8a04; }
        .p-low      { color: #16a34a; }

        /* ── SECTION ── */
        .sec { margin-bottom: 12px; }

        .sec-head {
            background: #4a6741;
            color: #fff;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 4px 10px;
        }

        /* ── INFO ROWS ── */
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            border: 1px solid #e2e8f0;
            border-top: none;
            padding: 5px 10px;
            vertical-align: middle;
            color: #1e293b;
            font-size: 10px;
        }
        .info-table .lbl {
            background: #f8fafc;
            font-weight: bold;
            color: #475569;
            font-size: 9px;
            white-space: nowrap;
        }
        /* Fechas: 4 columnas iguales con nowrap en valores */
        .dates-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .dates-table td {
            border: 1px solid #e2e8f0;
            border-top: none;
            padding: 5px 10px;
            vertical-align: middle;
            color: #1e293b;
            font-size: 10px;
            white-space: nowrap;
        }
        .dates-table .lbl {
            background: #f8fafc;
            font-weight: bold;
            color: #475569;
            font-size: 9px;
            width: 22%;
        }
        .dates-table .val { width: 28%; }

        /* ── DESCRIPTION ── */
        .desc-body {
            border: 1px solid #e2e8f0;
            border-top: none;
            padding: 8px 10px;
            font-size: 10px;
            color: #334155;
            line-height: 1.5;
        }

        /* ── TWO COL ── */
        .two-col-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 12px;
        }
        .two-col-table > tbody > tr > td { vertical-align: top; padding: 0; }
        .col-l { width: 49%; padding-right: 4px; }
        .col-r { width: 51%; padding-left: 4px; }

        /* ── CHECKLIST ── */
        .chk-table {
            width: 100%;
            border-collapse: collapse;
        }
        .chk-table th {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-top: none;
            padding: 4px 8px;
            font-size: 8px;
            color: #64748b;
            text-transform: uppercase;
            text-align: left;
        }
        .chk-table td {
            border: 1px solid #e2e8f0;
            border-top: none;
            padding: 5px 8px;
            vertical-align: middle;
            color: #1e293b;
        }
        .chk-table tr:nth-child(even) td { background: #f8fafc; }
        .chk-box {
            display: inline-block;
            width: 12px; height: 12px;
            border: 1.5px solid #94a3b8;
            border-radius: 2px;
            text-align: center;
            line-height: 11px;
            font-size: 8px;
        }
        .chk-box.done { background: #dcfce7; border-color: #16a34a; color: #16a34a; }

        /* ── TAGS ── */
        .tag {
            display: inline-block;
            background: #e2e8f0;
            color: #334155;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 9px;
            margin: 1px 2px;
        }

        /* ── OT BOX ── */
        .ot-body {
            border: 1px solid #bfdbfe;
            border-top: none;
            background: #eff6ff;
            padding: 8px 11px;
        }
        .ot-code { font-size: 13px; font-weight: bold; color: #1d4ed8; }
        .ot-name { font-size: 9.5px; color: #374151; margin-top: 2px; }

        /* ── EQUIP STATUS ── */
        .eq-stop    { color: #dc2626; font-weight: bold; }
        .eq-limited { color: #ea580c; font-weight: bold; }
        .eq-normal  { color: #16a34a; }

        /* ── FOOTER ── */
        .footer {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            border-top: 1px solid #e2e8f0;
            padding: 4px 0 2px;
            font-size: 8px;
            color: #94a3b8;
            text-align: center;
            background: #fff;
        }
    </style>
</head>
<body>

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
                <div class="doc-type">SOLICITUD DE TRABAJO</div>
                <div class="company-sub">{{ $workRequest->company->trade_name ?? $workRequest->company->legal_name ?? '' }}</div>
            </td>
            <td class="code-cell">
                <div class="code-badge">{{ $workRequest->code }}</div>
                <div class="gen-date">Generado: {{ now('America/Bogota')->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>

    {{-- TÍTULO --}}
    <div class="req-title">{{ $workRequest->title }}</div>

    {{-- ESTADO / PRIORIDAD / TIPO / ORIGEN --}}
    @php
        $tz = 'America/Bogota';
        $statusLabels   = ['pending'=>'Pendiente','under_review'=>'En revisión','approved'=>'Aprobada','rejected'=>'Rechazada','completed'=>'Completada','cancelled'=>'Cancelada'];
        $priorityLabels = ['critical'=>'Crítica','high'=>'Alta','medium'=>'Media','low'=>'Baja'];
        $typeLabels     = ['corrective'=>'Correctivo','preventive'=>'Preventivo','predictive'=>'Predictivo','inspection'=>'Inspección','improvement'=>'Mejora','emergency'=>'Emergencia','other'=>'Otro'];
        $equipLabels    = ['operating_normally'=>'Operando normalmente','operating_restricted'=>'Operación restringida','full_stop'=>'Paro total','unknown'=>'Desconocido'];
    @endphp
    <table class="status-table">
        <tr>
            <td>
                <div class="s-lbl">Estado</div>
                <div class="s-val">
                    <span class="badge b-{{ $workRequest->status }}">
                        {{ $statusLabels[$workRequest->status] ?? $workRequest->status }}
                    </span>
                </div>
            </td>
            <td>
                <div class="s-lbl">Prioridad</div>
                <div class="s-val p-{{ $workRequest->priority }}">{{ $priorityLabels[$workRequest->priority] ?? $workRequest->priority }}</div>
            </td>
            <td>
                <div class="s-lbl">Tipo</div>
                <div class="s-val">{{ $typeLabels[$workRequest->request_type] ?? $workRequest->request_type }}</div>
            </td>
            <td>
                <div class="s-lbl">Origen</div>
                <div class="s-val">{{ $workRequest->is_public_request ? 'Formulario público' : 'Portal interno' }}</div>
            </td>
        </tr>
    </table>

    {{-- DESCRIPCIÓN --}}
    @if($workRequest->description)
    <div class="sec">
        <div class="sec-head">Descripción</div>
        <div class="desc-body">{{ $workRequest->description }}</div>
    </div>
    @endif

    {{-- SOLICITANTE + ACTIVO --}}
    <table class="two-col-table">
        <tr>
            <td class="col-l">
                <div class="sec-head">Solicitante</div>
                <table class="info-table">
                    <tr>
                        <td class="lbl">Nombre</td>
                        <td>
                            @if($workRequest->requester)
                                {{ $workRequest->requester->first_name }} {{ $workRequest->requester->last_name }}
                            @else
                                {{ $workRequest->requester_name ?? '—' }}
                            @endif
                        </td>
                    </tr>
                    @if($workRequest->requester_email)
                    <tr><td class="lbl">Email</td><td>{{ $workRequest->requester_email }}</td></tr>
                    @endif
                    @if($workRequest->requester_phone)
                    <tr><td class="lbl">Teléfono</td><td>{{ $workRequest->requester_phone }}</td></tr>
                    @endif
                    @if($workRequest->location_details)
                    <tr><td class="lbl">Ubicación</td><td>{{ $workRequest->location_details }}</td></tr>
                    @endif
                    @if($workRequest->equipment_status)
                    <tr>
                        <td class="lbl">Estado equipo</td>
                        <td>
                            @php
                                $eqClass = match($workRequest->equipment_status) {
                                    'full_stop'            => 'eq-stop',
                                    'operating_restricted' => 'eq-limited',
                                    default                => 'eq-normal',
                                };
                            @endphp
                            <span class="{{ $eqClass }}">{{ $equipLabels[$workRequest->equipment_status] ?? $workRequest->equipment_status }}</span>
                        </td>
                    </tr>
                    @endif
                </table>
            </td>
            <td class="col-r">
                <div class="sec-head">Activo</div>
                @if($workRequest->asset)
                <table class="info-table">
                    <tr>
                        <td class="lbl">Código</td>
                        <td><strong>{{ $workRequest->asset->code }}</strong></td>
                    </tr>
                    <tr><td class="lbl">Nombre</td><td>{{ $workRequest->asset->name }}</td></tr>
                    @if($workRequest->asset->category)
                    <tr><td class="lbl">Categoría</td><td>{{ $workRequest->asset->category->name }}</td></tr>
                    @endif
                    @if($workRequest->asset->companySite)
                    <tr><td class="lbl">Sede</td><td>{{ $workRequest->asset->companySite->name }}</td></tr>
                    @endif
                </table>
                @else
                <div class="desc-body" style="color:#94a3b8; font-style:italic;">Sin activo asociado</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- FECHAS Y SLA --}}
    <div class="sec">
        <div class="sec-head">Fechas y SLA</div>
        <table class="dates-table">
            <tr>
                <td class="lbl">Creada</td>
                <td class="val">{{ $workRequest->created_at->setTimezone($tz)->format('d/m/Y H:i') }}</td>
                <td class="lbl">Respuesta límite</td>
                <td class="val">{{ $workRequest->response_due_at ? $workRequest->response_due_at->setTimezone($tz)->format('d/m/Y H:i') : '—' }}</td>
            </tr>
            <tr>
                <td class="lbl">Primera respuesta</td>
                <td class="val">{{ $workRequest->first_response_at ? $workRequest->first_response_at->setTimezone($tz)->format('d/m/Y H:i') : '—' }}</td>
                <td class="lbl">Resolución límite</td>
                <td class="val">{{ $workRequest->resolution_due_at ? $workRequest->resolution_due_at->setTimezone($tz)->format('d/m/Y H:i') : '—' }}</td>
            </tr>
            <tr>
                <td class="lbl">SLA</td>
                <td colspan="3" style="white-space:normal;">
                    @if($workRequest->sla_breached)
                        <span class="badge b-breach">Incumplido</span>
                        @if($workRequest->sla_breach_reason)
                            &nbsp;<span style="color:#64748b;">{{ $workRequest->sla_breach_reason }}</span>
                        @endif
                    @else
                        <span class="badge b-ok">A tiempo</span>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- APROBACIÓN --}}
    @if($workRequest->status === 'approved' && $workRequest->approvedBy)
    <div class="sec">
        <div class="sec-head">Aprobación</div>
        <table class="dates-table">
            <tr>
                <td class="lbl">Aprobado por</td>
                <td class="val">{{ $workRequest->approvedBy->first_name }} {{ $workRequest->approvedBy->last_name }}</td>
                <td class="lbl">Fecha</td>
                <td class="val">{{ $workRequest->approved_at ? $workRequest->approved_at->setTimezone($tz)->format('d/m/Y H:i') : '—' }}</td>
            </tr>
            @if($workRequest->actual_cost || $workRequest->actual_hours)
            <tr>
                <td class="lbl">Costo real</td>
                <td class="val">{{ $workRequest->actual_cost ? '$ ' . number_format($workRequest->actual_cost, 2) : '—' }}</td>
                <td class="lbl">Horas reales</td>
                <td class="val">{{ $workRequest->actual_hours ? $workRequest->actual_hours . ' h' : '—' }}</td>
            </tr>
            @endif
        </table>
    </div>
    @endif

    {{-- RECHAZO --}}
    @if($workRequest->status === 'rejected')
    <div class="sec">
        <div class="sec-head" style="background:#b91c1c;">Rechazo</div>
        <table class="dates-table">
            @if($workRequest->rejectedBy)
            <tr>
                <td class="lbl">Rechazado por</td>
                <td class="val">{{ $workRequest->rejectedBy->first_name }} {{ $workRequest->rejectedBy->last_name }}</td>
                <td class="lbl">Fecha</td>
                <td class="val">{{ $workRequest->rejected_at ? $workRequest->rejected_at->setTimezone($tz)->format('d/m/Y H:i') : '—' }}</td>
            </tr>
            @endif
            @if($workRequest->rejection_reason)
            <tr>
                <td class="lbl">Motivo</td>
                <td colspan="3" style="white-space:normal;">{{ $workRequest->rejection_reason }}</td>
            </tr>
            @endif
        </table>
    </div>
    @endif

    {{-- ESTIMACIONES --}}
    @if($workRequest->estimated_cost || $workRequest->estimated_hours)
    <div class="sec">
        <div class="sec-head">Estimaciones</div>
        <table class="info-table">
            <tr>
                <td class="lbl" style="width:25%;">Costo estimado</td>
                <td>{{ $workRequest->estimated_cost ? '$ ' . number_format($workRequest->estimated_cost, 2) : '—' }}</td>
                <td class="lbl" style="width:25%;">Horas estimadas</td>
                <td>{{ $workRequest->estimated_hours ? $workRequest->estimated_hours . ' h' : '—' }}</td>
            </tr>
        </table>
    </div>
    @endif

    {{-- CHECKLIST --}}
    @if($workRequest->checklistItems && $workRequest->checklistItems->count() > 0)
    @php $checked = $workRequest->checklistItems->where('is_checked', true)->count(); $total = $workRequest->checklistItems->count(); @endphp
    <div class="sec">
        <div class="sec-head">
            Checklist &nbsp;<span style="font-weight:normal; opacity:.75;">{{ $checked }}/{{ $total }} completados</span>
        </div>
        <table class="chk-table">
            <thead>
                <tr>
                    <th style="width:5%; text-align:center;">✓</th>
                    <th>Ítem</th>
                    <th style="width:14%; text-align:center;">Requerido</th>
                </tr>
            </thead>
            <tbody>
                @foreach($workRequest->checklistItems->sortBy('display_order') as $item)
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
                    <td style="text-align:center; color:{{ $item->is_required ? '#dc2626' : '#94a3b8' }}; font-size:9px;">
                        {{ $item->is_required ? '★ Sí' : 'No' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- ETIQUETAS --}}
    @if($workRequest->tags && $workRequest->tags->count() > 0)
    <div class="sec">
        <div class="sec-head">Etiquetas</div>
        <div style="border:1px solid #e2e8f0; border-top:none; padding:6px 9px;">
            @foreach($workRequest->tags as $tag)
                <span class="tag">{{ $tag->name }}</span>
            @endforeach
        </div>
    </div>
    @endif

    {{-- OT VINCULADA --}}
    @if($workRequest->workOrder)
    <div class="sec">
        <div class="sec-head" style="background:#1d4ed8;">Orden de Trabajo Vinculada</div>
        <div class="ot-body">
            <div class="ot-code">{{ $workRequest->workOrder->code }}</div>
            @if($workRequest->workOrder->title)
            <div class="ot-name">{{ $workRequest->workOrder->title }}</div>
            @endif
        </div>
    </div>
    @endif

    {{-- FOOTER --}}
    <div class="footer">
        {{ $workRequest->company->trade_name ?? $workRequest->company->legal_name ?? config('app.name') }}
        &nbsp;&mdash;&nbsp;
        {{ $workRequest->code }}
        &nbsp;&mdash;&nbsp;
        Generado el {{ now('America/Bogota')->format('d/m/Y H:i:s') }}
    </div>

</body>
</html>
