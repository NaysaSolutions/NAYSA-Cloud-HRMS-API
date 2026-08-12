<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminApprovalMail extends Mailable
{
    use Queueable, SerializesModels;

    public $adminName;
    public $newUserCode;
    public $newUserName;
    public $newUserEmail;
    public $company;
    public $isRejection; // Added this variable

    // We set default values to null and false so we can reuse this class flexibly
    public function __construct($adminName, $newUserCode = null, $newUserName = null, $newUserEmail = null, $company = null, $isRejection = false)
    {
        $this->adminName = $adminName;
        $this->newUserCode = $newUserCode;
        $this->newUserName = $newUserName;
        $this->newUserEmail = $newUserEmail;
        $this->company = $company;
        $this->isRejection = $isRejection;
    }

    public function build()
    {
        // Change the subject line dynamically based on whether it is a rejection
        $subject = $this->isRejection 
            ? "Update on your NAYSA Financials Cloud Registration"
            : "Action Required: New User Registration ({$this->newUserCode})";

        return $this->subject($subject)
                    ->view('emails.admin_approval_notification');
    }
}