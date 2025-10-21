<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class MDEFSM_REST_IMAGES {
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        register_rest_route('md/v1', '/products/(?P<id>\d+)/images', array(
            'methods'  => WP_REST_Server::READABLE,
            'callback' => array($this, 'list_product_images'),
            'permission_callback' => function( WP_REST_Request $req ) {
                $pid = (int) $req['id'];
                return current_user_can('manage_woocommerce') || current_user_can('edit_post', $pid);
            },
        ));

        register_rest_route('md/v1', '/media/(?P<att>\d+)', array(
            'methods'  => WP_REST_Server::DELETABLE,
            'callback' => array($this, 'delete_product_image'),
            'args' => array(
                'product' => array('required' => true, 'type' => 'integer'),
                'hard'    => array('required' => false, 'type' => 'boolean'),
            ),
            'permission_callback' => function( WP_REST_Request $req ) {
                $pid = (int) $req->get_param('product');
                return current_user_can('manage_woocommerce') || current_user_can('delete_post', $pid);
            },
        ));
    }

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

        // Unset featured if matches
        if ( get_post_thumbnail_id($pid) === $att ) {
            delete_post_thumbnail($pid);
        }

        // Remove from gallery
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
