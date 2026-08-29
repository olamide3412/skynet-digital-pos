<?php

namespace App\Mail;

use App\Models\AdminBroadcast;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BroadcastNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AdminBroadcast $broadcast,
        public User $recipient,
        public ?string $loginUrl = null
    ) {}

    public function envelope(): Envelope
    {
        $companyName = SystemSetting::get('company_name', 'Skynet POS');

        return new Envelope(
            subject: "[{$companyName}] {$this->broadcast->title}",
        );
    }

    public function content(): Content
    {
        $companyName = SystemSetting::get('company_name', 'Skynet POS');
        $companyLogo = SystemSetting::get('company_logo_path');
        $currencySymbol = SystemSetting::get('currency_symbol', '₦');
        $supportEmail = SystemSetting::get('support_email', 'support@skynetdigitalltd.com');

        return new Content(
            view: 'emails.broadcast',
            with: [
                'broadcast'      => $this->broadcast,
                'recipient'      => $this->recipient,
                'companyName'    => $companyName,
                'companyLogo'    => $companyLogo ? asset('storage/' . $companyLogo) : null,
                'supportEmail'   => $supportEmail,
                'branchName'     => $this->recipient->branch?->name ?? 'System',
                'loginUrl'       => $this->loginUrl ?? url('/'),
            ]
        );
    }
}
