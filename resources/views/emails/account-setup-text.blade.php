{{ $brandName }} — verify your email

Hello {{ $name }},

Your Lwotowone account is ready. Verify your email address here:
{{ $verificationUrl }}

@if($temporaryPassword)
Login email: {{ $email }}
Temporary password: {{ $temporaryPassword }}
You must change this temporary password immediately after your first sign-in.
@endif

If you did not expect this account, you can ignore this email.
