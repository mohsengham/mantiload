/**
 * Simple, Fast Indexing - No Bullshit
 */
jQuery(document).ready(function($) {
	var isIndexing = false;

	$('#mantiload-start-index').on('click', function(e) {
		e.preventDefault();

		if (isIndexing) {
			return;
		}

		isIndexing = true;
		var $btn = $(this);
		var $abort = $('#mantiload-abort-index');
		var $progress = $('#mantiload-progress');
		var $bar = $('#mantiload-bar');
		var $text = $('#mantiload-text');

		$btn.prop('disabled', true);
		$abort.show();
		$progress.show();
		$bar.css('width', '0%');
		$text.text('Starting...');

		// Start indexing
		$.post(mantiloadAjax.ajaxurl, {
			action: 'mantiload_start_index',
			nonce: mantiloadAjax.nonce
		}, function(response) {
			if (!response.success) {
				alert('Error: ' + (response.data || 'Unknown error'));
				resetUI();
				return;
			}

			var total = response.data.total;
			var postTypes = response.data.post_types;
			var batchSize = response.data.batch_size; // Get batch size from settings
			var indexed = 0;
			var startTime = Date.now();

			$text.text('0 / ' + total + ' indexed');

			// Process each post type
			processPostType(0);

			function processPostType(typeIndex) {
				if (typeIndex >= postTypes.length) {
					// Done!
					var elapsed = ((Date.now() - startTime) / 1000).toFixed(1);
					$bar.css('width', '100%');
					$text.html('<strong>✅ Complete!</strong> Indexed ' + indexed + ' posts in ' + elapsed + 's');
					setTimeout(function() {
						location.reload();
					}, 2000);
					return;
				}

				var postType = postTypes[typeIndex];
				var offset = 0;

				processBatch();

				function processBatch() {
					$.post(mantiloadAjax.ajaxurl, {
						action: 'mantiload_index_batch',
						nonce: mantiloadAjax.nonce,
						post_type: postType,
						offset: offset,
						batch_size: batchSize
					}, function(batchResponse) {
						if (!batchResponse.success) {
							alert('Error: ' + (batchResponse.data || 'Unknown error'));
							resetUI();
							return;
						}

						indexed += batchResponse.data.indexed;
						var percent = Math.min(100, (indexed / total) * 100);
						var elapsed = (Date.now() - startTime) / 1000;
						var speed = Math.round(indexed / elapsed);

						$bar.css('width', percent + '%');
						$('#mantiload-percentage').text(percent.toFixed(1) + '%');
						$text.text(indexed + ' / ' + total + ' indexed - ' + speed + ' posts/sec');

						if (batchResponse.data.has_more) {
							offset += batchSize; // Use configurable batch size
							processBatch();
						} else {
							processPostType(typeIndex + 1);
						}
					}).fail(function() {
						alert('AJAX request failed');
						resetUI();
					});
				}
			}
		}).fail(function() {
			alert('AJAX request failed');
			resetUI();
		});

		function resetUI() {
			isIndexing = false;
			$btn.prop('disabled', false);
			$abort.hide();
		}
	});

	$('#mantiload-abort-index').on('click', function() {
		if (confirm('Abort indexing?')) {
			isIndexing = false;
			location.reload();
		}
	});

	// Create index - Beautiful notifications
	$('#mantiload-create-index').on('click', function(e) {
		e.preventDefault();

		if (!confirm('Create a new search index? This is required when changing the index name or setting up for the first time.')) {
			return;
		}

		var $btn = $(this);
		var originalHTML = $btn.html();
		$btn.prop('disabled', true).html('<span class="mantiload-spinner"></span> <span>Creating index...</span>');

		// Show inline progress indicator
		showInlineStatus($btn, 'info', '⚡ Creating Manticore search index with multi-language support...', true);

		$.post(mantiloadAjax.ajaxurl, {
			action: 'mantiload_create_index',
			nonce: mantiloadAjax.nonce
		}, function(response) {
			if (response.success) {
				showInlineStatus($btn, 'success', '✅ ' + response.data.message);
				$btn.prop('disabled', false).html(originalHTML);
				if (typeof lucide !== 'undefined') lucide.createIcons();
				setTimeout(function() { location.reload(); }, 2000);
			} else {
				showInlineStatus($btn, 'error', '❌ Error: ' + (response.data || 'Unknown error'));
				$btn.prop('disabled', false).html(originalHTML);
				if (typeof lucide !== 'undefined') lucide.createIcons();
			}
		}).fail(function() {
			showInlineStatus($btn, 'error', '❌ Network error: Failed to create index');
			$btn.prop('disabled', false).html(originalHTML);
			if (typeof lucide !== 'undefined') lucide.createIcons();
		});
	});

	// Truncate index - Beautiful notifications
	$('#mantiload-truncate-index').on('click', function(e) {
		e.preventDefault();

		if (!confirm('⚠️ WARNING: This will DELETE ALL indexed data!\n\nAre you absolutely sure you want to truncate the index?')) {
			return;
		}

		var $btn = $(this);
		var originalHTML = $btn.html();
		$btn.prop('disabled', true).html('<span class="mantiload-spinner"></span> <span>Truncating...</span>');

		// Show inline progress indicator
		showInlineStatus($btn, 'warning', '⚠️ Removing all indexed data from Manticore...', true);

		$.post(mantiloadAjax.ajaxurl, {
			action: 'mantiload_truncate_index',
			nonce: mantiloadAjax.nonce
		}, function(response) {
			if (response.success) {
				showInlineStatus($btn, 'success', '✅ ' + response.data.message);
				setTimeout(function() { location.reload(); }, 1500);
			} else {
				showInlineStatus($btn, 'error', '❌ Error: ' + (response.data || 'Unknown error'));
				$btn.prop('disabled', false).html(originalHTML);
				if (typeof lucide !== 'undefined') lucide.createIcons();
			}
		}).fail(function() {
			showInlineStatus($btn, 'error', '❌ Network error: Failed to truncate index');
			$btn.prop('disabled', false).html(originalHTML);
			if (typeof lucide !== 'undefined') lucide.createIcons();
		});
	});

	// Optimize index - Beautiful notifications
	$('#mantiload-optimize-index').on('click', function(e) {
		e.preventDefault();

		var $btn = $(this);
		var originalHTML = $btn.html();
		$btn.prop('disabled', true).html('<span class="mantiload-spinner"></span> <span>Optimizing...</span>');

		// Show inline progress indicator
		showInlineStatus($btn, 'info', '⚡ Optimizing index for better performance...', true);

		$.post(mantiloadAjax.ajaxurl, {
			action: 'mantiload_optimize_index',
			nonce: mantiloadAjax.nonce
		}, function(response) {
			if (response.success) {
				showInlineStatus($btn, 'success', '✅ ' + response.data.message);
				$btn.prop('disabled', false).html(originalHTML);
				if (typeof lucide !== 'undefined') lucide.createIcons();
			} else {
				showInlineStatus($btn, 'error', '❌ Error: ' + (response.data || 'Unknown error'));
				$btn.prop('disabled', false).html(originalHTML);
				if (typeof lucide !== 'undefined') lucide.createIcons();
			}
		}).fail(function() {
			showInlineStatus($btn, 'error', '❌ Network error: Failed to optimize index');
			$btn.prop('disabled', false).html(originalHTML);
			if (typeof lucide !== 'undefined') lucide.createIcons();
		});
	});

	// Helper: Beautiful inline status notifications
	function showInlineStatus($btn, type, message, showSpinner) {
		// Remove any existing status
		$btn.parent().find('.mantiload-inline-status').remove();

		var colors = {
			'success': { bg: '#f0fdf4', border: '#10b981', text: '#10b981' },
			'error': { bg: '#fef2f2', border: '#ef4444', text: '#ef4444' },
			'warning': { bg: '#fffbeb', border: '#f59e0b', text: '#f59e0b' },
			'info': { bg: '#f0f9ff', border: '#3b82f6', text: '#1e40af' }
		};

		var color = colors[type] || colors['info'];
		var spinnerHTML = showSpinner ? '<span class="mantiload-spinner"></span> ' : '';

		var $status = $('<div class="mantiload-inline-status" style="' +
			'margin-top: 12px; padding: 12px; border-radius: 6px; ' +
			'background: ' + color.bg + '; ' +
			'border: 1px solid ' + color.border + '; ' +
			'color: ' + color.text + '; ' +
			'font-weight: 600; font-size: 13px;">' +
			spinnerHTML + message +
			'</div>');

		$btn.parent().append($status);

		// Auto-remove success/error messages after 5 seconds
		if (type === 'success' || type === 'error') {
			setTimeout(function() {
				$status.fadeOut(300, function() { $(this).remove(); });
			}, 5000);
		}
	}
});
