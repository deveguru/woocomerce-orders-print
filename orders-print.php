<?php
/*
Plugin Name: WooCommerce Order Print
Description: Print WooCommerce orders as HTML
Version: 1.0
Author: Alireza Fatemi
Author URI: https://alirezafatemi.ir
Plugin URI: https://github.com/deveguru
Text Domain: wc-order-print
Domain Path: /languages
*/

if (!defined('ABSPATH')) {
 exit;
}

class WC_Order_Print {

 public function __construct() {
 add_action('plugins_loaded', array($this, 'load_textdomain'));
 add_action('admin_menu', array($this, 'add_admin_menu'));
 add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
 add_action('admin_footer', array($this, 'add_bulk_print_action')); 
 add_action('admin_init', array($this, 'handle_print_request'));
 add_filter('bulk_actions-edit-shop_order', array($this, 'register_bulk_actions'));
 add_filter('handle_bulk_actions-edit-shop_order', array($this, 'handle_bulk_actions'), 10, 3);
 }
 
 public function load_textdomain() {
 load_plugin_textdomain('wc-order-print', false, dirname(plugin_basename(__FILE__)) . '/languages');
 }
 
 public function get_translated_text($english_text, $persian_text) {
 $locale = get_locale();
 return ($locale === 'fa_IR') ? $persian_text : $english_text;
 }

 public function enqueue_scripts($hook) {
 if ('edit.php' !== $hook || !isset($_GET['post_type']) || 'shop_order' !== $_GET['post_type']) {
 return;
 }
 
 wp_enqueue_script('wc-order-print', plugins_url('order-print.js', __FILE__), array('jquery'), '1.0', true);
 wp_localize_script('wc-order-print', 'wc_order_print', array(
 'ajaxurl' => admin_url('admin-ajax.php'),
 'nonce' => wp_create_nonce('wc-order-print-nonce'),
 'print_url' => admin_url('admin.php?page=wc-order-print&action=print'),
 'select_orders' => $this->get_translated_text('Please select at least one order.', 'لطفاً حداقل یک سفارش را انتخاب کنید.')
 ));
 
 wp_enqueue_style('wc-order-print', plugins_url('order-print.css', __FILE__), array(), '1.0');
 }
 
 public function is_rtl() {
 $locale = get_locale();
 return ($locale === 'fa_IR');
 }

 public function add_admin_menu() {
 add_submenu_page( null,
 $this->get_translated_text('Print Orders', 'چاپ سفارشات'),
 $this->get_translated_text('Print Orders', 'چاپ سفارشات'),
 'manage_woocommerce',
 'wc-order-print',
 array($this, 'print_page')
 );
 }

 public function register_bulk_actions($bulk_actions) {
 $bulk_actions['print_orders'] = $this->get_translated_text('Print Orders', 'چاپ سفارشات');
 return $bulk_actions;
 }

 public function handle_bulk_actions($redirect_to, $action, $post_ids) {
 if ($action !== 'print_orders') {
 return $redirect_to;
 }
 
 $redirect_to = add_query_arg(array(
 'page' => 'wc-order-print',
 'action' => 'print',
 'order_ids' => implode(',', $post_ids),
 ), admin_url('admin.php'));
 
 return $redirect_to;
 }

 public function add_bulk_print_action() {
 if (!isset($_GET['post_type']) || $_GET['post_type'] !== 'shop_order') {
 return;
 }
 
 $print_button_text = $this->get_translated_text('Print Selected Orders', 'چاپ سفارشات انتخاب شده');
 
 ?>
 <script type="text/javascript">
 jQuery(document).ready(function($) {
 $('.tablenav.top .bulkactions').append('<button type="button" id="print-selected-orders" class="button"><?php echo $print_button_text; ?></button>');
 
 $('#print-selected-orders').on('click', function(e) {
 e.preventDefault();
 
 var selected = [];
 $('input[name="post[]"]:checked').each(function() {
 selected.push($(this).val());
 });
 
 if (selected.length === 0) {
 alert(wc_order_print.select_orders);
 return;
 }
 
 var printUrl = '<?php echo admin_url('admin.php?page=wc-order-print&action=print'); ?>&order_ids=' + selected.join(',');
 window.open(printUrl, '_blank');
 });
 });
 </script>
 <?php
 }

 public function handle_print_request() {
 if (!isset($_GET['page']) || $_GET['page'] !== 'wc-order-print' || !isset($_GET['action']) || $_GET['action'] !== 'print') {
 return;
 }
 
 if (!current_user_can('manage_woocommerce')) {
 wp_die($this->get_translated_text('You do not have permission to access this page.', 'شما اجازه دسترسی به این صفحه را ندارید.'));
 }
 }

 public function print_page() {
 if (!isset($_GET['action']) || $_GET['action'] !== 'print') {
 return;
 }
 
 $order_ids = isset($_GET['order_ids']) ? explode(',', sanitize_text_field($_GET['order_ids'])) : array();
 
 $orders = array();
 if (!empty($order_ids)) {
 foreach ($order_ids as $order_id) {
 $order = wc_get_order($order_id);
 if ($order) {
 $orders[] = $order;
 }
 }
 }
 
 $this->render_print_template($orders);
 exit;
 }

 private function render_print_template($orders) {
 $is_rtl = $this->is_rtl();
 $dir_attr = $is_rtl ? 'rtl' : 'ltr';
 $text_align = $is_rtl ? 'right' : 'left';
 
 ?>
 <!DOCTYPE html>
 <html dir="<?php echo $dir_attr; ?>">
 <head>
 <meta charset="UTF-8">
 <title><?php echo $this->get_translated_text('Print WooCommerce Orders', 'چاپ سفارشات ووکامرس'); ?></title>
 <style>
 @font-face {
 font-family: 'Arian';
 src: url('https://cdn.jsdelivr.net/gh/farsiweb/arian-font/dist/Arian.eot');
 src: url('https://cdn.jsdelivr.net/gh/farsiweb/arian-font/dist/Arian.eot?#iefix') format('embedded-opentype'),
 url('https://cdn.jsdelivr.net/gh/farsiweb/arian-font/dist/Arian.woff2') format('woff2'),
 url('https://cdn.jsdelivr.net/gh/farsiweb/arian-font/dist/Arian.woff') format('woff'),
 url('https://cdn.jsdelivr.net/gh/farsiweb/arian-font/dist/Arian.ttf') format('truetype');
 font-weight: normal;
 font-style: normal;
 }
 
 body {
 font-family: 'Arian', Tahoma, Arial, sans-serif;
 direction: <?php echo $dir_attr; ?>;
 margin: 0;
 padding: 0;
 background-color: #f1f1f1;
 font-size: 12px;
 }
 
 .slip-container {
 display: flex;
 flex-direction: column;
 padding: 10mm;
 box-sizing: border-box;
 width: 100%;
 }
 
 .order-slip {
 width: 100%;
 margin-bottom: 5mm;
 box-sizing: border-box;
 border: 1px solid #333;
 border-radius: 10px;
 padding: 5mm;
 background: #fff;
 box-shadow: 0 1px 3px rgba(0,0,0,0.1);
 page-break-inside: avoid;
 }
 
 .order-header {
 border-bottom: 1px solid #ddd;
 padding-bottom: 3mm;
 margin-bottom: 3mm;
 display: flex;
 justify-content: space-between;
 }
 
 .order-number {
 font-weight: bold;
 font-size: 14px;
 }
 
 .order-date {
 color: #555;
 }
 
 .order-body {
 display: flex;
 justify-content: space-between;
 margin-bottom: 3mm;
 }
 
 .receiver-info, .sender-info {
 width: 48%;
 }
 
 .info-title {
 font-weight: bold;
 margin-bottom: 2mm;
 border-bottom: 1px solid #eee;
 padding-bottom: 1mm;
 }
 
 .info-content {
 line-height: 1.4;
 }
 
 .shipping-method {
 border-top: 1px solid #eee;
 border-bottom: 1px solid #eee;
 padding: 2mm 0;
 margin: 2mm 0;
 }
 
 .products-list {
 margin-bottom: 3mm;
 }
 
 .products-title {
 font-weight: bold;
 margin-bottom: 2mm;
 }
 
 .product-item {
 margin-bottom: 1mm;
 }
 
 .order-footer {
 display: flex;
 justify-content: space-between;
 border-top: 1px solid #eee;
 padding-top: 2mm;
 }
 
 .order-total {
 font-weight: bold;
 }
 
 .no-print {
 position: fixed;
 top: 10px;
 left: 10px;
 z-index: 1000;
 }
 
 .print-button {
 background-color: #0073aa;
 color: white;
 border: none;
 padding: 10px 20px;
 font-size: 16px;
 cursor: pointer;
 border-radius: 4px;
 box-shadow: 0 2px 5px rgba(0,0,0,0.2);
 }
 
 #adminmenuwrap, #adminmenu, #wpadminbar, #adminmenumain, #wpfooter {
 display: none !important;
 }
 
 #wpcontent, #wpfooter {
 margin-left: 0 !important;
 }
 
 @media print {
 body {
 background-color: #fff;
 }
 
 .no-print {
 display: none !important;
 }
 
 .slip-container {
 padding: 0;
 }
 
 .order-slip {
 page-break-inside: avoid;
 box-shadow: none;
 border: 1px solid #333;
 }
 
 .order-slip:nth-child(4n) {
 page-break-after: always;
 }
 
 @page {
 size: A4;
 margin: 10mm;
 }
 
 #adminmenuwrap, #adminmenu, #wpadminbar, #adminmenumain, #wpfooter {
 display: none !important;
 }
 
 #wpcontent, #wpfooter {
 margin-left: 0 !important;
 }
 }
 </style>
 </head>
 <body>
 
 <div class="no-print">
 <button class="print-button" onclick="window.print();"><?php echo $this->get_translated_text('Print', 'چاپ'); ?></button>
 </div>
 
 <div class="slip-container">
 <?php if (!empty($orders)): ?>
 <?php foreach ($orders as $order): ?>
 <div class="order-slip">
 <div class="order-header">
 <div class="order-number">
 <?php echo $this->get_translated_text('Order #:', 'شماره سفارش:'); ?> <?php echo $order->get_order_number(); ?>
 </div>
 <div class="order-date">
 <?php echo $this->get_translated_text('Date:', 'تاریخ:'); ?> <?php echo $order->get_date_created()->date_i18n('Y-m-d'); ?>
 </div>
 </div>
 
 <div class="order-body">
 <div class="receiver-info">
 <div class="info-title"><?php echo $this->get_translated_text('Receiver:', 'گیرنده:'); ?></div>
 <div class="info-content">
 <strong><?php echo $order->get_formatted_billing_full_name(); ?></strong><br>
 <?php echo $this->get_translated_text('Phone:', 'تلفن:'); ?> <?php echo $order->get_billing_phone() ?: '-'; ?><br>
 <?php echo $this->get_translated_text('Postal Code:', 'کد پستی:'); ?> <?php echo $order->get_billing_postcode() ?: '-'; ?><br>
 <strong><?php echo $this->get_translated_text('Address:', 'آدرس:'); ?></strong> <?php echo $order->get_formatted_shipping_address() ?: $order->get_formatted_billing_address(); ?>
 </div>
 </div>
 
 <div class="sender-info">
 <div class="info-title"><?php echo $this->get_translated_text('Sender:', 'فرستنده:'); ?></div>
 <div class="info-content">
 <strong><?php echo get_bloginfo('name'); ?></strong><br>
 <?php echo $this->get_translated_text('Phone:', 'تلفن:'); ?> 09224489341<br>
 <?php echo $this->get_translated_text('Address:', 'آدرس:'); ?> <?php echo $this->get_translated_text('Zanjan - Upper Bazaar - Four-Door Timcheh', 'زنجان – بازار بالا – تیمچه چهار دربی'); ?>
 </div>
 </div>
 </div>
 
 <div class="shipping-method">
 <?php 
 $shipping_label = $order->get_meta('_shipping_type_label') ?: ($this->is_rtl() ? 'تعیین نشده' : 'Not Set');
 ?>
 <strong><?php echo $this->get_translated_text('Shipping:', 'نوع ارسال:'); ?></strong> <?php echo esc_html($shipping_label); ?>
 &nbsp;&nbsp;
 <strong><?php echo $this->get_translated_text('Payment:', 'پرداخت:'); ?></strong> <?php echo $order->get_payment_method_title(); ?>
 </div>
 
 <div class="products-list">
 <div class="products-title"><?php echo $this->get_translated_text('Products:', 'محصولات:'); ?></div>
 <?php foreach ($order->get_items() as $item): ?>
 <div class="product-item">
 - <?php echo $item->get_quantity(); ?> × <?php echo $item->get_name(); ?>
 </div>
 <?php endforeach; ?>
 </div>
 
 <div class="order-footer">
 <div class="order-total">
 <?php echo $this->get_translated_text('Total:', 'مجموع:'); ?> <?php echo $order->get_formatted_order_total(); ?>
 </div>
 <?php 
 $cart_weight = $order->get_meta('_cart_weight');
 if ($cart_weight > 0): 
 ?>
 <div class="order-weight">
 <?php echo $this->get_translated_text('Weight:', 'وزن:'); ?> <?php echo esc_html($cart_weight); ?> <?php echo $this->get_translated_text('g', 'گرم'); ?>
 </div>
 <?php endif; ?>
 <?php if ($order->get_customer_note()): ?>
 <div class="order-note">
 <?php echo $this->get_translated_text('Notes:', 'یادداشت:'); ?> <?php echo $order->get_customer_note(); ?>
 </div>
 <?php endif; ?>
 </div>
 </div>
 <?php endforeach; ?>
 <?php else: ?>
 <p><?php echo $this->get_translated_text('No orders selected for printing.', 'هیچ سفارشی برای چاپ انتخاب نشده است.'); ?></p>
 <?php endif; ?>
 </div>
 
 <script>
 window.onload = function() {
 if (window.location.href.indexOf('action=print') > -1) {
 if (document.getElementById('adminmenuwrap')) document.getElementById('adminmenuwrap').style.display = 'none';
 if (document.getElementById('wpadminbar')) document.getElementById('wpadminbar').style.display = 'none';
 if (document.getElementById('adminmenumain')) document.getElementById('adminmenumain').style.display = 'none';
 if (document.getElementById('wpfooter')) document.getElementById('wpfooter').style.display = 'none';
 document.getElementById('wpcontent').style.marginLeft = '0';
 
 setTimeout(function() {
 window.print();
 }, 500);
 }
 };
 </script>
 </body>
 </html>
 <?php
 }
}

function wc_order_print_create_files() {
 $js_content = <<<EOT
jQuery(document).ready(function($) {
 var locale = document.documentElement.lang || 'en-US';
 var isRTL = locale.startsWith('fa');
 var printAllText = isRTL ? 'چاپ همه سفارشات (نمای کلی)' : 'Print All Orders (Overview)';
 
 $('.tablenav.top .actions:first').append('<a href="#" class="button print-all-orders">' + printAllText + '</a>');
 
 $('.print-all-orders').on('click', function(e) {
 e.preventDefault();
 
 var status = $('select[name="post_status"]').val() || 'all';
 var printUrl = wc_order_print.print_url + '&status=' + status;
 
 window.open(printUrl, '_blank');
 });
});
EOT;

 $css_content = <<<EOT
.print-all-orders {
 margin-<?php echo is_rtl() ? 'right' : 'left'; ?>: 5px !important;
}
#print-selected-orders {
 margin-<?php echo is_rtl() ? 'right' : 'left'; ?>: 5px;
}
EOT;

 $plugin_dir = WP_PLUGIN_DIR . '/wc-order-print';
 if (!file_exists($plugin_dir)) {
 mkdir($plugin_dir, 0755, true);
 }

 $languages_dir = $plugin_dir . '/languages';
 if (!file_exists($languages_dir)) {
 mkdir($languages_dir, 0755, true);
 }

 file_put_contents($plugin_dir . '/order-print.js', $js_content);
 file_put_contents($plugin_dir . '/order-print.css', $css_content);
}

register_activation_hook(__FILE__, 'wc_order_print_create_files');

$wc_order_print = new WC_Order_Print();
