<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>User Registration</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { margin:0; padding:0; background:linear-gradient(180deg,#edf4ff 0%,#f8fafc 100%); font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif; }
    .preheader { display:none !important; visibility:hidden; opacity:0; color:transparent; height:0; width:0; overflow:hidden; }
  </style>
</head>
<body>
@php
  // 1. Safely handle all variables
  $baseUrl     = rtrim(config('app.url', ''), '/');
  $isReject    = isset($isRejection) && $isRejection ? true : false;
  
  $safeCompany = isset($company) ? $company : '';
  $safeUser    = isset($newUserCode) ? $newUserCode : '';
  
  $companyQ    = urlencode($safeCompany);
  $userQ       = urlencode($safeUser);
  $actionUrl   = "{$baseUrl}/approve-user?userCode={$userQ}&company={$companyQ}";
  $logoUrl     = "{$baseUrl}/naysa_logo.png";

  // 2. Pre-calculate text based on the mode
  $headerText  = $isReject ? 'ACCOUNT REJECTED' : 'NEW USER PENDING';
  
  // 3. BUILD ALL DYNAMIC CSS STRINGS HERE TO BYPASS VS CODE LINTER ERRORS
  $headerStyle = "padding:32px 24px 32px 24px; background:linear-gradient(180deg,#f8fafc 0%,#ffffff 100%);";
  
  // Large, bold main title
  $titleStyle  = "font-size:28px; font-weight:900; color:#1e3a8a; letter-spacing:-0.02em; margin-bottom:16px;";
  
  // Rounded Pill Badge design matching your screenshot
  $badgeStyle  = "display:inline-block; background-color:#e0f2fe; border:1px solid #bae6fd; border-radius:50px; padding:8px 20px; font-size:13px; font-weight:800; color:#1d4ed8; letter-spacing:0.05em; text-transform:uppercase;";
  
  $bodyPadding = $isReject ? '40px' : '30px';
  $tdStyle     = "padding:20px 34px {$bodyPadding} 34px; color:#0f172a; font-size:15px; line-height:1.6;";

  $preheader   = $isReject 
      ? 'Your NAYSA account registration has been updated.' 
      : 'A new user has registered and requires your approval to access NAYSA Financials Cloud.';
@endphp

<div class="preheader">{{ $preheader }}</div>

<table width="100%" cellpadding="0" cellspacing="0" border="0">
  <tr>
    <td align="center" style="padding:32px 14px;">
      <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:640px;background:#ffffff;border-radius:22px;border:1px solid #dbe7f5;overflow:hidden;box-shadow:0 18px 45px rgba(37,99,235,0.10);">
        
        <tr>
          <td align="center" style="{{ $headerStyle }}">
            <img src="{{ $logoUrl }}" alt="NAYSA Logo" style="display:block;width:120px;margin:0 auto 16px auto;">
            
            <div style="{{ $titleStyle }}">
              NAYSA Financials Cloud
            </div>
            
            <div style="{{ $badgeStyle }}">
              {{ $headerText }}
            </div>
          </td>
        </tr>

        <tr>
          <td style="{{ $tdStyle }}">
            <p style="margin:0 0 16px 0;">Hello <strong>{{ $adminName ?? 'User' }}</strong>,</p>
            
            @if($isReject)
                <p style="margin:0 0 16px 0;color:#475569;">After reviewing your registration request, your account application was not approved by the Security Administrator at this time.</p>
                <p style="margin:0 0 0 0;color:#475569;">If you believe this was a mistake, or if you need to request access again, please contact your internal Security Administrator directly.</p>
            
            @else
                <p style="margin:0 0 16px 0;color:#475569;">A new user has registered for the NAYSA Financials Cloud and is awaiting your approval to gain access.</p>

                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:20px;margin:20px 0;">
                  <table width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                      <td style="padding-bottom:12px;border-bottom:1px solid #e2e8f0;">
                        <span style="font-size:11px;color:#64748b;text-transform:uppercase;font-weight:700;">User ID</span><br>
                        <span style="font-size:16px;font-weight:600;color:#0f172a;">{{ $safeUser }}</span>
                      </td>
                    </tr>
                    <tr>
                      <td style="padding:12px 0;border-bottom:1px solid #e2e8f0;">
                        <span style="font-size:11px;color:#64748b;text-transform:uppercase;font-weight:700;">Full Name</span><br>
                        <span style="font-size:15px;color:#0f172a;">{{ $newUserName ?? '' }}</span>
                      </td>
                    </tr>
                    <tr>
                      <td style="padding-top:12px;">
                        <span style="font-size:11px;color:#64748b;text-transform:uppercase;font-weight:700;">Email Address</span><br>
                        <span style="font-size:15px;color:#2563eb;">{{ $newUserEmail ?? '' }}</span>
                      </td>
                    </tr>
                  </table>
                </div>

                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:24px;">
                  <tr>
                    <td align="center">
                      <a href="{{ $actionUrl }}" style="display:inline-block;background:linear-gradient(135deg,#2563eb 0%,#4f46e5 100%);color:#ffffff;text-decoration:none;padding:14px 28px;border-radius:12px;font-size:15px;font-weight:700;box-shadow:0 10px 24px rgba(37,99,235,0.22);">Review & Approve User</a>
                    </td>
                  </tr>
                </table>
            @endif

          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
```