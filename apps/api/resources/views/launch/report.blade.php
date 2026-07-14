<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PAYLITY Launch Readiness Report</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111; margin: 24px; }
        h1, h2 { margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin: 12px 0 24px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 13px; }
        th { background: #f5f5f5; }
        .meta { color: #555; font-size: 13px; margin-bottom: 16px; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 999px; font-size: 12px; font-weight: bold; }
        .critical { background: #fee2e2; color: #991b1b; }
        .warning { background: #fef3c7; color: #92400e; }
        .info { background: #e2e8f0; color: #334155; }
    </style>
</head>
<body>
    <h1>PAYLITY Launch Readiness Report</h1>
    <p class="meta">
        Generated: {{ $report['generated_at'] ?? '—' }}<br>
        Operator: {{ $report['operator'] ?? 'system' }}<br>
        Build: {{ $report['build_version']['version'] ?? '—' }} ({{ $report['build_version']['build'] ?? '—' }})
    </p>

    <h2>Environment</h2>
    <table>
        <tr><th>APP_ENV</th><td>{{ $report['environment']['app_env'] ?? '—' }}</td></tr>
        <tr><th>Launch Mode</th><td>{{ $report['environment']['launch_mode'] ?? '—' }}</td></tr>
        <tr><th>APP_DEBUG</th><td>{{ ($report['environment']['app_debug'] ?? false) ? 'true' : 'false' }}</td></tr>
        <tr><th>APP_URL</th><td>{{ $report['environment']['app_url'] ?? '—' }}</td></tr>
        <tr><th>Preflight Status</th><td>{{ $report['preflight']['status'] ?? '—' }}</td></tr>
    </table>

    <h2>Launch Blockers</h2>
    @if (empty($report['blockers']))
        <p>No launch blockers detected.</p>
    @else
        <table>
            <tr><th>Code</th><th>Message</th><th>Severity</th></tr>
            @foreach ($report['blockers'] as $blocker)
                <tr>
                    <td>{{ $blocker['code'] ?? '—' }}</td>
                    <td>{{ $blocker['message'] ?? '—' }}</td>
                    <td><span class="badge {{ $blocker['severity'] ?? 'info' }}">{{ $blocker['severity'] ?? 'info' }}</span></td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>Preflight Checks</h2>
    <table>
        <tr><th>Name</th><th>Status</th><th>Severity</th><th>Message</th></tr>
        @foreach (($report['preflight']['checks'] ?? []) as $check)
            <tr>
                <td>{{ $check['name'] ?? ($check['check'] ?? '—') }}</td>
                <td>{{ $check['status'] ?? '—' }}</td>
                <td>{{ $check['severity'] ?? '—' }}</td>
                <td>{{ $check['message'] ?? ($check['detail'] ?? '—') }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Provider Modes</h2>
    <table>
        <tr><th>Paystack Mode</th><td>{{ $report['provider_modes']['paystack']['mode'] ?? '—' }}</td></tr>
        <tr><th>VTPass Mode</th><td>{{ $report['provider_modes']['vtpass']['mode'] ?? '—' }}</td></tr>
    </table>

    <h2>Finance & Ledger</h2>
    <table>
        <tr><th>Negative Margins</th><td>{{ $report['finance_summary']['negative_margin_count'] ?? 0 }}</td></tr>
        <tr><th>Paystack Clearing (kobo)</th><td>{{ $report['finance_summary']['paystack_clearing_kobo'] ?? 0 }}</td></tr>
        <tr><th>Settlement Difference (kobo)</th><td>{{ $report['finance_summary']['settlement_difference_kobo'] ?? 0 }}</td></tr>
        <tr><th>Ledger Imbalances</th><td>{{ $report['ledger_summary']['imbalance_count'] ?? 0 }}</td></tr>
    </table>

    <h2>Wallet</h2>
    <table>
        <tr><th>Health</th><td>{{ $report['wallet']['health'] ?? 'unknown' }}</td></tr>
        <tr><th>Balance</th><td>{{ $report['wallet']['balance'] ?? '—' }}</td></tr>
    </table>

    <h2>Production Checklist</h2>
    <p>Progress: {{ $report['checklist']['progress_pct'] ?? 0 }}% ({{ $report['checklist']['completed_count'] ?? 0 }}/{{ $report['checklist']['total_count'] ?? 0 }})</p>
    <table>
        <tr><th>Item</th><th>Completed</th></tr>
        @foreach (($report['checklist']['items'] ?? []) as $item)
            <tr>
                <td>{{ $item['label'] ?? '—' }}</td>
                <td>{{ ($item['completed'] ?? false) ? 'Yes' : 'No' }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>
