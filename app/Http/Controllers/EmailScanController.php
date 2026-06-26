<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLogger;
use App\Models\EmailScan;
use App\Services\FlaggedDomainService;
use App\Services\Ocr\OcrService;
use App\Services\RiskEngine;
use Illuminate\Http\Request;

class EmailScanController extends Controller
{
    public function index()
    {
        $recentScans = EmailScan::where('organization_id', auth()->user()->organization_id)
            ->latest()
            ->take(15)
            ->get();

        return view('email-scans.index', compact('recentScans'));
    }

    public function analyze(Request $request, OcrService $ocr)
    {
        $request->validate([
            'sender_email' => 'required|email',
            'subject' => 'nullable|string|max:255',
            'email_content' => 'nullable|string',
            'attachments' => 'nullable|string',
            'attachment_file' => 'nullable|file|max:10240',
            'from_name' => 'nullable|string|max:255',
            'reply_to' => 'nullable|string|max:255',
            'return_path' => 'nullable|string|max:255',
        ]);

        $organizationId = auth()->user()->organization_id;

        // Attachments are entered one filename per line.
        $attachments = collect(preg_split('/\r\n|\r|\n/', (string) $request->attachments))
            ->map(fn ($name) => trim($name))
            ->filter()
            ->values()
            ->all();

        // An uploaded image/scan is OCR'd and its text folded into the content the
        // engines analyse; its filename also joins the attachment-level checks.
        $content = (string) $request->email_content;
        $ocrText = '';

        if ($request->hasFile('attachment_file')) {
            $file = $request->file('attachment_file');
            $attachments[] = $file->getClientOriginalName();
            $ocrText = $ocr->extract((string) $file->getRealPath());

            if ($ocrText !== '') {
                $content = trim($content."\n".$ocrText);
            }
        }

        $result = RiskEngine::evaluate([
            'sender_email' => $request->sender_email,
            'subject' => $request->subject,
            'email_content' => $content,
            'attachments' => $attachments,
            'headers' => [
                'from_name' => $request->from_name,
                'reply_to' => $request->reply_to,
                'return_path' => $request->return_path,
            ],
        ], $organizationId);

        if ($ocrText !== '') {
            array_unshift($result['findings'], 'Text extracted from an attachment via OCR and analysed');
        }

        EmailScan::create([
            'organization_id' => $organizationId,
            'user_id' => auth()->id(),
            'sender_email' => $request->sender_email,
            'sender_domain' => $result['domain'],
            'subject' => $request->subject,
            'email_content' => $content,
            'risk_score' => $result['risk_score'],
            'risk_level' => $result['risk_level'],
            'decision' => $result['decision'],
            'confidence' => $result['confidence'],
            'recommendations' => $result['recommendations'],
            'findings' => $result['findings'],
            'is_trusted_domain' => $result['signals']['is_trusted_domain'] ?? false,
            'is_blocked_domain' => $result['signals']['is_blocked_domain'] ?? false,
            'spf_pass' => (bool) ($result['signals']['spf_pass'] ?? false),
            'dkim_pass' => (bool) ($result['signals']['dkim_pass'] ?? false),
            'dmarc_pass' => (bool) ($result['signals']['dmarc_pass'] ?? false),
        ]);

        AuditLogger::log(
            'SCAN',
            'EMAIL',
            null,
            'Scanned '.$request->sender_email.' — '.$result['risk_level'].' ('.$result['decision'].')'
        );

        // Auto-record any impersonation / untrusted domain. On a repeat sighting
        // we surface a popup warning (with an escalate-to-manager option).
        $record = FlaggedDomainService::record(
            $result['domain'],
            $result['signals']['domain_flags'] ?? [],
            $organizationId,
            auth()->id()
        );

        $flash = [
            'risk_score' => $result['risk_score'],
            'risk_level' => $result['risk_level'],
            'decision' => $result['decision'],
            'confidence' => $result['confidence'],
            'recommendations' => $result['recommendations'],
            'findings' => $result['findings'],
        ];

        if ($record && $record['repeat']) {
            $flash['domain_warning'] = $this->warningPayload($record['flagged'], [
                'recipient_email' => $request->sender_email,
                'subject' => $request->subject,
                'email_content' => $request->email_content,
            ], 'scan');
        }

        return back()->with($flash);
    }

    /**
     * Build the session payload the <x-flagged-domain-warning> modal renders.
     */
    private function warningPayload($flagged, array $email, string $context): array
    {
        return [
            'domain' => $flagged->domain,
            'type' => $flagged->detection_type,
            'reason' => $flagged->reason,
            'times_seen' => $flagged->times_seen,
            'resembles' => $flagged->resembles,
            'context' => $context,
            'email' => $email,
        ];
    }
}
