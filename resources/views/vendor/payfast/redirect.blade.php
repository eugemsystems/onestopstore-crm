<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Redirecting to PayFast…</title></head>
<body onload="document.forms.payfast_form.submit();">
{!! $paymentForm !!}
<noscript>
    <p>Please click the button below to pay:</p>
    <button type="submit" form="payfast_form">Pay Now</button>
</noscript>
</body>
</html>
