<?php defined( 'ABSPATH' ) || exit; ?>

<div class="wrap">
	<?php settings_errors( 'mantiload' ); ?>
	<?php settings_errors( 'mantiload_synonyms' ); ?>
</div>

<div class="mantiload-admin-wrap">
	<div class="mantiload-header">
		<h1>
			<span class="mantiload-logo">
				<img src="<?php echo esc_url( MANTILOAD_PLUGIN_URL . 'assets/img/logo.png' ); ?>" alt="MantiLoad">
			</span>
			Search Synonyms
		</h1>
		<p>Help customers find what they want, even when they use different words</p>
	</div>

	<!-- Add New Synonym Form -->
	<div class="mantiload-card">
		<h2><i data-lucide="plus-circle"></i> Add New Synonym</h2>
		<p class="mantiload-description">Example: User searches "gown" → automatically finds "dress" products too!</p>

		<div class="mantiload-divider"></div>

		<form method="post" action="">
			<?php wp_nonce_field( 'mantiload-synonyms' ); ?>
			<input type="hidden" name="action" value="add_synonym">

			<table class="mantiload-form-table">
				<tr>
					<th>
						<label for="term">Search Term</label>
					</th>
					<td>
						<input type="text" name="term" id="term" class="mantiload-input" placeholder="e.g., dress" required>
						<span class="mantiload-description">The main word customers search for</span>
					</td>
				</tr>
				<tr>
					<th>
						<label for="synonyms">Synonyms</label>
					</th>
					<td>
						<input type="text" name="synonyms" id="synonyms" class="mantiload-input" placeholder="e.g., gown, frock, evening wear" required style="max-width: 500px;">
						<span class="mantiload-description">Comma-separated words that mean the same thing</span>
					</td>
				</tr>
			</table>

			<div style="margin-top: var(--ml-space-lg);">
				<button type="submit" class="ml-btn ml-btn-primary">
					<i data-lucide="plus"></i>
					Add Synonym
				</button>
			</div>
		</form>
	</div>

	<!-- Existing Synonyms -->
	<div class="mantiload-card">
		<h2><i data-lucide="list"></i> Your Synonyms (<?php echo count( $synonyms ); ?>)</h2>

		<?php if ( empty( $synonyms ) ): ?>
			<div style="text-align: center; padding: 60px 20px; color: var(--ml-gray-500);">
				<i data-lucide="search" style="width: 48px; height: 48px; margin: 0 auto var(--ml-space-md); display: block; opacity: 0.3;"></i>
				<h3 style="color: var(--ml-gray-700); font-size: 18px; margin: 0 0 var(--ml-space-sm) 0;">No synonyms yet</h3>
				<p style="margin: 0;">Add your first synonym above to start improving search results</p>
			</div>
		<?php else: ?>
			<form method="post" action="" id="bulk-delete-form">
				<?php wp_nonce_field( 'mantiload-synonyms' ); ?>
				<input type="hidden" name="action" value="bulk_delete_synonyms">

				<div style="margin-bottom: var(--ml-space-md); display: flex; align-items: center; gap: var(--ml-space-md);">
					<button type="submit" class="ml-btn ml-btn-danger" id="bulk-delete-btn" disabled onclick="return confirm('Delete selected synonyms?');">
						<i data-lucide="trash-2"></i>
						Delete Selected (<span id="selected-count">0</span>)
					</button>
					<label style="cursor: pointer; color: var(--ml-gray-700);">
						<input type="checkbox" id="select-all" style="margin-right: 8px;">
						Select All
					</label>
				</div>

				<table class="widefat striped" style="margin-top: var(--ml-space-md);">
					<thead>
						<tr>
							<th style="width: 40px;"></th>
							<th style="width: 200px;">Search Term</th>
							<th>Synonyms</th>
							<th style="width: 100px; text-align: center;">Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $synonyms as $mantiload_synonym ): ?>
						<tr>
							<td>
								<input type="checkbox" name="synonym_ids[]" value="<?php echo esc_attr( $mantiload_synonym->id ); ?>" class="synonym-checkbox">
							</td>
							<td>
								<strong style="color: var(--ml-black);"><?php echo esc_html( $mantiload_synonym->term ); ?></strong>
							</td>
							<td>
								<span style="color: var(--ml-gray-700);"><?php echo esc_html( $mantiload_synonym->synonyms ); ?></span>
							</td>
							<td style="text-align: center;">
								<button type="button" class="ml-btn ml-btn-danger delete-single-btn" data-id="<?php echo esc_attr( $mantiload_synonym->id ); ?>" style="padding: 6px 12px; font-size: 12px;">
									<i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
									Delete
								</button>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</form>
		<?php endif; ?>
	</div>

	<!-- Tips -->
	<div class="mantiload-card">
		<h2><i data-lucide="lightbulb"></i> Synonym Tips</h2>
		<ul style="margin: var(--ml-space-md) 0 0 0; padding-left: 20px; color: var(--ml-gray-700); line-height: 1.8;">
			<li><strong>Bidirectional:</strong> Synonyms work both ways - searching any word finds all related terms</li>
			<li><strong>Multiple words:</strong> Separate synonyms with commas: gown, frock, evening wear</li>
			<li><strong>Product names:</strong> Add brand-specific terms: iPhone → smartphone, mobile</li>
			<li><strong>Typos:</strong> Include common misspellings to help users find results</li>
			<li><strong>Regional terms:</strong> Add local variations: sneakers, trainers, kicks</li>
		</ul>
	</div>
</div>

<script>
// Initialize Lucide icons after DOM load
document.addEventListener('DOMContentLoaded', function() {
	if (typeof lucide !== 'undefined') {
		lucide.createIcons();
	}

	// Bulk delete functionality
	const checkboxes = document.querySelectorAll('.synonym-checkbox');
	const selectAll = document.getElementById('select-all');
	const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
	const selectedCount = document.getElementById('selected-count');

	function updateBulkDeleteButton() {
		const checkedCount = document.querySelectorAll('.synonym-checkbox:checked').length;
		selectedCount.textContent = checkedCount;
		bulkDeleteBtn.disabled = checkedCount === 0;
	}

	// Select all checkbox
	if (selectAll) {
		selectAll.addEventListener('change', function() {
			checkboxes.forEach(cb => cb.checked = this.checked);
			updateBulkDeleteButton();
		});
	}

	// Individual checkboxes
	checkboxes.forEach(cb => {
		cb.addEventListener('change', function() {
			updateBulkDeleteButton();
			// Update select-all checkbox
			if (selectAll) {
				selectAll.checked = document.querySelectorAll('.synonym-checkbox:checked').length === checkboxes.length;
			}
		});
	});

	// Single delete buttons
	document.querySelectorAll('.delete-single-btn').forEach(btn => {
		btn.addEventListener('click', function() {
			if (confirm('Delete this synonym?')) {
				const form = document.createElement('form');
				form.method = 'post';
				form.innerHTML = `
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_nonce_field() output is already escaped by WordPress core
					echo wp_nonce_field( 'mantiload-synonyms', '_wpnonce', true, false );
					?>
					<input type="hidden" name="action" value="delete_synonym">
					<input type="hidden" name="synonym_id" value="${this.dataset.id}">
				`;
				document.body.appendChild(form);
				form.submit();
			}
		});
	});
});
</script>
