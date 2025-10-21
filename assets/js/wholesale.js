(function(){
  function el(sel, ctx){ return (ctx||document).querySelector(sel); }
  const form = el('#mdWholesaleForm');
  if(!form) return;
  form.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const data = Object.fromEntries(new FormData(form).entries());
    if(!data.name || !data.phone){ el('#whFormMsg').textContent = 'الاسم ورقم الجوال مطلوبان.'; return; }
    const r = await fetch((window.MDEFSM_W?.rest||'') + 'wholesale/submit', {
      method: 'POST',
      headers: {'Content-Type':'application/json','X-WP-Nonce': (window.MDEFSM_W?.nonce||'') },
      body: JSON.stringify(data)
    });
    if(r.ok){
      el('#whFormMsg').textContent = 'تم استلام طلبك بنجاح. سنقوم بالتواصل معك قريبًا.';
      form.reset();
    } else {
      el('#whFormMsg').textContent = 'حدث خطأ أثناء الإرسال، حاول مرة أخرى.';
    }
  });
})();