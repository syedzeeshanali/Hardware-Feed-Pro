<?php
/**
 * Admin Logic Class for HardwareFeed Pro
 * Handles Modern UI, Field selection, and Auto-Sync Frequency.
 * Author: Syed Zeeshan Ali
 * Website: https://www.szeeshanali.com
 * VERSION: 4.1 (Inline Style Forced)
 */

if (!defined('ABSPATH')) exit;

class HFP_Admin {
    public function __construct() {
        add_action('admin_menu', array($this, 'create_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_hfp_search_products', array($this, 'ajax_search_products'));
    }
    
    public function enqueue_assets($hook) {
        if ('toplevel_page_hardware-feed-pro' !== $hook) return;
        // External CSS disabled to prevent conflicts - using Inline Logic
        // wp_enqueue_style('hfp-admin-style', plugin_dir_url(__FILE__) . 'css/admin-style.css', [], time());
    }

    public function create_menu() {
        add_menu_page('Hardware Feed', 'Hardware Feed Pro', 'manage_options', 'hardware-feed-pro', array($this, 'render_ui'), 'dashicons-rss', 30);
    }

    private function delete_feed_cache() {
        $upload_dir = wp_upload_dir();
        $xml_file = $upload_dir['basedir'] . '/hfp-feed.xml';
        if (file_exists($xml_file)) {
            unlink($xml_file);
        }
    }

    public function render_ui() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
        $this->handle_post_requests();

        echo '<div class="wrap" style="max-width: 1220px; margin: 30px 20px 30px 0; font-family: \'Inter\', sans-serif;">';
        echo '<h1 style="font-weight: 800; font-size: 28px; color: #1e293b; margin-bottom: 25px;">Hardware Feed Pro <small style="color: #94a3b8; font-weight: 300; font-size: 14px; margin-left: 10px;">v4.1</small></h1>';
        
        echo '<div style="border-bottom: 2px solid #e2e8f0; margin-bottom: 30px; display: flex; gap: 5px;">';
        foreach(['overview' => 'Dashboard', 'fields' => 'Feed Fields', 'products' => 'Filter Products', 'categories' => 'Filter Categories'] as $slug => $label) {
            $is_active = ($active_tab == $slug);
            $style = "padding: 12px 24px; text-decoration: none; font-weight: 600; font-size: 14px; transition: 0.3s; border-bottom: 2px solid " . ($is_active ? '#4f46e5' : 'transparent') . "; color: " . ($is_active ? '#4f46e5' : '#64748b') . ";";
            echo "<a href='?page=hardware-feed-pro&tab=$slug' style='$style'>$label</a>";
        }
        echo '</div>';

        echo '<div style="display: flex; gap: 30px; align-items: flex-start;">';
            echo '<div style="flex: 3; min-width: 0;">';
                switch($active_tab) {
                    case 'fields':     $this->render_fields_tab(); break;
                    case 'products':   $this->render_products_tab(); break;
                    case 'categories': $this->render_categories_tab(); break;
                    default:           $this->render_dashboard_tab(); break;
                }
            echo '</div>';
            $this->render_marketing_sidebar();
        echo '</div></div>';
    }

    private function render_dashboard_tab() {
        $current_strategy = get_option('hfp_feed_strategy', 'price_only'); 
        $current_interval = get_option('hfp_cron_interval', 'daily');
        $upload_dir = wp_upload_dir();
        $xml_file = $upload_dir['basedir'] . '/hfp-feed.xml';
        $xml_url  = $upload_dir['baseurl'] . '/hfp-feed.xml';
        
        $is_writable = is_writable($upload_dir['basedir']);
        $file_exists = file_exists($xml_file);
        $last_build  = $file_exists ? date("F j, Y, g:i a", filemtime($xml_file)) : 'Not yet generated';

        ?>
        <div style="background: white; padding: 40px; border-radius: 24px; box-shadow: 0 15px 45px rgba(0,0,0,0.06); border: 1px solid #f0f0f0; text-align: center;">
            <div style="margin-bottom: 25px;">
                <?php if ($is_writable): ?>
                    <span style="padding: 8px 18px; border-radius: 30px; font-size: 12px; font-weight: 700; background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;">● System Ready: Directory Writable</span>
                <?php else: ?>
                    <span style="padding: 8px 18px; border-radius: 30px; font-size: 12px; font-weight: 700; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;">X Permissions Alert: /uploads folder not writable</span>
                <?php endif; ?>
            </div>

            <form method="post">
                <h2 style="margin-top: 0; color: #1e293b; font-size: 24px; font-weight: 800;">Feed Configuration</h2>
                <p style="color: #64748b; margin-bottom: 30px;">Choose your logic for the automated Google Merchant feed.</p>
                
                <div style="background: #eff6ff; border-left: 4px solid #3b82f6; padding: 18px; border-radius: 12px; text-align: left; margin: 25px 0; font-size: 14px; color: #1e40af; line-height: 1.5;">
                    <strong>💡 Pro Tip:</strong> You can control exactly which data points are sent to Google by visiting the <strong><a href="?page=hardware-feed-pro&tab=fields" style="color: #2563eb; text-decoration: underline;">Feed Fields</a></strong> tab.
                </div>
                
                <div style="text-align: left; max-width: 580px; margin: 0 auto; padding: 30px; background: #f8fafc; border-radius: 20px; border: 1px solid #e2e8f0;">
                    <?php 
                    $opts = [
                        'cats' => '1. Based on <strong>Selected Categories</strong>',
                        'prods' => '2. Based on <strong>Selected Products</strong>',
                        'all' => '3. <strong>All Products</strong> (Full Catalog)',
                        'price_only' => '4. <strong>Products with Price only</strong>'
                    ];
                    foreach($opts as $val => $text): ?>
                    <label style="display: flex; align-items: center; cursor: pointer; font-size: 15px; margin-bottom: 12px; color: #334155;">
                        <input type="radio" name="hfp_feed_strategy" value="<?php echo $val; ?>" <?php checked($current_strategy, $val); ?> style="margin-right: 12px; transform: scale(1.1);">
                        <span><?php echo $text; ?></span>
                    </label>
                    <?php endforeach; ?>
                    
                    <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;">
                    
                    <label style="font-weight: 700; display: block; margin-bottom: 10px; color: #1e293b; font-size: 14px;">Auto-Sync Frequency</label>
                    <select name="hfp_cron_interval" style="width: 100%; height: 48px; border-radius: 12px; border: 1px solid #cbd5e1; padding: 0 15px; background: white; font-weight: 500;">
                        <option value="hourly" <?php selected($current_interval, 'hourly'); ?>>Every Hour</option>
                        <option value="twicedaily" <?php selected($current_interval, 'twicedaily'); ?>>Every 12 Hours</option>
                        <option value="daily" <?php selected($current_interval, 'daily'); ?>>Every 24 Hours (Recommended)</option>
                    </select>
                </div>
                
                <div style="margin-top: 35px;">
                    <input type="submit" name="hfp_save_strategy" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; padding: 0 40px; height: 54px; border-radius: 50px; font-weight: 700; cursor: pointer; border: none; box-shadow: 0 10px 20px rgba(79, 70, 229, 0.3); font-size: 16px;" value="Save & Rebuild Feed">
                </div>
            </form>

            <div style="margin-top: 45px; padding-top: 35px; border-top: 1px solid #f1f5f9;">
                <p style="color: #64748b; font-size: 13px; margin-bottom: 15px; font-weight: 600;">Your Google Merchant Center URL:</p>
                <div style="display: flex; gap: 10px; justify-content: center; max-width: 800px; margin: 0 auto;">
                    <input type="text" value="<?php echo get_home_url() . '/feed/hardware-google/'; ?>" id="hfp_url" readonly style="flex: 1; background: #f9fafb; border: 1px solid #e2e8f0; padding: 0 20px; height: 50px; border-radius: 15px; font-family: monospace; color: #475569; font-size: 13px;">
                    <button type="button" id="copy-url-btn" style="background: white; border: 1px solid #e2e8f0; color: #1e293b; padding: 0 25px; border-radius: 15px; font-weight: 600; cursor: pointer;">Copy URL</button>
                    <?php if ($file_exists): ?>
                        <a href="<?php echo $xml_url; ?>" download style="background: #10b981; color: white; padding: 0 25px; border-radius: 15px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; box-shadow: 0 5px 15px rgba(16, 185, 129, 0.2);">Download XML</a>
                    <?php endif; ?>
                </div>
                <p style="color: #94a3b8; font-size: 12px; margin-top: 20px;">
                    <span style="color: <?php echo $file_exists ? '#22c55e' : '#ef4444'; ?>; margin-right: 5px;">●</span> 
                    Feed Status: <strong style="color: #475569;"><?php echo $file_exists ? 'Online' : 'Pending'; ?></strong> | Last Build: <strong style="color: #475569;"><?php echo $last_build; ?></strong>
                </p>
            </div>

            <?php $this->render_preview_table(); ?>
        </div>

        <script>
        document.getElementById('copy-url-btn').addEventListener('click', function() {
            const url = document.getElementById('hfp_url').value;
            navigator.clipboard.writeText(url).then(() => {
                const btn = this;
                btn.textContent = 'Copied!';
                btn.style.background = '#10b981';
                btn.style.color = '#fff';
                btn.style.borderColor = '#10b981';
                setTimeout(() => {
                    btn.textContent = 'Copy URL';
                    btn.style.background = '#fff';
                    btn.style.color = '#1e293b';
                    btn.style.borderColor = '#e2e8f0';
                }, 2000);
            });
        });
        </script>
        <?php
    }

    private function render_preview_table() {
        $upload_dir = wp_upload_dir();
        $xml_file = $upload_dir['basedir'] . '/hfp-feed.xml';
        if (!file_exists($xml_file)) return;

        $preview_items = [];
        libxml_use_internal_errors(true);
        $xml_content = @simplexml_load_file($xml_file);

        if ($xml_content && isset($xml_content->channel->item)) {
            $count = 0;
            foreach ($xml_content->channel->item as $item) {
                if ($count >= 5) break;
                $g = $item->children('g', true);
                $preview_items[] = [
                    'id'    => (string)$g->id,
                    'title' => (string)$g->title,
                    'price' => (string)$g->price,
                    'stock' => (string)$g->availability,
                ];
                $count++;
            }
        }
        if (empty($preview_items)) return;

        ?>
        <div style="margin-top: 50px; text-align: left;">
            <h3 style="font-size: 16px; color: #1e293b; margin-bottom: 20px; font-weight: 800; display: flex; align-items: center;">
                <span style="width: 10px; height: 10px; background: #10b981; border-radius: 50%; display: inline-block; margin-right: 12px; box-shadow: 0 0 8px #10b981;"></span>
                Live Data Preview (Last 5 Products)
            </h3>
            
            <div style="background: #ffffff; border-radius: 16px; border: 1px solid #f1f5f9; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid #f1f5f9;">
                            <th style="padding: 15px; text-align: left; color: #64748b; font-weight: 700;">ID</th>
                            <th style="padding: 15px; text-align: left; color: #64748b; font-weight: 700;">Title</th>
                            <th style="padding: 15px; text-align: left; color: #64748b; font-weight: 700;">Price</th>
                            <th style="padding: 15px; text-align: left; color: #64748b; font-weight: 700;">Availability</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preview_items as $item): ?>
                        <tr style="border-bottom: 1px solid #f8fafc;">
                            <td style="padding: 12px 15px; color: #94a3b8;">#<?php echo esc_html($item['id']); ?></td>
                            <td style="padding: 12px 15px; font-weight: 600; color: #1e293b;"><?php echo esc_html($item['title']); ?></td>
                            <td style="padding: 12px 15px; color: #6366f1; font-weight: 800;"><?php echo esc_html($item['price']); ?></td>
                            <td style="padding: 12px 15px;">
                                <span style="background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 8px; font-size: 11px; font-weight: 700; text-transform: uppercase;">
                                    <?php echo esc_html($item['stock']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    private function render_marketing_sidebar() {
        ?>
        <div style="flex: 1; min-width: 280px; background: white; padding: 30px; border-radius: 24px; border: 1px solid #e2e8f0; box-shadow: 0 10px 30px rgba(0,0,0,0.03); position: sticky; top: 50px;">
            <h3 style="margin-top: 0; color: #1e293b; font-weight: 800; font-size: 18px;">Need Custom Work?</h3>
            <p style="color: #64748b; font-size: 14px; line-height: 1.6; margin-bottom: 25px;">I specialize in high-performance WordPress plugins, SaaS solutions, and custom API integrations.</p>
            <div style="background: #f8fafc; padding: 15px; border-radius: 15px; border: 1px solid #f1f5f9; margin-bottom: 25px;">
                <p style="margin: 0; font-size: 14px; color: #1e293b; font-weight: 700;">Syed Zeeshan Ali</p>
                <p style="margin: 4px 0 0; font-size: 12px; color: #94a3b8; font-weight: 500;">Senior Web Developer</p>
            </div>
            <a href="https://www.szeeshanali.com/collaborate" target="_blank" style="display: block; text-align: center; background: #1e293b; color: white !important; padding: 16px; border-radius: 15px; font-weight: 600; text-decoration: none; transition: 0.2s;">Let's Collaborate →</a>
            <p style="font-size: 11px; color: #cbd5e1; margin-top: 20px; text-align: center; line-height: 1.4;">Available for custom Shopify & Salesforce projects too.</p>
        </div>
        <?php
    }

private function render_categories_tab() {
    $selected_cats = get_option('hfp_selected_cats', array());
    $categories = get_terms('product_cat', array('hide_empty' => false));
    ?>
    <div style="background: white; padding: 35px; border-radius: 24px; border: 1px solid #e2e8f0; box-shadow: 0 10px 20px rgba(0,0,0,0.02);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <div>
                <h3 style="margin: 0; font-size: 20px; color: #1e293b; font-weight: 800;">Filter by Categories</h3>
                <p style="color: #64748b; margin: 5px 0 0 0;">Only products from checked categories will be included.</p>
            </div>
            <label style="background: #f8fafc; padding: 8px 15px; border-radius: 10px; border: 1px solid #e2e8f0; font-size: 13px; font-weight: 600; cursor: pointer; color: #475569;">
                <input type="checkbox" id="hfp-select-all-cats" style="margin-right: 8px;"> Select All
            </label>
        </div>

        <form method="post">
            <div style="max-height: 450px; overflow-y: auto; border: 1px solid #f1f5f9; border-radius: 16px; scrollbar-width: thin;">
                <table class="wp-list-table widefat striped" style="border: none; box-shadow: none; table-layout: auto;">
                    <thead>
                        <tr style="background: #f8fafc;">
                            <th style="padding: 15px; width: 40px;"></th>
                            <th style="padding: 15px; font-weight: 700; color: #475569;">Category Name</th>
                            <th style="padding: 15px; font-weight: 700; color: #475569;">Slug</th>
                            <th style="padding: 15px; font-weight: 700; color: #475569; text-align: center;">Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td style="padding: 12px 15px;"><input type="checkbox" name="hfp_cats[]" value="<?php echo $cat->slug; ?>" <?php checked(in_array($cat->slug, $selected_cats)); ?>></td>
                            <td style="padding: 12px 15px;"><strong style="color: #1e293b; font-size: 14px;"><?php echo $cat->name; ?></strong></td>
                            <td style="padding: 12px 15px;"><code style="background: #f1f5f9; padding: 4px 8px; border-radius: 6px; font-size: 11px; color: #6366f1; border: 1px solid #e2e8f0;"><?php echo $cat->slug; ?></code></td>
                            <td style="padding: 12px 15px; text-align: center; color: #94a3b8; font-size: 13px;"><?php echo $cat->count; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 30px; text-align: right;">
                <input type="submit" name="hfp_save_cats" style="background: #4f46e5; color: white; padding: 14px 35px; border-radius: 50px; font-weight: 700; cursor: pointer; border: none; box-shadow: 0 10px 15px rgba(79, 70, 229, 0.2);" value="Update Category Filters">
            </div>
        </form>
    </div>
    <script>
    document.getElementById('hfp-select-all-cats').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('input[name="hfp_cats[]"]');
        checkboxes.forEach(cb => cb.checked = this.checked);
    });
    </script>
    <?php
}

private function render_products_tab() {
    $selected_prods = get_option('hfp_selected_prods', array());
    // Initial load: first 20 products
    $prods = wc_get_products(array('limit' => 20, 'status' => 'publish', 'orderby' => 'name', 'order' => 'ASC'));
    ?>
    <div style="background: white; padding: 35px; border-radius: 24px; border: 1px solid #e2e8f0; box-shadow: 0 10px 20px rgba(0,0,0,0.02);">
        <h3 style="margin-top:0; font-size: 20px; font-weight: 800; color: #1e293b;">Individual Product Selection</h3>
        
        <div style="position: relative; margin: 20px 0;">
             <input type="text" style="width:100%; padding: 16px 20px; border-radius: 16px; border: 1px solid #e2e8f0; background: #fcfcfc;" placeholder="Search entire library by name, SKU or ID..." id="hfp-product-search">
             <?php wp_nonce_field('hfp_admin_nonce', 'hfp_nonce'); ?>
        </div>

        <form method="post" id="hfp-products-form">
            <div id="hfp-selection-buffer" style="display:none;">
                <?php foreach($selected_prods as $pid): ?>
                    <input type="checkbox" name="hfp_prods[]" value="<?php echo $pid; ?>" checked>
                <?php endforeach; ?>
            </div>

            <div style="max-height: 500px; overflow-y: auto; border: 1px solid #f1f5f9; border-radius: 16px; margin-bottom: 10px;">
                <table class="wp-list-table widefat striped" style="border: none; table-layout: auto;">
                    <thead>
                        <tr style="background: #f8fafc;">
                            <th style="padding: 15px; width: 40px;"><input type="checkbox" id="hfp-select-all-prods"></th>
                            <th style="padding: 15px; font-weight: 700;">Product Detail</th>
                            <th style="padding: 15px; font-weight: 700;">Identifiers</th>
                            <th style="padding: 15px; font-weight: 700;">Price</th>
                        </tr>
                    </thead>
                    <tbody id="hfp-ajax-results">
                        <?php foreach ($prods as $p): 
                            $pid = $p->get_id();
                            $checked = in_array($pid, $selected_prods) ? 'checked' : '';
                        ?>
                        <tr data-id="<?php echo $pid; ?>">
                            <td style="padding: 12px 15px;"><input type="checkbox" class="hfp-item-checkbox" value="<?php echo $pid; ?>" <?php echo $checked; ?>></td>
                            <td style="padding: 12px 15px;">
                                <div style="font-weight: 700; color: #1e293b;"><?php echo $p->get_name(); ?></div>
                            </td>
                            <td style="padding: 12px 15px; font-size: 12px; color: #64748b;">ID: <?php echo $pid; ?></td>
                            <td style="padding: 12px 15px; color: #10b981; font-weight: 800;"><?php echo get_woocommerce_currency_symbol() . $p->get_price(); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="text-align: center; padding: 10px; border-bottom: 1px solid #f1f5f9; margin-bottom: 20px;">
                <button type="button" id="hfp-load-more" data-page="1" style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 8px 20px; border-radius: 50px; font-size: 12px; font-weight: 600; cursor: pointer; color: #64748b;">Load More Products ↓</button>
            </div>
            
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span id="hfp-selection-count" style="color: #6366f1; font-weight: 700; font-size: 13px;">
                    <?php echo count($selected_prods); ?> items selected in total
                </span>
                <input type="submit" name="hfp_save_prods" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; padding: 14px 40px; border-radius: 50px; font-weight: 700; cursor: pointer; border: none; box-shadow: 0 10px 20px rgba(79, 70, 229, 0.2);" value="Save Selection">
            </div>
        </form>
    </div>

    <script>
    jQuery(document).ready(function($) {
        let searchTimer;
        const buffer = $('#hfp-selection-buffer');

        // 1. SYNC LOGIC: Updates hidden buffer when table checkboxes change
        $(document).on('change', '.hfp-item-checkbox', function() {
            const id = $(this).val();
            if ($(this).is(':checked')) {
                if (buffer.find(`input[value="${id}"]`).length === 0) {
                    buffer.append(`<input type="checkbox" name="hfp_prods[]" value="${id}" checked>`);
                }
            } else {
                buffer.find(`input[value="${id}"]`).remove();
            }
            updateCount();
        });

        function updateCount() {
            $('#hfp-selection-count').text(buffer.find('input').length + ' items selected in total');
        }

        // 2. SEARCH LOGIC (AJAX)
        $('#hfp-product-search').on('keyup', function() {
            clearTimeout(searchTimer);
            const term = $(this).val();
            const btn = $('#hfp-load-more');

            searchTimer = setTimeout(function() {
                $('#hfp-ajax-results').css('opacity', '0.5');
                btn.data('page', 1); // Reset pagination on search

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hfp_search_products',
                        term: term,
                        page: 1,
                        nonce: $('#hfp_nonce').val()
                    },
                    success: function(res) {
                        if (res.success) {
                            $('#hfp-ajax-results').html(res.data).css('opacity', '1');
                            syncTableWithBuffer();
                        }
                    }
                });
            }, 600);
        });

        // 3. LOAD MORE (PAGINATION) LOGIC
        $('#hfp-load-more').on('click', function() {
            const btn = $(this);
            const nextPage = parseInt(btn.data('page')) + 1;
            const term = $('#hfp-product-search').val();

            btn.text('Loading...').prop('disabled', true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hfp_search_products',
                    term: term,
                    page: nextPage,
                    nonce: $('#hfp_nonce').val()
                },
                success: function(res) {
                    if (res.success && res.data.trim() !== '') {
                        $('#hfp-ajax-results').append(res.data);
                        btn.data('page', nextPage).text('Load More Products ↓').prop('disabled', false);
                        syncTableWithBuffer();
                    } else {
                        btn.text('No more products found').fadeOut();
                    }
                }
            });
        });

        // 4. SYNC TABLE: When AJAX loads, check boxes of items already in buffer
        function syncTableWithBuffer() {
            buffer.find('input').each(function() {
                const id = $(this).val();
                $(`.hfp-item-checkbox[value="${id}"]`).prop('checked', true);
            });
        }
    });
    </script>
    <?php
}

    private function render_fields_tab() {
        $enabled = get_option('hfp_feed_fields', array('id', 'title', 'price'));
        $custom_mappings = get_option('hfp_custom_mappings', array());
        $all_fields = array('id' => 'Product ID', 'title' => 'Product Title', 'description' => 'Description', 'link' => 'Product URL', 'image_link' => 'Main Image URL', 'price' => 'Price', 'availability' => 'Stock Status', 'brand' => 'Brand', 'gtin' => 'GTIN', 'mpn' => 'MPN', 'condition' => 'Condition', 'product_type' => 'Category Path');

        // Fetch common meta keys for the dropdown
        global $wpdb;
        $meta_keys = $wpdb->get_col("SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE meta_key NOT LIKE '\_%' LIMIT 50");
        ?>
        <div style="background: white; padding: 35px; border-radius: 24px; border: 1px solid #e2e8f0; box-shadow: 0 10px 20px rgba(0,0,0,0.02);">
            <h3 style="margin-top: 0; font-size: 20px; font-weight: 800; color: #1e293b;">Google Feed Attributes</h3>
            <p style="color: #64748b; margin-bottom: 30px;">Toggle standard data points and map custom fields (ACF/Pods) below.</p>
            
            <form method="post">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 30px;">
                    <?php foreach ($all_fields as $key => $label): ?>
                    <label style="display: flex; align-items: center; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; cursor: pointer;">
                        <input type="checkbox" name="hfp_fields[]" value="<?php echo $key; ?>" <?php checked(in_array($key, $enabled)); ?> style="margin-right: 10px;">
                        <span style="font-weight: 600; color: #334155; font-size: 13px;"><?php echo $label; ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>

                <hr style="border: 0; border-top: 1px solid #f1f5f9; margin: 30px 0;">
                
                <h3 style="font-size: 18px; font-weight: 800; color: #1e293b;">Custom Field Mapping (ACF / Pods)</h3>
                <table style="width: 100%; margin-bottom: 20px;" id="hfp-custom-fields-table">
                    <thead>
                        <tr style="text-align: left; font-size: 12px; color: #94a3b8;">
                            <th style="padding-bottom: 10px;">Google Tag (e.g. material)</th>
                            <th style="padding-bottom: 10px;">Source Field (WP Meta)</th>
                            <th style="padding-bottom: 10px; width: 50px;"></th>
                        </tr>
                    </thead>
                    <tbody id="hfp-custom-fields-list">
                        <?php if (!empty($custom_mappings)): foreach ($custom_mappings as $map): ?>
                        <tr>
                            <td><input type="text" name="hfp_map_tag[]" value="<?php echo esc_attr($map['tag']); ?>" placeholder="material" style="width:95%; padding:10px; border-radius:8px; border:1px solid #e2e8f0;"></td>
                            <td>
                                <select name="hfp_map_key[]" style="width:100%; padding:10px; border-radius:8px; border:1px solid #e2e8f0;">
                                    <?php foreach ($meta_keys as $key): ?>
                                        <option value="<?php echo $key; ?>" <?php selected($map['key'], $key); ?>><?php echo $key; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><button type="button" onclick="this.closest('tr').remove()" style="background:none; border:none; color:#ef4444; cursor:pointer; font-size:18px;">&times;</button></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>

                <button type="button" onclick="addHfpRow()" style="background: #f1f5f9; border: 1px dashed #cbd5e1; color: #475569; padding: 10px 20px; border-radius: 10px; cursor: pointer; font-weight: 600;">+ Add Custom Field Mapping</button>

                <div style="text-align: right; margin-top: 40px; border-top: 1px solid #f1f5f9; padding-top: 25px;">
                    <input type="submit" name="hfp_save_fields" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; padding: 14px 40px; border-radius: 50px; font-weight: 700; cursor: pointer; border: none; box-shadow: 0 10px 20px rgba(79, 70, 229, 0.2);" value="Save Configuration">
                </div>
            </form>
        </div>

        <script>
        function addHfpRow() {
            const container = document.getElementById('hfp-custom-fields-list');
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><input type="text" name="hfp_map_tag[]" placeholder="e.g. color" style="width:95%; padding:10px; border-radius:8px; border:1px solid #e2e8f0; margin-top:10px;"></td>
                <td>
                    <select name="hfp_map_key[]" style="width:100%; padding:10px; border-radius:8px; border:1px solid #e2e8f0; margin-top:10px;">
                        <option value="">-- Select Field --</option>
                        <?php foreach ($meta_keys as $key): ?>
                            <option value="<?php echo $key; ?>"><?php echo $key; ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><button type="button" onclick="this.closest('tr').remove()" style="background:none; border:none; color:#ef4444; cursor:pointer; font-size:18px; margin-top:10px;">&times;</button></td>
            `;
            container.appendChild(row);
        }
        </script>
        <?php
    }

    private function handle_post_requests() {
    if (isset($_POST['hfp_save_strategy']) || isset($_POST['hfp_save_cats']) || isset($_POST['hfp_save_prods']) || isset($_POST['hfp_save_fields'])) {
        
        // 1. Save Feed Strategy & Global Settings
        if (isset($_POST['hfp_feed_strategy'])) {
            update_option('hfp_feed_strategy', sanitize_text_field($_POST['hfp_feed_strategy']));
        }

        // 2. Save Category Filter
        if (isset($_POST['hfp_save_cats'])) {
            $cats = isset($_POST['hfp_cats']) ? array_map('sanitize_text_field', $_POST['hfp_cats']) : array();
            update_option('hfp_selected_cats', $cats);
        }

        // 3. Save Product Filter
        if (isset($_POST['hfp_save_prods'])) {
            $prods = isset($_POST['hfp_prods']) ? array_map('intval', $_POST['hfp_prods']) : array();
            update_option('hfp_selected_prods', $prods);
        }

        // 4. Save Standard Fields & Custom Mappings (The Missing Part)
        if (isset($_POST['hfp_save_fields'])) {
            // Save standard checkboxes
            $fields = isset($_POST['hfp_fields']) ? $_POST['hfp_fields'] : array();
            update_option('hfp_feed_fields', $fields);

            // Save Custom Mappings (ACF/Pods/Meta)
            $mappings = array();
            if (isset($_POST['hfp_map_tag']) && isset($_POST['hfp_map_key'])) {
                $tags = $_POST['hfp_map_tag'];
                $keys = $_POST['hfp_map_key'];

                foreach ($tags as $index => $tag) {
                    $clean_tag = sanitize_text_field($tag);
                    $clean_key = sanitize_text_field($keys[$index]);

                    if (!empty($clean_tag) && !empty($clean_key)) {
                        $mappings[] = array(
                            'tag' => $clean_tag,
                            'key' => $clean_key
                        );
                    }
                }
            }
            update_option('hfp_custom_mappings', $mappings);
        }

        // 5. Handle Sync Frequency & Cron Rescheduling
        if (isset($_POST['hfp_cron_interval'])) {
            $new_interval = sanitize_text_field($_POST['hfp_cron_interval']);
            update_option('hfp_cron_interval', $new_interval);
            
            wp_clear_scheduled_hook('hfp_cron_generate');
            wp_schedule_event(time(), $new_interval, 'hfp_cron_generate');
        }

        // 6. Delete Cache & Rebuild the XML
        $this->delete_feed_cache();
        
        if (class_exists('HFP_Feed')) {
            $feed_engine = new HFP_Feed();
            $feed_engine->generate_batch_xml();
        }

        // 7. Inline Styled Success Notice
        echo '<div class="notice notice-success is-dismissible" style="border-radius: 12px; border-left: 5px solid #10b981; padding: 10px 20px; background: #f0fdf4; color: #166534; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-top: 20px;">';
        echo '<p style="margin: 0; font-weight: 600;">Success: Settings saved and Feed rebuilt with custom mappings!</p>';
        echo '</div>';
    }
}

   public function ajax_search_products() {
            check_ajax_referer('hfp_admin_nonce', 'nonce');
        
            $search = isset($_POST['term']) ? sanitize_text_field($_POST['term']) : '';
            $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
            $limit = 20;
            $offset = ($page - 1) * $limit;
        
            $args = array(
                'status' => 'publish',
                'limit'  => $limit,
                'offset' => $offset,
                'orderby' => 'name',
                'order'   => 'ASC',
            );
        
            if (!empty($search)) {
                $args['s'] = $search;
            }
        
            $products = wc_get_products($args);
            $html = '';
        
            if (!empty($products)) {
                foreach ($products as $p) {
                    $pid = $p->get_id();
                    $price = get_woocommerce_currency_symbol() . $p->get_price();
                    
                    $html .= "<tr data-id='{$pid}'>
                        <td style='padding: 12px 15px;'><input type='checkbox' class='hfp-item-checkbox' value='{$pid}'></td>
                        <td style='padding: 12px 15px;'>
                            <div style='font-weight: 700; color: #1e293b;'>{$p->get_name()}</div>
                        </td>
                        <td style='padding: 12px 15px; font-size: 12px; color: #94a3b8;'>ID: {$pid}</td>
                        <td style='padding: 12px 15px; color: #10b981; font-weight: 800;'>{$price}</td>
                    </tr>";
                }
            }
            wp_send_json_success($html);
        }
}