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
        $scopes = 'read_products,write_products'; // Adjust scopes as needed

        $install_url = "https://$shop/admin/oauth/authorize?client_id={$this->api_key}&scope=$scopes&redirect_uri=$redirect_uri";
        redirect($install_url);
    }

    public function callback() {
        $shop = $this->input->get('shop');
        $code = $this->input->get('code');

        if (empty($shop) || empty($code)) {
            show_error('Missing shop or code parameter.');
        }

        $token_url = "https://$shop/admin/oauth/access_token";
        $response = file_get_contents($token_url, false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query([
                    'client_id' => $this->api_key,
                    'client_secret' => $this->api_secret,
                    'code' => $code
                ])
            ]
        ]));

        $data = json_decode($response, true);
        $access_token = $data['access_token'] ?? null;

        if (empty($access_token)) {
            show_error('Failed to retrieve access token.');
        }

        // Save access_token and shop in the database
        $this->Shopify_model->insert_store($shop, $access_token);

        // Redirect to a page to show the success message or perform other actions
        redirect('shopify/get_products');
    }

    public function get_products() {
        $shop = $this->config->item('SHOP_URL');
        $store_data = $this->Shopify_model->get_store($shop);
        $access_token = $store_data['access_token'] ?? null;
        $key = getenv('SHOPIFY_API_KEY');
        if (empty($shop) || empty($access_token)) {
            show_error('Shop URL or access token is not set.');
        }
    
        // Construct the products URL
        $products_url = "https://$shop/admin/api/2024-01/products.json";
    
        // Get the products
        $response = @file_get_contents($products_url, false, stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'X-Shopify-Access-Token: ' . $access_token
            ]
        ]));
    
        if ($response === FALSE) {
            show_error('Failed to fetch products from Shopify API.');
        }
    
        $products = json_decode($response, true);
    
        // Load a view to display the products
        $this->load->view('products_view', ['products' => $products]);
    }
}
