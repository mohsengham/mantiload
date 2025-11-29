jQuery(document).ready(function($) {
	var abortIndexing = false; // Flag to abort indexing

	// Initialize Select2 for searchable multi-select dropdowns
	if (typeof $.fn.select2 !== 'undefined') {
		$('.mantiload-select2').select2({
			placeholder: 'Select attributes to exclude...',
			allowClear: true,
			width: '100%'
		});
	}

	// AJAX Reindex with Progress Bar
	$('#mantiload-reindex-ajax').on('click', function(e) {
		e.preventDefault();

		var $button = $(this);
		var $abortButton = $('#mantiload-abort-ajax');
		var $progressContainer = $('#mantiload-progress-container');
		var $progressBar = $('#mantiload-progress-bar');
		var $progressText = $('#mantiload-progress-text');
		var $progressStats = $('#mantiload-progress-stats');

		// Reset abort flag
		abortIndexing = false;

		// Disable start button, enable abort button
		$button.prop('disabled', true).html('<span class="dashicons dashicons-update" style="margin-top: 3px;"></span> Indexing...');
		$abortButton.prop('disabled', false).show();

		// Show progress container
		$progressContainer.show();
		$progressBar.css('width', '0%').css('background', 'linear-gradient(90deg, #00a32a 0%, #008a20 100%)');
		$progressText.text('Starting...');
		$progressStats.html('');

		// Get total posts first
		$.ajax({
			url: mantiloadAdmin.ajaxurl,
			type: 'POST',
			data: {
				action: 'mantiload_start_index',
				nonce: mantiloadAdmin.nonce
			},
			success: function(response) {
				if (response.success) {
					var totalPosts = response.data.total;
					var postTypes = response.data.post_types;
					var indexed = 0;
					var failed = 0;
					var startTime = Date.now();
					var batchSize = response.data.batch_size || 500; // Use setting or default 500

					$progressText.text('0 / ' + totalPosts.toLocaleString() + ' posts');

					// Process each post type
					processPostTypes(postTypes, 0);

					function processPostTypes(postTypes, typeIndex) {
						// Check if aborted
						if (abortIndexing) {
							var totalTime = ((Date.now() - startTime) / 1000).toFixed(2);
							$progressBar.css('background', 'linear-gradient(90deg, #d63638 0%, #b32d2e 100%)');
							$progressText.html('<strong>⚠️ Aborted!</strong>');
							$progressStats.html(
								'<strong>Indexed:</strong> ' + indexed.toLocaleString() + ' | ' +
								'<strong>Failed:</strong> ' + failed + ' | ' +
								'<strong>Time:</strong> ' + totalTime + 's | ' +
								'<span style="color: #d63638;"><strong>Status:</strong> Stopped by user</span>'
							);
							$button.prop('disabled', false).html('<span class="dashicons dashicons-update" style="margin-top: 3px;"></span> Reindex All Posts');
							$abortButton.hide();
							return;
						}

						if (typeIndex >= postTypes.length) {
							// All done!
							var totalTime = ((Date.now() - startTime) / 1000).toFixed(2);
							$progressBar.css('width', '100%');
							$progressText.html('<strong>✅ Complete!</strong>');
							$progressStats.html(
								'<strong>Indexed:</strong> ' + indexed.toLocaleString() + ' | ' +
								'<strong>Failed:</strong> ' + failed + ' | ' +
								'<strong>Time:</strong> ' + totalTime + 's | ' +
								'<strong>Speed:</strong> ' + Math.round(indexed / totalTime) + ' posts/sec'
							);
							$button.prop('disabled', false).html('<span class="dashicons dashicons-update" style="margin-top: 3px;"></span> Reindex All Posts');
							$abortButton.hide();

							// Reload page after 2 seconds to show updated stats
							setTimeout(function() {
								location.reload();
							}, 2000);
							return;
						}

						var postType = postTypes[typeIndex];
						var offset = 0;

						processBatch();

						function processBatch() {
							// Check if aborted before each batch
							if (abortIndexing) {
								processPostTypes(postTypes, postTypes.length); // Jump to end
								return;
							}

							$.ajax({
								url: mantiloadAdmin.ajaxurl,
								type: 'POST',
								data: {
									action: 'mantiload_index_batch',
									nonce: mantiloadAdmin.nonce,
									post_type: postType,
									offset: offset,
									batch_size: batchSize
								},
								success: function(batchResponse) {
									if (batchResponse.success) {
										indexed += batchResponse.data.indexed;
										failed += batchResponse.data.failed;

										var percentage = Math.min(100, (indexed / totalPosts) * 100);
										var elapsed = ((Date.now() - startTime) / 1000);
										var speed = indexed / elapsed;
										var remaining = Math.round((totalPosts - indexed) / speed);

										$progressBar.css('width', percentage.toFixed(1) + '%');
										$progressBar.text(percentage.toFixed(0) + '%');
										$progressText.text(indexed.toLocaleString() + ' / ' + totalPosts.toLocaleString() + ' posts (' + percentage.toFixed(1) + '%)');
										$progressStats.html(
											'<strong>Post Type:</strong> ' + postType + ' | ' +
											'<strong>Speed:</strong> ' + Math.round(speed) + ' posts/sec | ' +
											'<strong>ETA:</strong> ' + remaining + 's'
										);

										// If this batch had results, continue with next batch
										if (batchResponse.data.indexed > 0 || batchResponse.data.failed > 0) {
											offset += batchSize;
											processBatch();
										} else {
											// No more posts for this type, move to next type
											processPostTypes(postTypes, typeIndex + 1);
										}
									} else {
										$progressBar.css('background', 'linear-gradient(90deg, #d63638 0%, #b32d2e 100%)');
										$progressText.html('<strong>❌ Error:</strong> ' + (batchResponse.data || mantiloadAdmin.i18n.unknownError));
										$button.prop('disabled', false).html('<span class="dashicons dashicons-update" style="margin-top: 3px;"></span> Reindex All Posts');
										$abortButton.hide();
									}
								},
								error: function(jqXHR, textStatus, errorThrown) {
									console.error('AJAX error:', {jqXHR: jqXHR, textStatus: textStatus, errorThrown: errorThrown});
									console.error('Response text:', jqXHR.responseText);
									$progressBar.css('background', 'linear-gradient(90deg, #d63638 0%, #b32d2e 100%)');
									$progressText.html('<strong>❌ AJAX Error:</strong> ' + textStatus);
									$progressStats.html('<small style="color: #d63638;">Check browser console for details</small>');
									$button.prop('disabled', false).html('<span class="dashicons dashicons-update" style="margin-top: 3px;"></span> Reindex All Posts');
									$abortButton.hide();
								}
							});
						}
					}
				} else {
					console.error('Error response:', response);
					var errorMsg = response.data || mantiloadAdmin.i18n.unknownError;
					if (typeof errorMsg === 'object') {
						errorMsg = JSON.stringify(errorMsg);
					}
					$progressBar.css('background', 'linear-gradient(90deg, #d63638 0%, #b32d2e 100%)');
					$progressText.html('<strong>❌ Error:</strong> ' + errorMsg);
					$button.prop('disabled', false).html('<span class="dashicons dashicons-update" style="margin-top: 3px;"></span> Reindex All Posts');
					$abortButton.hide();
				}
			},
			error: function(jqXHR, textStatus, errorThrown) {
				console.error('AJAX error:', {jqXHR: jqXHR, textStatus: textStatus, errorThrown: errorThrown});
				console.error('Response text:', jqXHR.responseText);
				$progressBar.css('background', 'linear-gradient(90deg, #d63638 0%, #b32d2e 100%)');
				$progressText.html('<strong>❌ AJAX Error:</strong> ' + textStatus);
				$progressStats.html('<small style="color: #d63638;">Check browser console for details</small>');
				$button.prop('disabled', false).html('<span class="dashicons dashicons-update" style="margin-top: 3px;"></span> Reindex All Posts');
				$abortButton.hide();
			}
		});
	});

	// Abort Indexing
	$('#mantiload-abort-ajax').on('click', function(e) {
		e.preventDefault();

		if (confirm(mantiloadAdmin.i18n.confirmAbort?\n\nProgress will be saved, but remaining posts will not be indexed.')) {
			abortIndexing = true;
			$(this).prop('disabled', true).html('<span class="dashicons dashicons-no" style="margin-top: 3px;"></span> Aborting...');
		}
	});

	// Legacy form button spinner
	$('.mantiload-actions-form button').on('click', function(e) {
		if ($(this).attr('id') === 'mantiload-reindex-ajax') {
			return; // Skip for AJAX button
		}
		$(this).prop('disabled', true).append(' <span class="spinner is-active" style="float:none;margin:0 0 0 8px;"></span>');
	});

	// ========================================
	// DASHBOARD QUICK ACTIONS - Beautiful Real-Time Progress
	// ========================================

	// Dashboard: Reindex All - Beautiful batch-by-batch progress
	$('#dashboard-reindex-btn').on('click', function(e) {
		e.preventDefault();

		if (!confirm(mantiloadAdmin.i18n.confirmReindex?\n\nThis will reindex all products with full categories, attributes, and filter data.')) {
			return;
		}

		var $btn = $(this);
		var $progressContainer = $('#dashboard-progress-container');
		var $progressBar = $('#dashboard-progress-bar');
		var $progressText = $('#dashboard-progress-text');
		var $progressStats = $('#dashboard-progress-stats');
		var originalHTML = $btn.html();

		// Disable button
		$btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none;"></span> Reindexing...');

		// Show progress container
		$progressContainer.show();
		$progressBar.css('width', '0%').css('background', 'linear-gradient(90deg, #00a32a 0%, #008a20 100%)');
		$progressText.text('Starting...');
		$progressStats.html('');

		// Get total posts first
		$.ajax({
			url: mantiloadAdmin.ajaxurl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'mantiload_start_index',
				nonce: mantiloadAdmin.nonce
			},
			success: function(response) {
				if (response.success) {
					var totalPosts = response.data.total;
					var postTypes = response.data.post_types;
					var indexed = 0;
					var failed = 0;
					var startTime = Date.now();
					var batchSize = response.data.batch_size || 500;

					$progressText.text('0 / ' + totalPosts.toLocaleString() + ' posts');

					// Process each post type
					processPostTypes(postTypes, 0);

					function processPostTypes(postTypes, typeIndex) {
						if (typeIndex >= postTypes.length) {
							// All done!
							var totalTime = ((Date.now() - startTime) / 1000).toFixed(2);
							$progressBar.css('width', '100%');
							$progressText.html('<strong>✅ Complete!</strong>');
							$progressStats.html(
								'<strong>Indexed:</strong> ' + indexed.toLocaleString() + ' | ' +
								'<strong>Failed:</strong> ' + failed + ' | ' +
								'<strong>Time:</strong> ' + totalTime + 's | ' +
								'<strong>Speed:</strong> ' + Math.round(indexed / totalTime) + ' posts/sec'
							);
							$btn.prop('disabled', false).html(originalHTML);
							if (typeof lucide !== 'undefined') lucide.createIcons();

							// Reload page after 2 seconds to show updated stats
							setTimeout(function() {
								location.reload();
							}, 2000);
							return;
						}

						var postType = postTypes[typeIndex];
						var offset = 0;

						processBatch();

						function processBatch() {
							$.ajax({
								url: mantiloadAdmin.ajaxurl,
								type: 'POST',
								dataType: 'json',
								data: {
									action: 'mantiload_index_batch',
									nonce: mantiloadAdmin.nonce,
									post_type: postType,
									offset: offset,
									batch_size: batchSize
								},
								success: function(batchResponse) {
									if (batchResponse.success) {
										indexed += batchResponse.data.indexed;
										failed += batchResponse.data.failed;

										var percentage = Math.min(100, (indexed / totalPosts) * 100);
										var elapsed = ((Date.now() - startTime) / 1000);
										var speed = indexed / elapsed;
										var remaining = Math.round((totalPosts - indexed) / speed);

										$progressBar.css('width', percentage.toFixed(1) + '%');
										$progressBar.text(percentage.toFixed(0) + '%');
										$progressText.text(indexed.toLocaleString() + ' / ' + totalPosts.toLocaleString() + ' posts (' + percentage.toFixed(1) + '%)');
										$progressStats.html(
											'<strong>Post Type:</strong> ' + postType + ' | ' +
											'<strong>Speed:</strong> ' + Math.round(speed) + ' posts/sec | ' +
											'<strong>ETA:</strong> ' + remaining + 's'
										);

										// If this batch had results, continue with next batch
										if (batchResponse.data.indexed > 0 || batchResponse.data.failed > 0) {
											offset += batchSize;
											processBatch();
										} else {
											// No more posts for this type, move to next type
											processPostTypes(postTypes, typeIndex + 1);
										}
									} else {
										$progressBar.css('background', 'linear-gradient(90deg, #d63638 0%, #b32d2e 100%)');
										$progressText.html('<strong>❌ Error:</strong> ' + (batchResponse.data || mantiloadAdmin.i18n.unknownError));
										$btn.prop('disabled', false).html(originalHTML);
										if (typeof lucide !== 'undefined') lucide.createIcons();
									}
								},
								error: function() {
									$progressBar.css('background', 'linear-gradient(90deg, #d63638 0%, #b32d2e 100%)');
									$progressText.html('<strong>❌ Error:</strong> Network error');
									$btn.prop('disabled', false).html(originalHTML);
									if (typeof lucide !== 'undefined') lucide.createIcons();
								}
							});
						}
					}
				} else {
					console.error('Dashboard reindex start failed:', response);
					var errorMsg = 'Could not start reindex';
					if (response.data) {
						errorMsg += ': ' + (typeof response.data === 'string' ? response.data : JSON.stringify(response.data));
					}
					$progressBar.css('background', 'linear-gradient(90deg, #d63638 0%, #b32d2e 100%)');
					$progressText.html('<strong>❌ Error:</strong> ' + errorMsg);
					$btn.prop('disabled', false).html(originalHTML);
					if (typeof lucide !== 'undefined') lucide.createIcons();
				}
			},
			error: function(xhr, status, error) {
				console.error('Dashboard reindex AJAX error:', {xhr: xhr, status: status, error: error, responseText: xhr.responseText});
				$progressBar.css('background', 'linear-gradient(90deg, #d63638 0%, #b32d2e 100%)');
				$progressText.html('<strong>❌ Error:</strong> AJAX failed - ' + status + ' (check console)');
				$btn.prop('disabled', false).html(originalHTML);
				if (typeof lucide !== 'undefined') lucide.createIcons();
			}
		});
	});

	// Dashboard: Optimize Indexes - Beautiful inline notification
	$('#dashboard-optimize-btn').on('click', function(e) {
		e.preventDefault();

		var $btn = $(this);
		var originalHTML = $btn.html();
		$btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none;"></span> Optimizing...');

		showDashboardStatus('info', '⚡ Optimizing index for better performance...', true);

		$.ajax({
			url: mantiloadAdmin.ajaxurl,
			type: 'POST',
			data: {
				action: 'mantiload_optimize_index',
				nonce: mantiloadAdmin.nonce
			},
			success: function(response) {
				if (response.success) {
					showDashboardStatus('success', '✅ ' + (response.data.message || mantiloadAdmin.i18n.indexOptimized));
					$btn.prop('disabled', false).html(originalHTML);
					if (typeof lucide !== 'undefined') lucide.createIcons();
				} else {
					showDashboardStatus('error', '❌ Error: ' + (response.data || mantiloadAdmin.i18n.optimizationFailed));
					$btn.prop('disabled', false).html(originalHTML);
					if (typeof lucide !== 'undefined') lucide.createIcons();
				}
			},
			error: function() {
				showDashboardStatus('error', '❌ Network error: Failed to optimize index');
				$btn.prop('disabled', false).html(originalHTML);
				if (typeof lucide !== 'undefined') lucide.createIcons();
			}
		});
	});

	// Dashboard: Clear Cache - Beautiful inline notification
	$('#dashboard-clear-cache-btn').on('click', function(e) {
		e.preventDefault();

		if (!confirm(mantiloadAdmin.i18n.confirmClearCache search results?')) {
			return;
		}

		var $btn = $(this);
		var originalHTML = $btn.html();
		$btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none;"></span> Clearing...');

		showDashboardStatus('info', '🗑️ Clearing all cached search results...', true);

		// Use a form submit for this (no AJAX endpoint exists)
		var form = $('<form method="post" style="display:none;">')
			.append($('<input type="hidden" name="mantiload_action" value="clear_search_cache">'))
			.append('<?php echo wp_nonce_field("mantiload-action", "_wpnonce", true, false); ?>');

		$('body').append(form);
		form.submit();
	});

	// Helper: Beautiful inline status notifications for dashboard
	function showDashboardStatus(type, message, showSpinner) {
		var $container = $('#dashboard-status-container');
		$container.empty();

		var colors = {
			'success': { bg: '#f0fdf4', border: '#10b981', text: '#10b981' },
			'error': { bg: '#fef2f2', border: '#ef4444', text: '#ef4444' },
			'warning': { bg: '#fffbeb', border: '#f59e0b', text: '#f59e0b' },
			'info': { bg: '#f0f9ff', border: '#3b82f6', text: '#1e40af' }
		};

		var color = colors[type] || colors['info'];
		var spinnerHTML = showSpinner ? '<span class="spinner is-active" style="float:none; margin-right: 8px;"></span>' : '';

		var $status = $('<div class="mantiload-dashboard-status" style="' +
			'margin-top: 15px; padding: 15px; border-radius: 6px; ' +
			'background: ' + color.bg + '; ' +
			'border: 1px solid ' + color.border + '; ' +
			'color: ' + color.text + '; ' +
			'font-weight: 600; font-size: 14px;">' +
			spinnerHTML + message +
			'</div>');

		$container.append($status);

		// Auto-remove success/error messages after 5 seconds
		if (type === 'success' || type === 'error') {
			setTimeout(function() {
				$status.fadeOut(300, function() { $(this).remove(); });
			}, 5000);
		}
	}
});

// ========================================
// DESERT - Admin Notice Cleaner
// ========================================

jQuery(document).ready(function($) {
	// View hidden notices modal
	$('.desert-view-notices').on('click', function(e) {
		e.preventDefault();

		$.ajax({
			url: desertAjax.ajaxurl,
			type: 'POST',
			data: {
				action: 'desert_get_hidden_notices',
				nonce: desertAjax.nonce
			},
			success: function(response) {
				if (response.success) {
					showHiddenNoticesModal(response.data.notices);
				} else {
					alert(mantiloadAdmin.i18n.errorLoadingNotices);
				}
			},
			error: function() {
				alert(mantiloadAdmin.i18n.errorLoadingNotices);
			}
		});
	});

	function showHiddenNoticesModal(notices) {
		// Create modal
		var modal = $('<div class="desert-modal-overlay" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 99999; display: flex; align-items: center; justify-content: center;">');
		var content = $('<div class="desert-modal-content" style="background: white; border-radius: 8px; max-width: 600px; max-height: 80vh; overflow-y: auto; padding: 20px; margin: 20px;">');

		content.append('<h2>Hidden Admin Notices (' + notices.length + ')</h2>');

		if (notices.length === 0) {
			content.append('<p>No notices have been hidden yet.</p>');
		} else {
			notices.forEach(function(notice) {
				var noticeDiv = $('<div class="notice ' + notice.classes + '" style="margin: 10px 0;">');
				noticeDiv.html(notice.html);
				content.append(noticeDiv);
			});
		}

		var closeBtn = $('<button class="button button-primary" style="margin-top: 15px;">Close</button>');
		closeBtn.on('click', function() {
			modal.remove();
		});

		content.append(closeBtn);
		modal.append(content);

		// Close on overlay click
		modal.on('click', function(e) {
			if (e.target === modal[0]) {
				modal.remove();
			}
		});

		$('body').append(modal);
	}
});
