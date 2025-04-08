<?php
/**
 * Plugin Name: Blog Post Related Products
 * Description: Allows selection of related WooCommerce products for blog posts and displays them in a slider on the front-end.
 * Version: 1.0.1
 * Author: seb-dev
 * Requires PHP: 8.0
 * Requires at least: 6.7
 * Tested up to: 6.7.2
 * WC requires at least: 6.0
 * WC tested up to: 9.7.1
 * Text Domain: blog-post-related-products
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

class BPRP_Blog_Post_Related_Products {
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_meta_box_to_posts']);
        add_action('save_post', [$this, 'save_post_related_products']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_head', [$this, 'custom_slider_styles']);
        add_action('the_content', [$this, 'append_related_products_to_post']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    public function enqueue_assets() {
        if (is_single() && get_post_type() === 'post') {
            wp_enqueue_style('swiper', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css', [], '11.0.0');
            wp_enqueue_script('swiper', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', [], '11.0.0', true);
            wp_add_inline_script('swiper', "document.addEventListener('DOMContentLoaded', function () {
                new Swiper('.crp-swiper-container', {
                    slidesPerView: 1,
                    spaceBetween: 20,
                    navigation: {
                        nextEl: '.crp-swiper-button-next',
                        prevEl: '.crp-swiper-button-prev',
                    },
                    breakpoints: {
                        640: {
                            slidesPerView: 2
                        },
                        1024: {
                            slidesPerView: 3
                        }
                    }
                });
            });");
        }
    }

    public function custom_slider_styles() {
        if (is_single() && get_post_type() === 'post') {
            echo '<style>
                .crp-product {
                    background: #fff;
                    border: 1px solid #eee;
                    border-radius: 10px;
                    padding: 15px;
                    text-align: center;
                }
                .crp-product img {
                    max-width: 100%;
                    height: auto;
                    margin-bottom: 10px;
                }
                .crp-product h3 {
                    font-size: 16px;
                    margin: 0 0 5px;
                }
                .swiper-button-prev, .swiper-button-next {
                    color: #000;
                }
            </style>';
        }
    }

    public function add_meta_box_to_posts() {
        add_meta_box(
            'bprp_related_products_box',
            __('Related Products', 'blog-post-related-products'),
            [$this, 'render_post_meta_box'],
            'post',
            'side',
            'default'
        );
    }

    public function enqueue_admin_scripts($hook) {
        if ('post.php' === $hook || 'post-new.php' === $hook) {
            // Enqueue WooCommerce admin scripts and styles
            wp_enqueue_script('woocommerce_admin');
            wp_enqueue_script('wc-admin-meta-boxes');
            wp_enqueue_script('jquery-ui-sortable');
            wp_enqueue_script('selectWoo');
            wp_enqueue_style('select2');
            wp_enqueue_script('woocommerce_admin_meta_boxes');
            wp_enqueue_style('woocommerce_admin_styles');
        }
    }

    public function render_post_meta_box($post) {
        $selected = get_post_meta($post->ID, '_bprp_related_products_post', true);
        $selected = !empty($selected) ? explode(',', $selected) : [];
        wp_nonce_field('bprp_save_post_products', 'bprp_post_nonce');

        ?>
        <select class="wc-product-search" multiple="multiple" style="width: 100%;" id="bprp_related_products_post" name="bprp_related_products_post[]" data-placeholder="<?php esc_attr_e('Search for a product&hellip;', 'blog-post-related-products'); ?>" data-action="woocommerce_json_search_products">
            <?php
            foreach ($selected as $product_id) {
                $product = wc_get_product($product_id);
                if ($product) {
                    echo '<option value="' . esc_attr($product_id) . '" selected>' . esc_html($product->get_name()) . '</option>';
                }
            }
            ?>
        </select>
        <p class="description"><?php _e('Select related WooCommerce products to display with this post.', 'blog-post-related-products'); ?></p>
        <?php
    }

    public function save_post_related_products($post_id) {
        if (!isset($_POST['bprp_post_nonce']) || !wp_verify_nonce($_POST['bprp_post_nonce'], 'bprp_save_post_products')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (isset($_POST['bprp_related_products_post'])) {
            $related = array_map('intval', $_POST['bprp_related_products_post']);
            update_post_meta($post_id, '_bprp_related_products_post', implode(',', $related));
        } else {
            delete_post_meta($post_id, '_bprp_related_products_post');
        }
    }

    public function append_related_products_to_post($content) {
        if (!is_single() || get_post_type() !== 'post') return $content;

        $related_ids = get_post_meta(get_the_ID(), '_bprp_related_products_post', true);
        if (empty($related_ids)) return $content;

        $ids = explode(',', $related_ids);
        $output = '<h2>' . esc_html__('Related Products', 'blog-post-related-products') . '</h2>';
        $output .= '<div class="crp-swiper-container swiper"><div class="swiper-wrapper">';

        foreach ($ids as $id) {
            $product = wc_get_product($id);
            if ($product) {
                $output .= '<div class="swiper-slide"><div class="crp-product">';
                $output .= '<a href="' . get_permalink($id) . '">';
                $output .= $product->get_image();
                $output .= '<h3>' . esc_html($product->get_name()) . '</h3>';
                $output .= '</a>';
                $output .= '<span class="price">' . $product->get_price_html() . '</span>';
                $output .= '</div></div>';
            }
        }

        $output .= '</div>';
        $output .= '<div class="crp-swiper-button-prev swiper-button-prev"></div>';
        $output .= '<div class="crp-swiper-button-next swiper-button-next"></div>';
        $output .= '</div>';

        return $content . $output;
    }
}

add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

new BPRP_Blog_Post_Related_Products();
