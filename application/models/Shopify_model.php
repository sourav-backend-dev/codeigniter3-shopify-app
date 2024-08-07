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

    public function get_store($shop) {
        $query = $this->db->get_where('stores', ['shop' => $shop]);
        return $query->row_array();
    }
}
