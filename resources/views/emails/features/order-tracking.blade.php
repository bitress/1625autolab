@extends('emails.features.layout')

@section('content')
<p style="margin:0 0 16px">Hi {!! $1 !!},</p>
<p style="margin:0 0 16px">We updated the delivery details for order <strong style="color:#f8fafc">{!! $1 !!}</strong>.</p>

<div style="background:#162032;border:1px solid #334155;border-radius:8px;padding:20px;margin-bottom:20px">
  <div style="margin-bottom:12px">{!! $1 !!}</div>
  <p style="margin:0;color:#cbd5e1">{!! $1 !!}</p>
</div>

<div style="background:#162032;border:1px solid #334155;border-radius:8px;overflow:hidden;margin-bottom:20px">
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr>
      <td style="padding:10px 16px;border-bottom:1px solid #334155;color:#64748b;font-size:13px;width:160px">Order Number</td>
      <td style="padding:10px 16px;border-bottom:1px solid #334155;color:#f1f5f9">{!! $1 !!}</td>
    </tr>
    <tr>
      <td style="padding:10px 16px;border-bottom:1px solid #334155;color:#64748b;font-size:13px">Courier</td>
      <td style="padding:10px 16px;border-bottom:1px solid #334155;color:#f1f5f9">{!! $1 !!}</td>
    </tr>
    <tr>
      <td style="padding:10px 16px;color:#64748b;font-size:13px">Tracking Number</td>
      <td style="padding:10px 16px;color:#f97316;font-weight:800">{!! $1 !!}</td>
    </tr>
  </table>
</div>

{!! $1 !!}
@endsection
