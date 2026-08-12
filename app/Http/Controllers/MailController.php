<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MailController extends Controller
{
    public function send(Request $request)
    {
        $request->validate([
            'to'      => 'required|email',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        // Send a raw text email (quick test)
        Mail::raw($request->message, function ($mail) use ($request) {
            $mail->to($request->to)
                 ->subject($request->subject)
                 ->from(config('mail.from.address'), config('mail.from.name'));
        });

        return response()->json([
            'success' => true,
            'message' => "Email sent directly to {$request->to}"
        ]);
    }
}
