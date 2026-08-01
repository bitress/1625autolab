@extends('emails.features.layout')

@section('content')
<p style="margin:0 0 4px;font-size:11px;font-weight:700;letter-spacing:2px;color:#64748b;text-transform:uppercase">Build Update</p>
<h2 style="margin:0 0 6px;font-size:26px;font-weight:900;color:#f1f5f9;line-height:1.2">Hi {!! $1 !!},</h2>
<p style="margin:0 0 24px;font-size:15px;color:#94a3b8">There&rsquo;s a new progress update on your <strong style="color:#f1f5f9">{!! $1 !!}</strong> build!</p>

{!! $1 !!}

{!! $1 !!}

<p style="margin:20px 0 0;font-size:12px;color:#475569">Posted {!! $1 !!}</p>
{!! $1 !!}

@endsection
