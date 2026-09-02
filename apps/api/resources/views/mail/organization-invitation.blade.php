<x-mail::message>
# {{ $invitationHeadline }}

@if (filled($teamName))
{{ $inviterName }} invited you to join **{{ $teamName }}** in **{{ $organizationName }}** on HelixDeploy.
@else
{{ $inviterName }} invited you to join **{{ $organizationName }}** on HelixDeploy.
@endif

<x-mail::button :url="$invitationUrl">
Accept invitation
</x-mail::button>

This link expires in 7 days. If you were not expecting this invitation, you can ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
