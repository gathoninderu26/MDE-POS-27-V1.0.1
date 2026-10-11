<?php

namespace App\Mail;

use App\Models\WaitlistLead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminNewLeadNotification extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public WaitlistLead $lead) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $biz = $this->lead->business_name ?: 'Unknown Merchant';
        $type = $this->lead->type === 'callback_request' ? 'Callback Request' : 'Waitlist Lead';

        return new Envelope(
            subject: "[MDE POS] {$type}: {$biz}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildHtml(),
        );
    }

    /**
     * Build the clean, professional admin alert body.
     */
    private function buildHtml(): string
    {
        $lead = $this->lead;
        $bizName = htmlspecialchars($lead->business_name ?? 'N/A');
        $contactName = htmlspecialchars($lead->full_name ?? 'Not provided');
        $email = htmlspecialchars($lead->email ?? 'Not provided');
        $phone = htmlspecialchars($lead->phone ?? 'Not provided');
        $typeLabel = $lead->type === 'callback_request' ? 'Administrator Callback Request' : 'Early Access Waitlist Registration';
        $bizType = htmlspecialchars($lead->business_type ?? 'Retail');
        $message = htmlspecialchars($lead->message ?? 'No additional notes');
        $leadId = $lead->id ? '#'.str_pad((string) $lead->id, 5, '0', STR_PAD_LEFT) : 'Pending';
        $createdAt = $lead->created_at ? $lead->created_at->format('Y-m-d H:i:s').' EAT' : date('Y-m-d H:i:s').' EAT';

        return <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>New Lead Notification</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; color: #0f172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f1f5f9; padding: 36px 16px;">
        <tr>
            <td align="center">
                <!-- Notification Container -->
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width: 600px; background-color: #ffffff; border-radius: 6px; border: 1px solid #cbd5e1; overflow: hidden;">
                    
                    <!-- Header Bar -->
                    <tr>
                        <td style="padding: 18px 28px; background-color: #0f172a; color: #ffffff;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td>
                                        <span style="font-size: 13px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; color: #94a3b8;">MDE POS</span>
                                        <span style="font-size: 13px; color: #64748b; margin: 0 6px;">/</span>
                                        <span style="font-size: 13px; font-weight: 600; color: #f8fafc;">Central Portal Alert</span>
                                    </td>
                                    <td align="right">
                                        <span style="font-size: 11px; font-family: monospace; color: #94a3b8;">{$leadId}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Title & Overview -->
                    <tr>
                        <td style="padding: 24px 28px 16px; border-bottom: 1px solid #f1f5f9;">
                            <h1 style="margin: 0 0 6px; font-size: 18px; font-weight: 700; color: #0f172a;">
                                {$typeLabel}
                            </h1>
                            <p style="margin: 0; font-size: 13px; color: #64748b;">
                                A new inquiry has been submitted through the public Welcome portal.
                            </p>
                        </td>
                    </tr>

                    <!-- Lead Details Data Table -->
                    <tr>
                        <td style="padding: 20px 28px 28px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse: collapse; width: 100%;">
                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                    <td style="padding: 10px 0; font-size: 13px; font-weight: 600; color: #64748b; width: 150px;">Store / Business</td>
                                    <td style="padding: 10px 0; font-size: 13px; font-weight: 700; color: #0f172a;">{$bizName}</td>
                                </tr>
                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                    <td style="padding: 10px 0; font-size: 13px; font-weight: 600; color: #64748b;">Contact Name</td>
                                    <td style="padding: 10px 0; font-size: 13px; color: #1e293b;">{$contactName}</td>
                                </tr>
                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                    <td style="padding: 10px 0; font-size: 13px; font-weight: 600; color: #64748b;">Email Address</td>
                                    <td style="padding: 10px 0; font-size: 13px;">
                                        <a href="mailto:{$email}" style="color: #2563eb; text-decoration: none; font-weight: 600;">{$email}</a>
                                    </td>
                                </tr>
                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                    <td style="padding: 10px 0; font-size: 13px; font-weight: 600; color: #64748b;">Phone Number</td>
                                    <td style="padding: 10px 0; font-size: 13px;">
                                        <a href="tel:{$phone}" style="color: #059669; text-decoration: none; font-weight: 600; font-family: monospace;">{$phone}</a>
                                    </td>
                                </tr>
                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                    <td style="padding: 10px 0; font-size: 13px; font-weight: 600; color: #64748b;">Business Category</td>
                                    <td style="padding: 10px 0; font-size: 13px; color: #1e293b;">{$bizType}</td>
                                </tr>
                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                    <td style="padding: 10px 0; font-size: 13px; font-weight: 600; color: #64748b; vertical-align: top;">Notes / Message</td>
                                    <td style="padding: 10px 0; font-size: 13px; color: #334155; line-height: 18px;">{$message}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 0; font-size: 13px; font-weight: 600; color: #64748b;">Timestamp</td>
                                    <td style="padding: 10px 0; font-size: 12px; color: #64748b; font-family: monospace;">{$createdAt}</td>
                                </tr>
                            </table>

                            <!-- Action Buttons -->
                            <div style="margin-top: 24px; padding-top: 18px; border-top: 1px solid #e2e8f0;">
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                    <tr>
                                        <td style="border-radius: 4px; background-color: #0f172a; margin-right: 8px;">
                                            <a href="mailto:{$email}?subject=MDE Point of Sale — Onboarding Follow-up" style="display: inline-block; padding: 8px 18px; font-size: 12px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 4px;">
                                                Reply to Lead
                                            </a>
                                        </td>
                                        <td style="width: 10px;"></td>
                                        <td style="border-radius: 4px; background-color: #f1f5f9; border: 1px solid #cbd5e1;">
                                            <a href="tel:{$phone}" style="display: inline-block; padding: 8px 18px; font-size: 12px; font-weight: 600; color: #334155; text-decoration: none; border-radius: 4px;">
                                                Call Phone
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 14px 28px; background-color: #f8fafc; border-top: 1px solid #e2e8f0; font-size: 11px; color: #94a3b8;">
                            Sent automatically by MDE Central Engine to <span style="font-family: monospace; color: #64748b;">gathonipeter26@gmail.com</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}
