<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Shopify extends CI_Controller {

    private $api_key;
    private $api_secret;

    public function __construct() {
        parent::__construct();
        $this->load->helper('url');
        $this->load->model('Shopify_model');
        $this->api_key = $this->config->item('API_KEY');
        $this->api_secret = $this->config->item('AUTH_TOKEN');
    }

    public function install() {
        $shop = $this->input->get('shop');
        $redirect_uri = urlencode(base_url('shopify/callback'));
        $scopes = 'write_products,write_content,write_inventory,write_discounts,write_price_rules,unauthenticated_read_product_inventory,unauthenticated_read_content,unauthenticated_read_product_listings'; 

        $install_url = "https://$shop/admin/oauth/authorize?client_id={$this->api_key}&scope=$scopes&redirect_uri=$redirect_uri";
        redirect($install_url);
    }

    public function callback() {
        $shop = $this->input->get('shop');
        $code = $this->input->get('code');
    
        if (empty($shop) || empty($code)) {
            show_error('Missing shop or code parameter.');
        }
    
        $access_token = $this->getAccessToken($shop, $code);
    
        if (empty($access_token)) {
            show_error('Failed to retrieve access token.');
        }

        $blogs = $this->fetchApiData($shop, $access_token, 'blogs');
        $blog_post = $this->fetchApiData($shop, $access_token, 'articles');
        $products = $this->fetchApiData($shop, $access_token, 'products');
        // $inventory_items = $this->fetchApiData($shop, $access_token, 'inventory_levels');
        $shopapi =json_decode( $this->fetchApiData($shop, $access_token, 'shop'));
        // $collections = $this->fetchApiData($shop, $access_token, 'collects');
        // $price_rules = $this->fetchApiData($shop, $access_token, 'price_rules');
        $pages = $this->fetchApiData($shop, $access_token, 'pages');
        $currencies = $this->fetchApiData($shop, $access_token, 'currencies');
            
        // print_r($currencies);
        // Save access_token and shop in the database
        $this->Shopify_model->insert_store($shop, $access_token);
        $this->Shopify_model->insert_pages($pages);
        $this->Shopify_model->insert_store_data($shopapi);
        $this->Shopify_model->insert_blog_data($blogs,$blog_post);
        $this->Shopify_model->insert_products($products);
        $this->Shopify_model->insert_variants_data($products);
        $this->Shopify_model->insert_images($products);
        $this->Shopify_model->insert_currencies($currencies);
    
        // Redirect to the custom view
        $data = ['shop' => $shop];
        $this->load->view('callback_success_view', $data);
    }

    private function getAccessToken($shop, $code) {
        $token_url = "https://$shop/admin/oauth/access_token";
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query([
                    'client_id' => $this->api_key,
                    'client_secret' => $this->api_secret,
                    'code' => $code
                ])
            ]
        ]);

        $response = file_get_contents($token_url, false, $context);

        if ($response === FALSE) {
            // Log error or handle it appropriately
            return null;
        }

        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }

    private function fetchApiData($shop, $access_token, $endpoint) {
        $url = "https://$shop/admin/api/2024-01/$endpoint.json";
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'X-Shopify-Access-Token: ' . $access_token
            ]
        ]);

        $response = file_get_contents($url, false, $context);

        if ($response === FALSE) {
            // Log error or handle it appropriately
            return null;
        }

        return $response;
    }

    public function fetch_and_store_products() {
        $shop = $this->input->post('shop');
        $store_data = $this->Shopify_model->get_store($shop);
        $access_token = $store_data['access_token'] ?? null;
    
        if (empty($shop) || empty($access_token)) {
            show_error('Shop URL or access token is not set.');
        }
    
        $products = $this->fetchApiData($shop, $access_token, 'products.json');
    
        if ($products === null) {
            show_error('Failed to fetch products from Shopify API.');
        }
    
        // Store products in the database
        $this->Shopify_model->insert_products($products);
    
        // Redirect to a success page or show a success message
        redirect('shopify/products_stored_success');
    }

    public function products_stored_success() {
        $this->load->view('products_stored_success_view');
    }
    
    public function get_products() {
        $shop = $this->config->item('SHOP_URL');
        $store_data = $this->Shopify_model->get_store($shop);
        $access_token = $store_data['access_token'] ?? null;
    
        if (empty($shop) || empty($access_token)) {
            show_error('Shop URL or access token is not set.');
        }
    
        $products = $this->fetchApiData($shop, $access_token, 'products.json');
    
        if ($products === null) {
            show_error('Failed to fetch products from Shopify API.');
        }
    
        // Load a view to display the products
        $this->load->view('products_view', ['products' => $products]);
    }
}
