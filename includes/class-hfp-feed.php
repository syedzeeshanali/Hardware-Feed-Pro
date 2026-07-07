<?php
/**
 * Feed Generation Engine for HardwareFeed Pro
 * Handles Batch Processing, XML Caching, and Google Attribute Mapping.
 * Author: Syed Zeeshan Ali
 */

if (!defined('ABSPATH')) exit;

class HFP_Feed {
    public function __construct() {
        add_action('init', array($this, 'add_endpoint'));
        add_action('hfp_cron_generate', array($this, 'generate_batch_xml'));
        
        // Use the saved interval or default to daily
        $interval = get_option('hfp_cron_interval', 'daily');
        if (!wp_next_scheduled('hfp_cron_generate')) {
            wp_schedule_event(time(), $interval, 'hfp_cron_generate');
        }
    }

    public function add_endpoint() {
        // URL: yoursite.com/feed/hardware-google/
        add_feed('hardware-google', array($this, 'serve_cached_xml'));
    }

    public function serve_cached_xml() {
        $upload_dir = wp_upload_dir();
        $xml_file = $upload_dir['basedir'] . '/hfp-feed.xml';

        // Serve the file if it exists, otherwise generate it on the fly
        if (file_exists($xml_file)) {
            header('Content-Type: text/xml; charset=UTF-8');
            readfile($xml_file);
            exit;
        } else {
            $this->generate_batch_xml();
            if (file_exists($xml_file)) {
                header('Content-Type: text/xml; charset=UTF-8');
                readfile($xml_file);
                exit;
            }
        }
    }

    public function generate_batch_xml() {
        $strategy = get_option('hfp_feed_strategy', 'all');
        $enabled_fields = get_option('hfp_feed_fields', array('id', 'title', 'description', 'link', 'image_link', 'price', 'availability'));
        
        $upload_dir = wp_upload_dir();
        $xml_file = $upload_dir['basedir'] . '/hfp-feed.xml';
        
        $fp = fopen($xml_file, 'w');
        if (!$fp) return;

        // Write XML Header
        fwrite($fp, '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL);
        fwrite($fp, '<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">' . PHP_EOL);
        fwrite($fp, '<channel>' . PHP_EOL);
        fwrite($fp, '<title>' . htmlspecialchars(get_bloginfo('name')) . ' Product Feed</title>' . PHP_EOL);
        fwrite($fp, '<link>' . get_home_url() . '</link>' . PHP_EOL);
        fwrite($fp, '<description>Custom Hardware Feed Pro Engine</description>' . PHP_EOL);

        $offset = 0;
        $batch_size = 100;

        while (true) {
            $args = array(
                'limit'   => $batch_size,
                'offset'  => $offset,
                'status'  => 'publish',
                'orderby' => 'ID',
                'order'   => 'ASC',
            );

            // Strategy Logic
            if ($strategy === 'cats') {
                $args['category'] = get_option('hfp_selected_cats', array());
                if (empty($args['category'])) break; 
            } elseif ($strategy === 'prods') {
                $args['include'] = get_option('hfp_selected_prods', array());
                if (empty($args['include'])) break;
            }

            $products = wc_get_products($args);
            if (empty($products)) break;

            foreach ($products as $product) {
                $price = $product->get_price();

                // If Strategy is "Price Only", skip products without a price
                if ($strategy === 'price_only' && (empty($price) || $price <= 0)) {
                    continue;
                }

                // Global safety: Don't send products with zero price to Google
                if (empty($price) || $price <= 0) {
                    continue; 
                }

                $xml_item = $this->build_xml_item($product, $enabled_fields);
                fwrite($fp, $xml_item);
            }
            
            $offset += $batch_size;
            
            // Manual selection doesn't need batch looping
            if ($strategy === 'prods') break;

            usleep(50000); // Keep CPU usage low
        }

        fwrite($fp, '</channel></rss>');
        fclose($fp);
    }

    private function build_xml_item($product, $fields) {
        $id = $product->get_id();
        $currency = get_woocommerce_currency();
        $custom_mappings = get_option('hfp_custom_mappings', array()); // NEW
        
        $xml = "  <item>" . PHP_EOL;

        if (in_array('id', $fields)) {
            $xml .= "    <g:id>" . $id . "</g:id>" . PHP_EOL;
        }
        if (in_array('title', $fields)) {
            $xml .= "    <g:title>" . htmlspecialchars($product->get_name()) . "</g:title>" . PHP_EOL;
        }
        if (in_array('description', $fields)) {
            $desc = $product->get_short_description() ?: $product->get_description();
            $xml .= "    <g:description>" . htmlspecialchars(wp_strip_all_tags($desc)) . "</g:description>" . PHP_EOL;
        }
        if (in_array('link', $fields)) {
            $xml .= "    <g:link>" . get_the_permalink($id) . "</g:link>" . PHP_EOL;
        }
        if (in_array('image_link', $fields)) {
            $xml .= "    <g:image_link>" . wp_get_attachment_url($product->get_image_id()) . "</g:image_link>" . PHP_EOL;
        }
        if (in_array('price', $fields)) {
            $xml .= "    <g:price>" . $product->get_price() . " " . $currency . "</g:price>" . PHP_EOL;
        }
        if (in_array('availability', $fields)) {
            $status = $product->is_in_stock() ? 'in stock' : 'out of stock';
            $xml .= "    <g:availability>" . $status . "</g:availability>" . PHP_EOL;
        }
        if (in_array('condition', $fields)) {
            $xml .= "    <g:condition>new</g:condition>" . PHP_EOL;
        }
        if (in_array('brand', $fields)) {
            $xml .= "    <g:brand>" . htmlspecialchars(get_bloginfo('name')) . "</g:brand>" . PHP_EOL;
        }
        if (in_array('gtin', $fields)) {
            $gtin = $product->get_meta('_gtin') ?: $product->get_sku(); // Fallback to SKU if GTIN meta not found
            $xml .= "    <g:gtin>" . htmlspecialchars($gtin) . "</g:gtin>" . PHP_EOL;
        }
        if (in_array('mpn', $fields)) {
            $xml .= "    <g:mpn>" . htmlspecialchars($product->get_sku()) . "</g:mpn>" . PHP_EOL;
        }
        if (in_array('product_type', $fields)) {
            $terms = get_the_terms($id, 'product_cat');
            if ($terms && !is_wp_error($terms)) {
                $xml .= "    <g:product_type>" . htmlspecialchars($terms[0]->name) . "</g:product_type>" . PHP_EOL;
            }
        // NEW: Custom Mappings Logic
        if (!empty($custom_mappings)) {
            foreach ($custom_mappings as $map) {
                $meta_value = get_post_meta($id, $map['key'], true);
                if (!empty($meta_value)) {
                    $xml .= "    <g:{$map['tag']}>" . htmlspecialchars($meta_value) . "</g:{$map['tag']}>" . PHP_EOL;
                }
            }
        }
    }

        $xml .= "  </item>" . PHP_EOL;

        return $xml;
    }
}