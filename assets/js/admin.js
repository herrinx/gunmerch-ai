/**
 * GunMerch AI Admin JavaScript
 *
 * @package GunMerch_AI
 * @since 1.0.0
 */

(function($) {
	'use strict';

	// Toast notification system
	var GMA_Toast = {
		container: null,

		init: function() {
			this.container = $('<div class="gma-toast-container"></div>');
			$('body').append(this.container);
		},

		show: function(message, type) {
			if (!this.container) {
				this.init();
			}

			type = type || 'info';

			var toast = $('<div class="gma-toast gma-toast-' + type + '">' +
				'<p class="gma-toast-message">' + message + '</p>' +
				'</div>');

			this.container.append(toast);

			// Auto-remove after 5 seconds
			setTimeout(function() {
				toast.fadeOut(300, function() {
					$(this).remove();
				});
			}, 5000);
		},

		success: function(message) {
			this.show(message, 'success');
		},

		error: function(message) {
			this.show(message, 'error');
		},

		warning: function(message) {
			this.show(message, 'warning');
		}
	};

	// Check for notifications
	function checkNotifications() {
		$.ajax({
			url: gma_admin.ajax_url,
			type: 'POST',
			data: {
				action: 'gma_get_notifications',
				nonce: gma_admin.nonce
			},
			success: function(response) {
				if (response.success && response.data.notifications.length > 0) {
					response.data.notifications.forEach(function(notification) {
						GMA_Toast.show(notification.text, notification.type);
						
						// Dismiss the notification
						$.post(gma_admin.ajax_url, {
							action: 'gma_dismiss_notice',
							nonce: gma_admin.nonce,
							notice_key: notification.key
						});
					});
				}
			}
		});
	}

	$(document).ready(function() {
		// Initialize toast container
		GMA_Toast.init();

		// Check for notifications on page load
		checkNotifications();

		// Check for notifications every 30 seconds
		setInterval(checkNotifications, 30000);

		// Approve single design
		$(document).on('click', '.gma-btn-approve', function(e) {
			e.preventDefault();

			var button = $(this);
			var designId = button.data('design-id');
			var card = button.closest('.gma-design-card');

			if (!confirm(gma_admin.i18n.approve_confirm)) {
				return;
			}

			button.prop('disabled', true).addClass('gma-loading');

			$.ajax({
				url: gma_admin.ajax_url,
				type: 'POST',
				data: {
					action: 'gma_approve_design',
					nonce: gma_admin.nonce,
					design_id: designId
				},
				success: function(response) {
					if (response.success) {
						GMA_Toast.success(response.data.message);
						card.fadeOut(300, function() {
							$(this).remove();
							// Reload page if no more designs
							if ($('.gma-design-card').length === 0) {
								window.location.reload();
							}
						});
					} else {
						GMA_Toast.error(response.data.message);
						button.prop('disabled', false).removeClass('gma-loading');
					}
				},
				error: function() {
					GMA_Toast.error('An error occurred. Please try again.');
					button.prop('disabled', false).removeClass('gma-loading');
				}
			});
		});

		// Reject single design
		$(document).on('click', '.gma-btn-reject', function(e) {
			e.preventDefault();

			var button = $(this);
			var designId = button.data('design-id');
			var card = button.closest('.gma-design-card');

			if (!confirm(gma_admin.i18n.reject_confirm)) {
				return;
			}

			button.prop('disabled', true).addClass('gma-loading');

			$.ajax({
				url: gma_admin.ajax_url,
				type: 'POST',
				data: {
					action: 'gma_reject_design',
					nonce: gma_admin.nonce,
					design_id: designId
				},
				success: function(response) {
					if (response.success) {
						GMA_Toast.success(response.data.message);
						card.fadeOut(300, function() {
							$(this).remove();
							// Reload page if no more designs
							if ($('.gma-design-card').length === 0) {
								window.location.reload();
							}
						});
					} else {
						GMA_Toast.error(response.data.message);
						button.prop('disabled', false).removeClass('gma-loading');
					}
				},
				error: function() {
					GMA_Toast.error('An error occurred. Please try again.');
					button.prop('disabled', false).removeClass('gma-loading');
				}
			});
		});

		// Generate image for design
		$(document).on('click', '.gma-btn-generate-image', function(e) {
			e.preventDefault();
			console.log('Generate Image button clicked');

			var button = $(this);
			var designId = button.data('design-id');
			var card = button.closest('.gma-design-card');

			if (!designId) {
				console.error('No design ID found on button');
				GMA_Toast.error('Error: No design ID');
				return;
			}

			console.log('Generating image for design ID:', designId);
			button.prop('disabled', true).addClass('gma-loading').text('Generating...');

			$.ajax({
				url: gma_admin.ajax_url,
				type: 'POST',
				data: {
					action: 'gma_generate_image',
					nonce: gma_admin.nonce,
					design_id: designId
				},
				success: function(response) {
					console.log('AJAX success:', response);
					if (response.success) {
						GMA_Toast.success(response.data.message);
						// Reload to show the new image
						window.location.reload();
					} else {
						GMA_Toast.error(response.data.message);
						button.prop('disabled', false).removeClass('gma-loading').text('Generate Image');
					}
				},
				error: function(xhr, status, error) {
					console.error('AJAX error:', status, error);
					GMA_Toast.error('An error occurred. Please try again.');
					button.prop('disabled', false).removeClass('gma-loading').text('Generate Image');
				}
			});
		});

		// Regenerate image for design
		$(document).on('click', '.gma-btn-regenerate-image', function(e) {
			e.preventDefault();

			var button = $(this);
			var designId = button.data('design-id');

			if (!confirm('Delete current image and generate new one?')) {
				return;
			}

			button.prop('disabled', true).addClass('gma-loading').text('Regenerating...');

			$.ajax({
				url: gma_admin.ajax_url,
				type: 'POST',
				data: {
					action: 'gma_regenerate_image',
					nonce: gma_admin.nonce,
					design_id: designId
				},
				success: function(response) {
					if (response.success) {
						GMA_Toast.success(response.data.message);
						window.location.reload();
					} else {
						GMA_Toast.error(response.data.message);
						button.prop('disabled', false).removeClass('gma-loading').text('Regenerate');
					}
				},
				error: function() {
					GMA_Toast.error('An error occurred. Please try again.');
					button.prop('disabled', false).removeClass('gma-loading').text('Regenerate');
				}
			});
		});

		// Use text design (no AI image, just text for Printful)
		$(document).on('click', '.gma-btn-use-text-design', function(e) {
			e.preventDefault();

			var button = $(this);
			var designId = button.data('design-id');
			var card = button.closest('.gma-design-card');

			button.prop('disabled', true).addClass('gma-loading').text('Setting up...');

			$.ajax({
				url: gma_admin.ajax_url,
				type: 'POST',
				data: {
					action: 'gma_use_text_design',
					nonce: gma_admin.nonce,
					design_id: designId
				},
				success: function(response) {
					if (response.success) {
						GMA_Toast.success(response.data.message);
						// Hide the placeholder actions and show ready state
						card.find('.gma-placeholder-actions').html('<p class="gma-status-ready">✓ Text design ready</p>');
					} else {
						GMA_Toast.error(response.data.message);
						button.prop('disabled', false).removeClass('gma-loading').text('Use This Text Design');
					}
				},
				error: function() {
					GMA_Toast.error('An error occurred. Please try again.');
					button.prop('disabled', false).removeClass('gma-loading').text('Use This Text Design');
				}
			});
		});

		// Bulk actions
		$('#gma-bulk-apply').on('click', function() {
			var action = $('#gma-bulk-action').val();
			var checkedBoxes = $('input[name="design_ids[]"]:checked');

			if (!action) {
				GMA_Toast.warning('Please select a bulk action.');
				return;
			}

			if (checkedBoxes.length === 0) {
				GMA_Toast.warning('Please select at least one design.');
				return;
			}

			var designIds = checkedBoxes.map(function() {
				return $(this).val();
			}).get();

			if (action === 'approve' && !confirm(gma_admin.i18n.bulk_approve)) {
				return;
			}

			if (action === 'reject' && !confirm(gma_admin.i18n.bulk_reject)) {
				return;
			}

			var ajaxAction = action === 'approve' ? 'gma_bulk_approve' : 'gma_bulk_reject';

			$(this).prop('disabled', true).addClass('gma-loading');

			$.ajax({
				url: gma_admin.ajax_url,
				type: 'POST',
				data: {
					action: ajaxAction,
					nonce: gma_admin.nonce,
					design_ids: designIds
				},
				success: function(response) {
					if (response.success) {
						GMA_Toast.success(response.data.message);
						window.location.reload();
					} else {
						GMA_Toast.error(response.data.message);
					}
				},
				error: function() {
					GMA_Toast.error('An error occurred. Please try again.');
				},
				complete: function() {
					$('#gma-bulk-apply').prop('disabled', false).removeClass('gma-loading');
				}
			});
		});

		// Quick action buttons (scan trends, generate designs)
		$('.gma-action-btn').on('click', function() {
			var action = $(this).data('action');
			var button = $(this);

			button.prop('disabled', true).addClass('gma-loading');

			var ajaxData = {
				url: gma_admin.ajax_url,
				type: 'POST',
				data: {
					action: 'gma_' + action,
					nonce: gma_admin.nonce
				},
				success: function(response) {
					if (response.success) {
						GMA_Toast.success(response.data.message);
						// Reload page after a short delay to show new data
						setTimeout(function() {
							window.location.reload();
						}, 1500);
					} else {
						GMA_Toast.error(response.data.message);
					}
				},
				error: function() {
					GMA_Toast.error('An error occurred. Please try again.');
				},
				complete: function() {
					button.prop('disabled', false).removeClass('gma-loading');
				}
			};

			// Add count parameter for generate_designs
			if (action === 'generate_designs') {
				ajaxData.data.count = 5;
			}

			$.ajax(ajaxData);
		});

		// Save custom prompt
		$(document).on('click', '.gma-btn-save-prompt', function(e) {
			e.preventDefault();

			var button = $(this);
			var designId = button.data('design-id');
			var promptText = $('#gma-prompt-' + designId).val();

			button.prop('disabled', true).text('Saving...');

			$.ajax({
				url: gma_admin.ajax_url,
				type: 'POST',
				data: {
					action: 'gma_save_prompt',
					nonce: gma_admin.nonce,
					design_id: designId,
					prompt: promptText
				},
				success: function(response) {
					if (response.success) {
						GMA_Toast.success(response.data.message);
					} else {
						GMA_Toast.error(response.data.message);
					}
				},
				error: function() {
					GMA_Toast.error('An error occurred. Please try again.');
				},
				complete: function() {
					button.prop('disabled', false).text('Save Prompt');
				}
			});
		});

		// Remove background
		$(document).on('click', '.gma-btn-remove-bg', function(e) {
			e.preventDefault();

			var button = $(this);
			var designId = button.data('design-id');

			if (!confirm('Remove background from this image?')) {
				return;
			}

			button.prop('disabled', true).addClass('gma-loading').text('Removing...');

			$.ajax({
				url: gma_admin.ajax_url,
				type: 'POST',
				data: {
					action: 'gma_remove_background',
					nonce: gma_admin.nonce,
					design_id: designId
				},
				success: function(response) {
					if (response.success) {
						GMA_Toast.success(response.data.message);
						window.location.reload();
					} else {
						GMA_Toast.error(response.data.message);
						button.prop('disabled', false).removeClass('gma-loading').text('Remove BG');
					}
				},
				error: function() {
					GMA_Toast.error('An error occurred. Please try again.');
					button.prop('disabled', false).removeClass('gma-loading').text('Remove BG');
				}
			});
		});

		// Upscale image
		$(document).on('click', '.gma-btn-upscale', function(e) {
			e.preventDefault();

			var button = $(this);
			var designId = button.data('design-id');

			if (!confirm('Upscale this image to double resolution?')) {
				return;
			}

			button.prop('disabled', true).addClass('gma-loading').text('Upscaling...');

			$.ajax({
				url: gma_admin.ajax_url,
				type: 'POST',
				data: {
					action: 'gma_upscale_image',
					nonce: gma_admin.nonce,
					design_id: designId
				},
				success: function(response) {
					if (response.success) {
						GMA_Toast.success(response.data.message);
						window.location.reload();
					} else {
						GMA_Toast.error(response.data.message);
						button.prop('disabled', false).removeClass('gma-loading').text('Upscale');
					}
				},
				error: function() {
					GMA_Toast.error('An error occurred. Please try again.');
					button.prop('disabled', false).removeClass('gma-loading').text('Upscale');
				}
			});
		});

		// Toggle log meta
		$(document).on('click', '.gma-toggle-meta', function(e) {
			e.preventDefault();
			var logId = $(this).data('log-id');
			var meta = $('#gma-log-meta-' + logId);
			
			if (meta.is(':visible')) {
				meta.hide();
				$(this).text('Show Details');
			} else {
				meta.show();
				$(this).text('Hide Details');
			}
		});

		// Clear logs
		$('.gma-clear-logs').on('click', function() {
			if (!confirm('Are you sure you want to clear all logs? This cannot be undone.')) {
				return;
			}

			var button = $(this);
			button.prop('disabled', true);

			$.ajax({
				url: gma_admin.ajax_url,
				type: 'POST',
				data: {
					action: 'gma_clear_logs',
					nonce: gma_admin.nonce
				},
				success: function(response) {
					if (response.success) {
						GMA_Toast.success(response.data.message);
						window.location.reload();
					} else {
						GMA_Toast.error(response.data.message);
					}
				},
				error: function() {
					GMA_Toast.error('An error occurred. Please try again.');
				},
				complete: function() {
					button.prop('disabled', false);
				}
			});
		});

		// Select all checkbox
		$(document).on('change', '.gma-design-checkbox input[type="checkbox"]', function() {
			if ($(this).is(':checked')) {
				$(this).closest('.gma-design-card').addClass('gma-selected');
			} else {
				$(this).closest('.gma-design-card').removeClass('gma-selected');
			}
		});
	});

})(jQuery);