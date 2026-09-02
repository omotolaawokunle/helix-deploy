<x-mail::message>
# You're invited to {{ $organizationName }}

{{ $inviterName }} invited you to join **{{ $organizationName }}** on HelixDeploy.

<x-mail::button :url="$invitationUrl">
Accept invitation
</x-mail::button>

This link expires in 7 days. If you were not expecting this invitation, you can ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
