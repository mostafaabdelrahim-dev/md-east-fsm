<?php
/*
Plugin Name: MD East Frontend Store Manager
Description: Frontend dashboard + wholesale form + product editor for WooCommerce. Adds a Store Manager role, creates pages, provides REST endpoints for orders, wholesale requests, product management, invoices, and a frontend panel to manage product images.
Version: 1.0.6
Author: Mostafa Abdelrahim
Text Domain: md-east-fsm
*/

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'MDEFSM_VER', '1.0.6' );
define( 'MDEFSM_PATH', plugin_dir_path( __FILE__ ) );
define( 'MDEFSM_URL', plugin_dir_url( __FILE__ ) );

class MDEFSM_Plugin {
    public function __construct() {
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        add_action( 'init', array( $this, 'register_cpt_wholesale' ) );
        add_action( 'init', array( $this, 'register_shortcodes' ) );

        // i18n
        add_action( 'init', array( $this, 'load_textdomain' ) );
        add_filter( 'gettext', array( $this, 'force_arabic_for_plugin_strings' ), 10, 3 );

        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 999 );
        add_action( 'rest_api_init', array( $this, 'register_rest' ) );

        // فتح صفحة طباعة الجملة مباشرة من السلاج
        add_action( 'template_redirect', array( $this, 'render_wholesale_print_page_direct' ) );
        // إجبار الـ Store Manager على البقاء داخل لوحة التحكم
        add_action( 'template_redirect', array( $this, 'force_front_to_dashboard_for_manager' ), 1 );

        // Menu hiding & admin redirect for Store Manager
        add_action( 'admin_menu', array( $this, 'limit_admin_menu' ), 999 );
        add_action( 'admin_init', array( $this, 'redirect_store_manager_from_admin' ) );
        add_filter( 'show_admin_bar', array( $this, 'maybe_hide_admin_bar' ) );

        // Login redirect
        add_filter( 'login_redirect', array( $this, 'login_redirect' ), 10, 3 );

        // تسمية الدور بوضوح داخل لوحة ووردبريس
        add_action( 'init', array( $this, 'rename_store_manager_role_label' ) );
    }

    /* ===== Activation / Deactivation ===== */

    public function activate() {
        $this->register_cpt_wholesale();

        // Create Store Manager role
        add_role( 'md_store_manager', 'MD Store Manager (Frontend)', array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
            // Woo & product caps
            'manage_woocommerce' => true,
            'view_woocommerce_reports' => true,
            'edit_products' => true,
            'publish_products' => true,
            'delete_products' => true,
            'edit_product' => true,
            'read_product' => true,
            'delete_product' => true,
            'upload_files' => true,
            'edit_shop_orders' => true,
            'read_shop_order' => true,
            'delete_shop_orders' => true,
        ) );

        // Create pages with shortcodes
        if ( ! get_page_by_path( 'store-dashboard' ) ) {
            wp_insert_post( array(
                'post_title' => 'Store Dashboard',
                'post_name'  => 'store-dashboard',
                'post_type'  => 'page',
                'post_status'=> 'publish',
                'post_content' => '[md_store_dashboard]'
            ) );
        }
        if ( ! get_page_by_path( 'wholesale-request' ) ) {
            wp_insert_post( array(
                'post_title' => 'Wholesale Request',
                'post_name'  => 'wholesale-request',
                'post_type'  => 'page',
                'post_status'=> 'publish',
                'post_content' => '[md_wholesale_form]'
            ) );
        }
        if ( ! get_page_by_path( 'wholesale-print' ) ) {
            wp_insert_post( array(
                'post_title' => 'Wholesale Print',
                'post_name'  => 'wholesale-print',
                'post_type'  => 'page',
                'post_status'=> 'publish',
                'post_content' => '[md_wholesale_print]'
            ) );
        }

        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }

    /* ===== Custom Post Type ===== */

    public function register_cpt_wholesale() {
        register_post_type( 'md_wholesale', array(
            'labels' => array(
                'name' => 'Wholesale Requests',
                'singular_name' => 'Wholesale Request'
            ),
            'public' => false,
            'show_ui' => true,
            'capability_type' => 'post',
            'supports' => array( 'title', 'editor' ),
            'show_in_rest' => false,
        ) );
    }

    /* ===== Shortcodes ===== */

    public function register_shortcodes() {
        add_shortcode( 'md_store_dashboard', array( $this, 'render_dashboard_shortcode') );
        add_shortcode( 'md_wholesale_form', array( $this, 'render_wholesale_form_shortcode') );
        add_shortcode( 'md_wholesale_print', array( $this, 'render_wholesale_print_shortcode') );
    }

    public function render_dashboard_shortcode() {
        ob_start();
        include MDEFSM_PATH . 'templates/dashboard.php';
        return ob_get_clean();
    }

    public function render_wholesale_form_shortcode() {
        ob_start();
        include MDEFSM_PATH . 'templates/wholesale-form.php';
        return ob_get_clean();
    }

    public function render_wholesale_print_shortcode() {
        ob_start();
        include MDEFSM_PATH . 'templates/wholesale-print.php';
        return ob_get_clean();
    }

    /* ===== Assets ===== */

    public function enqueue_assets() {
        $is_singular = is_singular();
        $content = $is_singular ? get_post()->post_content : '';
        $is_dashboard = $is_singular && has_shortcode( $content, 'md_store_dashboard' );
        $is_wh_form  = $is_singular && has_shortcode( $content, 'md_wholesale_form' );

        if ( ! ( $is_dashboard || $is_wh_form ) ) {
            return;
        }

        $v_dash_css = file_exists( MDEFSM_PATH . 'assets/css/dashboard.css' ) ? filemtime( MDEFSM_PATH . 'assets/css/dashboard.css' ) : MDEFSM_VER;
        $v_form_css = file_exists( MDEFSM_PATH . 'assets/css/form.css' ) ? filemtime( MDEFSM_PATH . 'assets/css/form.css' ) : MDEFSM_VER;
        $v_dash_js  = file_exists( MDEFSM_PATH . 'assets/js/dashboard.js' ) ? filemtime( MDEFSM_PATH . 'assets/js/dashboard.js' ) : MDEFSM_VER;
        $v_wh_js    = file_exists( MDEFSM_PATH . 'assets/js/wholesale.js' ) ? filemtime( MDEFSM_PATH . 'assets/js/wholesale.js' ) : MDEFSM_VER;

        wp_enqueue_style( 'mdefsm-dashboard-css', MDEFSM_URL . 'assets/css/dashboard.css', array(), $v_dash_css );
        wp_enqueue_style( 'mdefsm-form-css', MDEFSM_URL . 'assets/css/form.css', array(), $v_form_css );

        $inline = '.mdefsm-wrap{direction:inherit!important;text-align:inherit!important}'
                .'.product-editor{direction:inherit!important;text-align:inherit!important}';
        wp_add_inline_style( 'mdefsm-dashboard-css', $inline );

        wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.1', true );
        wp_enqueue_script( 'mdefsm-dashboard-js', MDEFSM_URL . 'assets/js/dashboard.js', array('chartjs'), $v_dash_js, true );
        wp_localize_script( 'mdefsm-dashboard-js', 'MDEFSM', array(
            'rest'  => esc_url_raw( rest_url( 'md/v1/' ) ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
            'assetsUrl' => MDEFSM_URL . 'assets/',
            'logo' => MDEFSM_URL . 'assets/logo.png'
        ) );

        wp_enqueue_script( 'mdefsm-wholesale-js', MDEFSM_URL . 'assets/js/wholesale.js', array(), $v_wh_js, true );
        wp_localize_script( 'mdefsm-wholesale-js', 'MDEFSM_W', array(
            'rest'  => esc_url_raw( rest_url( 'md/v1/' ) ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
        ) );
    }

    /* ===== Pages Direct Rendering ===== */

    public function render_wholesale_print_page_direct() {
        if ( function_exists('is_page') && is_page('wholesale-print') ) {
            if ( is_user_logged_in() && ( current_user_can('manage_woocommerce') || current_user_can('md_store_manager') || current_user_can('administrator') ) ) {
                status_header(200);
                include MDEFSM_PATH . 'templates/wholesale-print.php';
                exit;
            }
        }
    }

    /* === إجبار Store Manager على البقاء في لوحة التحكم === */
    public function force_front_to_dashboard_for_manager() {
        if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return;
        }
        $user = wp_get_current_user();
        if ( ! $user || ! in_array( 'md_store_manager', (array) $user->roles, true ) ) {
            return;
        }
        $allowed_slugs = array( 'store-dashboard', 'wholesale-print' );
        $is_allowed = false;
        if ( function_exists( 'is_page' ) ) {
            foreach ( $allowed_slugs as $slug ) {
                if ( is_page( $slug ) ) { $is_allowed = true; break; }
            }
        }
        if ( ! $is_allowed ) {
            $dashboard_page = get_page_by_path( 'store-dashboard' );
            if ( $dashboard_page ) {
                wp_safe_redirect( get_permalink( $dashboard_page->ID ) );
                exit;
            }
        }
    }

    /* ===== REST ===== */

    public function register_rest() {
        // Your existing REST controller
        $file1 = MDEFSM_PATH . 'includes/rest.php';
        if ( file_exists( $file1 ) ) {
            require_once $file1;
            if ( class_exists( 'MDEFSM_REST' ) ) {
                new MDEFSM_REST();
            }
        }
        // NEW: images controller for editor
        $file2 = MDEFSM_PATH . 'includes/images-rest.php';
        if ( file_exists( $file2 ) ) {
            require_once $file2;
            if ( class_exists( 'MDEFSM_REST_IMAGES' ) ) {
                new MDEFSM_REST_IMAGES();
            }
        }
    }

    /* ===== Admin visibility ===== */

    public function limit_admin_menu() {
        if ( ! current_user_can( 'md_store_manager' ) && ! current_user_can( 'administrator' ) ) {
            return;
        }
        $user = wp_get_current_user();
        if ( in_array( 'md_store_manager', (array) $user->roles, true ) ) {
            remove_menu_page( 'index.php' );
            remove_menu_page( 'edit.php' );
            remove_menu_page( 'upload.php' );
            remove_menu_page( 'link-manager.php' );
            remove_menu_page( 'edit.php?post_type=page' );
            remove_menu_page( 'plugins.php' );
            remove_menu_page( 'themes.php' );
            remove_menu_page( 'users.php' );
            remove_menu_page( 'tools.php' );
            remove_menu_page( 'options-general.php' );
        }
    }

    public function redirect_store_manager_from_admin() {
        if ( is_admin() && ! wp_doing_ajax() ) {
            $user = wp_get_current_user();
            if ( $user && in_array( 'md_store_manager', (array) $user->roles, true ) ) {
                $dashboard_page = get_page_by_path( 'store-dashboard' );
                if ( $dashboard_page ) {
                    wp_redirect( get_permalink( $dashboard_page->ID ) );
                    exit;
                }
            }
        }
    }

    public function maybe_hide_admin_bar( $show ) {
        $user = wp_get_current_user();
        if ( $user && in_array( 'md_store_manager', (array) $user->roles, true ) ) {
            return false;
        }
        return $show;
    }

    public function login_redirect( $redirect_to, $request, $user ) {
        if ( isset( $user->roles ) && is_array( $user->roles ) && in_array( 'md_store_manager', $user->roles, true ) ) {
            $page = get_page_by_path( 'store-dashboard' );
            if ( $page ) {
                return get_permalink( $page->ID );
            }
        }
        return $redirect_to;
    }

    /* ===== i18n ===== */

    public function load_textdomain() {
        load_plugin_textdomain( 'md-east-fsm', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    public function force_arabic_for_plugin_strings( $translated, $text, $domain ) {
        if ( $domain !== 'md-east-fsm' ) { return $translated; }
        static $map = null;
        if ( $map === null ) {
            $map = array(
                "Please log in to access the Store Dashboard." => "من فضلك سجّل الدخول للوصول إلى لوحة المتجر.",
                "Store Dashboard" => "لوحة المتجر",
                "Monitor orders, manage products, and handle wholesale requests — all from the frontend." => "تابع الطلبات، وأدر المنتجات، وتعامل مع طلبات الجملة من الواجهة الأمامية.",
                "Refresh" => "تحديث",
                "Print all wholesale" => "طباعة جميع طلبات الجملة",
                "Logout" => "تسجيل الخروج",
                "Today's Revenue" => "مبيعات اليوم",
                "Orders Today" => "طلبات اليوم",
                "Unfulfilled" => "قيد المعالجة",
                "Store Orders" => "طلبات المتجر",
                "Wholesale Requests" => "طلبات الجملة",
                "Products" => "المنتجات",
                "Order #" => "رقم الطلب",
                "Customer" => "العميل",
                "Status" => "الحالة",
                "Date" => "التاريخ",
                "Total" => "الإجمالي",
                "Actions" => "إجراءات",
                "Phone" => "الجوال",
                "Email" => "البريد الإلكتروني",
                "Company" => "الشركة",
                "Product" => "المنتج",
                "Qty" => "الكمية",
                "Price" => "السعر",
                "Stock" => "المخزون",
                "Name" => "الاسم",
                "Add New" => "إضافة جديد",
                "Ultimate Product Editor" => "محرر المنتجات المتكامل",
                "Regular Price" => "السعر الأساسي",
                "Sale Price" => "سعر التخفيض",
                "Stock Quantity" => "كمية المخزون",
                "Publish" => "نشر",
                "Draft" => "مسودة",
                "Pending" => "معلّق",
                "Short Description" => "وصف مختصر",
                "Description" => "الوصف",
                "Featured Image" => "الصورة الرئيسية",
                "Gallery" => "معرض الصور",
                "Save Product" => "حفظ المنتج",
                "Reset" => "إعادة تعيين",
                "Invoice" => "فاتورة",
                "Items" => "العناصر",
                "Print Invoice" => "طباعة الفاتورة",
            );
        }
        return isset( $map[ $text ] ) ? $map[ $text ] : $translated;
    }

    /* ===== Helpers ===== */

    public function rename_store_manager_role_label() {
        if ( ! class_exists( 'WP_Roles' ) ) { return; }
        global $wp_roles;
        if ( ! isset( $wp_roles ) ) { $wp_roles = wp_roles(); }
        if ( isset( $wp_roles->roles['md_store_manager'] ) ) {
            $wp_roles->roles['md_store_manager']['name'] = 'MD Store Manager (Frontend)';
            $wp_roles->role_names['md_store_manager']   = 'MD Store Manager (Frontend)';
        }
    }
}

new MDEFSM_Plugin();
