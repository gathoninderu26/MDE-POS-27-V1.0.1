<?php

namespace App\Mail;

use App\Models\WaitlistLead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WaitlistConfirmation extends Mailable
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
        $biz = $this->lead->business_name ? ": {$this->lead->business_name}" : '';

        return new Envelope(
            subject: "Waitlist Confirmation — MDE Point of Sale{$biz}",
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
     * Build the clean, professional HTML email body.
     */
    private function buildHtml(): string
    {
        $lead         = $this->lead;
        $name         = htmlspecialchars($lead->full_name ?: $lead->business_name ?: 'there');
        $business     = htmlspecialchars($lead->business_name ?: 'Not specified');
        $email        = htmlspecialchars($lead->email ?? '');
        $phone        = htmlspecialchars($lead->phone ?: 'Not provided');
        $businessType = htmlspecialchars($lead->business_type ?? 'Retail Store');
        $refId        = str_pad((string) ($lead->id ?? 1), 5, '0', STR_PAD_LEFT);
        $year         = date('Y');

        return <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Waitlist Confirmation</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; color: #1e293b;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f8fafc; padding: 40px 16px;">
        <tr>
            <td align="center">
                <!-- Main Container -->
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width: 580px; background-color: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    
                    <!-- Brand Top Header -->
                    <tr>
                        <td style="padding: 28px 36px 20px; border-bottom: 1px solid #edf2f7;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td>
                                        <span style="font-size: 16px; font-weight: 800; letter-spacing: -0.3px; color: #0f172a; text-transform: uppercase;">MDE</span>
                                        <span style="font-size: 14px; font-weight: 500; color: #64748b; margin-left: 6px;">Point of Sale</span>
                                    </td>
                                    <td align="right">
                                        <span style="display: inline-block; font-size: 11px; font-weight: 600; color: #475569; background-color: #f1f5f9; padding: 4px 10px; border-radius: 4px; border: 1px solid #e2e8f0;">Early Access</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td style="padding: 36px 36px 28px;">
                            <h1 style="margin: 0 0 16px; font-size: 20px; font-weight: 700; color: #0f172a; letter-spacing: -0.4px;">
                                We've received your registration
                            </h1>
                            
                            <p style="margin: 0 0 16px; font-size: 14px; line-height: 22px; color: #334155;">
                                Hi {$name},
                            </p>

                            <p style="margin: 0 0 24px; font-size: 14px; line-height: 22px; color: #334155;">
                                Thank you for your interest in MDE Point of Sale. Your details have been recorded and <strong>{$business}</strong> has been added to our merchant onboarding queue.
                            </p>

                            <!-- Summary Box -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; margin: 0 0 28px;">
                                <tr>
                                    <td style="padding: 16px 20px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td style="padding: 6px 0; font-size: 12px; font-weight: 600; color: #64748b; width: 140px;">Reference</td>
                                                <td style="padding: 6px 0; font-size: 12px; font-weight: 600; color: #0f172a; font-family: monospace;">#MDE-{$refId}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 6px 0; font-size: 12px; font-weight: 600; color: #64748b;">Business Name</td>
                                                <td style="padding: 6px 0; font-size: 12px; font-weight: 600; color: #0f172a;">{$business}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 6px 0; font-size: 12px; font-weight: 600; color: #64748b;">Business Type</td>
                                                <td style="padding: 6px 0; font-size: 12px; font-weight: 600; color: #0f172a;">{$businessType}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 6px 0; font-size: 12px; font-weight: 600; color: #64748b;">Registered Email</td>
                                                <td style="padding: 6px 0; font-size: 12px; font-weight: 600; color: #0f172a;">{$email}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 6px 0; font-size: 12px; font-weight: 600; color: #64748b;">Phone</td>
                                                <td style="padding: 6px 0; font-size: 12px; font-weight: 600; color: #0f172a;">{$phone}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 6px 0; font-size: 12px; font-weight: 600; color: #64748b;">Queue Status</td>
                                                <td style="padding: 6px 0; font-size: 12px; font-weight: 600; color: #0369a1;">Received &bull; Queued for Review</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <h2 style="margin: 0 0 10px; font-size: 14px; font-weight: 700; color: #0f172a;">
                                What happens next?
                            </h2>
                            <p style="margin: 0 0 8px; font-size: 13px; line-height: 20px; color: #475569;">
                                1. Our onboarding team reviews store requirements and catalog volume within 1–2 business days.
                            </p>
                            <p style="margin: 0 0 8px; font-size: 13px; line-height: 20px; color: #475569;">
                                2. You will receive an activation email with your store's dedicated domain, initial credentials, and hardware setup instructions.
                            </p>
                            <p style="margin: 0 0 28px; font-size: 13px; line-height: 20px; color: #475569;">
                                3. An onboarding specialist will assist with importing existing products, configuring receipt templates, and setting up cashier stations.
                            </p>

                            <!-- CTA button -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 0 28px;">
                                <tr>
                                    <td align="center" style="border-radius: 6px; background-color: #0f172a;">
                                        <a href="http://localhost:8000" target="_blank" style="display: inline-block; padding: 11px 24px; font-size: 13px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 6px;">
                                            Visit MDE POS Portal &rarr;
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0; font-size: 13px; line-height: 20px; color: #64748b;">
                                If you have any urgent questions or specific hardware requirements, please reply directly to this email or write to <a href="mailto:support@mdepos.co.ke" style="color: #0f172a; font-weight: 600; text-decoration: underline;">support@mdepos.co.ke</a>.
                            </p>

                            <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #f1f5f9;">
                                <p style="margin: 0; font-size: 13px; font-weight: 600; color: #334155;">
                                    The MDE POS Team
                                </p>
                                <p style="margin: 2px 0 0; font-size: 12px; color: #94a3b8;">
                                    Retail Infrastructure &amp; POS Systems
                                </p>
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 24px 36px; background-color: #f8fafc; border-top: 1px solid #edf2f7; font-size: 12px; line-height: 18px; color: #94a3b8;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td>
                                        &copy; {$year} MDE Software Systems Ltd. All rights reserved.<br />
                                        Nairobi, Kenya &bull; <a href="http://localhost:8000" style="color: #64748b; text-decoration: none;">mdepos.co.ke</a>
                                    </td>
                                </tr>
                            </table>
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
