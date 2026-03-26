/**
 * Admin JavaScript for BS Custom Mail
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Copy to clipboard
        $('.bs-copy').on('click', function() {
            var text = $(this).data('clipboard');
            var $el = $(this);
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    $el.addClass('copied');
                    $el.text('Kopiert!');
                    setTimeout(function() {
                        $el.removeClass('copied');
                        $el.text(text);
                    }, 1500);
                });
            } else {
                // Fallback for older browsers
                var textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                
                $el.addClass('copied');
                $el.text('Kopiert!');
                setTimeout(function() {
                    $el.removeClass('copied');
                    $el.text(text);
                }, 1500);
            }
        });
        
        // Confirm actions
        $('.bs-confirm-action').on('click', function(e) {
            var message = $(this).data('confirm');
            if (message && !confirm(message)) {
                e.preventDefault();
            }
        });

        // Confirm delete
        $('.bs-confirm-delete').on('click', function(e) {
            var message = $(this).data('confirm') || 'Sind Sie sicher?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });

        // AJAX Test Email
        $('#bs-send-test-ajax').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var email = $('#test_email_ajax').val();
            var template = $button.data('template') || $('#test_template_ajax').val();
            
            if (!email) {
                alert('Bitte geben Sie eine E-Mail Adresse ein.');
                return;
            }
            
            var originalText = $button.html();
            $button.prop('disabled', true).html('<span class="dashicons dashicons-update-alt spinning" style="font-size: 16px; line-height: 1.4; margin-right: 4px;"></span> Wird gesendet...');
            
            $.ajax({
                url: bs_custom_mail_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'bs_custom_mail_send_test',
                    nonce: bs_custom_mail_ajax.nonce,
                    email: email,
                    template: template
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                    } else {
                        alert('Fehler: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.');
                },
                complete: function() {
                    $button.prop('disabled', false).html(originalText);
                }
            });
        });

        // Media Uploader for product attachments
        var mediaUploader;
        
        $('#bs_add_attachments').on('click', function(e) {
            e.preventDefault();
            
            // If the uploader object has already been created, reopen the dialog
            if (mediaUploader) {
                mediaUploader.open();
                return;
            }
            
            // Create the media uploader
            mediaUploader = wp.media({
                title: 'Dokumente auswählen',
                button: {
                    text: 'Zu Anhängen hinzufügen'
                },
                multiple: true,
                library: {
                    type: ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/*']
                }
            });
            
            // When files are selected
            mediaUploader.on('select', function() {
                var attachments = mediaUploader.state().get('selection').toJSON();
                var $list = $('#bs_attachments_list');
                
                attachments.forEach(function(attachment) {
                    // Check if already added
                    if ($list.find('[data-id="' + attachment.id + '"]').length > 0) {
                        return;
                    }
                    
                    var icon = attachment.icon || attachment.url;
                    var filename = attachment.filename || attachment.title;
                    
                    var html = '<div class="bs-attachment-item" data-id="' + attachment.id + '" style="display: flex; align-items: center; padding: 8px; background: #f9f9f9; border: 1px solid #ddd; margin-bottom: 5px; border-radius: 3px;">' +
                        '<img src="' + icon + '" alt="" style="width: 32px; height: 32px; margin-right: 10px;">' +
                        '<span style="flex: 1;">' + filename + '</span>' +
                        '<button type="button" class="button button-small bs-remove-attachment" style="color: #a00;">Entfernen</button>' +
                        '<input type="hidden" name="_bs_custom_mail_attachments[]" value="' + attachment.id + '">' +
                        '</div>';
                    
                    $list.append(html);
                });
            });
            
            mediaUploader.open();
        });
        
        // Remove attachment
        $(document).on('click', '.bs-remove-attachment', function(e) {
            e.preventDefault();
            $(this).closest('.bs-attachment-item').remove();
        });

        // Template Attachments Media Uploader
        var templateMediaUploader;
        
        $('.bs-add-template-attachments').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $list = $('#bs-template-attachments-list');
            
            // If the uploader object has already been created, reopen the dialog
            if (templateMediaUploader) {
                templateMediaUploader.open();
                return;
            }
            
            // Create the media uploader
            templateMediaUploader = wp.media({
                title: 'Template-Dokumente auswählen',
                button: {
                    text: 'Zum Template hinzufügen'
                },
                multiple: true,
                library: {
                    type: ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/*']
                }
            });
            
            // When files are selected
            templateMediaUploader.on('select', function() {
                var attachments = templateMediaUploader.state().get('selection').toJSON();
                
                attachments.forEach(function(attachment) {
                    // Check if already added
                    if ($list.find('[data-id="' + attachment.id + '"]').length > 0) {
                        return;
                    }
                    
                    var icon = attachment.icon || attachment.url;
                    var filename = attachment.filename || attachment.title;
                    var extension = filename.split('.').pop().toUpperCase();
                    var fileSize = attachment.filesizeHumanReadable || '-';
                    
                    var html = '<div class="bs-attachment-item" data-id="' + attachment.id + '">' +
                        '<img src="' + icon + '" alt="" class="bs-attachment-icon-file">' +
                        '<div class="bs-attachment-info">' +
                            '<span class="bs-attachment-name">' + filename + '</span>' +
                            '<span class="bs-attachment-meta">' + extension + ' • ' + fileSize + '</span>' +
                        '</div>' +
                        '<button type="button" class="button-link bs-remove-attachment" title="Entfernen">' +
                            '<span class="dashicons dashicons-no-alt"></span>' +
                        '</button>' +
                        '<input type="hidden" name="template_attachments[]" value="' + attachment.id + '">' +
                    '</div>';
                    
                    $list.append(html);
                });
                
                // Hide "no attachments" message
                $('.bs-no-attachments').hide();
            });
            
            templateMediaUploader.open();
        });

        // Template preview in product editor
        $('#bs_preview_template').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var template = $('#_bs_custom_mail_template').val();
            
            if (!template) {
                alert('Bitte wählen Sie zuerst ein Template aus.');
                return;
            }
            
            $button.prop('disabled', true).text('Laden...');
            
            $.ajax({
                url: bs_custom_mail_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'bs_custom_mail_preview_template',
                    nonce: bs_custom_mail_ajax.nonce,
                    template: template
                },
                success: function(response) {
                    if (response.success) {
                        // Open preview in modal or new window
                        var previewWindow = window.open('', '_blank', 'width=800,height=600,scrollbars=yes');
                        previewWindow.document.write('<!DOCTYPE html><html><head><title>Template Vorschau</title></head><body style="padding: 20px; background: #f5f5f5;">');
                        previewWindow.document.write('<h2 style="text-align: center;">Template Vorschau</h2>');
                        previewWindow.document.write(response.data.html);
                        previewWindow.document.write('</body></html>');
                        previewWindow.document.close();
                    } else {
                        alert('Fehler: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Template Vorschau');
                }
            });
        });

        // Template preview toggle
        $('.bs-toggle-preview').on('click', function(e) {
            e.preventDefault();
            var $preview = $(this).closest('.bs-template-row').find('.bs-template-preview');
            $preview.slideToggle();
        });

        // Auto-save indicator
        var $editor = $('.bs-custom-mail-editor');
        if ($editor.length) {
            var $form = $editor.find('form');
            var $inputs = $form.find('input, textarea');
            var isDirty = false;

            $inputs.on('change input', function() {
                isDirty = true;
            });

            $(window).on('beforeunload', function() {
                if (isDirty) {
                    return 'Sie haben ungespeicherte Änderungen. Möchten Sie die Seite wirklich verlassen?';
                }
            });

            $form.on('submit', function() {
                isDirty = false;
            });
        }

    });

})(jQuery);
