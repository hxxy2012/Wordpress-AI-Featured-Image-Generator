<?php
/**
 * AI Image Generator Class
 * Handles API interactions and image processing
 */
class AI_Image_Generator {

    /**
     * Initialize the class
     */
    public function __construct() {
        add_action('save_post', array($this, 'maybe_generate_featured_image'), 10, 3);
    }

    /**
     * Check if we should generate a featured image for a post
     *
     * @param int $post_id The post ID
     * @param WP_Post $post The post object
     * @param bool $update Whether this is an update
     */
    public function maybe_generate_featured_image($post_id, $post, $update) {
        // Skip if auto-generate is disabled
        if (!get_option('aifig_auto_generate', false)) {
            return;
        }

        // Skip if post already has featured image, is a revision, or auto-draft
        if (has_post_thumbnail($post_id) || 
            wp_is_post_revision($post_id) || 
            wp_is_post_autosave($post_id) || 
            $post->post_status == 'auto-draft') {
            return;
        }

        // Skip if not a post or page (can be customized)
        if (!in_array($post->post_type, array('post', 'page'))) {
            return;
        }

        // Generate image based on post title
        $this->generate_featured_image_for_post($post_id);
    }

    /**
     * Generate a featured image for a post
     *
     * @param int $post_id The post ID
     * @return bool|array Success status and result data
     */
    public function generate_featured_image_for_post($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }

        // Get post title for image generation
        $title = get_the_title($post_id);
        if (empty($title)) {
            return false;
        }

        // Generate image from API
        $image_url = $this->generate_ai_image($title);
        if (!$image_url) {
            return false;
        }

        // Process the image and set as featured image
        $result = $this->set_featured_image($post_id, $image_url, $title);
        return $result;
    }

    /**
     * Generate an AI image using the API
     *
     * @param string $content The content to generate image from
     * @return string|bool Image URL or false on failure
     */
    public function generate_ai_image($content) {
        // Sanitize content
        $content = sanitize_text_field($content);
        $content = str_replace('.txt', '', $content);
        
        // Create prompt for AI
        $prompt = "帮我画出一张" . $content . "的高清图片，图片中不要出现文字乱码。";
        
        // Get API key and URL from settings
        $api_key = get_option('aifig_api_key', AIFIG_DEFAULT_API_KEY);
        $api_url = get_option('aifig_api_url', AIFIG_DEFAULT_API_URL);
        
        // Set up API request
        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'body' => wp_json_encode(array(
                'model' => 'wanxiang',
                'prompt' => $prompt,
            )),
            'timeout' => 60,
        ));

        // Check for errors
        if (is_wp_error($response)) {
            error_log('AI Image Generator Error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if (200 !== $response_code) {
            error_log('AI Image Generator Error: ' . wp_remote_retrieve_body($response));
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['data'][0]['url'])) {
            return $body['data'][0]['url'];
        }

        return false;
    }

    /**
     * Download and set featured image for a post
     *
     * @param int $post_id The post ID
     * @param string $image_url The image URL
     * @param string $title The image title
     * @return array|bool The upload result or false
     */
    public function set_featured_image($post_id, $image_url, $title) {
        // Download and upload the image
        $upload = $this->download_and_upload_image($image_url, $title);
        
        if (!$upload) {
            return false;
        }

        // Set as featured image
        set_post_thumbnail($post_id, $upload['id']);
        
        return $upload;
    }

    /**
     * Download image and upload to WordPress media library
     *
     * @param string $image_url Remote image URL
     * @param string $title Image title
     * @return array|bool Upload info or false
     */
    private function download_and_upload_image($image_url, $title) {
        // Make sure we have the required file functions
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        // Download file to temp location
        $temp_file = download_url($image_url);
        
        if (is_wp_error($temp_file)) {
            error_log('AI Image Generator - Download Error: ' . $temp_file->get_error_message());
            return false;
        }
        
        // Prepare file data for upload
        $file_array = array(
            'name' => sanitize_file_name($title . '-ai-generated.png'),
            'tmp_name' => $temp_file,
            'error' => 0,
            'size' => filesize($temp_file),
        );
        
        // Move the temporary file into the uploads directory
        $result = wp_handle_sideload($file_array, array('test_form' => false));
        
        if (isset($result['error'])) {
            @unlink($file_array['tmp_name']);
            error_log('AI Image Generator - Upload Error: ' . $result['error']);
            return false;
        }
        
        // Insert as attachment
        $attachment = array(
            'post_mime_type' => $result['type'],
            'post_title' => sanitize_text_field($title . ' - AI Generated Image'),
            'post_content' => '',
            'post_status' => 'inherit',
            'guid' => $result['url']
        );
        
        $attachment_id = wp_insert_attachment($attachment, $result['file']);
        
        if (is_wp_error($attachment_id)) {
            error_log('AI Image Generator - Attachment Error: ' . $attachment_id->get_error_message());
            return false;
        }
        
        // Generate and update attachment metadata
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $result['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);
        
        return array(
            'id' => $attachment_id,
            'url' => $result['url'],
        );
    }
}