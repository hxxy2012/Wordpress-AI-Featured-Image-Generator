<?php
/**
 * Admin Class
 * Handles admin interface and AJAX functionality
 */
class AI_Featured_Image_Generator_Admin {
    
    /**
     * The AI image generator instance
     */
    private $generator;

    /**
     * Initialize the admin functionality
     *
     * @param AI_Image_Generator $generator The generator instance
     */
    public function __construct($generator) {
        $this->generator = $generator;
        
        // Admin menu and settings
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Meta box for post editor
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        
        // AJAX handlers
        add_action('wp_ajax_aifig_generate_image', array($this, 'ajax_generate_image'));
        
        // Admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Add the admin menu item
     */
    public function add_admin_menu() {
        add_options_page(
            __('AI 特色图像生成器', 'ai-featured-image-generator'),
            __('AI 特色图像', 'ai-featured-image-generator'),
            'manage_options',
            'ai-featured-image-generator',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Register the settings
     */
    public function register_settings() {
        register_setting('aifig_settings', 'aifig_auto_generate', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ));
        
        register_setting('aifig_settings', 'aifig_api_key', array(
            'type' => 'string',
            'default' => AIFIG_DEFAULT_API_KEY,
            'sanitize_callback' => 'sanitize_text_field',
        ));
        
        register_setting('aifig_settings', 'aifig_api_url', array(
            'type' => 'string',
            'default' => AIFIG_DEFAULT_API_URL,
            'sanitize_callback' => 'sanitize_url',
        ));
        
        add_settings_section(
            'aifig_main_section',
            __('主要设置', 'ai-featured-image-generator'),
            function() {
                echo '<p>' . __('配置AI特色图像生成器的设置。', 'ai-featured-image-generator') . '</p>';
            },
            'aifig_settings'
        );
        
        add_settings_field(
            'aifig_auto_generate',
            __('自动生成特色图像', 'ai-featured-image-generator'),
            function() {
                $auto_generate = get_option('aifig_auto_generate', false);
                echo '<input type="checkbox" name="aifig_auto_generate" value="1" ' . checked(1, $auto_generate, false) . ' />';
                echo '<p class="description">' . __('为新发布的文章自动生成特色图像', 'ai-featured-image-generator') . '</p>';
            },
            'aifig_settings',
            'aifig_main_section'
        );
        
        add_settings_field(
            'aifig_api_url',
            __('API接口地址', 'ai-featured-image-generator'),
            function() {
                $api_url = get_option('aifig_api_url', AIFIG_DEFAULT_API_URL);
                echo '<input type="url" name="aifig_api_url" value="' . esc_attr($api_url) . '" class="regular-text" />';
                echo '<p class="description">' . __('AI图像生成API的URL地址', 'ai-featured-image-generator') . '</p>';
            },
            'aifig_settings',
            'aifig_main_section'
        );
        
        add_settings_field(
            'aifig_api_key',
            __('API密钥', 'ai-featured-image-generator'),
            function() {
                $api_key = get_option('aifig_api_key', AIFIG_DEFAULT_API_KEY);
                echo '<input type="text" name="aifig_api_key" value="' . esc_attr($api_key) . '" class="regular-text" />';
                echo '<p class="description">' . __('用于访问AI图像生成API的密钥', 'ai-featured-image-generator') . '</p>';
            },
            'aifig_settings',
            'aifig_main_section'
        );
    }
    
    /**
     * Add meta box to post editor
     */
    public function add_meta_box() {
        $post_types = array('post', 'page');
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'aifig_meta_box',
                __('AI 特色图像生成器', 'ai-featured-image-generator'),
                array($this, 'render_meta_box'),
                $post_type,
                'side',
                'default'
            );
        }
    }
    
    /**
     * AJAX handler for generating images
     */
    public function ajax_generate_image() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'aifig_generate_nonce')) {
            wp_send_json_error(array('message' => __('安全验证失败', 'ai-featured-image-generator')));
        }
        
        // Check for post ID
        if (!isset($_POST['post_id']) || empty($_POST['post_id'])) {
            wp_send_json_error(array('message' => __('缺少文章ID', 'ai-featured-image-generator')));
        }
        
        $post_id = intval($_POST['post_id']);
        
        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => __('权限不足', 'ai-featured-image-generator')));
        }
        
        // Generate image
        $result = $this->generator->generate_featured_image_for_post($post_id);
        
        if (!$result) {
            wp_send_json_error(array('message' => __('生成图像失败', 'ai-featured-image-generator')));
        }
        
        wp_send_json_success(array(
            'message' => __('特色图像生成成功', 'ai-featured-image-generator'),
            'thumbnail_html' => _wp_post_thumbnail_html($result['id'], $post_id),
            'image_url' => $result['url']
        ));
    }
    
    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook The current admin page
     */
    public function enqueue_admin_scripts($hook) {
        $screen = get_current_screen();
        
        // Load only on post edit screens or our settings page
        if (($hook == 'post.php' || $hook == 'post-new.php' || $hook == 'settings_page_ai-featured-image-generator')) {
            wp_enqueue_style('aifig-admin-style', AIFIG_PLUGIN_URL . 'assets/css/admin.css', array(), AIFIG_VERSION);
            wp_enqueue_script('aifig-admin-script', AIFIG_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), AIFIG_VERSION, true);
            
            wp_localize_script('aifig-admin-script', 'aifig_data', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aifig_generate_nonce'),
                'generating_text' => __('正在生成图像...', 'ai-featured-image-generator'),
                'generate_text' => __('生成AI特色图像', 'ai-featured-image-generator'),
                'success_text' => __('特色图像生成成功！', 'ai-featured-image-generator'),
                'error_text' => __('生成图像时发生错误。', 'ai-featured-image-generator')
            ));
        }
    }
    
    /**
     * Render the settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('aifig_settings');
                do_settings_sections('aifig_settings');
                submit_button(__('保存设置', 'ai-featured-image-generator'));
                ?>
            </form>
            
            <div class="aifig-info-box">
                <h2><?php _e('如何使用', 'ai-featured-image-generator'); ?></h2>
                <p><?php _e('本插件允许您使用AI技术从文章标题自动生成特色图像。', 'ai-featured-image-generator'); ?></p>
                <ol>
                    <li><?php _e('启用"自动生成特色图像"选项，系统将在发布新文章时自动生成并设置特色图像。', 'ai-featured-image-generator'); ?></li>
                    <li><?php _e('或者，在编辑文章时，使用侧边栏中的"AI特色图像生成器"手动生成图像。', 'ai-featured-image-generator'); ?></li>
                    <li><?php _e('您可以自定义API接口地址和API密钥以连接到不同的AI图像生成服务。', 'ai-featured-image-generator'); ?></li>
                </ol>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render the meta box
     *
     * @param WP_Post $post The post object
     */
    public function render_meta_box($post) {
        $post_id = $post->ID;
        
        // Check if post already has a featured image
        $has_thumbnail = has_post_thumbnail($post_id);
        ?>
        <div class="aifig-meta-box">
            <?php if ($has_thumbnail): ?>
                <div class="aifig-thumbnail-preview">
                    <?php echo get_the_post_thumbnail($post_id, 'thumbnail'); ?>
                </div>
            <?php endif; ?>
            
            <p>
                <button type="button" class="button button-primary aifig-generate-button" data-post-id="<?php echo esc_attr($post_id); ?>">
                    <?php _e('生成AI特色图像', 'ai-featured-image-generator'); ?>
                </button>
                <span class="spinner aifig-spinner"></span>
            </p>
            
            <div class="aifig-result-message"></div>
            
            <p class="description">
                <?php _e('点击按钮根据文章标题生成AI特色图像。', 'ai-featured-image-generator'); ?>
            </p>
        </div>
        <?php
    }
}