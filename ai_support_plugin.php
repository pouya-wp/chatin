<?php
/**
 * Plugin Name:  چتینا
 * Description:  چت پشتیبانی با هوش مصنوعی، قابلیت پشتیبانی زنده و شخصی‌سازی کامل.
 * Version:      10.1.0
 * Author:       پویا وردپرس
 */

if (!defined('ABSPATH')) exit;

class PouyaWPAISupportDirectFinal {

    public function __construct() {
        add_action('init', [$this, 'init_plugin']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_footer', [$this, 'render_chat_interface']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'admin_init']);
        add_action('wp_ajax_pouyawp_ai_chat', [$this, 'handle_chat']);
        add_action('wp_ajax_nopriv_pouyawp_ai_chat', [$this, 'handle_chat']);
        add_action('wp_ajax_pouyawp_contact_operator', [$this, 'handle_contact_operator']);
        add_action('wp_ajax_nopriv_pouyawp_contact_operator', [$this, 'handle_contact_operator']);
        add_action('wp_ajax_pouyawp_operator_send_message', [$this, 'handle_operator_send_message']);
        add_action('wp_ajax_pouyawp_check_for_messages', [$this, 'handle_check_for_messages']);
        add_action('wp_ajax_nopriv_pouyawp_check_for_messages', [$this, 'handle_check_for_messages']);
        register_activation_hook(__FILE__, [$this, 'activate']);
    }

    public function init_plugin() {
        $this->ensure_db_table_exists();
    }

    public function ensure_db_table_exists() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'pouyawp_chat_history';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                session_id varchar(50) NOT NULL,
                ip_address varchar(45) NOT NULL,
                user_agent text NOT NULL,
                sender varchar(10) NOT NULL,
                message longtext NOT NULL,
                status varchar(20) DEFAULT 'none' NOT NULL,
                timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
                PRIMARY KEY  (id),
                KEY session_id (session_id)
            ) $charset_collate;";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    public function activate() { $this->ensure_db_table_exists(); }

    public function enqueue_scripts() {
        wp_enqueue_style('pouyawp-ai-chat-css', plugin_dir_url(__FILE__) . 'chat.css', [], '10.1.0');
        wp_enqueue_script('pouyawp-ai-chat-js', plugin_dir_url(__FILE__) . 'chat.js', ['jquery'], '10.1.0', true);
        wp_localize_script('pouyawp-ai-chat-js', 'pouyawp_chat_params', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pouyawp_ai_nonce'),
            'button_style' => get_option('pouyawp_chat_button_style', 'icon_only'),
            'initial_bot_message' => get_option('pouyawp_text_initial_bot', 'سلام! من دستیار هوشمند هستم 😊 چطور میتونم کمکت کنم؟'),
            'welcome_enabled' => get_option('pouyawp_chat_welcome_enabled', 'on') === 'on',
        ]);
    }

    public function render_chat_interface() {
        ?>
        <div class="pouyawp-chat-overlay"></div>
        <button id="pouyawp-chat-button" class="pouyawp-floating-btn" title="<?php echo esc_attr(get_option('pouyawp_text_main_btn_tooltip', 'شروع گفتگو')); ?>">
            <img width="30px" src="https://www.pouya-wp.ir/wp-content/uploads/2025/07/Chat.svg" alt="Your SVG Image">
        </button>
        <?php if (get_option('pouyawp_chat_welcome_enabled', 'on') === 'on'): ?>
        <div class="pouyawp-welcome-popup">
             <button class="pouyawp-welcome-close" title="<?php echo esc_attr(get_option('pouyawp_text_close_btn', 'بستن')); ?>"><?php echo wp_kses($this->get_sanitized_option('pouyawp_icon_close', '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>'), $this->get_allowed_svg_tags()); ?></button>
            <div class="pouyawp-welcome-content">
                <div class="pouyawp-welcome-avatar">💬</div>
                <div class="pouyawp-welcome-text">
                    <h4><?php echo esc_html(get_option('pouyawp_chat_header_title', 'پشتیبانی هوشمند')); ?></h4>
                    <p><?php echo esc_html(get_option('pouyawp_chat_welcome_message', 'سلام! 👋 چطور می‌تونم کمکتون کنم؟')); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <div id="pouyawp-chat-container">
            <div class="pouyawp-chat-header">
                <div class="pouyawp-chat-title-section">
                     <h3><?php echo esc_html(get_option('pouyawp_chat_header_title', 'پشتیبانی هوشمند')); ?></h3>
                     <div class="pouyawp-chat-status">
                         <span class="pouyawp-status-dot"></span> <?php echo esc_html(get_option('pouyawp_text_online_status', 'آنلاین')); ?>
                     </div>
                </div>
                <div class="pouyawp-chat-controls">
                    <?php if (get_option('pouyawp_chat_operator_button_enabled', 'on') === 'on'): ?>
                    <button id="pouyawp-contact-operator-btn" title="<?php echo esc_attr(get_option('pouyawp_text_operator_btn_tooltip', 'ارتباط با اپراتور')); ?>">
                        <span class="button-icon"><?php echo wp_kses($this->get_sanitized_option('pouyawp_icon_operator'), $this->get_allowed_svg_tags()); ?></span>
                        <span id="oprator-button" class="button-text"><?php echo esc_html(get_option('pouyawp_text_operator_btn', 'اپراتور')); ?></span>
                    </button>
                    <?php endif; ?>
                    <button id="pouyawp-chat-close" title="<?php echo esc_attr(get_option('pouyawp_text_close_btn', 'بستن چت')); ?>">
                        <span class="button-icon"><?php echo wp_kses($this->get_sanitized_option('pouyawp_icon_close'), $this->get_allowed_svg_tags()); ?></span>
                        <span id="closely-button"class="button-text"><?php echo esc_html(get_option('pouyawp_text_close_btn', 'بستن')); ?></span>
                    </button>
                </div>
            </div>
            <div id="pouyawp-chat-messages" class="pouyawp-chat-messages"></div>
            <div class="pouyawp-chat-input-container">
                <textarea id="pouyawp-chat-input" placeholder="<?php echo esc_attr(get_option('pouyawp_text_input_placeholder', 'سوال خود را بپرسید...')); ?>" rows="1"></textarea>
                <button id="pouyawp-chat-send" title="<?php echo esc_attr(get_option('pouyawp_text_send_btn_tooltip', 'ارسال پیام')); ?>">
                    <span class="button-icon"><?php echo wp_kses($this->get_sanitized_option('pouyawp_icon_send'), $this->get_allowed_svg_tags()); ?></span>
                    <span id="senderk-button"class="button-text"><?php echo esc_html(get_option('pouyawp_text_send_btn', 'ارسال')); ?></span>
                </button>
            </div>
        </div>
        <?php
    }

    public function handle_chat() {
        check_ajax_referer('pouyawp_ai_nonce', 'nonce');
        $user_message = sanitize_textarea_field($_POST['message']);
        $session_id = sanitize_text_field($_POST['session_id']);
        $this->log_message($session_id, 'user', $user_message);
        $response_text = $this->call_ai_api($user_message);
        $this->log_message($session_id, 'bot', $response_text);
        wp_send_json_success(['response' => $response_text]);
    }

    public function handle_contact_operator() {
        check_ajax_referer('pouyawp_ai_nonce', 'nonce');
        $session_id = sanitize_text_field($_POST['session_id']);
        $this->log_message($session_id, 'system', "کاربر درخواست ارتباط با اپراتور را دارد.", 'operator_request');
        $user_notification = get_option('pouyawp_text_operator_confirm_msg', 'درخواست شما برای اپراتور ارسال شد. لطفاً سوال خود را بپرسید، به زودی پاسخگو خواهیم بود.');
        wp_send_json_success(['message' => $user_notification]);
    }

    public function handle_operator_send_message() {
        if (!current_user_can('manage_options')) { wp_send_json_error('Forbidden'); }
        check_ajax_referer('pouyawp_operator_nonce', 'nonce');
        $session_id = sanitize_text_field($_POST['session_id']);
        $message = sanitize_textarea_field($_POST['message']);
        if (empty($message) || empty($session_id)) { wp_send_json_error('Empty message or session.'); }
        $this->log_message($session_id, 'operator', $message);
        wp_send_json_success();
    }

    public function handle_check_for_messages() {
        check_ajax_referer('pouyawp_ai_nonce', 'nonce');
        global $wpdb;
        $table_name = $wpdb->prefix . 'pouyawp_chat_history';
        $session_id = sanitize_text_field($_POST['session_id']);
        $last_id = absint($_POST['last_id']);
        $new_messages = $wpdb->get_results($wpdb->prepare(
            "SELECT id, sender, message FROM $table_name WHERE session_id = %s AND id > %d AND sender = 'operator' ORDER BY id ASC",
            $session_id, $last_id
        ));
        wp_send_json_success($new_messages);
    }
    
    private function log_message($session_id, $sender, $message, $status = 'none') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'pouyawp_chat_history';
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
        $wpdb->insert($table_name, [
            'session_id' => $session_id, 'ip_address' => $ip_address, 'user_agent' => $user_agent,
            'sender' => $sender, 'message' => $message, 'status' => $status, 'timestamp' => current_time('mysql'),
        ], ['%s', '%s', '%s', '%s', '%s', '%s', '%s']);
        return $wpdb->insert_id;
    }

    private function call_ai_api($user_message) {
        $api_key = get_option('pouyawp_ai_api_key');
        if(empty($api_key)){
            return 'خطا: کلید API در تنظیمات پلاگین وارد نشده است.';
        }
        
        $api_url = 'https://openrouter.ai/api/v1/chat/completions';
        $model = get_option('pouyawp_ai_model', 'google/gemini-2.0-flash-exp:free');
        $tone = get_option('pouyawp_ai_tone', 'دوستانه و کمک‌کننده');
        $site_info = get_option('pouyawp_ai_site_info', '');
        $products = get_option('pouyawp_ai_products', '');
        $system_prompt = "شما یک دستیار پشتیبانی باهوش با لحن {$tone} هستید. همیشه از ایموجی‌های مرتبط استفاده کن. اطلاعات سایت: {$site_info}. محصولات: {$products}. همیشه به زبان فارسی روان پاسخ بده.";
        
        $postData = [
            'model' => $model,
            'messages' => [['role' => 'system', 'content' => $system_prompt], ['role' => 'user', 'content' => $user_message]]
        ];

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key,
            'HTTP-Referer: ' . home_url(),
            'X-Title: ' . get_bloginfo('name') . ' Chatina'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        
        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200) {
            $error_message = $curlError ? 'cURL Error: ' . $curlError : 'HTTP Status: ' . $httpCode . ' - Response: ' . $responseBody;
            error_log('Chatina Direct Connection Error: ' . $error_message);
            return 'متاسفانه در حال حاضر امکان پاسخگویی وجود ندارد. لطفاً دقایقی دیگر تلاش کنید. (کد: ' . $httpCode . ')';
        }
        
        $body = json_decode($responseBody, true);
        if (isset($body['choices'][0]['message']['content'])) {
            return $body['choices'][0]['message']['content'];
        } elseif (isset($body['error']['message'])) {
             error_log('Chatina API Error from Service: ' . $body['error']['message']);
             return 'سرویس هوش مصنوعی یک خطا برگرداند: ' . $body['error']['message'];
        } else {
            return 'پاسخ دریافتی از هوش مصنوعی معتبر نبود.';
        }
    }

    private function parse_user_agent($ua_string) {
        $os = 'Unknown OS';
        if (preg_match('/windows nt 10/i', $ua_string)) $os = 'Windows 10/11';
        elseif (preg_match('/windows nt 6.3/i', $ua_string)) $os = 'Windows 8.1';
        elseif (preg_match('/windows nt 6.2/i', $ua_string)) $os = 'Windows 8';
        elseif (preg_match('/windows nt 6.1/i', $ua_string)) $os = 'Windows 7';
        elseif (preg_match('/macintosh|mac os x/i', $ua_string)) $os = 'macOS';
        elseif (preg_match('/android/i', $ua_string)) $os = 'Android';
        elseif (preg_match('/iphone/i', $ua_string)) $os = 'iPhone';
        elseif (preg_match('/linux/i', $ua_string)) $os = 'Linux';
        $browser = 'Unknown Browser';
        if (preg_match('/firefox/i', $ua_string)) $browser = 'Firefox';
        elseif (preg_match('/edg/i', $ua_string)) $browser = 'Edge';
        elseif (preg_match('/chrome/i', $ua_string) && !preg_match('/edg/i', $ua_string)) $browser = 'Chrome';
        elseif (preg_match('/safari/i', $ua_string) && !preg_match('/chrome/i', $ua_string)) $browser = 'Safari';
        elseif (preg_match('/opera|opr/i', $ua_string)) $browser = 'Opera';
        return "$os, $browser";
    }

    public function admin_menu() {
        add_menu_page('چتینا', 'چتینا', 'manage_options', 'pouyawp-ai-support', [$this, 'render_settings_page'],'dashicons-format-chat', 25);
        add_submenu_page('pouyawp-ai-support', 'تاریخچه چت', 'تاریخچه چت', 'manage_options', 'pouyawp-chat-history', [$this, 'render_history_page']);
    }

    public function render_history_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'pouyawp_chat_history';
        $conversations = $wpdb->get_results("SELECT DISTINCT session_id, ip_address, user_agent, MAX(timestamp) as last_message_time, MAX(CASE WHEN status = 'operator_request' THEN 1 ELSE 0 END) as needs_attention FROM $table_name GROUP BY session_id ORDER BY last_message_time DESC");
        ?>
        <div class="wrap">
            <h1><span class="dashicons dashicons-list-view"></span> تاریخچه گفتگوهای کاربران</h1>
            <?php if (empty($conversations)): ?>
                <p>هنوز هیچ گفتگویی ثبت نشده است.</p>
            <?php else: foreach ($conversations as $convo): ?>
                <details class="pouya-history-item" data-session="<?php echo esc_attr($convo->session_id); ?>">
                    <summary>
                        <span>گفتگو با: <code style="direction: ltr;"><?php echo esc_html($this->parse_user_agent($convo->user_agent)); ?> (IP: <?php echo esc_html($convo->ip_address); ?>)</code></span>
                        <span><?php echo esc_html($convo->last_message_time); ?></span>
                        <?php if ($convo->needs_attention): ?><strong class="pouya-needs-attention">🚨 نیاز به بررسی اپراتور</strong><?php endif; ?>
                    </summary>
                    <div class="pouya-history-content">
                        <?php $messages = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE session_id = %s ORDER BY timestamp ASC", $convo->session_id));
                        foreach ($messages as $msg) {
                             $sender_style = 'user';
                             if ($msg->sender === 'bot') $sender_style = 'bot';
                             if ($msg->sender === 'operator') $sender_style = 'operator';
                             if ($msg->sender === 'system') $sender_style = 'system';
                             echo '<div class="pouya-history-message msg-'.$sender_style.'"><strong>' . esc_html(ucfirst($msg->sender)) . ':</strong> ' . nl2br(esc_html($msg->message)) . '<small>' . esc_html($msg->timestamp) . '</small></div>';
                        }?>
                        <div class="pouya-operator-reply-box">
                            <textarea placeholder="پاسخ خود را به عنوان پشتیبان بنویسید..."></textarea>
                            <button class="button button-primary">ارسال پیام به کاربر</button>
                            <span class="spinner"></span>
                        </div>
                    </div>
                </details>
            <?php endforeach; endif; ?>
        </div>
        <style>.pouya-history-item{border:1px solid #ddd;padding:10px;margin-bottom:15px;border-radius:5px;background:#fff}.pouya-history-item summary{cursor:pointer;font-weight:700;display:flex;justify-content:space-between;align-items:center;padding:5px;}.pouya-needs-attention{color:#d63638;animation:pulse_error 2s infinite}@keyframes pulse_error{0%{transform:scale(.95);box-shadow:0 0 0 0 rgba(214,54,56,.7)}70%{transform:scale(1);box_shadow:0 0 0 10px rgba(214,54,56,0)}100%{transform:scale(.95);box-shadow:0 0 0 0 rgba(214,54,56,0)}}.pouya-history-content{margin-top:15px;border-top:1px dashed #eee;padding-top:10px}.pouya-history-message{padding:8px 12px;border-radius:5px;margin-bottom:8px;border-right:3px solid}.pouya-history-message.msg-user{background-color:#e1f5fe;border-color:#0288d1}.pouya-history-message.msg-bot{background-color:#f1f8e9;border-color:#7cb342}.pouya-history-message.msg-operator{background-color:#fff3e0;border-color:#fb8c00}.pouya-history-message.msg-system{background-color:#eee;border-color:#666;text-align:center}.pouya-history-message small{display:block;text-align:left;opacity:.7;font-size:11px;margin-top:5px}.pouya-operator-reply-box{margin-top:20px;display:flex;gap:10px}.pouya-operator-reply-box textarea{width:100%}</style>
        <script>
        jQuery(document).ready(function($){
            $('.pouya-operator-reply-box button').on('click', function(e){
                e.preventDefault();
                var $this = $(this);
                var $box = $this.closest('.pouya-operator-reply-box');
                var $textarea = $box.find('textarea');
                var message = $textarea.val();
                var session_id = $this.closest('.pouya-history-item').data('session');
                if(!message.trim()) { alert('پیام خالی است!'); return; }
                $this.prop('disabled', true).siblings('.spinner').addClass('is-active');
                $.post(ajaxurl, {
                    action: 'pouyawp_operator_send_message',
                    nonce: '<?php echo wp_create_nonce("pouyawp_operator_nonce"); ?>',
                    session_id: session_id,
                    message: message
                }).done(function(){
                    $textarea.val('');
                    var sentMessageHtml = '<div class="pouya-history-message msg-operator"><strong>Operator:</strong> ' + message.replace(/</g, "&lt;").replace(/>/g, "&gt;") + '<small>همین الان</small></div>';
                    $box.before(sentMessageHtml);
                }).fail(function(){
                    alert('خطا در ارسال پیام.');
                }).always(function(){
                    $this.prop('disabled', false).siblings('.spinner').removeClass('is-active');
                });
            });
        });
        </script>
        <?php
    }

    public function admin_init() {
        $settings_to_register = [
            'pouyawp_chat_header_title' => 'sanitize_text_field', 'pouyawp_chat_welcome_message' => 'sanitize_textarea_field', 'pouyawp_chat_button_style' => 'sanitize_text_field', 
            'pouyawp_text_main_btn_tooltip' => 'sanitize_text_field', 'pouyawp_text_online_status' => 'sanitize_text_field', 'pouyawp_text_input_placeholder' => 'sanitize_text_field', 
            'pouyawp_text_initial_bot' => 'sanitize_textarea_field', 'pouyawp_text_operator_btn' => 'sanitize_text_field', 'pouyawp_text_operator_btn_tooltip' => 'sanitize_text_field',
            'pouyawp_text_operator_confirm_msg' => 'sanitize_textarea_field', 'pouyawp_text_close_btn' => 'sanitize_text_field', 'pouyawp_text_send_btn' => 'sanitize_text_field', 
            'pouyawp_text_send_btn_tooltip' => 'sanitize_text_field', 'pouyawp_ai_base_url' => 'esc_url_raw', 'pouyawp_ai_api_key' => 'sanitize_text_field', 
            'pouyawp_ai_model' => 'sanitize_text_field', 'pouyawp_ai_tone' => 'sanitize_text_field', 'pouyawp_ai_site_info' => 'sanitize_textarea_field', 'pouyawp_ai_products' => 'sanitize_textarea_field'
        ];
        foreach ($settings_to_register as $option_name => $sanitize_callback) {
            register_setting('pouyawp_ai_settings', $option_name, ['sanitize_callback' => $sanitize_callback]);
        }
        $checkboxes = ['pouyawp_chat_welcome_enabled', 'pouyawp_chat_operator_button_enabled'];
        foreach($checkboxes as $cb){
            register_setting('pouyawp_ai_settings', $cb, ['sanitize_callback' => function($val){ return $val === 'on' ? 'on' : ''; }]);
        }
        $svg_icons = ['pouyawp_icon_main_chat', 'pouyawp_icon_operator', 'pouyawp_icon_close', 'pouyawp_icon_send'];
        foreach ($svg_icons as $icon) {
            register_setting('pouyawp_ai_settings', $icon, ['sanitize_callback' => [$this, 'sanitize_svg_callback']]);
        }
    }

    public function sanitize_svg_callback($input) { return wp_kses($input, $this->get_allowed_svg_tags()); }

    private function get_allowed_svg_tags() { return ['svg'=>['class'=>true,'xmlns'=>true,'width'=>true,'height'=>true,'viewbox'=>true,'fill'=>true,'stroke'=>true,'stroke-width'=>true,'stroke-linecap'=>true,'stroke-linejoin'=>true,'style'=>true],'path'=>['d'=>true,'fill'=>true],'circle'=>['cx'=>true,'cy'=>true,'r'=>true,'fill'=>true],'line'=>['x1'=>true,'y1'=>true,'x2'=>true,'y2'=>true],'polygon'=>['points'=>true]]; }

    private function get_sanitized_option($name, $default = '') { return get_option($name, $default); }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><span class="dashicons dashicons-admin-generic"></span> تنظیمات چت هوشمند چتینا</h1>
            <form method="post" action="options.php" novalidate="novalidate">
                <?php settings_fields('pouyawp_ai_settings'); ?>
                <h2 class="nav-tab-wrapper"><a href="#general-settings" class="nav-tab nav-tab-active">تنظیمات عمومی</a><a href="#customization-settings" class="nav-tab">شخصی‌سازی</a><a href="#api-settings" class="nav-tab">تنظیمات API</a></h2>
                <div id="general-settings" class="tab-content active">
                    <table class="form-table">
                        <tr><th scope="row"><label for="pouyawp_chat_header_title">عنوان هدر چت</label></th><td><input type="text" id="pouyawp_chat_header_title" name="pouyawp_chat_header_title" value="<?php echo esc_attr(get_option('pouyawp_chat_header_title', 'پشتیبانی هوشمند')); ?>" class="regular-text" /></td></tr>
                        <tr><th scope="row">پیام خوش‌آمدگویی</th><td><label><input type="checkbox" name="pouyawp_chat_welcome_enabled" <?php checked(get_option('pouyawp_chat_welcome_enabled', 'on'), 'on'); ?> /> فعال کردن پاپ‌آپ</label><br><textarea name="pouyawp_chat_welcome_message" rows="3" class="large-text"><?php echo esc_textarea(get_option('pouyawp_chat_welcome_message', 'سلام! 👋 چطور می‌تونم کمکتون کنم؟')); ?></textarea></td></tr>
                        <tr><th scope="row"><label for="pouyawp_chat_button_style">استایل دکمه‌ها</label></th><td><select id="pouyawp_chat_button_style" name="pouyawp_chat_button_style"><option value="icon_text" <?php selected(get_option('pouyawp_chat_button_style'), 'icon_text'); ?>>آیکون و متن</option><option value="icon_only" <?php selected(get_option('pouyawp_chat_button_style'), 'icon_only'); ?>>فقط آیکون</option><option value="text_only" <?php selected(get_option('pouyawp_chat_button_style'), 'text_only'); ?>>فقط متن</option></select></td></tr>
                        <tr><th scope="row">قابلیت‌های تعاملی</th><td><label><input type="checkbox" name="pouyawp_chat_operator_button_enabled" <?php checked(get_option('pouyawp_chat_operator_button_enabled', 'on'), 'on'); ?> /> فعال کردن دکمه اپراتور</label></td></tr>
                    </table>
                </div>
                <div id="customization-settings" class="tab-content">
                    <h3>شخصی‌سازی متون</h3>
                    <table class="form-table">
                         <tr><th scope="row"><label for="pouyawp_text_main_btn_tooltip">تولتیپ دکمه اصلی</label></th><td><input type="text" name="pouyawp_text_main_btn_tooltip" value="<?php echo esc_attr(get_option('pouyawp_text_main_btn_tooltip', 'شروع گفتگو')); ?>" class="regular-text" /></td></tr>
                         <tr><th scope="row"><label for="pouyawp_text_online_status">متن وضعیت آنلاین</label></th><td><input type="text" name="pouyawp_text_online_status" value="<?php echo esc_attr(get_option('pouyawp_text_online_status', 'آنلاین')); ?>" class="regular-text" /></td></tr>
                         <tr><th scope="row"><label for="pouyawp_text_input_placeholder">متن فیلد ورودی</label></th><td><input type="text" name="pouyawp_text_input_placeholder" value="<?php echo esc_attr(get_option('pouyawp_text_input_placeholder', 'سوال خود را بپرسید...')); ?>" class="regular-text" /></td></tr>
                         <tr><th scope="row"><label for="pouyawp_text_initial_bot">اولین پیام ربات</label></th><td><textarea name="pouyawp_text_initial_bot" rows="2" class="large-text"><?php echo esc_textarea(get_option('pouyawp_text_initial_bot', 'سلام! من دستیار هوشمند هستم 😊 چطور میتونم کمکت کنم؟')); ?></textarea></td></tr>
                         <tr><th scope="row"><label for="pouyawp_text_operator_btn">متن دکمه اپراتور</label></th><td><input type="text" name="pouyawp_text_operator_btn" value="<?php echo esc_attr(get_option('pouyawp_text_operator_btn', 'اپراتور')); ?>" class="regular-text" /></td></tr>
                         <tr><th scope="row"><label for="pouyawp_text_operator_btn_tooltip">تولتیپ دکمه اپراتور</label></th><td><input type="text" name="pouyawp_text_operator_btn_tooltip" value="<?php echo esc_attr(get_option('pouyawp_text_operator_btn_tooltip', 'ارتباط با اپراتور')); ?>" class="regular-text" /></td></tr>
                         <tr><th scope="row"><label for="pouyawp_text_operator_confirm_msg">پیام تایید درخواست اپراتور</label></th><td><textarea name="pouyawp_text_operator_confirm_msg" rows="2" class="large-text"><?php echo esc_textarea(get_option('pouyawp_text_operator_confirm_msg', 'درخواست شما برای اپراتور ارسال شد. به زودی پاسخگو خواهیم بود.')); ?></textarea></td></tr>
                         <tr><th scope="row"><label for="pouyawp_text_close_btn">متن دکمه بستن</label></th><td><input type="text" name="pouyawp_text_close_btn" value="<?php echo esc_attr(get_option('pouyawp_text_close_btn', 'بستن')); ?>" class="regular-text" /></td></tr>
                         <tr><th scope="row"><label for="pouyawp_text_send_btn">متن دکمه ارسال</label></th><td><input type="text" name="pouyawp_text_send_btn" value="<?php echo esc_attr(get_option('pouyawp_text_send_btn', 'ارسال')); ?>" class="regular-text" /></td></tr>
                         <tr><th scope="row"><label for="pouyawp_text_send_btn_tooltip">تولتیپ دکمه ارسال</label></th><td><input type="text" name="pouyawp_text_send_btn_tooltip" value="<?php echo esc_attr(get_option('pouyawp_text_send_btn_tooltip', 'ارسال پیام')); ?>" class="regular-text" /></td></tr>
                    </table><hr><h3>شخصی‌سازی آیکون‌ها (کد SVG)</h3>
                    <table class="form-table">
                        <tr><th scope="row"><label for="pouyawp_icon_main_chat">آیکون دکمه اصلی</label></th><td><textarea name="pouyawp_icon_main_chat" rows="4" class="large-text"><?php echo esc_textarea($this->get_sanitized_option('pouyawp_icon_main_chat', '<svg width="32" height="32" viewBox="0 0 24 24" fill="white"><path d="M20 2H4C2.9 2 2 2.9 2 4V22L6 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2Z"/></svg>')); ?></textarea></td></tr>
                        <tr><th scope="row"><label for="pouyawp_icon_operator">آیکون دکمه اپراتور</label></th><td><textarea name="pouyawp_icon_operator" rows="4" class="large-text"><?php echo esc_textarea($this->get_sanitized_option('pouyawp_icon_operator', '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12.75c1.63 0 3.07.39 4.24.9c1.23.52 2.26 1.32 2.76 2.36A3.003 3.003 0 0 1 16.5 21H7.5a3 3 0 0 1-2.5-4.99c.5-.98 1.5-1.78 2.75-2.3C8.92 13.13 10.37 12.75 12 12.75M12 6a3.5 3.5 0 1 1 0 7a3.5 3.5 0 0 1 0-7z"/></svg>')); ?></textarea></td></tr>
                        <tr><th scope="row"><label for="pouyawp_icon_close">آیکون دکمه بستن</label></th><td><textarea name="pouyawp_icon_close" rows="4" class="large-text"><?php echo esc_textarea($this->get_sanitized_option('pouyawp_icon_close', '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>')); ?></textarea></td></tr>
                        <tr><th scope="row"><label for="pouyawp_icon_send">آیکون دکمه ارسال</label></th><td><textarea name="pouyawp_icon_send" rows="4" class="large-text"><?php echo esc_textarea($this->get_sanitized_option('pouyawp_icon_send', '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>')); ?></textarea></td></tr>
                    </table>
                </div>
                <div id="api-settings" class="tab-content">
                     <h3>تنظیمات API</h3>
                     <table class="form-table">
                        <tr><th scope="row"><label for="pouyawp_ai_base_url">آدرس API یا پراکسی</label></th><td><input type="url" name="pouyawp_ai_base_url" value="<?php echo esc_attr(get_option('pouyawp_ai_base_url', '')); ?>" class="regular-text" /><p class="description">برای اتصال مستقیم، این فیلد را خالی بگذارید و کلید API را در فیلد بعدی وارد کنید.</p></td></tr>
                        <tr><th scope="row"><label for="pouyawp_ai_api_key">کلید API</label></th><td><input type="password" name="pouyawp_ai_api_key" value="<?php echo esc_attr(get_option('pouyawp_ai_api_key', '')); ?>" class="regular-text" /></td></tr>
                        <tr><th scope="row"><label for="pouyawp_ai_model">مدل زبان</label></th><td><input type="text" name="pouyawp_ai_model" value="<?php echo esc_attr(get_option('pouyawp_ai_model', 'mistralai/mistral-7b-instruct:free')); ?>" class="regular-text" /><p class="description">شناسه دقیق مدل از سایت OpenRouter را وارد کنید.</p></td></tr>
                        <tr><th scope="row"><label for="pouyawp_ai_tone">لحن مکالمه</label></th><td><select name="pouyawp_ai_tone"><option value="دوستانه و کمک‌کننده" <?php selected(get_option('pouyawp_ai_tone'), 'دوستانه و کمک‌کننده'); ?>>دوستانه</option><option value="رسمی و حرفه‌ای" <?php selected(get_option('pouyawp_ai_tone'), 'رسمی و حرفه‌ای'); ?>>رسمی</option></select></td></tr>
                        <tr><th scope="row"><label for="pouyawp_ai_site_info">اطلاعات سایت (برای AI)</label></th><td><textarea name="pouyawp_ai_site_info" rows="4" class="large-text"><?php echo esc_textarea(get_option('pouyawp_ai_site_info')); ?></textarea></td></tr>
                        <tr><th scope="row"><label for="pouyawp_ai_products">محصولات و خدمات (برای AI)</label></th><td><textarea name="pouyawp_ai_products" rows="6" class="large-text"><?php echo esc_textarea(get_option('pouyawp_ai_products')); ?></textarea></td></tr>
                     </table>
                </div>
                <?php submit_button('ذخیره تمام تنظیمات'); ?>
            </form>
            <style>.tab-content{display:none;margin-top:-1px}.tab-content.active{display:block;padding:20px;border:1px solid #ddd;background:#fff}.nav-tab-wrapper{margin-bottom:0}</style>
            <script>jQuery(document).ready(function(a){a(".nav-tab-wrapper a").click(function(b){b.preventDefault(),a(".nav-tab-wrapper a").removeClass("nav-tab-active"),a(this).addClass("nav-tab-active"),a(".tab-content").removeClass("active"),a(a(this).attr("href")).addClass("active")})});</script>
        </div>
        <?php
    }
}
new PouyaWPAISupportDirectFinal();