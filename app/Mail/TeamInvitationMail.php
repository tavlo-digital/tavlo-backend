<?php

namespace App\Mail;

use App\Models\TeamMember;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TeamInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public TeamMember $member)
    {
    }

    public function build(): self
    {
        $vendorName = $this->member->vendor?->restaurant_name
            ?: $this->member->vendor?->name
            ?: 'Tavlo';

        $role = ucfirst($this->member->role);
        $url = $this->inviteUrl();

        return $this
            ->subject("You're invited to {$vendorName} on Tavlo")
            ->html(<<<HTML
                <p>Hello,</p>
                <p>You have been invited to join <strong>{$vendorName}</strong> as <strong>{$role}</strong>.</p>
                <p><a href="{$url}">Accept your invitation and create a password</a></p>
                <p>If the button does not work, open this link: {$url}</p>
            HTML);
    }

    public function inviteUrl(): string
    {
        $baseUrl = rtrim((string) config('app.vendor_frontend_url'), '/');

        return "{$baseUrl}/staff/invite/{$this->member->invitation_token}";
    }
}
