<?php

namespace App\Http\Controllers;

use App\Mail\AdminNewLeadNotification;
use App\Mail\WaitlistConfirmation;
use App\Models\WaitlistLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PublicPortalController extends Controller
{
    /**
     * Store a new early-access waitlist lead.
     */
    public function storeWaitlist(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'full_name'     => ['nullable', 'string', 'max:255'],
            'email'         => ['required', 'email', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:50'],
            'business_type' => ['nullable', 'string', 'max:100'],
        ]);

        $lead = WaitlistLead::updateOrCreate(
            ['email' => strtolower(trim($validated['email']))],
            [
                'business_name' => trim($validated['business_name']),
                'full_name'     => !empty($validated['full_name']) ? trim($validated['full_name']) : null,
                'phone'         => !empty($validated['phone']) ? trim($validated['phone']) : null,
                'business_type' => $validated['business_type'] ?? 'Supermarket',
                'type'          => 'waitlist',
                'status'        => 'Pending',
                'message'       => 'Priority early access waitlist registration via Welcome portal',
            ]
        );

        $adminEmail = config('mail.admin_notification_email', env('ADMIN_NOTIFICATION_EMAIL', 'gathonipeter26@gmail.com'));

        // 1. Send confirmation email to the user
        try {
            Mail::to($lead->email)->send(new WaitlistConfirmation($lead));
        } catch (\Throwable $e) {
            Log::error('Failed to send waitlist user confirmation email: ' . $e->getMessage(), [
                'email' => $lead->email,
            ]);
        }

        // 2. Send instant alert notification to administrator (gathonipeter26@gmail.com)
        try {
            Mail::to($adminEmail)->send(new AdminNewLeadNotification($lead));
        } catch (\Throwable $e) {
            Log::error('Failed to send admin waitlist alert email: ' . $e->getMessage(), [
                'adminEmail' => $adminEmail,
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Thank you! You have been placed on our priority waitlist.',
                'lead'    => $lead,
            ]);
        }

        return back()->with('success', 'Thank you! You have been placed on our priority waitlist.');
    }

    /**
     * Store an administrator callback or setup inquiry.
     */
    public function storeAdminInquiry(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'phone'         => ['required', 'string', 'min:7', 'max:50'],
            'email'         => ['nullable', 'email', 'max:255'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'message'       => ['nullable', 'string', 'max:1000'],
        ]);

        $lead = WaitlistLead::create([
            'phone'         => trim($validated['phone']),
            'email'         => !empty($validated['email']) ? strtolower(trim($validated['email'])) : null,
            'business_name' => !empty($validated['business_name']) ? trim($validated['business_name']) : 'Direct Inquiry Contact',
            'full_name'     => null,
            'business_type' => 'Retail / FMCG',
            'type'          => 'callback_request',
            'status'        => 'Pending',
            'message'       => !empty($validated['message']) ? trim($validated['message']) : 'Direct setup callback requested from Welcome portal',
        ]);

        $adminEmail = config('mail.admin_notification_email', env('ADMIN_NOTIFICATION_EMAIL', 'gathonipeter26@gmail.com'));

        // Send instant alert notification to administrator (gathonipeter26@gmail.com)
        try {
            Mail::to($adminEmail)->send(new AdminNewLeadNotification($lead));
        } catch (\Throwable $e) {
            Log::error('Failed to send admin callback inquiry alert email: ' . $e->getMessage(), [
                'adminEmail' => $adminEmail,
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Callback request received! Our administrator will contact you shortly.',
                'lead'    => $lead,
            ]);
        }

        return back()->with('success', 'Callback request received! Our administrator will contact you shortly.');
    }
}
