<?php
if ( ! is_user_logged_in() || ! ( current_user_can('manage_woocommerce') || current_user_can('md_store_manager') || current_user_can('administrator') ) ) {
    echo '<div style="padding:20px;border:1px solid #ddd;border-radius:10px;background:#fff8f0;font-family:Tahoma,Arial">عذرًا، هذه الصفحة للمسؤولين فقط.</div>';
    return;
}
$posts = get_posts( array(
    'post_type' => 'md_wholesale',
    'numberposts' => -1,
    'orderby' => 'date',
    'order' => 'DESC'
) );
?><!doctype html>
<html dir="rtl" lang="ar">
<head>
<meta charset="utf-8">
<title>طباعة كل طلبات الجملة</title>
<style>
body{font-family:Tahoma,Arial;background:#fff;color:#111;margin:20px}
table{width:100%;border-collapse:collapse;margin-top:10px}
th,td{border:1px solid #ddd;padding:8px}
th{background:#f3f3f3}
.header{display:flex;align-items:center;gap:12px}
.header img{height:54px}
.print-btn{margin:10px 0;padding:8px 12px;border:1px solid #333;background:#fff;border-radius:8px;cursor:pointer}
@media print {.print-btn{display:none} a{display:none}}
</style>
</head>
<body>
<div class="header">
    <img src="<?php echo esc_url( MDEFSM_URL . 'assets/logo.png' ); ?>" alt="logo">
    <h2>طلبات الجملة — الكل</h2>
</div>
<button class="print-btn" onclick="window.print()">طباعة</button>
<table>
    <thead><tr><th>ID</th><th>التاريخ</th><th>الاسم</th><th>الجوال</th><th>الإيميل</th><th>الشركة</th><th>المنتج</th><th>الكمية</th><th>ملاحظات</th></tr></thead>
    <tbody>
        <?php foreach( $posts as $p ): ?>
            <tr>
                <td><?php echo intval($p->ID); ?></td>
                <td><?php echo esc_html( get_the_date('Y-m-d H:i', $p) ); ?></td>
                <td><?php echo esc_html( $p->post_title ); ?></td>
                <td><?php echo esc_html( get_post_meta($p->ID,'_md_w_phone',true) ); ?></td>
                <td><?php echo esc_html( get_post_meta($p->ID,'_md_w_email',true) ); ?></td>
                <td><?php echo esc_html( get_post_meta($p->ID,'_md_w_company',true) ); ?></td>
                <td><?php echo esc_html( get_post_meta($p->ID,'_md_w_product',true) ); ?></td>
                <td><?php echo esc_html( get_post_meta($p->ID,'_md_w_qty',true) ); ?></td>
                <td><?php echo esc_html( $p->post_content ); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<script>window.addEventListener('load', ()=>{ setTimeout(()=>{ window.print(); }, 300); });</script>
</body>
</html>
