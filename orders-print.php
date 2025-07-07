<?php
/*
Plugin Name: WooCommerce Order Print
Description: Print WooCommerce orders as HTML
Version: 1.0
Author: Alireza Fatemi
Author URI: https://alirezafatemi.ir
Plugin URI: https://github.com/Ftepic
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
 
 $css = "
 @media print {
 body {
 font-family: 'Vazir', Tahoma, Arial, sans-serif;
 direction: " . ($this->is_rtl() ? 'rtl' : 'ltr') . ";
 }
 .no-print {
 display: none !important;
 }
 .print-only {
 display: block !important;
 }
 table {
 width: 100%;
 border-collapse: collapse;
 }
 th, td {
 border: 1px solid #ddd;
 padding: 8px;
 text-align: " . ($this->is_rtl() ? 'right' : 'left') . ";
 }
 th {
 background-color: #f2f2f2;
 }
 }
 ";
 
 wp_add_inline_style('wc-order-print', $css);
 }
 
 public function is_rtl() {
 $locale = get_locale();
 return ($locale === 'fa_IR');
 }

 public function add_admin_menu() {
 add_submenu_page(
 null,
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
 
 if (empty($order_ids)) {
 $args = array(
 'limit' => -1,
 'orderby' => 'date',
 'order' => 'DESC',
 );
 
 if (isset($_GET['status']) && !empty($_GET['status'])) {
 $args['status'] = sanitize_text_field($_GET['status']);
 }
 
 if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
 $args['date_created'] = '>' . sanitize_text_field($_GET['date_from']);
 }
 
 if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
 if (isset($args['date_created'])) {
 $args['date_created'] = array($args['date_created'], '<' . sanitize_text_field($_GET['date_to']));
 } else {
 $args['date_created'] = '<' . sanitize_text_field($_GET['date_to']);
 }
 }
 
 $orders = wc_get_orders($args);
 } else {
 $orders = array();
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
 $font_family = $is_rtl ? "'Vazir', Tahoma, Arial, sans-serif" : "Arial, Helvetica, sans-serif";
 
 ?>
 <!DOCTYPE html>
 <html dir="<?php echo $dir_attr; ?>">
 <head>
 <meta charset="UTF-8">
 <title><?php echo $this->get_translated_text('Print WooCommerce Orders', 'چاپ سفارشات ووکامرس'); ?></title>
 <style>
 <?php if ($is_rtl): ?>
 @font-face {
 font-family: 'Vazir';
 src: url('https://cdn.jsdelivr.net/gh/rastikerdar/vazir-font@v30.1.0/dist/Vazir.eot');
 src: url('https://cdn.jsdelivr.net/gh/rastikerdar/vazir-font@v30.1.0/dist/Vazir.eot?#iefix') format('embedded-opentype'),
 url('https://cdn.jsdelivr.net/gh/rastikerdar/vazir-font@v30.1.0/dist/Vazir.woff2') format('woff2'),
 url('https://cdn.jsdelivr.net/gh/rastikerdar/vazir-font@v30.1.0/dist/Vazir.woff') format('woff'),
 url('https://cdn.jsdelivr.net/gh/rastikerdar/vazir-font@v30.1.0/dist/Vazir.ttf') format('truetype');
 font-weight: normal;
 font-style: normal;
 }
 <?php endif; ?>
 
 body {
 font-family: <?php echo $font_family; ?>;
 direction: <?php echo $dir_attr; ?>;
 margin: 0;
 padding: 20px;
 font-size: 12px;
 }
 
 .print-header {
 text-align: center;
 margin-bottom: 20px;
 }
 
 .print-header h1 {
 font-size: 18px;
 margin-bottom: 5px;
 }
 
 .print-header p {
 font-size: 12px;
 color: #666;
 margin: 5px 0;
 }
 
 .store-info {
 display: flex;
 justify-content: space-between;
 margin-bottom: 15px;
 border: 1px solid #ddd;
 padding: 10px;
 background-color: #f9f9f9;
 }
 
 .store-info-section {
 flex: 1;
 }
 
 .store-info h3 {
 margin-top: 0;
 margin-bottom: 10px;
 font-size: 14px;
 }
 
 .store-info p {
 margin: 5px 0;
 }
 
 .orders-table {
 width: 100%;
 border-collapse: collapse;
 margin-bottom: 20px;
 font-size: 11px;
 }
 
 .orders-table th {
 background-color: #f2f2f2;
 border: 1px solid #ddd;
 padding: 6px;
 text-align: <?php echo $text_align; ?>;
 font-weight: bold;
 }
 
 .orders-table td {
 border: 1px solid #ddd;
 padding: 6px;
 text-align: <?php echo $text_align; ?>;
 }
 
 .orders-table tr:nth-child(even) {
 background-color: #f9f9f9;
 }
 
 .order-products {
 margin-bottom: 20px;
 page-break-inside: avoid;
 }
 
 .order-products h3 {
 font-size: 14px;
 margin-bottom: 5px;
 padding: 5px;
 background-color: #f2f2f2;
 border: 1px solid #ddd;
 }
 
 .products-table {
 width: 100%;
 border-collapse: collapse;
 font-size: 10px;
 }
 
 .products-table th {
 background-color: #f2f2f2;
 border: 1px solid #ddd;
 padding: 4px;
 text-align: <?php echo $text_align; ?>;
 font-weight: bold;
 }
 
 .products-table td {
 border: 1px solid #ddd;
 padding: 4px;
 text-align: <?php echo $text_align; ?>;
 }
 
 .products-table tr:nth-child(even) {
 background-color: #f9f9f9;
 }
 
 .order-totals {
 text-align: <?php echo $is_rtl ? 'left' : 'right'; ?>;
 margin-bottom: 10px;
 }
 
 .order-totals table {
 width: auto;
 margin-left: <?php echo $is_rtl ? '0' : 'auto'; ?>;
 margin-right: <?php echo $is_rtl ? 'auto' : '0'; ?>;
 border-collapse: collapse;
 }
 
 .order-totals table td {
 border: none;
 padding: 3px 6px;
 }
 
 .order-totals table tr:last-child {
 font-weight: bold;
 }
 
 .no-print {
 display: block;
 }
 
 .print-only {
 display: none;
 }
 
 .print-actions {
 text-align: center;
 margin: 20px 0;
 }
 
 .print-button {
 background-color: #0073aa;
 color: white;
 border: none;
 padding: 10px 20px;
 font-size: 16px;
 cursor: pointer;
 border-radius: 4px;
 }
 
 .print-button:hover {
 background-color: #005177;
 }
 
 .status-completed {
 background-color: #c6e1c6;
 color: #5b841b;
 padding: 3px 6px;
 border-radius: 3px;
 font-size: 10px;
 }
 
 .status-processing {
 background-color: #c8d7e1;
 color: #2e4453;
 padding: 3px 6px;
 border-radius: 3px;
 font-size: 10px;
 }
 
 .status-on-hold {
 background-color: #f8dda7;
 color: #94660c;
 padding: 3px 6px;
 border-radius: 3px;
 font-size: 10px;
 }
 
 .status-cancelled {
 background-color: #eba3a3;
 color: #761919;
 padding: 3px 6px;
 border-radius: 3px;
 font-size: 10px;
 }
 
 .shipping-type-cash-on-delivery {
 background-color: #ffe8e8;
 color: #d63638;
 padding: 3px 6px;
 border-radius: 3px;
 font-size: 10px;
 }
 
 .shipping-type-regular {
 background-color: #e8f4ff;
 color: #0073aa;
 padding: 3px 6px;
 border-radius: 3px;
 font-size: 10px;
 }
 
 @media print {
 .no-print {
 display: none !important;
 }
 
 .print-only {
 display: block !important;
 }
 
 body {
 padding: 10px;
 }
 
 .order-products {
 page-break-inside: avoid;
 margin-bottom: 15px;
 }
 
 @page {
 margin: 1cm;
 }
 }
 </style>
 </head>
 <body>
 <div class="print-header">
 <h1><?php echo $this->get_translated_text('WooCommerce Orders List', 'لیست سفارشات ووکامرس'); ?></h1>
 <p><?php echo $this->get_translated_text('Print Date:', 'تاریخ چاپ:'); ?> <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format')); ?></p>
 </div>
 
 <div class="store-info">
 <div class="store-info-section">
 <h3><?php echo $this->get_translated_text('Store Information', 'اطلاعات فروشگاه'); ?></h3>
 <p><strong><?php echo $this->get_translated_text('Store:', 'فروشگاه:'); ?></strong> <?php echo get_bloginfo('name'); ?></p>
 <p><strong><?php echo $this->get_translated_text('Phone:', 'شماره تماس:'); ?></strong> 09224489341</p>
 </div>
 <div class="store-info-section">
 <p><strong><?php echo $this->get_translated_text('Email:', 'ایمیل:'); ?></strong> shopadshop.ir@gmail.com</p>
 <p><strong><?php echo $this->get_translated_text('Address:', 'آدرس:'); ?></strong> <?php echo $this->get_translated_text('Zanjan - Upper Bazaar - Four-Door Timcheh', 'زنجان – بازار بالا – تیمچه چهار دربی'); ?></p>
 </div>
 </div>
 
 <div class="print-actions no-print">
 <button class="print-button" onclick="window.print();"><?php echo $this->get_translated_text('Print Orders', 'چاپ سفارشات'); ?></button>
 </div>
 
 <?php if (!empty($orders)): ?>
 <h2><?php echo $this->get_translated_text('Orders List', 'لیست سفارشات'); ?> (<?php echo count($orders); ?> <?php echo $this->get_translated_text('orders', 'سفارش'); ?>)</h2>
 <table class="orders-table">
 <thead>
 <tr>
 <th><?php echo $this->get_translated_text('Order #', 'شماره سفارش'); ?></th>
 <th><?php echo $this->get_translated_text('Date', 'تاریخ'); ?></th>
 <th><?php echo $this->get_translated_text('Customer', 'مشتری'); ?></th>
 <th><?php echo $this->get_translated_text('Phone', 'تلفن'); ?></th>
 <th><?php echo $this->get_translated_text('Address', 'آدرس'); ?></th>
 <th><?php echo $this->get_translated_text('Status', 'وضعیت'); ?></th>
 <th><?php echo $this->get_translated_text('Shipping Type', 'نوع ارسال'); ?></th>
 <th><?php echo $this->get_translated_text('Total', 'مجموع'); ?></th>
 </tr>
 </thead>
 <tbody>
 <?php foreach ($orders as $order): ?>
 <tr>
 <td><?php echo $order->get_order_number(); ?></td>
 <td><?php echo $order->get_date_created()->date_i18n(get_option('date_format')); ?></td>
 <td><?php echo $order->get_formatted_billing_full_name(); ?></td>
 <td><?php echo $order->get_billing_phone() ? $order->get_billing_phone() : '-'; ?></td>
 <td>
 <?php
 $address = $order->get_formatted_shipping_address();
 if (!$address) {
 $address = $order->get_formatted_billing_address();
 }
 if (!$address) {
 $address = '-';
 }
 echo $address;
 ?>
 </td>
 <td>
 <span class="status-<?php echo esc_attr($order->get_status()); ?>">
 <?php echo wc_get_order_status_name($order->get_status()); ?>
 </span>
 </td>
 <td>
 <?php 
 $shipping_type = $order->get_meta('_shipping_type');
 $shipping_label = $order->get_meta('_shipping_type_label');
 
 if ($shipping_type === 'cash_on_delivery') {
 echo '<span class="shipping-type-cash-on-delivery">' . ($shipping_label ? esc_html($shipping_label) : ($this->is_rtl() ? 'پس کرایه' : 'Cash on Delivery')) . '</span>';
 } elseif ($shipping_type === 'regular') {
 echo '<span class="shipping-type-regular">' . ($shipping_label ? esc_html($shipping_label) : ($this->is_rtl() ? 'ارسال پستی' : 'Postal Shipping')) . '</span>';
 } else {
 echo '-';
 }
 ?>
 </td>
 <td><?php echo wp_strip_all_tags($order->get_formatted_order_total()); ?></td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 
 <h2><?php echo $this->get_translated_text('Order Details', 'جزئیات سفارشات'); ?></h2>
 <?php foreach ($orders as $order): ?>
 <div class="order-products">
 <h3><?php echo $this->get_translated_text('Order', 'سفارش'); ?> #<?php echo $order->get_order_number(); ?> - <?php echo $order->get_formatted_billing_full_name(); ?> (<?php echo $order->get_date_created()->date_i18n(get_option('date_format')); ?>)</h3>
 <table class="products-table">
 <thead>
 <tr>
 <th width="5%"><?php echo $this->get_translated_text('Row', 'ردیف'); ?></th>
 <th width="45%"><?php echo $this->get_translated_text('Product', 'محصول'); ?></th>
 <th width="10%"><?php echo $this->get_translated_text('Quantity', 'تعداد'); ?></th>
 <th width="20%"><?php echo $this->get_translated_text('Unit Price', 'قیمت واحد'); ?></th>
 <th width="20%"><?php echo $this->get_translated_text('Total', 'مجموع'); ?></th>
 </tr>
 </thead>
 <tbody>
 <?php 
 $i = 1;
 foreach ($order->get_items() as $item): 
 ?>
 <tr>
 <td><?php echo $i++; ?></td>
 <td><?php echo $item->get_name(); ?></td>
 <td><?php echo $item->get_quantity(); ?></td>
 <td><?php echo wp_strip_all_tags(wc_price($order->get_item_subtotal($item, false, false))); ?></td>
 <td><?php echo wp_strip_all_tags(wc_price($order->get_line_subtotal($item, false, false))); ?></td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 <tfoot>
 <?php if ($order->get_shipping_total() > 0): ?>
 <tr>
 <td colspan="4" style="text-align: <?php echo $is_rtl ? 'left' : 'right'; ?>;"><?php echo $this->get_translated_text('Shipping:', 'هزینه ارسال:'); ?></td>
 <td><?php echo wp_strip_all_tags(wc_price($order->get_shipping_total())); ?></td>
 </tr>
 <?php endif; ?>
 <?php if ($order->get_total_discount() > 0): ?>
 <tr>
 <td colspan="4" style="text-align: <?php echo $is_rtl ? 'left' : 'right'; ?>;"><?php echo $this->get_translated_text('Discount:', 'تخفیف:'); ?></td>
 <td><?php echo wp_strip_all_tags(wc_price($order->get_total_discount())); ?></td>
 </tr>
 <?php endif; ?>
 <tr>
 <td colspan="4" style="text-align: <?php echo $is_rtl ? 'left' : 'right'; ?>; font-weight: bold;"><?php echo $this->get_translated_text('Grand Total:', 'جمع کل:'); ?></td>
 <td style="font-weight: bold;"><?php echo wp_strip_all_tags($order->get_formatted_order_total()); ?></td>
 </tr>
 </tfoot>
 </table>
 </div>
 <?php endforeach; ?>
 
 <?php else: ?>
 <div class="no-orders">
 <p><?php echo $this->get_translated_text('No orders found.', 'هیچ سفارشی یافت نشد.'); ?></p>
 </div>
 <?php endif; ?>
 
 <script>
 window.onload = function() {
 if (window.location.href.indexOf('action=print') > -1) {
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
 var isRTL = locale === 'fa-IR';
 var printAllText = isRTL ? 'چاپ همه سفارشات' : 'Print All Orders';
 
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
 margin-left: 10px !important;
}

#print-selected-orders {
 margin-left: 10px;
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
