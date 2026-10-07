<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Audit;
use App\Domain\DomainError;
use App\Domain\Hotel;
use App\Domain\Operations\BackupService;
use App\Domain\Operations\FiscalService;
use App\Domain\Operations\JobRunner;
use App\Domain\Operations\PrintService;
use App\Domain\Operations\ReportService;
use App\Domain\Payments\PaymentService;
use App\Security\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/** S28–S31 — reports, audit, operations/integrations and commerce settings. */
final class OperationsController
{
    /** @return array{0:string,1:string} */
    private function range(Request $request): array
    {
        $today = Hotel::businessDate();
        $valid = static fn ($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1 && strtotime($d) !== false;
        $from = $valid($request->query('from')) ? (string) $request->query('from') : $today;
        $to = $valid($request->query('to')) ? (string) $request->query('to') : $today;
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        if ((strtotime($to) - strtotime($from)) > 366 * 86400) {
            throw DomainError::invalid('Choose a range of one year or less.');
        }

        return [$from, $to];
    }

    public function reports(Request $request, ReportService $reports): View
    {
        [$from, $to] = $this->range($request);

        return view('admin.reports', ['r' => $reports->summary($from, $to), 'from' => $from, 'to' => $to, 'testMode' => Hotel::testMode()]);
    }

    public function export(Request $request, string $kind, ReportService $reports): Response
    {
        [$from, $to] = $this->range($request);
        $rows = match ($kind) {
            'payments' => $reports->paymentRows($from, $to),
            'sales' => $reports->salesRows($from, $to),
            default => abort(404),
        };
        Audit::record('report_exported', (string) Staff::id($request), ['kind' => $kind, 'from' => $from, 'to' => $to, 'rows' => count($rows) - 1]);

        return response(ReportService::csv($rows), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$kind.'-'.$from.'-to-'.$to.'.csv"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function audit(Request $request): View
    {
        $q = DB::table('audit_events')->leftJoin('staff_users', 'staff_users.id', '=', 'audit_events.actor_staff_user_id')->orderByDesc('audit_events.created_at');
        $event = trim((string) $request->query('event', ''));
        if ($event !== '') {
            $q->where('audit_events.event', 'like', str_replace(['%', '_'], ['\%', '\_'], $event).'%');
        }
        $actor = (string) $request->query('actor', '');
        if ($actor !== '') {
            $q->where('audit_events.actor_staff_user_id', $actor);
        }
        $page = max(1, (int) $request->query('page', 1));
        $rows = $q->offset(($page - 1) * 50)->limit(51)->get(['audit_events.*', 'staff_users.name as actor_name'])->all();

        return view('admin.audit', [
            'rows' => array_slice($rows, 0, 50), 'more' => count($rows) > 50, 'page' => $page, 'event' => $event, 'actor' => $actor,
            'staff' => DB::table('staff_users')->orderBy('name')->get(['id', 'name'])->all(),
            'events' => DB::table('audit_events')->distinct()->orderBy('event')->pluck('event')->all(),
        ]);
    }

    public function operations(Request $request, PrintService $printing, FiscalService $fiscal, JobRunner $jobs, BackupService $backups, PaymentService $payments): View
    {
        $heartbeats = DB::table('system_heartbeats')->get()->keyBy('name')->all();

        return view('admin.operations', [
            'printJobs' => $printing->recent(), 'fiscal' => $fiscal->recent(), 'fiscalProvider' => $fiscal->provider(),
            'jobs' => $jobs->recent(), 'backups' => $backups->list(), 'heartbeats' => $heartbeats,
            'mpesaMode' => (string) config('services.mpesa.mode', 'simulator'), 'attention' => $payments->attention(),
            'outboxCount' => DB::table('outbox_events')->count(),
            'canBackup' => Staff::can($request, 'backups.manage'), 'canPrint' => Staff::can($request, 'printing.manage'),
            'canFiscal' => Staff::can($request, 'fiscal.manage'), 'canSettings' => Staff::can($request, 'settings.manage'), 'settings' => Hotel::settings(),
            'bridgeConfigured' => DB::table('print_bridges')->where('active', 1)->exists(),
        ]);
    }

    public function reprint(Request $request, string $jobId, PrintService $printing): RedirectResponse
    {
        $printing->reprint($jobId, (string) Staff::id($request));

        return back()->with('status', 'Copy queued.');
    }

    public function markPrintJob(Request $request, string $jobId): RedirectResponse
    {
        $printed = $request->input('outcome') === 'printed';
        $n = DB::table('print_jobs')->where('id', $jobId)->whereIn('state', ['unknown', 'failed', 'queued'])
            ->update(['state' => $printed ? 'sent' : 'failed', 'last_error' => $printed ? null : 'Marked not printed by staff', 'updated_at' => now('UTC')]);
        if ($n === 0) {
            throw DomainError::conflict('PRINT_STATE', 'That job has already been resolved.');
        }
        Audit::record('print_job_resolved', (string) Staff::id($request), ['job_id' => $jobId, 'printed' => $printed]);

        return back()->with('status', 'Print job updated.');
    }

    public function retryFiscal(Request $request, string $documentId, FiscalService $fiscal): RedirectResponse
    {
        $fiscal->retry($documentId, (string) Staff::id($request));

        return back()->with('status', 'Fiscal submission queued again.');
    }

    public function runJobs(Request $request, JobRunner $jobs): RedirectResponse
    {
        $r = $jobs->tick(50);

        return back()->with('status', 'Background jobs run: '.json_encode($r));
    }

    public function createBackup(Request $request, BackupService $backups): RedirectResponse
    {
        $path = $backups->create('manual');
        $check = $backups->verify($path);
        Audit::record('backup_created', (string) Staff::id($request), ['file' => basename($path), 'verified' => $check['ok'] ?? false]);

        return back()->with('status', 'Backup '.basename($path).' created'.(($check['ok'] ?? false) ? ' and verified.' : ' but verification FAILED — investigate.'));
    }

    public function downloadBackup(Request $request, string $name, BackupService $backups): BinaryFileResponse
    {
        if (preg_match('/^hotel-[A-Za-z0-9_\-]+\.zip$/', $name) !== 1 || ! is_file($backups->directory().'/'.$name)) {
            abort(404);
        }
        Audit::record('backup_downloaded', (string) Staff::id($request), ['file' => $name]);

        return response()->download($backups->directory().'/'.$name, $name, ['Cache-Control' => 'no-store']);
    }

    public function commerce(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'tax_label' => ['nullable', 'string', 'max:40'],
            'kra_pin' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9]*$/'],
            'kiosk_payment_minutes' => ['required', 'integer', 'min:2', 'max:60'],
        ]);
        $rate = ($v['tax_rate'] ?? null) === null || $v['tax_rate'] === '' ? null : (int) round(((float) $v['tax_rate']) * 100);
        DB::table('hotel_settings')->update([
            'tax_rate_basis_points' => $rate, 'tax_label' => $v['tax_label'] ?? null, 'kra_pin' => isset($v['kra_pin']) && $v['kra_pin'] !== '' ? strtoupper($v['kra_pin']) : null,
            'kiosk_payment_minutes' => (int) $v['kiosk_payment_minutes'], 'updated_at' => now('UTC'),
        ]);
        Hotel::forget();
        Audit::record('commerce_settings_changed', (string) Staff::id($request), ['tax_rate_basis_points' => $rate]);

        return back()->with('status', 'Tax and payment settings saved.');
    }
}
