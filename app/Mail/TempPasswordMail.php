<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TempPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Purpose of the email:
     * - reset      : password reset (NO temp password)
     * - release    : approve self-registered user (NO temp password)
     * - admin_add  : admin-created user (WITH temp password)
     */
    public string $purpose;
    public string $name;
    public string $userCode;
    public ?string $temp;
    public ?string $company;

    public function __construct(
        string $purpose,
        string $name,
        string $userCode,
        ?string $temp = null,
        ?string $company = null
    ) {
        $this->purpose  = $purpose;
        $this->name     = $name;
        $this->userCode = $userCode;
        $this->temp     = $temp;      // ONLY for admin_add
        $this->company  = $company;   // tenant / company code
    }

    public function build()
    {
        // 📌 Subject line based on scenario
        $subject = match ($this->purpose) {
            'reset'     => 'Password Reset Request',
            'admin_add' => 'Your NAYSA Account Has Been Created',
            'release'   => 'Your NAYSA Account Has Been Approved',
            default     => 'NAYSA Account Notification',
        };

        return $this->subject($subject)
            ->view('emails.temp_password')
            ->with([
                'purpose'  => $this->purpose,
                'name'     => $this->name,
                'userCode' => $this->userCode,
                'temp'     => $this->temp,     // null unless admin_add
                'company'  => $this->company,  // enables &company=
            ]);
    }
}
