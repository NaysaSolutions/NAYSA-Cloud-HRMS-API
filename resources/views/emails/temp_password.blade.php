<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>
    NAYSA Financials Cloud –
    {{ ($purpose ?? '') === 'reset'
        ? 'Password Reset'
        : (($purpose ?? '') === 'admin_add' ? 'Account Created' : 'Account Approved') }}
  </title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    .preheader {
      display:none !important;
      visibility:hidden;
      opacity:0;
      color:transparent;
      height:0;
      width:0;
      overflow:hidden;
      mso-hide:all;
    }

    @media only screen and (max-width: 640px) {
      .email-container { width: 100% !important; }
      .px-mobile { padding-left: 18px !important; padding-right: 18px !important; }
      .stack-col,
      .stack-col td { display:block !important; width:100% !important; text-align:left !important; }
      .cta-btn { display:block !important; width:100% !important; box-sizing:border-box; }
    }
  </style>
</head>

<body style="margin:0;padding:0;background:linear-gradient(180deg,#edf4ff 0%,#f8fafc 100%);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,'Noto Sans',sans-serif;">

@php
  $baseUrl   = rtrim(config('app.url'), '/');
  $purpose   = $purpose ?? '';
  $userQ     = urlencode($userCode ?? '');
  $companyQ  = urlencode($company ?? $companyCode ?? $db ?? $branchcode ?? '');

  $isReset    = $purpose === 'reset';
  $isAdminAdd = $purpose === 'admin_add';
  $isRelease  = $purpose === 'release';

  $actionUrl = "{$baseUrl}/change-password?mode={$purpose}&user={$userQ}&company={$companyQ}";

  // Change this to your real public logo URL
  $logoUrl = "{$baseUrl}/naysa_logo.png";
@endphp

<div class="preheader">
  {{ $isReset
      ? 'Password reset request. Use the secure link to set a new password.'
      : ($isAdminAdd
          ? 'Your account has been created. Temporary password and secure link inside.'
          : 'Your account has been approved. Set your password to continue.') }}
</div>

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:linear-gradient(180deg,#edf4ff 0%,#f8fafc 100%);">
  <tr>
    <td align="center" style="padding:32px 14px;">
      <table class="email-container" width="100%" cellpadding="0" cellspacing="0" border="0"
             style="max-width:640px;background:#ffffff;border-radius:22px;border:1px solid #dbe7f5;overflow:hidden;box-shadow:0 18px 45px rgba(37,99,235,0.10);">

        <!-- Top brand area -->
        <tr>
          <td align="center" style="padding:28px 24px 18px 24px;background:linear-gradient(180deg,#eaf2ff 0%,#ffffff 100%);">
            <img src="{{ $logoUrl }}" alt="NAYSA Logo"
                 style="display:block;width:120px;max-width:120px;height:auto;margin:0 auto 12px auto;">
            <div style="font-size:30px;line-height:36px;font-weight:800;color:#1e3a8a;letter-spacing:-0.02em;">
              NAYSA Financials Cloud
            </div>
            <div style="margin-top:8px;display:inline-block;background:#dbeafe;color:#1d4ed8;border:1px solid #bfdbfe;
                        border-radius:999px;padding:7px 14px;font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;">
              {{ $isReset ? 'Password Reset' : ($isAdminAdd ? 'Account Created' : 'Account Approved') }}
            </div>
          </td>
        </tr>

        <!-- Main body -->
        <tr>
          <td class="px-mobile" style="padding:30px 34px 12px 34px;color:#0f172a;font-size:15px;line-height:1.7;">
            <p style="margin:0 0 14px 0;font-size:16px;">
              Hi <strong>{{ $name }}</strong>,
            </p>

            @if ($isReset)
              <p style="margin:0 0 16px 0;color:#334155;">
                We received a request to reset your password for
                <strong>NAYSA Financials Cloud</strong>. Please click the button below to set a new password securely.
              </p>

            @elseif ($isAdminAdd)
              <p style="margin:0 0 16px 0;color:#334155;">
                Your <strong>NAYSA Financials Cloud</strong> account has been created.
                Please use the temporary password below and update it immediately after signing in.
              </p>

              <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:18px 0 10px 0;">
                <tr>
                  <td style="background:linear-gradient(135deg,#eff6ff 0%,#f8fbff 100%);
                             border:1px solid #cfe0ff;border-radius:18px;padding:18px 18px 16px 18px;">
                    <div style="font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#2563eb;margin-bottom:10px;">
                      Temporary Login Details
                    </div>

                    <table width="100%" cellpadding="0" cellspacing="0" border="0">
                      <tr class="stack-col">
                        <td style="padding:0 0 10px 0;font-size:14px;color:#475569;">
                          <strong style="color:#0f172a;">User ID:</strong><br>
                          <span style="display:inline-block;margin-top:4px;background:#ffffff;border:1px solid #dbeafe;border-radius:10px;padding:8px 10px;color:#1e293b;">
                            {{ $userCode }}
                          </span>
                        </td>
                      </tr>
                      <tr class="stack-col">
                        <td style="padding:0;font-size:14px;color:#475569;">
                          <strong style="color:#0f172a;">Temporary Password:</strong><br>
                          <span style="display:inline-block;margin-top:4px;background:#ffffff;border:1px solid #bfdbfe;border-radius:10px;padding:10px 12px;color:#1d4ed8;font-weight:800;letter-spacing:.02em;">
                            {{ $temp }}
                          </span>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>

            @else
              <p style="margin:0 0 16px 0;color:#334155;">
                Your <strong>NAYSA Financials Cloud</strong> account has been approved.
                Click the button below to create your password and activate your account.
              </p>
            @endif

            <!-- CTA card -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0 8px 0;">
              <tr>
                <td align="center" style="padding:0;">
                  <a href="{{ $actionUrl }}"
                     class="cta-btn"
                     style="display:inline-block;background:linear-gradient(135deg,#2563eb 0%,#4f46e5 100%);
                            color:#ffffff;text-decoration:none;padding:14px 24px;border-radius:12px;
                            font-size:15px;font-weight:700;box-shadow:0 10px 24px rgba(37,99,235,0.22);">
                    {{ $isReset ? 'Reset My Password' : 'Set My Password' }}
                  </a>
                </td>
              </tr>
            </table>

            <!-- Info note -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:22px;">
              <tr>
                <td style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:16px 18px;">
                  <div style="font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#64748b;margin-bottom:8px;">
                    Security Reminders
                  </div>
                  <ul style="margin:0;padding-left:18px;color:#475569;font-size:13px;line-height:1.8;">
                    @if ($isAdminAdd)
                      <li>Change your password immediately after your first login.</li>
                    @endif
                    <li>Use at least 8 characters with uppercase, lowercase, numbers, and symbols.</li>
                    <li>Keep your password private and do not share it with anyone.</li>
                    <li>NAYSA staff will never ask for your password.</li>
                  </ul>
                </td>
              </tr>
            </table>

            <p style="margin:18px 0 8px 0;font-size:12px;color:#64748b;">
              If you did not expect this email, please contact your system administrator right away.
            </p>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="padding:18px 24px 24px 24px;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="border-top:1px solid #e5e7eb;padding-top:14px;">
              <tr class="stack-col">
                <td style="padding-top:14px;font-size:12px;color:#64748b;">
                  © {{ date('Y') }} NAYSA Financials Cloud
                </td>
                <td align="right" style="padding-top:14px;font-size:12px;">
                  <a href="https://www.naysasolutions.com/contact-us"
                     style="color:#2563eb;text-decoration:none;font-weight:600;">
                    Contact Support
                  </a>
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