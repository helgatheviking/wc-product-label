<?php
/**
 * Plugin Name: Product Label Gallery for WooCommerce
 * Plugin URI: https://github.com/helgatheviking/wc-product-label
 * Description: Show product labels under the product title
 * Version: 1.0.0-beta
 * Author: helgatheviking
 * Author URI: http://www.kathyisawesome.com
 * License: GPL2 http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wc-label-gallery
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WC_Label_Gallery {

    const VERSION = '1.0.0-rc-1';
    const REQUIRED_WOO = '3.1.0';

    public static $_instance;

    /**
    * Get an instance of this class.
    * @since  1.0.0
    */
    public static function get_instance() {
        if ( is_null( self::$_instance ) )
            self::$_instance = new self();
        return self::$_instance;
    }

    /**
     * Initialize the plugin.
     */
    public function __construct() {

        // Set up localisation.
        add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

        // Set up metabox.
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ), 30 );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 30 );
        add_action( 'woocommerce_process_product_meta', array( $this, 'save_gallery' ), 10, 2 );

        // Display on front-end
        add_action( 'after_setup_theme', array( $this, 'add_image_sizes' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'frontend_styles' ) );
        add_action( 'woocommerce_single_product_summary', array( $this, 'display_gallery' ), 7 );
        add_action( 'woocommerce_after_shop_loop_item_title', array( $this, 'display_gallery' ), 3 );  

    }

    /*-----------------------------------------------------------------------------------*/
    /*  Localization                                                                    */
    /*-----------------------------------------------------------------------------------*/


    /**
     * Load Localisation files.
     *
     * Note: the first-loaded translation file overrides any following ones if the same translation is present.
     *
     * Locales found in:
     *      - WP_LANG_DIR/snappic/snappic-LOCALE.mo
     *      - WP_LANG_DIR/plugins/snappic-LOCALE.mo
     */
    public function load_plugin_textdomain() {
        $locale = is_admin() && function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
        $locale = apply_filters( 'plugin_locale', $locale, 'wc-label-gallery' );

        unload_textdomain( 'wc-label-gallery' );
        load_plugin_textdomain( 'wc-label-gallery', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
    }

    /*-----------------------------------------------------------------------------------*/
    /*  Admin                                                                            */
    /*-----------------------------------------------------------------------------------*/

    /**
     * Enqueue scripts.
     */
    public function admin_scripts() {
        global $wp_query, $post;

        $screen       = get_current_screen();
        $screen_id    = $screen ? $screen->id : '';

        // Meta boxes
        if ( in_array( $screen_id, array( 'product', 'edit-product' ) ) ) {

            wp_enqueue_style( 'wc-admin-label-meta-box', $this->plugin_url() . '/assets/css/label-metabox.css', array( 'woocommerce_admin_styles' ) );
            wp_enqueue_media();
            wp_enqueue_script( 'wc-admin-label-meta-box', $this->plugin_url() . '/assets/js/label-metabox.js', array( 'wc-admin-product-meta-boxes', 'media-models' ) );

            $params = array(
                'post_id'                             => isset( $post->ID ) ? $post->ID : '',
                'plugin_url'                          => WC()->plugin_url(),
                'ajax_url'                            => admin_url( 'admin-ajax.php' ),
                'woocommerce_placeholder_img_src'     => wc_placeholder_img_src(),
                'i18n_choose_image'                   => esc_js( __( 'Choose an image', 'wc-label-gallery' ) ),
            );

            wp_localize_script( 'wc-admin-label-meta-box', 'wc_admin_label_meta_box', $params );
        }

    }


    /**
     * Add WC Meta boxes.
     */
    public function add_meta_boxes() {
        add_meta_box( 'wc-product-label-gallery', __( 'Label gallery', 'wc-label-gallery' ), array( $this, 'gallery_output' ), 'product', 'side', 'low' );
    }


    public function gallery_output( $post ) {

        ?>
        <div id="product_label_images_container">
            <ul class="product_label_images">
                <?php
                    $product_label_gallery = '';

                    if ( metadata_exists( 'post', $post->ID, '_product_label_gallery' ) ) {
                        $product_label_gallery = get_post_meta( $post->ID, '_product_label_gallery', true );
                    }

                    $attachments         = array_filter( explode( ',', $product_label_gallery ) );
                    $update_meta         = false;
                    $updated_gallery_ids = array();

                    if ( ! empty( $attachments ) ) {
                        foreach ( $attachments as $attachment_id ) {
                            $attachment = wp_get_attachment_image( $attachment_id, 'thumbnail' );

                            // if attachment is empty skip
                            if ( empty( $attachment ) ) {
                                $update_meta = true;
                                continue;
                            }

                            echo '<li class="image" data-attachment_id="' . esc_attr( $attachment_id ) . '">
                                ' . $attachment . '
                                <ul class="actions">
                                    <li><a href="#" class="delete tips" data-tip="' . esc_attr__( 'Delete image', 'wc-label-gallery' ) . '">' . __( 'Delete', 'wc-label-gallery' ) . '</a></li>
                                </ul>
                            </li>';

                            // rebuild ids to be saved
                            $updated_gallery_ids[] = $attachment_id;
                        }

                        // need to update product meta to set new gallery ids
                        if ( $update_meta ) {
                            update_post_meta( $post->ID, '_product_label_gallery', implode( ',', $updated_gallery_ids ) );
                        }
                    }
                ?>
            </ul>

            <input type="hidden" id="product_label_gallery" name="product_label_gallery" value="<?php echo esc_attr( $product_label_gallery ); ?>" />

        </div>
        <p class="add_product_label_images hide-if-no-js">
            <a href="#" data-choose="<?php esc_attr_e( 'Add images to product label gallery', 'wc-label-gallery' ); ?>" data-update="<?php esc_attr_e( 'Add to label gallery', 'wc-label-gallery' ); ?>" data-delete="<?php esc_attr_e( 'Delete image', 'wc-label-gallery' ); ?>" data-text="<?php esc_attr_e( 'Delete', 'wc-label-gallery' ); ?>"><?php _e( 'Add product label images', 'wc-label-gallery' ); ?></a>
        </p>
        <?php
    }

    /**
     * Save meta box data.
     *
     * @param int $post_id
     * @param WP_Post $post
     */
    public function save_gallery( $post_id, $post ) {
        $attachment_ids = isset( $_POST['product_label_gallery'] ) ? array_filter( explode( ',', wc_clean( $_POST['product_label_gallery'] ) ) ) : array();

        update_post_meta( $post_id, '_product_label_gallery', implode( ',', $attachment_ids ) );
    }

    /*-----------------------------------------------------------------------------------*/
    /*  Front-end                                                                        */
    /*-----------------------------------------------------------------------------------*/


    /**
     * Add Image sizes to WP.
     *
     * @since 1.0
     */
    public function add_image_sizes() {
        add_image_size( 'label_thumbnail', 100, 100, true );
    }

    /**
     * Display gallery on front-end.
     *
     * @param int $post_id
     * @param WP_Post $post
     */
    public function frontend_styles() {
        wp_enqueue_style( 'wc-label-gallery', $this->plugin_url() . '/assets/css/label-frontend.css' );
    }

    /**
     * Display gallery on front-end.
     *
     * @param int $post_id
     * @param WP_Post $post
     */
    public function display_gallery() {

        global $product;

        $gallery = 
        wc_get_template(
            'single-product/label-thumbnails.php',
            array(),
            FALSE,
            $this->plugin_path() . '/templates/' );

    }


    /*-----------------------------------------------------------------------------------*/
    /*  Helpers                                                                   */
    /*-----------------------------------------------------------------------------------*/
    
    /**
     * Get the plugin url.
     *
     * @return string
     */
    public function plugin_url() {
        return untrailingslashit( plugins_url( '/', __FILE__ ) );
    }


    /**
     * Get the plugin path.
     *
     * @return string
     */
    public function plugin_path() {
        return untrailingslashit( plugin_dir_path( __FILE__ ) );
    }


}

// Initialize the plugin
add_action( 'woocommerce_loaded', array( 'WC_Label_Gallery', 'get_instance' ) );