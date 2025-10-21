<div class="mdefsm-form-wrap">
  <h2>طلب شراء جملة</h2>
  <p>املأ البيانات التالية وسيقوم فريقنا بالتواصل معك خلال وقت قصير.</p>
  <form id="mdWholesaleForm" class="md-form">
    <div class="grid">
      <label>الاسم الكامل * <input type="text" name="name" required placeholder="مثال: محمد علي"></label>
      <label>رقم الجوال * <input type="text" name="phone" required placeholder="+9665xxxxxxxx"></label>
      <label>الشركة <input type="text" name="company" placeholder="اسم الشركة (اختياري)"></label>
      <label>البريد الإلكتروني <input type="email" name="email" placeholder="example@email.com"></label>
      <label>المنتج المطلوب <input type="text" name="product" placeholder="اسم/نوع المنتج"></label>
      <label>الكمية المتوقعة <input type="text" name="qty" placeholder="مثال: 100 كرتونة"></label>
    </div>
    <label>تفاصيل إضافية <textarea name="notes" rows="5" placeholder="مواصفات، ملاحظات، مدينة التسليم.. إلخ"></textarea></label>
    <label class="agree"><input type="checkbox" name="agree" checked> أوافق على التواصل بخصوص طلبي عبر الجوال أو البريد.</label>
    <button type="submit" class="btn primary">إرسال الطلب</button>
    <div id="whFormMsg" class="msg"></div>
  </form>
</div>
