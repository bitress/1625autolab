@extends('emails.features.layout')

@section('content')
<p style="margin:0 0 16px">Hi {!! $1 !!},</p>
<p style="margin:0 0 16px">We received your order and it is now being reviewed by our team.</p>

<div style="background:#162032;border:1px solid #334155;border-radius:8px;padding:20px;margin-bottom:20px">
  <p style="margin:0 0 6px;font-size:11px;letter-spacing:1px;text-transform:uppercase;color:#94a3b8;font-weight:700">Order Number</p>
  <p style="margin:0 0 12px;font-size:20px;font-weight:800;color:#f8fafc">{!! $1 !!}</p>
  <div>{!! $1 !!}</div>
</div>

<div style="margin-bottom:20px">
  <p style="margin:0 0 10px;font-size:11px;letter-spacing:1px;text-transform:uppercase;color:#94a3b8;font-weight:700">Items</p>
  {!! $1 !!}
</div>

<div style="background:#162032;border:1px solid #334155;border-radius:8px;overflow:hidden;margin-bottom:20px">
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr>
      <td style="padding:10px 16px;border-bottom:1px solid #334155;color:#64748b;font-size:13px;width:160px">Fulfillment</td>
      <td style="padding:10px 16px;border-bottom:1px solid #334155;color:#f1f5f9">{!! $1 !!}</td>
    </tr>
    <tr>
      <td style="padding:10px 16px;border-bottom:1px solid #334155;color:#64748b;font-size:13px">Payment</td>
      <td style="padding:10px 16px;border-bottom:1px solid #334155;color:#f1f5f9">{!! $1 !!}</td>
    </tr>
    <tr>
      <td style="padding:10px 16px;border-bottom:1px solid #334155;color:#64748b;font-size:13px">Subtotal</td>
      <td style="padding:10px 16px;border-bottom:1px solid #334155;color:#f1f5f9">{!! $1 !!}</td>
    </tr>
    <tr>
      <td style="padding:10px 16px;border-bottom:1px solid #334155;color:#64748b;font-size:13px">Shipping</td>
      <td style="padding:10px 16px;border-bottom:1px solid #334155;color:#f1f5f9">{!! $1 !!}</td>
    </tr>
    <tr>
      <td style="padding:10px 16px;color:#64748b;font-size:13px">Total</td>
      <td style="padding:10px 16px;color:#f97316;font-weight:800">{!! $1 !!}</td>
    </tr>
  </table>
</div>

{!! $1 !!}
{!! $1 !!}

<p style="margin:20px 0 0;color:#94a3b8">We will email you again when your order status changes or when tracking is assigned.</p>
@endsection
