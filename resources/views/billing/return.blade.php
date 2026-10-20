<!doctype html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>DeliveryHub — الاشتراك</title>
<style>body{margin:0;background:#f5f7fb;font:17px/1.8 system-ui;color:#0b1730}main{max-width:520px;margin:12vh auto;padding:32px;background:white;border-radius:20px}a{display:block;text-align:center;background:#2563eb;color:white;padding:14px;border-radius:12px;text-decoration:none;margin-top:24px}small{color:#64748b}</style></head>
<body><main>
<h1>DeliveryHub</h1>
@if($outcome === 'cancel')
<h2>لم تكتمل عملية الاشتراك</h2><p>يمكنك العودة إلى التطبيق والمحاولة مجددًا. اشتراكك السابق لا يتغير بمجرد زيارة هذه الصفحة.</p>
<p lang="en" dir="ltr">Checkout was cancelled. Return to the app to review your subscription.</p>
@else
<h2>ارجع إلى التطبيق للتحقق من الاشتراك</h2><p>يتم تفعيل الاشتراك بعد تأكيد الدفع من Stripe. قد يستغرق ظهور التحديث لحظات.</p>
<p lang="en" dir="ltr">Return to the app to check your subscription. Access is granted after verified payment confirmation.</p>
@endif
<a href="deliveryhubmobile://merchant/subscription?billing=return">العودة إلى تطبيق DeliveryHub / Open app</a>
<small>إذا لم يفتح التطبيق، افتحه يدويًا وحدّث صفحة الاشتراكات. رابط العودة المباشر يتطلب نسخة مثبتة من التطبيق.</small>
</main></body></html>
