<?php
class Shopify_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    public function insert_store($shop, $access_token) {
        $data = [
            'shop' => $shop,
            'access_token' => $access_token
        ];
        $this->db->replace('stores', $data); // Use replace to handle updates as well
    }

    public function insert_store_data($shopData) {
        // Prepare data for insertion
        // print_r($shopData['id']);
        $data = [
            'id' => $shopData->shop->id ?? null,
            'domain' => $shopData->shop->domain ?? null,
            'name' => $shopData->shop->name ?? null,
            'address' => $shopData->shop->address1 ?? null, // Assuming address1 maps to address
            'phone' => $shopData->shop->phone ?? null,
            'admin_email' => $shopData->shop->email ?? null,
            'order_email' => $shopData->shop->customer_email ?? null,
            'timezone' => $shopData->shop->timezone ?? null,
            'language' => $shopData->shop->primary_locale ?? null,
            'currency' => $shopData->shop->currency ?? null,
            'currency_symbol' => $shopData->shop->money_format ?? '',
            'decimal_separator' => null, // Not provided
            'thousands_separator' => null, // Not provided
            'decimal_places' => null, // Not provided
            'currency_symbol_location' => null, // Not provided
            'weight_units' => $shopData->shop->weight_unit ?? null,
            'dimension_units' => null, // Not provided
            'dimension_decimal_places' => null, // Not provided
            'dimension_decimal_token' => null, // Not provided
            'dimension_thousands_token' => null, // Not provided
            'plan_name' => $shopData->shop->plan_name ?? null,
            'plan_level' => $shopData->shop->plan_display_name ?? null,
            'logo' => null, // Not provided
            'is_price_entered_with_tax' => null, // Not provided
            'active_comparison_modules' => null, // Not provided
            'features' => json_encode($shopData)
        ];

        // Insert or update store data
        $this->db->replace('store', $data);
    }

    public function insert_products($products) {
        $products = json_decode($products, true); // Decode JSON data
        foreach ($products['products'] as $product) {
            // Prepare data for insertion
            $data = [
                'id' => $product['id'],
                'name' => $product['title'],
                'sku' => $product['variants'][0]['sku'] ?? null,
                'description' => $product['body_html'],
                'custom_url' => $product['handle'],
                'price' => $product['variants'][0]['price'] ?? null,
                'cost_price' => $product['variants'][0]['cost'] ?? null,
                'retail_price' => $product['variants'][0]['price'] ?? null,
                'sale_price' => $product['variants'][0]['price'] ?? null,
                'calculated_price' => $product['variants'][0]['price'] ?? null,
                'sort_order' => null,
                'is_visible' => 1,
                'is_featured' => 0,
                'inventory_level' => $product['variants'][0]['inventory_quantity'] ?? 0,
                'inventory_warning_level' => null,
                'is_free_shipping' => 0,
                'inventory_tracking' => $product['variants'][0]['inventory_policy'] ?? null,
                'rating_total' => null,
                'rating_count' => null,
                'total_sold' => null,
                'date_created' => $product['created_at'],
                'brand_id' => null,
                'categories' => null,
                'date_modified' => $product['updated_at'],
                'condition' => null,
                'option_set_id' => null,
                'bin_picking_number' => null,
                'primary_image' => $product['images'][0]['src'] ?? null,
                'images' => json_encode(array_column($product['images'], 'src')),
                'option_set' => null,
                'search_keywords' => $product['title'],
                'upc' => $product['variants'][0]['barcode'] ?? '',
                'is_preorder_only' => 0,
                'is_price_hidden' => 0,
                'price_hidden_label' => null,
                'availability_description' => null,
                'weight' => $product['variants'][0]['weight'] ?? null,
                'width' => null,
                'height' => null,
                'depth' => null
            ];
    
            // Check if product exists
            $this->db->where('id', $data['id']);
            $query = $this->db->get('products');
    
            if ($query->num_rows() > 0) {
                // Update existing record
                $this->db->where('id', $data['id']);
                $this->db->update('products', $data);
            } else {
                // Insert new record
                $this->db->insert('products', $data);
            }
        }
    }
    
    public function insert_images($products_json) {
        // Decode JSON data
        $products = json_decode($products_json, true);
    
        // Iterate through the products
        foreach ($products['products'] as $product) {
            // Check if product has images
            if (isset($product['images']) && is_array($product['images'])) {
                foreach ($product['images'] as $image) {
                    // Prepare data for insertion
                    $data = [
                        'id' => $image['id'],
                        'product_id' => $product['id'],
                        'image_url' => $image['src']
                    ];
    
                    // Check if image already exists
                    $this->db->where('id', $data['id']);
                    $query = $this->db->get('images');
    
                    if ($query->num_rows() > 0) {
                        // Update existing record
                        $this->db->where('id', $data['id']);
                        $this->db->update('images', $data);
                    } else {
                        // Insert new record
                        $this->db->insert('images', $data);
                    }
                }
            }
        }
    }

    public function insert_variants_data($products_json) {
        // Decode JSON data
        $products = json_decode($products_json, true);
    
        // Iterate through the products
        foreach ($products['products'] as $product) {
            // Iterate through the variants
            foreach ($product['variants'] as $variant) {
    
                // Prepare data for insertion or update
                $data = [
                    'id' => $variant['id'],
                    'product_id' => $variant['product_id'],
                    'sku' => $variant['sku'] ?? '', // Default to empty string if null
                    'price' => (float)$variant['price'],
                    'calculated_price' => (float)$variant['price'], // Assuming calculated_price is same as price
                    'sale_price' => isset($variant['compare_at_price']) ? (float)$variant['compare_at_price'] : 0.00, // Default to 0 if null
                    'retail_price' => (float)$variant['price'], // Assuming retail_price is same as price
                    'image_url' => $this->get_image_url_for_variant($variant['id'], $product['images'])?$this->get_image_url_for_variant($variant['id'], $product['images']):'',
                    'option_id' => $this->get_option_id_for_variant($variant['option1'], $product['options']),
                    'value_id' => '', // Placeholder, no value_id in JSON provided
                    'options_data' => json_encode([
                        'option1' => $variant['option1'],
                        'option2' => $variant['option2'],
                        'option3' => $variant['option3']
                    ])
                ];
    
                // Check if variant exists
                $this->db->where('id', $data['id']);
                $query = $this->db->get('variants_data');
    
                if ($query->num_rows() > 0) {
                    // Update existing record
                    $this->db->where('id', $data['id']);
                    $this->db->update('variants_data', $data);
                } else {
                    // Insert new record
                    $this->db->insert('variants_data', $data);
                }
            }
        }
    }
    
    private function get_image_url_for_variant($variant_id, $images) {
        // Implement logic to get image URL based on variant ID
        foreach ($images as $image) {
            // Assume you have a way to match images to variants
            if (isset($image['variant_ids']) && in_array($variant_id, $image['variant_ids'])) {
                return $image['src'];
            }
            return '';
        }
        return null; // Return null if no image found
    }
    
    private function get_option_id_for_variant($option1, $options) {
        // Implement logic to get option ID based on option1 value
        foreach ($options as $option) {
            if (isset($option['values']) && in_array($option1, $option['values'])) {
                return $option['id'];
            }
        }
        return null; // Return null if no option found
    }
    
    public function insert_blog_data($blogs, $blog_posts) {
        $blogs = json_decode($blogs, true); // Decode JSON data
        $blog_posts = json_decode($blog_posts, true); // Decode JSON data

        // Insert or update blog data
        foreach ($blogs['blogs'] as $blog) {
            $blogData = [
                'id' => $blog['id'],
                'title' => $blog['title'],
                'url' => $blog['handle'],
                'preview_url' => null,
                'body' => null,
                'tags' => $blog['tags'],
                'summary' => null,
                'is_published' => 1,
                'meta_description' => null,
                'meta_keywords' => null,
                'thumbnail_url' => null,
                'post_date' => $blog['created_at'],
                'auther' => null
            ];

            $this->db->replace('blog_posts', $blogData);
        }

        // Insert or update blog post data
        foreach ($blog_posts['articles'] as $post) {
            $postData = [
                'id' => $post['id'],
                'title' => $post['title'],
                'url' => $post['handle'],
                'preview_url' => $post['image']['src'] ?? null,
                'body' => $post['body_html'],
                'tags' => $post['tags'],
                'summary' => $post['summary_html'],
                'is_published' => $post['published_at'] ? 1 : 0,
                'meta_description' => null,
                'meta_keywords' => null,
                'thumbnail_url' => $post['image']['src'] ?? null,
                'post_date' => $post['created_at'],
                'auther' => $post['author']
            ];

            $this->db->replace('blog_posts', $postData);

            // Insert tags
            $tags = array_filter(array_map('trim', explode(',', $post['tags']))); // Handle empty tags
            
            foreach ($tags as $tag) {
                $tagData = [
                    'id' => null, // ID is auto-incremented
                    'post_id' => $post['id'],
                    'tag' => $tag
                ];

                $this->db->insert('blog_post_tags', $tagData);
            }
        }
    }

    public function insert_pages($pages) {
        $pages = json_decode($pages, true);
        foreach ($pages['pages'] as $page) {
            $data = [
                'id' => $page['id'],
                'name' => $page['title'],
                'body' => $page['body_html'] ?? '',
                'url' => $page['handle'],
                'channel_id' => $page['shop_id'] ?? null
            ];

            $this->db->replace('pages', $data);
        }
    }

    public function insert_currencies($currencies) {
        $currencies = json_decode($currencies, true);
        foreach ($currencies['currencies'] as $currency) {
            $data = [
                'id' => $currency['currency'],
                'is_default' => $currency['enabled'] ? 'yes' : 'no',
                'currency_code' => $currency['currency'],
                'exchange_rate' => '',
                'name' => $currency['currency'],
                'symbol' => '',
                'location' => ''
            ];

            $this->db->replace('currencies', $data);
        }
    }

    public function get_store($shop) {
        $query = $this->db->get_where('stores', ['shop' => $shop]);
        return $query->row_array();
    }
}
