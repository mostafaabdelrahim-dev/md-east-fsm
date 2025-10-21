<?php
if ( ! is_user_logged_in() ) {
    echo '<div class="mdefsm-login-required">'.esc_html__('Please log in to access the Store Dashboard.','md-east-fsm').'</div>';
    return;
}
?>
<div class="mdefsm-wrap">
  <div class="mdefsm-header">
    <div class="left">
      <h2><?php esc_html_e('Store Dashboard','md-east-fsm'); ?></h2>
      <p><?php esc_html_e('Monitor orders, manage products, and handle wholesale requests — all from the frontend.','md-east-fsm'); ?></p>
    </div>
    <div class="right">
      <button id="mdefsm-refresh" class="btn primary"><?php esc_html_e('Refresh','md-east-fsm'); ?></button>
      <?php
        $p = get_page_by_path('wholesale-print');
        $link = $p ? get_permalink($p->ID) : home_url('/');
        $logout_url = wp_logout_url( wp_login_url() ); // بعد الخروج يذهب لصفحة تسجيل الدخول
      ?>
      <a href="<?php echo esc_url( $link ); ?>" target="_blank" class="btn ghost">
        <?php esc_html_e('Print all wholesale','md-east-fsm'); ?>
      </a>
      <a href="<?php echo esc_url( $logout_url ); ?>" class="btn danger">
        <?php esc_html_e('Logout','md-east-fsm'); ?>
      </a>
    </div>
  </div>

  <div class="mdefsm-cards">
    <div class="card">
      <div class="card-title"><?php esc_html_e("Today's Revenue",'md-east-fsm'); ?></div>
      <div class="card-value" id="todayRevenue">—</div>
    </div>
    <div class="card">
      <div class="card-title"><?php esc_html_e('Orders Today','md-east-fsm'); ?></div>
      <div class="card-value" id="ordersToday">—</div>
    </div>
    <div class="card">
      <div class="card-title"><?php esc_html_e('Unfulfilled','md-east-fsm'); ?></div>
      <div class="card-value" id="ordersPending">—</div>
    </div>
  </div>

  <div class="mdefsm-tabs">
    <button class="tab active" data-tab="orders"><?php esc_html_e('Store Orders','md-east-fsm'); ?></button>
    <button class="tab" data-tab="wholesale"><?php esc_html_e('Wholesale Requests','md-east-fsm'); ?></button>
    <button class="tab" data-tab="products"><?php esc_html_e('Products','md-east-fsm'); ?></button>
  </div>

  <div class="mdefsm-tab" id="tab-orders">
    <div class="table-scroll">
      <table class="mdefsm-table" id="ordersTable">
        <thead><tr>
          <th><?php esc_html_e('Order #','md-east-fsm'); ?></th>
          <th><?php esc_html_e('Customer','md-east-fsm'); ?></th>
          <th><?php esc_html_e('Status','md-east-fsm'); ?></th>
          <th><?php esc_html_e('Date','md-east-fsm'); ?></th>
          <th><?php esc_html_e('Total','md-east-fsm'); ?></th>
          <th><?php esc_html_e('Actions','md-east-fsm'); ?></th>
        </tr></thead>
        <tbody></tbody>
      </table>
    </div>
    <div class="chart-box">
      <canvas id="sales7days"></canvas>
    </div>
  </div>

  <div class="mdefsm-tab hidden" id="tab-wholesale">
    <div class="table-scroll">
      <table class="mdefsm-table" id="whTable">
        <thead><tr>
          <th>ID</th><th><?php esc_html_e('Date','md-east-fsm'); ?></th><th><?php esc_html_e('Name','md-east-fsm'); ?></th>
          <th><?php esc_html_e('Phone','md-east-fsm'); ?></th><th><?php esc_html_e('Email','md-east-fsm'); ?></th>
          <th><?php esc_html_e('Company','md-east-fsm'); ?></th><th><?php esc_html_e('Product','md-east-fsm'); ?></th><th><?php esc_html_e('Qty','md-east-fsm'); ?></th>
          <th><?php esc_html_e('Actions','md-east-fsm'); ?></th>
        </tr></thead>
        <tbody></tbody>
      </table>
    </div>
  </div>

  <div class="mdefsm-tab hidden" id="tab-products">
    <div class="products-layout">
      <div class="products-list">
        <div class="list-header">
          <strong><?php esc_html_e('Products','md-east-fsm'); ?></strong>
          <button id="newProduct" class="btn primary small"><?php esc_html_e('Add New','md-east-fsm'); ?></button>
        </div>
        <table class="mdefsm-table" id="prodTable">
          <thead>
            <tr>
              <th>ID</th>
              <th><?php esc_html_e('Name','md-east-fsm'); ?></th>
              <th>SKU</th>
              <th><?php esc_html_e('Price','md-east-fsm'); ?></th>
              <th><?php esc_html_e('Stock','md-east-fsm'); ?></th>
              <th><?php esc_html_e('Actions','md-east-fsm'); ?></th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>

      <div class="product-editor">
        <h3><?php esc_html_e('Ultimate Product Editor','md-east-fsm'); ?></h3>
        <form id="productForm">
          <input type="hidden" id="p_id">
          <label><?php esc_html_e('Name','md-east-fsm'); ?> <input id="p_name" type="text" required></label>
          <label><?php esc_html_e('Regular Price','md-east-fsm'); ?> <input id="p_regular_price" type="text" inputmode="decimal" placeholder="مثال: 120 أو 120.50"></label>
          <label><?php esc_html_e('Sale Price','md-east-fsm'); ?> <input id="p_sale_price" type="text" inputmode="decimal" placeholder="اختياري"></label>
          <label>SKU <input id="p_sku" type="text"></label>
          <label><?php esc_html_e('Stock Quantity','md-east-fsm'); ?> <input id="p_stock" type="number"></label>
          <label><?php esc_html_e('Status','md-east-fsm'); ?>
            <select id="p_status">
              <option value="publish"><?php esc_html_e('Publish','md-east-fsm'); ?></option>
              <option value="draft"><?php esc_html_e('Draft','md-east-fsm'); ?></option>
              <option value="pending"><?php esc_html_e('Pending','md-east-fsm'); ?></option>
            </select>
          </label>
          <label><?php esc_html_e('Short Description','md-east-fsm'); ?> <textarea id="p_short" rows="3"></textarea></label>
          <label><?php esc_html_e('Description','md-east-fsm'); ?> <textarea id="p_desc" rows="6"></textarea></label>

          <div class="images">
            <div>
              <span><?php esc_html_e('Featured Image','md-east-fsm'); ?></span>
              <input type="file" id="p_featured_file" accept="image/*">
              <input type="hidden" id="p_featured">
              <div id="p_featured_preview" class="img-preview"></div>
            </div>
            <div>
              <span><?php esc_html_e('Gallery','md-east-fsm'); ?></span>
              <input type="file" id="p_gallery_files" accept="image/*" multiple>
              <div id="p_gallery_preview" class="img-grid"></div>
            </div>
          </div>

          <!-- NEW: Images management panel (auto‑filled via REST) -->
          <div class="mdefsm-images-panel card" style="margin-top:12px">
            <h3 style="margin:0 0 10px;">صور المنتج</h3>
            <div id="mdefsm-images" data-current-product="0"></div>
            <p style="font-size:12px;color:#777;margin-top:8px;">
              يمكنك إزالة الصورة من المنتج أو حذفها نهائيًا من المكتبة.
            </p>
          </div>

          <div class="editor-actions">
            <button class="btn primary" id="saveProduct" type="submit"><?php esc_html_e('Save Product','md-east-fsm'); ?></button>
            <button class="btn ghost" id="resetProduct" type="button"><?php esc_html_e('Reset','md-east-fsm'); ?></button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Order Modal -->
<div class="mdefsm-modal hidden" id="orderModal">
  <div class="mdefsm-modal-content" id="orderModalContent">
    <button class="modal-close" id="closeModal">×</button>
    <div class="invoice-header">
      <img src="<?php echo esc_url( MDEFSM_URL . 'assets/logo.png' ); ?>" alt="logo">
      <div>
        <h3><?php esc_html_e('Invoice','md-east-fsm'); ?> <span id="invNumber"></span></h3>
        <small id="invDate"></small>
      </div>
    </div>
    <div class="invoice-body">
      <div class="summary">
        <div><strong><?php esc_html_e('Status','md-east-fsm'); ?>:</strong> <span id="invStatus"></span></div>
        <div><strong><?php esc_html_e('Payment','md-east-fsm'); ?>:</strong> <span id="invPayment"></span></div>
        <div><strong><?php esc_html_e('Total','md-east-fsm'); ?>:</strong> <span id="invTotal"></span></div>
      </div>
      <h4><?php esc_html_e('Items','md-east-fsm'); ?></h4>
      <table class="mdefsm-table small" id="invItems">
        <thead><tr><th>SKU</th><th><?php esc_html_e('Product','md-east-fsm'); ?></th><th>Qty</th><th><?php esc_html_e('Total','md-east-fsm'); ?></th></tr></thead>
        <tbody></tbody>
      </table>
      <h4><?php esc_html_e('Customer','md-east-fsm'); ?></h4>
      <div id="invBilling"></div>
    </div>
    <div class="modal-actions">
      <button class="btn primary" id="printInvoice"><?php esc_html_e('Print Invoice','md-east-fsm'); ?></button>
    </div>
  </div>
</div>
