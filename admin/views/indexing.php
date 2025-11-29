<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap mantiload-indexing">
	<h1><span class="mantiload-icon">⚡</span> MantiLoad Indexing</h1>

	<?php settings_errors( 'mantiload' ); ?>

	<!-- When to Reindex Info Box -->
	<div class="notice notice-info" style="margin: 20px 0; padding: 15px;">
		<h3 style="margin-top: 0;">ℹ️ When Do You Need to Reindex?</h3>

		<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
			<div>
				<h4 style="color: #00a32a; margin-top: 0;">✅ Auto-Indexed (No Action Needed)</h4>
				<ul style="margin: 10px 0; padding-left: 20px;">
					<li>Adding/editing products</li>
					<li>Changing product categories</li>
					<li>Updating prices or stock</li>
					<li>Adding attributes to products</li>
					<li>Publishing new products</li>
				</ul>
				<p style="margin: 10px 0; padding: 10px; background: #f0f6fc; border-left: 3px solid #00a32a;">
					<strong>These happen automatically!</strong> MantiLoad watches for changes and updates the index in real-time.
				</p>
			</div>

			<div>
				<h4 style="color: #d63638; margin-top: 0;">⚠️ Manual Reindex Required (Rare!)</h4>
				<ul style="margin: 10px 0; padding-left: 20px;">
					<li><strong>Initial setup</strong> (first time only)</li>
					<li><strong>Adding NEW attribute taxonomy</strong> (e.g., creating "Material" in WooCommerce → Attributes)</li>
					<li><strong>Plugin update</strong> with schema changes</li>
					<li><strong>Index corruption</strong> or errors</li>
				</ul>
				<p style="margin: 10px 0; padding: 10px; background: #fcf0f1; border-left: 3px solid #d63638;">
					<strong>You'll know when needed!</strong> Filters won't work or you'll see errors.
				</p>
			</div>
		</div>

		<div style="margin-top: 15px; padding: 10px; background: #fff3cd; border-left: 3px solid #f0b849;">
			<strong>💡 Tip:</strong> If search works but filters don't, you need a <strong>full reindex</strong> (not fast-reindex).
		</div>
	</div>

	<div class="mantiload-indexing-status">
		<h2>Indexing Status</h2>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th>Post Type</th>
					<th>Index Name</th>
					<th>Indexed</th>
					<th>Total</th>
					<th>Progress</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $stats as $post_type => $mantiload_stat ): ?>
				<tr>
					<td><strong><?php echo esc_html( ucfirst( $post_type ) ); ?></strong></td>
					<td><code><?php echo esc_html( $mantiload_stat['index'] ); ?></code></td>
					<td><?php echo number_format( $mantiload_stat['indexed'] ); ?></td>
					<td><?php echo number_format( $mantiload_stat['total'] ); ?></td>
					<td>
						<div class="mantiload-progress-bar">
							<div class="progress" style="width: <?php echo esc_attr( $mantiload_stat['percentage'] ); ?>%"></div>
						</div>
					</td>
					<td>
						<span class="percentage"><?php echo esc_html( $mantiload_stat['percentage'] ); ?>%</span>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	
	<div class="mantiload-indexing-actions">
		<h2>Indexing Actions</h2>

		<!-- Full Reindex with Progress Bar & Abort -->
		<div style="margin-bottom: 20px;">
			<button type="button" id="mantiload-reindex-ajax" class="button button-primary button-hero">
				<span class="dashicons dashicons-update" style="margin-top: 3px;"></span> Full Reindex (All Fields + Filters)
			</button>
			<button type="button" id="mantiload-abort-ajax" class="button button-secondary button-hero" style="display:none; margin-left: 10px;">
				<span class="dashicons dashicons-no" style="margin-top: 3px;"></span> Abort Indexing
			</button>
			<p class="description">
				<strong>Full reindex with ALL fields</strong> including categories, attributes, and filter data.
				<br>Use this for initial setup or when filters don't work.
				<span style="color: #00a32a;"><strong>Individual product changes are auto-indexed!</strong></span>
			</p>
		</div>

		<!-- Progress Container -->
		<div id="mantiload-progress-container" style="display:none; margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
			<div style="margin-bottom: 10px;">
				<div id="mantiload-progress-text" style="font-size: 14px; font-weight: 600; margin-bottom: 8px;">Starting...</div>
				<div style="background: #f0f0f1; height: 30px; border-radius: 4px; overflow: hidden;">
					<div id="mantiload-progress-bar" style="background: linear-gradient(90deg, #00a32a 0%, #008a20 100%); height: 100%; width: 0%; transition: width 0.3s; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 12px;"></div>
				</div>
				<div id="mantiload-progress-stats" style="font-size: 12px; color: #646970; margin-top: 8px;"></div>
			</div>
		</div>

		<hr style="margin: 30px 0;">

		<h3>Maintenance Actions</h3>
		<form method="post" class="mantiload-actions-form">
			<?php wp_nonce_field( 'mantiload-action' ); ?>

			<div class="action-buttons">
				<button type="submit" name="mantiload_action" value="create_indexes" class="button button-secondary">
					<span class="dashicons dashicons-plus-alt"></span> Create Indexes
				</button>

				<button type="submit" name="mantiload_action" value="truncate_indexes" class="button button-secondary" onclick="return confirm('⚠️ WARNING: This will DELETE ALL indexed data from MantiCore!\n\nAre you sure you want to clear all indexes?');" style="color: #d63638;">
					<span class="dashicons dashicons-trash"></span> Clear All Indexes
				</button>

				<button type="submit" name="mantiload_action" value="optimize_indexes" class="button button-secondary">
					<span class="dashicons dashicons-performance"></span> Optimize Indexes
				</button>
			</div>
		</form>
	</div>
	
	<div class="mantiload-cli-commands">
		<h2>WP-CLI Commands</h2>
		<p>For better performance on large sites, use WP-CLI commands:</p>
		<div class="code-block">
			<code>wp mantiload create-indexes</code> - Create Manticore indexes<br>
			<code>wp mantiload reindex</code> - <strong>Full reindex</strong> with all fields (categories, attributes, filters)<br>
			<code>wp mantiload fast-reindex</code> - <strong>Quick reindex</strong> (search only, no filter data)<br>
			<code>wp mantiload reindex --batch-size=200</code> - Reindex with custom batch size<br>
			<code>wp mantiload truncate</code> - Clear all indexes (with confirmation)<br>
			<code>wp mantiload optimize</code> - Optimize all indexes<br>
			<code>wp mantiload stats</code> - Show indexing statistics
		</div>

		<div style="margin-top: 15px; padding: 12px; background: #f0f6fc; border-left: 3px solid #2271b1;">
			<strong>💡 Which command to use?</strong><br>
			• <code>reindex</code> - Initial setup, filters not working, new attributes added<br>
			• <code>fast-reindex</code> - Daily price/stock updates, quick refresh
		</div>
	</div>
</div>
