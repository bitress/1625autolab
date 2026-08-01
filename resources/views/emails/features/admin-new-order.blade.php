@extends('emails.features.layout')

@section('content')
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px">
  <tr>
    <td bgcolor="#f97316" style="padding:16px 20px;border-radius:8px 0 0 8px;width:50%">
      <p style="margin:0;font-size:11px;letter-spacing:1px;text-transform:uppercase;color:#fff;font-weight:700">New Order</p>
      <p style="margin:6px 0 0;font-size:22px;font-weight:800;color:#fff">{!! $1 !!}</p>
    </td>
    <td bgcolor="#ea6b00" style="padding:16px 20px;border-radius:0 8px 8px 0;text-align:right">
      {!! $1 !!}
    </td>
  </tr>
</table>

<div style="background:#162032;border:1px solid #334155;border-radius:8px;overflow:hidden;margin-bottom:20px">
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr>
      <td style="padding:10px 16px;border-bottom:1px solid #334155;color:#64748b;font-size:13px;width:140px">Customer</td>
      <td style="padding:10px 16px;border-bottom:1px solid #334155;color:#f1f5f9;font-weight:600">{!! $1 !!}</td>
    </tr>
    <tr>
      <td style="padding:10px 16px;border-bottom:1px solid #334155;color:#64748b;font-size:13px">Contact</td>
      <td style="padding:10px 16px;border-bottom:1px solid #334155;color:#f1f5f9">{!! $1 !!} &middot; {!! $1 !!}</td>
    </tr>
    <tr>
      <td style="padding:10px 16px;border-bottom:1px solid #334155;color:#64748b;font-size:13px">Fulfillment</td>
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

<div style="margin-bottom:20px">
  <p style="margin:0 0 10px;font-size:11px;letter-spacing:1px;text-transform:uppercase;color:#94a3b8;font-weight:700">Items</p>
  {!! $1 !!}
</div>

{!! $1 !!}
{!! $1 !!}
@endsection
