<?php
/**
 * Media Mapper Class
 *
 * Handles mapping of media (images, etc.) between Faire and WooCommerce.
 * Manages product images, gallery images, and variation images.
 *
 * @package FaireWoo
 * @since   1.0.0
 */

namespace FaireWoo\Product;

use WC_Product;
use WC_Product_Variable;
use WP_Error;

defined('ABSPATH') || exit;

/**
 * Media Mapper Class
 */
class MediaMapper {
    /**
     * Default image sizes to download.
     * Faire provides multiple image sizes, we'll use the largest by default.
     */
    const DEFAULT_IMAGE_SIZE = 'original';

    /**
     * Map media from Faire to WooCommerce format.
     *
     * @param array $faire_product Faire product data.
     * @return array WooCommerce formatted media data.
     */
    public static function map_faire_to_wc($faire_product) {
        $media_data = array(
            'images' => array(),
            'gallery' => array(),
        );

        // Map main product image
        if (!empty($faire_product['images']) && isset($faire_product['images'][0])) {
            $media_data['images'][] = self::get_image_url($faire_product['images'][0]);
        }

        // Map additional images to gallery
        if (!empty($faire_product['images'])) {
            foreach (array_slice($faire_product['images'], 1) as $image) {
                $media_data['gallery'][] = self::get_image_url($image);
            }
        }

        // Map variation images if available
        if (!empty($faire_product['variants'])) {
            $media_data['variation_images'] = array();
            foreach ($faire_product['variants'] as $variant) {
                if (!empty($variant['images'])) {
                    $media_data['variation_images'][$variant['sku']] = self::get_image_url($variant['images'][0]);
                }
            }
        }

        return $media_data;
    }

    /**
     * Map media from WooCommerce to Faire format.
     *
     * @param WC_Product $product WooCommerce product object.
     * @return array Faire formatted media data.
     */
    public static function map_wc_to_faire($product) {
        $faire_data = array(
            'images' => array(),
        );

        // Map main image
        $image_id = $product->get_image_id();
        if ($image_id) {
            $faire_data['images'][] = self::get_wc_image_data($image_id);
        }

        // Map gallery images
        $gallery_ids = $product->get_gallery_image_ids();
        foreach ($gallery_ids as $gallery_id) {
            $faire_data['images'][] = self::get_wc_image_data($gallery_id);
        }

        // Map variation images if it's a variable product
        if ($product->is_type('variable')) {
            $faire_data['variant_images'] = array();
            foreach ($product->get_children() as $child_id) {
                $variation = wc_get_product($child_id);
                if (!$variation) {
                    continue;
                }

                $variation_image_id = $variation->get_image_id();
                if ($variation_image_id) {
                    $faire_data['variant_images'][$variation->get_sku()] = self::get_wc_image_data($variation_image_id);
                }
            }
        }

        return $faire_data;
    }

    /**
     * Update WooCommerce product with Faire media.
     *
     * @param WC_Product $product      WooCommerce product object.
     * @param array      $faire_product Faire product data.
     * @return WC_Product|WP_Error Updated product or error.
     */
    public static function update_wc_media($product, $faire_product) {
        try {
            $media_data = self::map_faire_to_wc($faire_product);

            // Update main image
            if (!empty($media_data['images'])) {
                $main_image_id = self::upload_image($media_data['images'][0]);
                if (!is_wp_error($main_image_id)) {
                    $product->set_image_id($main_image_id);
                }
            }

            // Update gallery images
            if (!empty($media_data['gallery'])) {
                $gallery_ids = array();
                foreach ($media_data['gallery'] as $gallery_url) {
                    $gallery_id = self::upload_image($gallery_url);
                    if (!is_wp_error($gallery_id)) {
                        $gallery_ids[] = $gallery_id;
                    }
                }
                $product->set_gallery_image_ids($gallery_ids);
            }

            // Update variation images
            if ($product->is_type('variable') && !empty($media_data['variation_images'])) {
                self::update_variation_images($product, $media_data['variation_images']);
            }

            $product->save();
            return $product;

        } catch (\Exception $e) {
            return new WP_Error(
                'media_update_failed',
                sprintf(__('Failed to update media: %s', 'faire-woo'), $e->getMessage())
            );
        }
    }

    /**
     * Compare media between Faire and WooCommerce.
     *
     * @param array      $faire_product Faire product data.
     * @param WC_Product $wc_product   WooCommerce product object.
     * @return array Array of differences found.
     */
    public static function compare_media($faire_product, $wc_product) {
        $differences = array();
        $faire_media = self::map_faire_to_wc($faire_product);
        
        // Compare main image
        $wc_image_url = wp_get_attachment_url($wc_product->get_image_id());
        if (!empty($faire_media['images']) && $wc_image_url !== $faire_media['images'][0]) {
            $differences['main_image'] = array(
                'faire' => $faire_media['images'][0],
                'wc' => $wc_image_url,
            );
        }

        // Compare gallery count
        $wc_gallery = $wc_product->get_gallery_image_ids();
        if (count($wc_gallery) !== count($faire_media['gallery'])) {
            $differences['gallery_count'] = array(
                'faire' => count($faire_media['gallery']),
                'wc' => count($wc_gallery),
            );
        }

        // Compare variation images
        if ($wc_product->is_type('variable') && !empty($faire_media['variation_images'])) {
            foreach ($faire_media['variation_images'] as $sku => $image_url) {
                $variation = self::find_variation_by_sku($wc_product, $sku);
                if ($variation) {
                    $variation_image_url = wp_get_attachment_url($variation->get_image_id());
                    if ($variation_image_url !== $image_url) {
                        $differences['variation_images'][$sku] = array(
                            'faire' => $image_url,
                            'wc' => $variation_image_url,
                        );
                    }
                }
            }
        }

        return $differences;
    }

    /**
     * Get the appropriate image URL from Faire image data.
     *
     * @param array $image_data Faire image data.
     * @return string Image URL.
     */
    private static function get_image_url($image_data) {
        if (isset($image_data['sizes'][self::DEFAULT_IMAGE_SIZE])) {
            return $image_data['sizes'][self::DEFAULT_IMAGE_SIZE];
        }
        return $image_data['url'] ?? '';
    }

    /**
     * Get WooCommerce image data in Faire format.
     *
     * @param int $attachment_id WordPress attachment ID.
     * @return array Image data in Faire format.
     */
    private static function get_wc_image_data($attachment_id) {
        $image_data = array(
            'url' => wp_get_attachment_url($attachment_id),
            'sizes' => array(),
        );

        // Map WooCommerce image sizes to Faire format
        $sizes = wp_get_attachment_metadata($attachment_id)['sizes'] ?? array();
        foreach ($sizes as $size_name => $size_data) {
            $image_data['sizes'][$size_name] = wp_get_attachment_image_url($attachment_id, $size_name);
        }

        return $image_data;
    }

    /**
     * Upload an image from URL to WordPress media library.
     *
     * @param string $url Image URL.
     * @return int|WP_Error Attachment ID if successful, WP_Error if not.
     */
    private static function upload_image($url) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Download file to temp dir
        $temp_file = download_url($url);
        if (is_wp_error($temp_file)) {
            return $temp_file;
        }

        // Prepare file data
        $file_array = array(
            'name' => basename($url),
            'tmp_name' => $temp_file,
        );

        // Upload the file
        $attachment_id = media_handle_sideload($file_array, 0);

        // Clean up temp file
        if (file_exists($temp_file)) {
            @unlink($temp_file);
        }

        return $attachment_id;
    }

    /**
     * Update variation images.
     *
     * @param WC_Product_Variable $product          Variable product.
     * @param array              $variation_images Array of variation images keyed by SKU.
     */
    private static function update_variation_images($product, $variation_images) {
        foreach ($variation_images as $sku => $image_url) {
            $variation = self::find_variation_by_sku($product, $sku);
            if (!$variation) {
                continue;
            }

            $image_id = self::upload_image($image_url);
            if (!is_wp_error($image_id)) {
                $variation->set_image_id($image_id);
                $variation->save();
            }
        }
    }

    /**
     * Find variation by SKU.
     *
     * @param WC_Product_Variable $product WooCommerce variable product.
     * @param string             $sku     SKU to find.
     * @return WC_Product|false Product if found, false otherwise.
     */
    private static function find_variation_by_sku($product, $sku) {
        foreach ($product->get_children() as $child_id) {
            $variation = wc_get_product($child_id);
            if ($variation && $variation->get_sku() === $sku) {
                return $variation;
            }
        }
        return false;
    }
} 