/**
 * Lean Admin JavaScript - Modern 2025 Interface
 */

jQuery(document).ready(function($) {
    'use strict';

    // Initialize Lean Admin Interface
    initLeanInterface();

    function initLeanInterface() {
        // Enhanced form submission
        initFormSubmission();

        // Plugin context interactions
        initPluginContexts();

        // UI enhancements
        initUIEnhancements();

        // Keyboard shortcuts
        initKeyboardShortcuts();
    }

    // Enhanced form submission with modern UX
    function initFormSubmission() {
        $('#lean-settings-form').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $saveBtn = $('#lean-save-btn');
            var originalHTML = $saveBtn.html();

            // Collect plugin contexts
            var pluginContexts = collectPluginContexts();

            // Update status
            updateSaveStatus('saving', leanAjax.strings.saving);

            // Add loading state
            $form.addClass('lean-loading');
            $saveBtn.prop('disabled', true).html('<span class="spinner"></span> ' + leanAjax.strings.saving);

            // Send AJAX request
            $.ajax({
                url: leanAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'lean_save_settings',
                    nonce: leanAjax.nonce,
                    lean_enabled: $form.find('input[name="lean_enabled"]').is(':checked') ? 1 : 0,
                    plugin_contexts: JSON.stringify(pluginContexts)
                },
                success: function(response) {
                    if (response.success) {
                        showNotification(response.data.message, 'success');
                        updateSaveStatus('saved', 'Settings saved successfully!');
                    } else {
                        showNotification(leanAjax.strings.error, 'error');
                        updateSaveStatus('error', 'Failed to save settings');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Lean save error:', error);
                    showNotification('Network error occurred', 'error');
                    updateSaveStatus('error', 'Network error');
                },
                complete: function() {
                    // Restore form state
                    $form.removeClass('lean-loading');
                    $saveBtn.prop('disabled', false).html(originalHTML);
                }
            });
        });
    }

    // Collect plugin contexts from UI
    function collectPluginContexts() {
        var pluginContexts = {};

        $('.context-checkbox:checked').each(function() {
            var $checkbox = $(this);
            var plugin = $checkbox.data('plugin');
            var context = $checkbox.data('context');

            if (!pluginContexts[plugin]) {
                pluginContexts[plugin] = { contexts: [] };
            }
            pluginContexts[plugin].contexts.push(context);
        });

        return pluginContexts;
    }

    // Plugin context interactions
    function initPluginContexts() {
        // Auto-save on context changes
        var saveTimeout;
        $(document).on('change', '.context-checkbox', function() {
            var $checkbox = $(this);
            var $card = $checkbox.closest('.lean-plugin-card');

            // Visual feedback
            animateContextChange($checkbox);

            // Update auto-optimization notice
            updateAutoOptimizationNotice($card);

            // Auto-save with debouncing
            clearTimeout(saveTimeout);
            updateSaveStatus('pending', 'Unsaved changes...');
            saveTimeout = setTimeout(function() {
                $('#lean-settings-form').trigger('submit');
            }, 1500);
        });

        // Plugin toggle button
        $(document).on('click', '.plugin-toggle-btn', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $card = $btn.closest('.lean-plugin-card');
            var $contexts = $card.find('.plugin-contexts');

            $btn.toggleClass('collapsed expanded');
            $contexts.slideToggle(300, function() {
                var isVisible = $contexts.is(':visible');
                $btn.find('.toggle-icon').text(isVisible ? '▼' : '▶');
                $btn.toggleClass('collapsed', !isVisible).toggleClass('expanded', isVisible);
            });
        });

        // Initialize all plugin cards as collapsed
        $('.plugin-contexts').hide();
        $('.plugin-toggle-btn').addClass('collapsed').find('.toggle-icon').text('▶');

        // Add click handler for entire plugin card header (optional enhancement)
        $(document).on('click', '.plugin-card-header', function(e) {
            // Only toggle if not clicking on the toggle button itself
            if (!$(e.target).closest('.plugin-toggle-btn').length) {
                $(this).find('.plugin-toggle-btn').click();
            }
        });

        // Context option hover effects
        $(document).on('mouseenter', '.context-option', function() {
            $(this).addClass('hover');
        }).on('mouseleave', '.context-option', function() {
            $(this).removeClass('hover');
        });
    }

    // Clear debug log
    $(document).on('click', '.lean-clear-debug', function() {
        if (confirm('Are you sure you want to clear the debug log?')) {
            $.ajax({
                url: leanAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'lean_clear_debug_log',
                    nonce: leanAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        showNotification('Failed to clear debug log', 'error');
                    }
                },
                error: function() {
                    showNotification('Network error', 'error');
                }
            });
        }
    });

    // UI enhancements
    function initUIEnhancements() {
        // Smooth scrolling for anchor links
        $('a[href^="#"]').on('click', function(e) {
            var target = $(this.getAttribute('href'));
            if (target.length) {
                e.preventDefault();
                $('html, body').animate({
                    scrollTop: target.offset().top - 100
                }, 500);
            }
        });

        // Enhanced tooltips
        initTooltips();

        // Animate cards on load
        animateCardsOnLoad();
    }

    // Keyboard shortcuts
    function initKeyboardShortcuts() {
        $(document).on('keydown', function(e) {
            // Ctrl/Cmd + S to save
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                $('#lean-save-btn').click();
            }

            // Ctrl/Cmd + R to reset
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                $('#lean-reset-btn').click();
            }
        });
    }

    // Reset to auto-optimization
    $('#lean-reset-btn').on('click', function() {
        if (confirm('This will reset all manual plugin configurations to automatic optimization. Continue?')) {
            $('.context-checkbox').prop('checked', false);
            updateSaveStatus('pending', 'Reset to auto-optimization...');
            setTimeout(function() {
                $('#lean-settings-form').trigger('submit');
            }, 500);
        }
    });

    // Helper functions
    function animateContextChange($checkbox) {
        var $option = $checkbox.closest('.context-option');
        $option.addClass('changed');

        setTimeout(function() {
            $option.removeClass('changed');
        }, 500);
    }

    function updateAutoOptimizationNotice($card) {
        var $notice = $card.find('.auto-optimization-notice');
        var hasChecked = $card.find('.context-checkbox:checked').length > 0;

        if (hasChecked) {
            $notice.fadeOut(200);
        } else {
            $notice.fadeIn(200);
        }
    }

    function updateSaveStatus(status, message) {
        var $status = $('.save-status .status-message');
        $status.removeClass('status-saved status-error status-saving status-pending');

        switch (status) {
            case 'saved':
                $status.addClass('status-saved').text(message);
                break;
            case 'error':
                $status.addClass('status-error').text(message);
                break;
            case 'saving':
                $status.addClass('status-saving').text(message);
                break;
            case 'pending':
                $status.addClass('status-pending').text(message);
                break;
            default:
                $status.text(message);
        }
    }

    function showNotification(message, type) {
        // Remove existing notifications
        $('.lean-notification').remove();

        var $notification = $('<div class="lean-notification notice-' + type + '">' +
            '<span class="notification-icon">' + (type === 'success' ? '✅' : '❌') + '</span>' +
            '<span class="notification-message">' + message + '</span>' +
            '<button class="notification-close">&times;</button>' +
            '</div>');

        $('body').append($notification);

        // Animate in
        $notification.css({
            'right': '-400px',
            'opacity': '0'
        }).animate({
            'right': '20px',
            'opacity': '1'
        }, 300);

        // Auto-dismiss
        setTimeout(function() {
            dismissNotification($notification);
        }, 4000);

        // Manual close
        $notification.find('.notification-close').on('click', function() {
            dismissNotification($notification);
        });
    }

    function dismissNotification($notification) {
        $notification.animate({
            'right': '-400px',
            'opacity': '0'
        }, 300, function() {
            $(this).remove();
        });
    }

    function initTooltips() {
        $('.info-tooltip').each(function() {
            var $tooltip = $(this);
            var title = $tooltip.attr('title');

            $tooltip.removeAttr('title').on('mouseenter', function(e) {
                showTooltip($tooltip, title, e);
            }).on('mouseleave', function() {
                hideTooltip();
            });
        });
    }

    function showTooltip($element, content, e) {
        hideTooltip();

        var $tooltip = $('<div class="lean-tooltip">' + content + '</div>');
        $('body').append($tooltip);

        var pos = $element.offset();
        $tooltip.css({
            'left': pos.left + ($element.outerWidth() / 2) - ($tooltip.outerWidth() / 2),
            'top': pos.top - $tooltip.outerHeight() - 10,
            'opacity': '0'
        }).animate({
            'opacity': '1',
            'top': pos.top - $tooltip.outerHeight() - 15
        }, 200);
    }

    function hideTooltip() {
        $('.lean-tooltip').fadeOut(200, function() {
            $(this).remove();
        });
    }

    function animateCardsOnLoad() {
        $('.lean-card').each(function(index) {
            var $card = $(this);
            $card.css('opacity', '0').delay(index * 100).animate({
                'opacity': '1'
            }, 500);
        });
    }
});