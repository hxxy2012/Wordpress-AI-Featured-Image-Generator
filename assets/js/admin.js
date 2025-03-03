/**
 * Admin JavaScript for AI Featured Image Generator
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Handle generate button click
        $('.aifig-generate-button').on('click', function(e) {
            e.preventDefault();
            
            var button = $(this);
            var postId = button.data('post-id');
            var spinner = button.next('.aifig-spinner');
            var resultMessage = button.closest('.aifig-meta-box').find('.aifig-result-message');
            
            // Disable button and show spinner
            button.prop('disabled', true).text(aifig_data.generating_text);
            spinner.addClass('is-active');
            resultMessage.removeClass('aifig-success aifig-error').hide();
            
            // AJAX request to generate image
            $.ajax({
                url: aifig_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'aifig_generate_image',
                    nonce: aifig_data.nonce,
                    post_id: postId
                },
                success: function(response) {
                    if (response.success) {
                        // Update featured image area with new image
                        if (response.data.thumbnail_html) {
                            $('#postimagediv .inside').html(response.data.thumbnail_html);
                        }
                        
                        // Show success message
                        resultMessage.addClass('aifig-success')
                            .text(response.data.message)
                            .show();
                        
                        // Add preview if there wasn't one before
                        if (!$('.aifig-thumbnail-preview').length) {
                            var previewHtml = $('<div class="aifig-thumbnail-preview"><img src="' + response.data.image_url + '" /></div>');
                            button.closest('.aifig-meta-box').prepend(previewHtml);
                        } else {
                            // Update existing preview
                            $('.aifig-thumbnail-preview img').attr('src', response.data.image_url);
                        }
                    } else {
                        // Show error message
                        resultMessage.addClass('aifig-error')
                            .text(response.data.message || aifig_data.error_text)
                            .show();
                    }
                },
                error: function() {
                    resultMessage.addClass('aifig-error')
                        .text(aifig_data.error_text)
                        .show();
                },
                complete: function() {
                    // Reset button and hide spinner
                    button.prop('disabled', false).text(aifig_data.generate_text);
                    spinner.removeClass('is-active');
                }
            });
        });
    });
})(jQuery);