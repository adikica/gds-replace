<?php
/*
Plugin Name: Global Database Search & Replace (Dev Mode)
Description: Search for a word across the entire database (excluding specific columns and backup tables) and edit the raw content via a source code editor with direct edit links.
Version: 1.0.0
Author: Adi Kica
Text Domain: gds-replace
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Add admin menu item.
 */
add_action( 'admin_menu', 'gds_add_admin_menu' );
function gds_add_admin_menu() {
	add_menu_page(
		__( 'DB Search & Replace', 'gds-replace' ),
		__( 'DB Search', 'gds-replace' ),
		'manage_options',
		'global-db-search',
		'gds_render_admin_page',
		'dashicons-search',
		2
	);
}

/**
 * Enqueue CodeMirror and jQuery.
 */
add_action( 'admin_enqueue_scripts', 'gds_enqueue_scripts' );
function gds_enqueue_scripts() {
	wp_enqueue_script( 'jquery' );
	wp_enqueue_script( 'codemirror', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/codemirror.min.js', array(), null, true );
	wp_enqueue_script( 'codemirror-mode-htmlmixed', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/htmlmixed/htmlmixed.min.js', array( 'codemirror' ), null, true );
	wp_enqueue_style( 'codemirror-style', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/codemirror.min.css' );
	
	// Localize AJAX URL and nonce.
	wp_localize_script( 'jquery', 'gds_ajax', array(
		'ajax_url'     => admin_url( 'admin-ajax.php' ),
		'update_nonce' => wp_create_nonce( 'gds_update_nonce' ),
	) );
}

/**
 * Render admin page with search and mass replace forms.
 */
function gds_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// For the search form.
	$search_word = isset( $_POST['search_word'] ) ? sanitize_text_field( $_POST['search_word'] ) : '';

	// For the mass replace form, use separate field names.
	$mass_search_word  = isset( $_POST['mass_search_word'] ) ? sanitize_text_field( $_POST['mass_search_word'] ) : '';
	$mass_replace_word = isset( $_POST['mass_replace_word'] ) ? sanitize_text_field( $_POST['mass_replace_word'] ) : '';
	$case_sensitive    = isset( $_POST['case_sensitive'] ) ? true : false;

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__( 'Global Database Search & Replace (Dev Mode)', 'gds-replace' ) . '</h1>';

	// Search form.
	echo '<form method="post" style="margin-bottom: 20px;">';
	wp_nonce_field( 'gds_search_action', 'gds_search_nonce' );
	echo '<input type="text" name="search_word" value="' . esc_attr( $search_word ) . '" placeholder="' . esc_attr__( 'Enter word to search', 'gds-replace' ) . '" required>';
	echo '<button type="submit" class="button button-primary">' . esc_html__( 'Search', 'gds-replace' ) . '</button>';
	echo '</form>';

	// Mass replace form.
	echo '<h2>' . esc_html__( 'Mass Replace in Posts', 'gds-replace' ) . '</h2>';
	echo '<form method="post">';
	wp_nonce_field( 'gds_mass_replace_action', 'gds_mass_replace_nonce' );
	echo '<input type="text" name="mass_search_word" placeholder="' . esc_attr__( 'Word to replace', 'gds-replace' ) . '" required>';
	echo '<input type="text" name="mass_replace_word" placeholder="' . esc_attr__( 'New word', 'gds-replace' ) . '" required>';
	echo '<label><input type="checkbox" name="case_sensitive" checked> ' . esc_html__( 'Case Sensitive', 'gds-replace' ) . '</label>';
	echo '<button type="submit" class="button button-primary">' . esc_html__( 'Replace All', 'gds-replace' ) . '</button>';
	echo '</form>';

	// Process search if search word provided.
	if ( ! empty( $search_word ) && check_admin_referer( 'gds_search_action', 'gds_search_nonce' ) ) {
		gds_search_database( $search_word );
	}

	// Process mass replace if provided.
	if ( ! empty( $mass_search_word ) && ! empty( $mass_replace_word ) && check_admin_referer( 'gds_mass_replace_action', 'gds_mass_replace_nonce' ) ) {
		gds_mass_replace_posts( $mass_search_word, $mass_replace_word, $case_sensitive );
	}

	echo '</div>';

	// Modal for editing content using CodeMirror.
	echo '
	<div id="gds-edit-modal" class="gds-modal" style="display:none; position: fixed; top: 10%; left: 50%; transform: translate(-50%, 0); width: 800px; background: #fff; padding: 20px; border: 1px solid #ccc; z-index: 9999;">
		<div class="gds-modal-content">
			<span class="gds-close" style="cursor:pointer; float:right;">&times;</span>
			<h2>' . esc_html__( 'Edit Raw Content (Code Editor)', 'gds-replace' ) . '</h2>
			<form id="gds-edit-form">
				<textarea id="gds-editor"></textarea>
				<input type="hidden" id="gds-table" name="table">
				<input type="hidden" id="gds-column" name="column">
				<input type="hidden" id="gds-id" name="id">
				<input type="hidden" id="gds-primary-key" name="primary_key">
				<input type="hidden" id="gds-primary-value" name="primary_value">
				<button type="submit" class="button button-primary">' . esc_html__( 'Update', 'gds-replace' ) . '</button>
			</form>
			<p id="gds-message" style="display:none; color: green;"></p>
		</div>
	</div>';
}

/**
 * Search the entire database for the given word, excluding specific columns and backup tables.
 */
function gds_search_database( $search_word ) {
	global $wpdb;

	$search_word = trim( $search_word );
	$results     = array();
	$tables      = $wpdb->get_results( "SHOW TABLES", ARRAY_N );
	// Define columns to exclude.
	$excluded_columns = array(
		'url', 'guid', 'slug', 'filename', 'source_url', 
		'post_name', 'option_value', 'package', 'real_path', 
		'path', 'wordpress_path','meta_value','user_nicename','username','name','layers'
	);
	// Get the posts backup prefix.
	$backup_prefix = $wpdb->prefix . 'posts_backup';

	foreach ( $tables as $table ) {
		$table_name = $table[0];
		// Exclude backup tables.
		if ( 0 === strpos( $table_name, $backup_prefix ) ) {
			continue;
		}
		$columns = $wpdb->get_results( "SHOW COLUMNS FROM `$table_name`", ARRAY_A );

		foreach ( $columns as $column ) {
			$column_name = $column['Field'];
			// Skip excluded columns.
			if ( in_array( strtolower( $column_name ), $excluded_columns, true ) ) {
				continue;
			}
			$data_type = $column['Type'];

			if ( false !== strpos( $data_type, 'text' ) || false !== strpos( $data_type, 'varchar' ) || false !== strpos( $data_type, 'longtext' ) ) {

				// Exclude revisions from posts table.
				$exclude_revisions = '';
				if ( $table_name === $wpdb->posts ) {
					$exclude_revisions = " AND `post_type` != 'revision'";
				}

				$query = $wpdb->prepare(
					"SELECT * FROM `$table_name` WHERE `$column_name` LIKE %s $exclude_revisions LIMIT 10",
					'%' . $wpdb->esc_like( $search_word ) . '%'
				);
				$rows = $wpdb->get_results( $query, ARRAY_A );

				foreach ( $rows as $row ) {
					$content = $row[ $column_name ];

					// Determine the primary key dynamically.
					$primary_key = 'ID';
					foreach ( $columns as $col ) {
						if ( 'PRI' === $col['Key'] ) {
							$primary_key = $col['Field'];
							break;
						}
					}
					$primary_value = isset( $row[ $primary_key ] ) ? $row[ $primary_key ] : null;

					// If content is serialized, unserialize it for display.
					if ( is_serialized( $content ) ) {
						$unserialized_content = @unserialize( $content );
						if ( is_array( $unserialized_content ) || is_object( $unserialized_content ) ) {
							$content = print_r( $unserialized_content, true );
						}
					}

					// Only add results if the content contains the search word.
					if ( false !== strpos( $content, $search_word ) ) {
						$context = __( 'General Data', 'gds-replace' );
						if ( $table_name === $wpdb->posts ) {
							$post_type = isset( $row['post_type'] ) ? $row['post_type'] : '';
							$context   = ucfirst( $post_type );
						}

						$highlighted_snippet = htmlspecialchars( substr( $content, 0, 150 ) );

						// Generate a direct edit link if possible.
						$edit_link = '';
						if ( $table_name === $wpdb->posts ) {
							if ( isset( $row['post_type'] ) && in_array( $row['post_type'], array( 'post', 'page', 'elementor_library', 'template' ), true ) ) {
								$edit_link = admin_url( "post.php?post={$primary_value}&action=edit" );
								$edit_link = '<a href="' . esc_url( $edit_link ) . '" target="_blank">' . esc_html__( 'Edit', 'gds-replace' ) . '</a>';
							}
						} elseif ( $table_name === $wpdb->postmeta && isset( $row['post_id'] ) ) {
							$edit_link = admin_url( "post.php?post={$row['post_id']}&action=edit" );
							$edit_link = '<a href="' . esc_url( $edit_link ) . '" target="_blank">' . esc_html__( 'Edit', 'gds-replace' ) . '</a>';
						}

						$edit_button = $primary_value ? '<button class="button gds-edit-btn" data-id="' . esc_attr( $primary_value ) . '" data-table="' . esc_attr( $table_name ) . '" data-column="' . esc_attr( $column_name ) . '" data-content="' . esc_attr( $content ) . '" data-primary-key="' . esc_attr( $primary_key ) . '" data-primary-value="' . esc_attr( $primary_value ) . '">' . esc_html__( 'Edit', 'gds-replace' ) . '</button>' : '';

						$results[] = array(
							'table'       => $table_name,
							'column'      => $column_name,
							'context'     => $context,
							'snippet'     => $highlighted_snippet,
							'edit_button' => $edit_button,
							'edit_link'   => $edit_link,
						);
					}
				}
			}
		}
	}

	if ( empty( $results ) ) {
		echo '<p>' . esc_html__( 'No results found for "', 'gds-replace' ) . esc_html( $search_word ) . '"</p>';
		return;
	}

	echo '<h2>' . esc_html__( 'Search Results:', 'gds-replace' ) . '</h2>';
	echo '<table class="widefat fixed">';
	echo '<thead><tr><th>' . esc_html__( 'Table', 'gds-replace' ) . '</th><th>' . esc_html__( 'Column', 'gds-replace' ) . '</th><th>' . esc_html__( 'Context', 'gds-replace' ) . '</th><th>' . esc_html__( 'Snippet', 'gds-replace' ) . '</th><th>' . esc_html__( 'Action', 'gds-replace' ) . '</th></tr></thead><tbody>';

	foreach ( $results as $result ) {
		echo '<tr>';
		echo '<td>' . esc_html( $result['table'] ) . '</td>';
		echo '<td>' . esc_html( $result['column'] ) . '</td>';
		echo '<td>' . esc_html( $result['context'] ) . '</td>';
		echo '<td>' . esc_html( $result['snippet'] ) . '</td>';
		echo '<td>' . $result['edit_button'] . ' ' . $result['edit_link'] . '</td>';
		echo '</tr>';
	}

	echo '</tbody></table>';
}

/**
 * Perform a mass replace in posts.
 */
function gds_mass_replace_posts( $search_word, $replace_word, $case_sensitive = true ) {
	global $wpdb;

	$search_word  = trim( $search_word );
	$replace_word = trim( $replace_word );

	if ( empty( $search_word ) || empty( $replace_word ) ) {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Please provide both a search word and a replace word.', 'gds-replace' ) . '</p></div>';
		return;
	}

	// Backup the posts table.
	$backup_table = $wpdb->prefix . 'posts_backup_' . time();
	$wpdb->query( "CREATE TABLE $backup_table AS SELECT * FROM $wpdb->posts" );

	$batch_size    = 100; // Number of posts per batch.
	$offset        = 0;
	$total_updated = 0;

	while ( true ) {
		$posts = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE post_type = 'post' LIMIT %d OFFSET %d", $batch_size, $offset ),
			ARRAY_A
		);

		if ( empty( $posts ) ) {
			break;
		}

		foreach ( $posts as $post ) {
			$post_content = $post['post_content'];

			// Perform the replacement.
			if ( $case_sensitive ) {
				$post_content = str_replace( $search_word, $replace_word, $post_content );
			} else {
				$post_content = str_ireplace( $search_word, $replace_word, $post_content );
			}

			$wpdb->update(
				$wpdb->posts,
				array( 'post_content' => $post_content ),
				array( 'ID' => $post['ID'] )
			);

			$total_updated++;
		}

		$offset += $batch_size;
	}

	if ( $total_updated > 0 ) {
		echo '<div class="notice notice-success"><p>' . esc_html( $total_updated . ' ' . __( 'posts updated successfully! Backup created in table: ', 'gds-replace' ) . $backup_table ) . '</p></div>';
	} else {
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'No posts were updated.', 'gds-replace' ) . '</p></div>';
	}
}

/**
 * Handle AJAX update request.
 */
add_action( 'wp_ajax_gds_update_content', function() {
	global $wpdb;

	$table         = sanitize_text_field( $_POST['table'] );
	$column        = sanitize_text_field( $_POST['column'] );
	$primary_key   = sanitize_text_field( $_POST['primary_key'] );
	$primary_value = sanitize_text_field( $_POST['primary_value'] );
	$new_content   = stripslashes( $_POST['content'] );

	// Verify AJAX nonce.
	if ( ! isset( $_POST['update_nonce'] ) || ! wp_verify_nonce( $_POST['update_nonce'], 'gds_update_nonce' ) ) {
		wp_send_json_error( array( 'message' => __( 'Nonce verification failed.', 'gds-replace' ) ) );
		exit;
	}

	if ( $table && $column && $primary_key && $primary_value ) {
		// If new content is serialized, re-serialize it.
		if ( is_serialized( $new_content ) ) {
			$new_content = serialize( $new_content );
		}

		$result = $wpdb->update( $table, array( $column => $new_content ), array( $primary_key => $primary_value ) );

		if ( false === $result ) {
			error_log( "Database error: " . $wpdb->last_error );
			wp_send_json_error( array( 'message' => __( 'Failed to update content. Database error: ', 'gds-replace' ) . $wpdb->last_error ) );
		} else {
			wp_send_json_success( array( 'message' => __( 'Content updated successfully!', 'gds-replace' ) ) );
		}
	} else {
		wp_send_json_error( array( 'message' => __( 'Invalid input data.', 'gds-replace' ) ) );
	}
} );

/**
 * JavaScript for modal and AJAX update.
 */
add_action( 'admin_footer', function() {
	?>
	<script>
	jQuery(document).ready(function($) {
		let editor;

		$('.gds-edit-btn').on('click', function() {
			$('#gds-table').val($(this).data('table'));
			$('#gds-column').val($(this).data('column'));
			$('#gds-id').val($(this).data('id'));
			$('#gds-primary-key').val($(this).data('primary-key'));
			$('#gds-primary-value').val($(this).data('primary-value'));

			let content = $(this).data('content');
			$('#gds-edit-modal').fadeIn();

			setTimeout(() => {
				if (!editor) {
					editor = CodeMirror.fromTextArea(document.getElementById("gds-editor"), {
						mode: "htmlmixed",
						lineNumbers: true
					});
				}
				editor.setValue(content);
			}, 200);
		});

		$('.gds-close').on('click', function() {
			$('#gds-edit-modal').fadeOut();
		});

		$('#gds-edit-form').on('submit', function(e) {
			e.preventDefault();
			let content = editor.getValue();

			let data = {
				action: 'gds_update_content',
				table: $('#gds-table').val(),
				column: $('#gds-column').val(),
				primary_key: $('#gds-primary-key').val(),
				primary_value: $('#gds-primary-value').val(),
				content: content,
				update_nonce: gds_ajax.update_nonce
			};

			$.post(gds_ajax.ajax_url, data, function(response) {
				if (response.success) {
					$('#gds-message').text(response.data.message).css('color','green').fadeIn();
					setTimeout(() => { 
						$('#gds-edit-modal').fadeOut(); 
						location.reload();
					}, 1500);
				} else {
					$('#gds-message').text(response.data.message).css('color','red').fadeIn();
				}
			}).fail(function(xhr, status, error) {
				$('#gds-message').text('AJAX request failed: ' + error).css('color','red').fadeIn();
			});
		});
	});
	</script>
	<?php
} );
