<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class MDEFSM_REST {

    /* Capabilities/role helpers */
    private function is_store_manager_role(): bool {
        $u = wp_get_current_user();
        return $u && in_array( 'md_store_manager', (array) $u->roles, true );
    }

    public function can_manage(): bool {
        // Use capabilities, not role strings, so admins (manage_options) and Woo managers (manage_woocommerce)
        // pass reliably; also allow your custom role by slug.
        return current_user_can('manage_woocommerce')
            || current_user_can('manage_options')
            || $this->is_store_manager_role();
    }

    public function __construct() {
        // ===== Orders =====
        register_rest_route( 'md/v1', '/orders', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'get_orders' ),
            'permission_callback' => array( $this, 'can_manage' ),
        ) );

        register_rest_route( 'md/v1', '/order/(?P<id>\d+)', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'get_order' ),
            'permission_callback' => array( $this, 'can_manage' ),
        ) );

        register_rest_route( 'md/v1', '/order/(?P<id>\d+)/delete', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'delete_order' ),
            'permission_callback' => array( $this, 'can_manage' ),
        ) );

        register_rest_route( 'md/v1', '/order/(?P<id>\d+)/send-invoice', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'send_invoice' ),
            'permission_callback' => array( $this, 'can_manage' ),
        ) );

        register_rest_route( 'md/v1', '/stats/last7', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'stats_last7' ),
            'permission_callback' => array( $this, 'can_manage' ),
        ) );

        // ===== Wholesale =====
        register_rest_route( 'md/v1', '/wholesale', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'get_wholesale' ),
            'permission_callback' => '__return_true',
        ) );
        register_rest_route( 'md/v1', '/wholesale/submit', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'submit_wholesale' ),
            'permission_callback' => '__return_true',
        ) );
        register_rest_route( 'md/v1', '/wholesale/(?P<id>\d+)/delete', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'delete_wholesale' ),
            'permission_callback' => array( $this, 'can_manage' ),
        ) );
        register_rest_route( 'md/v1', '/wholesale/print-all', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'print_wholesale_all' ),
            'permission_callback' => array( $this, 'can_manage' ),
        ) );

        // ===== Products (list/create/get/delete/upload) =====
        register_rest_route( 'md/v1', '/products', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'products_list' ),
            'permission_callback' => array( $this, 'can_manage' ),
        ) );
        register_rest_route( 'md/v1', '/product', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'product_save' ),
            'permission_callback' => array( $this, 'can_manage' ),
        ) );
        register_rest_route( 'md/v1', '/product/(?P<id>\d+)', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'product_get' ),
            'permission_callback' => array( $this, 'can_manage' ),
        ) );
        register_rest_route( 'md/v1', '/product/(?P<id>\d+)/delete', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'product_delete' ),
            'permission_callback' => array( $this, 'can_manage' ),
        ) );
        register_rest_route( 'md/v1', '/upload', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'upload_media' ),
            'permission_callback' => array( $this, 'can_manage' ),
        ) );

        // ===== NEW: Product images list/delete for frontend editor =====
        register_rest_route( 'md/v1', '/products/(?P<id>\d+)/images', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'list_product_images' ),
            'permission_callback' => function( WP_REST_Request $req ) {
                $pid = (int) $req['id'];
                return current_user_can('manage_woocommerce') || current_user_can('manage_options') || current_user_can('edit_post', $pid) || $this->is_store_manager_role();
            },
        ) );

        register_rest_route( 'md/v1', '/media/(?P<att>\d+)', array(
            'methods'  => 'DELETE',
            'callback' => array( $this, 'delete_product_image' ),
            'args' => array(
                'product' => array( 'required' => true, 'type' => 'integer' ),
                'hard'    => array( 'required' => false, 'type' => 'boolean' ),
            ),
            'permission_callback' => function( WP_REST_Request $req ) {
                $pid = (int) $req->get_param('product');
                return current_user_can('manage_woocommerce') || current_user_can('manage_options') || current_user_can('delete_post', $pid) || $this->is_store_manager_role();
            },
        ) );
    }

    /* ===== Orders ===== */
    private function order_to_row( $order ) {
        $id = $order->get_id();
        return array(
            'id' => $id,
            'number' => $order->get_order_number(),
            'status' => $order->get_status(),
            'date' => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y-m-d H:i' ) : '',
            'total' => $order->get_total(),
            'currency' => $order->get_currency(),
            'customer' => $order->get_formatted_billing_full_name(),
        );
    }

    public function get_orders( $request ) {
        if ( ! class_exists( 'WC_Order' ) ) {
            return new WP_Error( 'no_wc', 'WooCommerce is required' );
        }
        $args = array(
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'date_created' => '>' . ( new DateTime('-14 days') )->format('Y-m-d H:i:s'),
        );
        $orders = wc_get_orders( $args );
        $data = array_map( array( $this, 'order_to_row' ), $orders );
        return rest_ensure_response( $data );
    }

    public function get_order( $request ) {
        $id = absint( $request['id'] );
        $order = wc_get_order( $id );
        if ( ! $order ) return new WP_Error( 'not_found', 'Order not found' );
        $items = array();
        foreach ( $order->get_items() as $item_id => $item ) {
            $items[] = array(
                'name' => $item->get_name(),
                'qty'  => $item->get_quantity(),
                'total'=> wc_format_decimal( $item->get_total(), 2 ),
                'sku'  => $item->get_product() ? $item->get_product()->get_sku() : '',
            );
        }
        $billing = array(
            'name' => $order->get_formatted_billing_full_name(),
            'email'=> $order->get_billing_email(),
            'phone'=> $order->get_billing_phone(),
            'address' => $order->get_formatted_billing_address(),
        );
        $data = array(
            'id' => $order->get_id(),
            'number' => $order->get_order_number(),
            'status' => $order->get_status(),
            'payment' => $order->get_payment_method_title(),
            'total' => $order->get_total(),
            'currency' => $order->get_currency(),
            'date' => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y-m-d H:i' ) : '',
            'items' => $items,
            'billing' => $billing,
        );
        return rest_ensure_response( $data );
    }

    public function delete_order( $request ) {
        $id = absint( $request['id'] );
        if ( ! current_user_can( 'delete_shop_orders' ) ) {
            return new WP_Error( 'forbidden', 'You cannot delete orders' );
        }
        if ( function_exists('wc_get_order') && $order = wc_get_order( $id ) ) {
            wp_trash_post( $id );
            return rest_ensure_response( array( 'ok' => true ) );
        }
        return new WP_Error( 'not_found', 'Order not found' );
    }

    public function send_invoice( $request ) {
        $id = absint( $request['id'] );
        $order = wc_get_order( $id );
        if ( ! $order ) return new WP_Error( 'not_found', 'Order not found' );

        $to = $order->get_billing_email();
        if ( ! $to ) return new WP_Error( 'no_email', 'Order has no billing email' );
        $subject = sprintf( __( 'Invoice for order #%s', 'md-east-fsm' ), $order->get_order_number() );

        ob_start();
        $logo = MDEFSM_URL . 'assets/logo.png';
        ?>
        <html><body style="font-family:Tahoma,Arial; background:#f7f9fb; padding:20px;">
        <div style="max-width:720px;margin:auto;background:#ffffff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.06);overflow:hidden;">
            <div style="padding:20px 24px;border-bottom:1px solid #eee;display:flex;align-items:center;gap:16px;">
                <img src="<?php echo esc_url( $logo ); ?>" alt="MD east factory" style="height:54px;">
                <h2 style="margin:0;"><?php echo esc_html( get_bloginfo('name') ); ?> — <?php esc_html_e('Invoice','md-east-fsm'); ?></h2>
            </div>
            <div style="padding:24px;">
                <p><?php printf( __('Order #%s — %s','md-east-fsm'), $order->get_order_number(), wc_price($order->get_total(), array('currency'=>$order->get_currency())) ); ?></p>
                <p><strong><?php _e('Payment method:','md-east-fsm'); ?></strong> <?php echo esc_html( $order->get_payment_method_title() ); ?></p>
                <h3><?php _e('Items','md-east-fsm'); ?></h3>
                <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse">
                    <thead><tr><th align="left">Product</th><th align="center">Qty</th><th align="right">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ( $order->get_items() as $item ) : ?>
                        <tr style="border-top:1px solid #eee;">
                            <td><?php echo esc_html( $item->get_name() ); ?></td>
                            <td align="center"><?php echo esc_html( $item->get_quantity() ); ?></td>
                            <td align="right"><?php echo wp_kses_post( wc_price( $item->get_total(), array('currency'=>$order->get_currency()) ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="text-align:right;font-size:18px;margin-top:16px;"><strong><?php _e('Grand total:','md-east-fsm'); ?></strong> <?php echo wp_kses_post( wc_price( $order->get_total(), array('currency'=>$order->get_currency()) ) ); ?></p>
                <h3><?php _e('Billing','md-east-fsm'); ?></h3>
                <p><?php echo wp_kses_post( $order->get_formatted_billing_address() ); ?><br><?php echo esc_html( $order->get_billing_email() ); ?> — <?php echo esc_html( $order->get_billing_phone() ); ?></p>
            </div>
        </div>
        </body></html>
        <?php
        $html = ob_get_clean();

        add_filter( 'wp_mail_content_type', array( $this, 'set_html_mail' ) );
        $sent = wp_mail( $to, $subject, $html );
        remove_filter( 'wp_mail_content_type', array( $this, 'set_html_mail' ) );

        if ( $sent ) return rest_ensure_response( array('ok'=>true) );
        return new WP_Error( 'mail_failed', 'Failed to send invoice' );
    }

    public function set_html_mail() { return 'text/html'; }

    public function stats_last7( $request ) {
        if ( ! class_exists( 'WC_Order' ) ) {
            return rest_ensure_response( array( 'labels'=> array(), 'values'=> array() ) );
        }
        $labels = array();
        $values = array();
        for ( $i=6; $i>=0; $i-- ) {
            $day = new DateTime();
            $day->setTimestamp( current_time( 'timestamp' ) );
            $day->modify( '-' . $i . ' day' );
            $labels[] = $day->format( 'Y-m-d' );
            $orders = wc_get_orders( array(
                'limit' => -1,
                'date_created' => $day->format('Y-m-d 00:00:00') . '...' . $day->format('Y-m-d 23:59:59'),
                'status' => array_keys( wc_get_order_statuses() ),
                'return' => 'ids'
            ) );
            $sum = 0;
            foreach ( $orders as $oid ) {
                $o = wc_get_order( $oid );
                $sum += floatval( $o->get_total() );
            }
            $values[] = $sum;
        }
        return rest_ensure_response( compact('labels','values') );
    }

    /* ===== Wholesale ===== */
    public function submit_wholesale( $request ) {
        $params = $request->get_json_params();
        $name = sanitize_text_field( $params['name'] ?? '' );
        $phone = sanitize_text_field( $params['phone'] ?? '' );
        $company = sanitize_text_field( $params['company'] ?? '' );
        $email = sanitize_email( $params['email'] ?? '' );
        $product = sanitize_text_field( $params['product'] ?? '' );
        $qty = sanitize_text_field( $params['qty'] ?? '' );
        $notes = sanitize_textarea_field( $params['notes'] ?? '' );

        $post_id = wp_insert_post( array(
            'post_type' => 'md_wholesale',
            'post_title' => $name . ' — ' . $phone,
            'post_content' => $notes,
            'post_status' => 'publish',
            'meta_input' => array(
                '_md_w_phone' => $phone,
                '_md_w_company' => $company,
                '_md_w_email' => $email,
                '_md_w_product' => $product,
                '_md_w_qty' => $qty,
            ),
        ) );
        if ( is_wp_error( $post_id ) ) return $post_id;

        return rest_ensure_response( array( 'ok'=>true, 'id'=>$post_id ) );
    }

    public function get_wholesale( $request ) {
        $posts = get_posts( array(
            'post_type' => 'md_wholesale',
            'numberposts' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ) );
        $rows = array();
        foreach ( $posts as $p ) {
            $rows[] = array(
                'id' => $p->ID,
                'date' => get_the_date( 'Y-m-d H:i', $p ),
                'name' => $p->post_title,
                'notes' => $p->post_content,
                'phone' => get_post_meta( $p->ID, '_md_w_phone', true ),
                'company' => get_post_meta( $p->ID, '_md_w_company', true ),
                'email' => get_post_meta( $p->ID, '_md_w_email', true ),
                'product' => get_post_meta( $p->ID, '_md_w_product', true ),
                'qty' => get_post_meta( $p->ID, '_md_w_qty', true ),
            );
        }
        return rest_ensure_response( $rows );
    }

    public function delete_wholesale( $request ) {
        $id = absint( $request['id'] );
        wp_trash_post( $id );
        return rest_ensure_response( array( 'ok'=>true ) );
    }

    public function print_wholesale_all( $request ) {
        $rows = $this->get_wholesale( $request );
        if ( $rows instanceof WP_Error ) return $rows;
        $rows = $rows->data;
        ob_start();
        ?>
        <html><head>
        <meta charset="utf-8">
        <title><?php esc_html_e('Wholesale Orders','md-east-fsm'); ?></title>
        <style>body{font-family:Tahoma,Arial} table{border-collapse:collapse;width:100%} th,td{border:1px solid #ccc;padding:8px} th{background:#f3f3f3}</style>
        </head><body onload="window.print()">
        <h2><?php esc_html_e('Wholesale Orders (All)','md-east-fsm'); ?></h2>
        <table>
            <thead><tr><th>ID</th><th>Date</th><th>Name</th><th>Phone</th><th>Email</th><th>Company</th><th>Product</th><th>Qty</th><th>Notes</th></tr></thead>
            <tbody>
                <?php foreach ( $rows as $r ) : ?>
                    <tr>
                        <td><?php echo intval($r['id']); ?></td>
                        <td><?php echo esc_html($r['date']); ?></td>
                        <td><?php echo esc_html($r['name']); ?></td>
                        <td><?php echo esc_html($r['phone']); ?></td>
                        <td><?php echo esc_html($r['email']); ?></td>
                        <td><?php echo esc_html($r['company']); ?></td>
                        <td><?php echo esc_html($r['product']); ?></td>
                        <td><?php echo esc_html($r['qty']); ?></td>
                        <td><?php echo esc_html($r['notes']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </body></html>
        <?php
        $html = ob_get_clean();
        return new WP_REST_Response( $html, 200, array( 'Content-Type' => 'text/html; charset=UTF-8' ) );
    }

    /* ===== Products ===== */
    public function products_list( $request ) {
        $args = array(
            'status' => array( 'publish','draft','pending','private' ),
            'limit' => -1,
        );
        $items = wc_get_products( $args );
        $rows = array();
        foreach ( $items as $prod ) {
            $p = ( $prod instanceof WC_Product ) ? $prod : wc_get_product( $prod );
            if ( ! $p ) { continue; }
            $rows[] = array(
                'id' => $p->get_id(),
                'name' => $p->get_name(),
                'price'=> $p->get_regular_price(),
                'stock'=> $p->get_stock_quantity(),
                'sku'  => $p->get_sku(),
                'status'=> $p->get_status(),
            );
        }
        return rest_ensure_response( $rows );
    }

    public function product_get( $request ) {
        $id = absint( $request['id'] );
        $p = wc_get_product( $id );
        if ( ! $p ) return new WP_Error( 'not_found', 'Product not found' );
        return rest_ensure_response( array(
            'id' => $id,
            'name' => $p->get_name(),
            'description' => $p->get_description(),
            'short_description' => $p->get_short_description(),
            'regular_price' => $p->get_regular_price(),
            'sale_price' => $p->get_sale_price(),
            'sku' => $p->get_sku(),
            'stock_quantity' => $p->get_stock_quantity(),
            'status' => $p->get_status(),
            'images' => $p->get_gallery_image_ids(),
            'featured_image' => $p->get_image_id(),
        ) );
    }

    public function product_delete( $request ) {
        $id = absint( $request['id'] );
        if ( ! current_user_can( 'delete_products' ) ) return new WP_Error( 'forbidden', 'Cannot delete products' );
        wp_trash_post( $id );
        return rest_ensure_response( array( 'ok'=>true ) );
    }

    // ======= إصلاح السعر هنا =======
    public function product_save( $request ) {
        $params = $request->get_json_params();
        $id = isset($params['id']) ? absint($params['id']) : 0;

        // استخدم الموجود أو أنشئ منتج Simple
        $product = $id ? wc_get_product( $id ) : null;
        if ( ! $product || ! $product instanceof WC_Product ) {
            $product = new WC_Product_Simple();
        }

        // أسماء وأوصاف
        $product->set_name( sanitize_text_field( $params['name'] ?? '' ) );
        $product->set_description( wp_kses_post( $params['description'] ?? '' ) );
        $product->set_short_description( wp_kses_post( $params['short_description'] ?? '' ) );

        // أسعار (ندعم الفاصلة العشرية العربية)
        $reg_raw  = isset($params['regular_price']) ? (string)$params['regular_price'] : '0';
        $sale_raw = isset($params['sale_price']) ? (string)$params['sale_price'] : '';

        $reg_raw  = str_replace(',', '.', $reg_raw);
        $sale_raw = str_replace(',', '.', $sale_raw);

        $regular_price = wc_format_decimal( $reg_raw, 2 );
        $sale_price    = ($sale_raw !== '') ? wc_format_decimal( $sale_raw, 2 ) : '';

        // عيّن الأسعار كسلاسل نصية كما يتوقع WooCommerce
        $product->set_regular_price( (string)$regular_price );
        if ( $sale_price !== '' ) {
            $product->set_sale_price( (string)$sale_price );
            $product->set_price( (string)$sale_price ); // السعر المعروض
        } else {
            $product->set_sale_price( '' );
            $product->set_price( (string)$regular_price ); // السعر المعروض
        }

        // باقي الحقول
        $product->set_sku( sanitize_text_field( $params['sku'] ?? '' ) );

        if ( isset($params['stock_quantity']) && $params['stock_quantity'] !== '' ) {
            $qty = intval( $params['stock_quantity'] );
            $product->set_manage_stock( true );
            $product->set_stock_quantity( $qty );
            $product->set_stock_status( $qty > 0 ? 'instock' : 'outofstock' );
        }

        $status = in_array( $params['status'] ?? 'publish', array('publish','draft','pending'), true ) ? $params['status'] : 'publish';
        $product->set_status( $status );

        // الصور
        if ( ! empty( $params['featured_image'] ) ) {
            $product->set_image_id( absint( $params['featured_image'] ) );
        }
        if ( ! empty( $params['gallery'] ) && is_array( $params['gallery'] ) ) {
            $product->set_gallery_image_ids( array_map( 'absint', $params['gallery'] ) );
        }

        // حفظ
        $product_id = $product->save();

        // امسح أي كاش للأسعار/المنتج
        if ( function_exists( 'wc_delete_product_transients' ) ) {
            wc_delete_product_transients( $product_id );
        }

        return rest_ensure_response( array( 'ok'=>true, 'id'=>$product_id ) );
    }
    // ======= نهاية إصلاح السعر =======

    public function upload_media( $request ) {
        if ( empty( $_FILES['file'] ) ) {
            return new WP_Error( 'no_file', 'No file uploaded' );
        }
        $file = $_FILES['file'];
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $overrides = array( 'test_form' => false );
        $movefile = wp_handle_upload( $file, $overrides );
        if ( $movefile && ! isset( $movefile['error'] ) ) {
            $filetype = $movefile['type'];
            $filename = $movefile['file'];
            $attachment = array(
                'post_mime_type' => $filetype,
                'post_title' => sanitize_file_name( basename($filename) ),
                'post_content' => '',
                'post_status' => 'inherit'
            );
            $attach_id = wp_insert_attachment( $attachment, $filename );
            $attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
            wp_update_attachment_metadata( $attach_id, $attach_data );
            return rest_ensure_response( array( 'id' => $attach_id, 'url' => wp_get_attachment_url( $attach_id ) ) );
        } else {
            return new WP_Error( 'upload_error', $movefile['error'] );
        }
    }

    /* ===== Images endpoints handlers ===== */

    public function list_product_images( WP_REST_Request $req ) {
        $pid = (int) $req['id'];
        if ( get_post_type( $pid ) !== 'product' ) {
            return new WP_Error('mdefsm_invalid_product', 'Invalid product', array('status' => 400));
        }

        $featured_id = get_post_thumbnail_id($pid);
        $gallery_str = (string) get_post_meta($pid, '_product_image_gallery', true);
        $gallery_ids = array_filter(array_map('absint', explode(',', $gallery_str)));

        $to_item = function($id){
            if (!$id) return null;
            return array(
                'id'  => $id,
                'url' => wp_get_attachment_image_url($id, 'thumbnail'),
                'full'=> wp_get_attachment_url($id),
            );
        };

        return rest_ensure_response(array(
            'featured' => $featured_id ? $to_item($featured_id) : null,
            'gallery'  => array_map($to_item, $gallery_ids),
        ));
    }

    public function delete_product_image( WP_REST_Request $req ) {
        $pid  = (int) $req->get_param('product');
        $att  = (int) $req['att'];
        $hard = (bool) $req->get_param('hard');

        if ( get_post_type( $pid ) !== 'product' || ! $att ) {
            return new WP_Error('mdefsm_invalid', 'Invalid data', array('status' => 400));
        }

        // Remove featured if matched
        if ( get_post_thumbnail_id($pid) === $att ) {
            delete_post_thumbnail($pid);
        }

        // Remove from gallery meta
        $gallery_str = (string) get_post_meta($pid, '_product_image_gallery', true);
        $ids = array_filter(array_map('absint', explode(',', $gallery_str)));
        $new = array_values(array_diff($ids, array($att)));
        if ( $new !== $ids ) {
            update_post_meta($pid, '_product_image_gallery', implode(',', $new));
        }

        // Optional permanent delete
        if ( $hard && current_user_can('delete_post', $att) ) {
            wp_delete_attachment($att, true);
        }

        return rest_ensure_response(array('ok'=>true,'removed'=>$att,'product'=>$pid));
    }
}

/* Ensure routes are registered on REST bootstrap */
if ( ! function_exists('mdefsm_boot_rest') ) {
    function mdefsm_boot_rest() {
        if ( empty($GLOBALS['mdefsm_rest']) || ! ($GLOBALS['mdefsm_rest'] instanceof MDEFSM_REST) ) {
            $GLOBALS['mdefsm_rest'] = new MDEFSM_REST();
        }
    }
    add_action('rest_api_init', 'mdefsm_boot_rest');
}
