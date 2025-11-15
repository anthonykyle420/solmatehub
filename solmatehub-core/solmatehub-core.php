<?php
/**
 * Plugin Name: SolmateHub Core
 * Description: Custom functionality for SolmateHub - User roles, Listings, and all backend logic
 * Version: 2.3.0
 * Author: Your Name
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SOLMATEHUB_VERSION', '2.3.0');
define('SOLMATEHUB_PATH', plugin_dir_path(__FILE__));
define('SOLMATEHUB_URL', plugin_dir_url(__FILE__));

/**
 * Main Plugin Class
 */
class SolmateHub_Core {
    
    public function __construct() {
        // Activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // Init hooks
        add_action('init', array($this, 'register_post_types'));
        add_action('init', array($this, 'register_taxonomies'));
        add_action('init', array($this, 'register_user_roles'));
        
        // Admin hooks
        add_action('show_user_profile', array($this, 'add_user_meta_fields'));
        add_action('edit_user_profile', array($this, 'add_user_meta_fields'));
        add_action('personal_options_update', array($this, 'save_user_meta_fields'));
        add_action('edit_user_profile_update', array($this, 'save_user_meta_fields'));
        
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Listing status management
        add_action('transition_post_status', array($this, 'set_listing_pending_on_publish'), 10, 3);
        add_action('add_meta_boxes', array($this, 'add_listing_status_meta_box'));
        add_action('save_post_listing', array($this, 'save_listing_status'));

        // AJAX handlers for verification
add_action('wp_ajax_solmatehub_create_verification', array($this, 'ajax_create_verification'));
add_action('wp_ajax_solmatehub_upload_verification_photo', array($this, 'ajax_upload_verification_photo'));


        
        // Listing limit check - TEMPORARILY DISABLED (Manual control for now)
// add_action('transition_post_status', array($this, 'check_listing_limit_on_transition'), 10, 3);
// add_filter('wp_insert_post_data', array($this, 'prevent_exceeding_limit'), 10, 2);
// add_action('admin_notices', array($this, 'display_limit_notices'));
        
        // Admin columns
        add_filter('manage_listing_posts_columns', array($this, 'add_listing_columns'));
        add_action('manage_listing_posts_custom_column', array($this, 'display_listing_columns'), 10, 2);
    }



          /**
 * AJAX: Create verification request
 */
public function ajax_create_verification() {
    check_ajax_referer('solmatehub_verification', 'nonce');
    
    $listing_id = intval($_POST['listing_id']);
    $user_id = get_current_user_id();
    
    $result = $this->create_verification_request($listing_id, $user_id);
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}

/**
 * AJAX: Upload verification photo
 */
public function ajax_upload_verification_photo() {
    check_ajax_referer('solmatehub_verification', 'nonce');
    
    global $wpdb;
    
    $verification_id = intval($_POST['verification_id']);
    
    // Handle file upload
    if (!empty($_FILES['verification_photo'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        
        $uploadedfile = $_FILES['verification_photo'];
        $upload_overrides = array('test_form' => false);
        $movefile = wp_handle_upload($uploadedfile, $upload_overrides);
        
        if ($movefile && !isset($movefile['error'])) {
            $table_name = $wpdb->prefix . 'solmatehub_verifications';
            
            $updated = $wpdb->update(
                $table_name,
                array('photo_url' => $movefile['url']),
                array('id' => $verification_id),
                array('%s'),
                array('%d')
            );
            
            if ($updated) {
                wp_send_json_success(array('message' => 'Photo uploaded successfully!', 'url' => $movefile['url']));
            }
        } else {
            wp_send_json_error(array('message' => $movefile['error']));
        }
    }
    
    wp_send_json_error(array('message' => 'No file uploaded.'));
}






    
    /**
 * Plugin Activation
 */
public function activate() {
    $this->register_post_types();
    $this->register_taxonomies();
    $this->register_user_roles();
    
    // Set default global listing limit
    if (!get_option('solmatehub_default_listing_limit')) {
        update_option('solmatehub_default_listing_limit', 3);
    }
    
    // Create verification requests table
    $this->create_verification_table();
    
    flush_rewrite_rules();
}

/**
 * Create verification requests table
 */
private function create_verification_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'solmatehub_verifications';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        listing_id bigint(20) NOT NULL,
        user_id bigint(20) NOT NULL,
        verification_code varchar(20) NOT NULL,
        photo_url varchar(255) DEFAULT NULL,
        status varchar(20) DEFAULT 'pending',
        rejection_reason text DEFAULT NULL,
        submitted_date datetime DEFAULT CURRENT_TIMESTAMP,
        reviewed_date datetime DEFAULT NULL,
        reviewed_by bigint(20) DEFAULT NULL,
        PRIMARY KEY (id),
        KEY listing_id (listing_id),
        KEY user_id (user_id),
        KEY status (status)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

     /**
 * Generate unique verification code
 */
private function generate_verification_code() {
    $prefix = 'SH';
    $numbers = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
    return $prefix . '-' . $numbers;
}

/**
 * Create verification request for a listing
 */
public function create_verification_request($listing_id, $user_id = null) {
    global $wpdb;
    
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    // Check if listing belongs to user
    // Check if listing exists and is valid
$listing = get_post($listing_id);
if (!$listing || $listing->post_type !== 'listing') {
    return array('success' => false, 'message' => 'Invalid listing.');
}

// Check if user owns the listing OR is admin
$is_owner = ($listing->post_author == $user_id);
$is_admin = current_user_can('manage_options');

if (!$is_owner && !$is_admin) {
    return array('success' => false, 'message' => 'Permission denied. You can only verify your own listings.');
}
    
    // Check if already has a pending verification request
    $table_name = $wpdb->prefix . 'solmatehub_verifications';
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE listing_id = %d AND status = 'pending'",
        $listing_id
    ));
    
    if ($existing) {
        return array(
            'success' => true,
            'verification_id' => $existing->id,
            'code' => $existing->verification_code,
            'message' => 'Verification request already exists.'
        );
    }
    
    // Generate unique code
    $code = $this->generate_verification_code();
    
    // Insert verification request
    $inserted = $wpdb->insert(
        $table_name,
        array(
            'listing_id' => $listing_id,
            'user_id' => $user_id,
            'verification_code' => $code,
            'status' => 'pending',
            'submitted_date' => current_time('mysql')
        ),
        array('%d', '%d', '%s', '%s', '%s')
    );
    
    if ($inserted) {
        return array(
            'success' => true,
            'verification_id' => $wpdb->insert_id,
            'code' => $code,
            'message' => 'Verification request created successfully.'
        );
    }
    
    return array('success' => false, 'message' => 'Failed to create verification request.');
}

/**
 * Get verification request by listing ID
 */
public function get_verification_request($listing_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'solmatehub_verifications';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE listing_id = %d ORDER BY id DESC LIMIT 1",
        $listing_id
    ));
}



    
    /**
     * Register Custom Post Type: Listing
     */
    public function register_post_types() {
        $labels = array(
            'name'               => 'Listings',
            'singular_name'      => 'Listing',
            'add_new'            => 'Add New Listing',
            'add_new_item'       => 'Add New Listing',
            'edit_item'          => 'Edit Listing',
            'new_item'           => 'New Listing',
            'view_item'          => 'View Listing',
            'search_items'       => 'Search Listings',
            'not_found'          => 'No listings found',
            'not_found_in_trash' => 'No listings found in trash',
            'menu_name'          => 'Listings'
        );
        
        $args = array(
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'query_var'           => true,
            'rewrite'             => array('slug' => 'profile'),
            'capability_type'     => 'post',
            'has_archive'         => true,
            'hierarchical'        => false,
            'menu_position'       => 5,
            'menu_icon'           => 'dashicons-groups',
            'supports'            => array('title', 'editor', 'thumbnail', 'author'),
            'show_in_rest'        => true,
        );
        
        register_post_type('listing', $args);
    }
    
    /**
     * Register Taxonomies
     */
    public function register_taxonomies() {
        // Category Taxonomy
        register_taxonomy('listing_category', 'listing', array(
            'label'        => 'Categories',
            'rewrite'      => array('slug' => 'category'),
            'hierarchical' => true,
            'show_in_rest' => true,
        ));
        
        // Location Taxonomy
        register_taxonomy('listing_location', 'listing', array(
            'label'        => 'Locations',
            'rewrite'      => array('slug' => 'location'),
            'hierarchical' => true,
            'show_in_rest' => true,
        ));
        
        // Auto-create categories if they don't exist
        if (!term_exists('Male', 'listing_category')) {
            wp_insert_term('Male', 'listing_category');
        }
        if (!term_exists('Female', 'listing_category')) {
            wp_insert_term('Female', 'listing_category');
        }
        if (!term_exists('Shemale', 'listing_category')) {
            wp_insert_term('Shemale', 'listing_category');
        }
    }
    
    /**
     * Register Custom User Roles
     */
    public function register_user_roles() {
        // User Role (can browse and bookmark)
        add_role('solmate_user', 'User', array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
        ));
        
        // Adviser Role (can create listings)
        add_role('solmate_adviser', 'Adviser', array(
            'read' => true,
            'edit_posts' => true,
            'delete_posts' => true,
            'publish_posts' => true,
            'upload_files' => true,
        ));
    }
    
    /**
     * Add User Meta Fields in Admin Profile
     */
    public function add_user_meta_fields($user) {
        $user_listing_count = $this->get_user_listing_count($user->ID);
        $user_limit = get_user_meta($user->ID, 'listing_limit', true);
        $global_limit = get_option('solmatehub_default_listing_limit', 3);
        $effective_limit = !empty($user_limit) ? $user_limit : $global_limit;
        
        ?>
        <h3>SolmateHub User Settings</h3>
        <table class="form-table">
            <tr>
                <th><label>Current Listings</label></th>
                <td>
                    <p style="font-size: 18px; font-weight: bold; color: <?php echo ($user_listing_count >= $effective_limit) ? '#dc3232' : '#2271b1'; ?>;">
                        <?php echo $user_listing_count; ?> / <?php echo $effective_limit; ?> listings
                    </p>
                    <span class="description">
                        This user has created <?php echo $user_listing_count; ?> listing(s) out of <?php echo $effective_limit; ?> allowed.
                        <?php if ($user_listing_count >= $effective_limit) : ?>
                            <strong style="color: #dc3232;">⚠️ Limit reached!</strong>
                        <?php endif; ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th><label for="user_credits">Credits</label></th>
                <td>
                    <input type="number" name="user_credits" id="user_credits" value="<?php echo esc_attr(get_user_meta($user->ID, 'user_credits', true)); ?>" class="regular-text" min="0" />
                    <span class="description">Manually add/remove credits for this user.</span>
                </td>
            </tr>
            <tr>
                <th><label for="listing_limit">Listing Limit Override</label></th>
                <td>
                    <input type="number" name="listing_limit" id="listing_limit" value="<?php echo esc_attr($user_limit); ?>" class="regular-text" min="1" max="100" placeholder="<?php echo $global_limit; ?>" />
                    <span class="description">
                        Leave empty to use global default (<?php echo $global_limit; ?>). 
                        Set a custom number to override for this specific user.
                    </span>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Save User Meta Fields
     */
    public function save_user_meta_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }
        
        if (isset($_POST['user_credits'])) {
            update_user_meta($user_id, 'user_credits', intval($_POST['user_credits']));
        }
        
        if (isset($_POST['listing_limit'])) {
            $limit = intval($_POST['listing_limit']);
            if ($limit > 0) {
                update_user_meta($user_id, 'listing_limit', $limit);
            } else {
                delete_user_meta($user_id, 'listing_limit'); // Use global default
            }
        }
    }
    
    /**
     * Get user's current listing count
     */
    private function get_user_listing_count($user_id) {
        $args = array(
            'post_type' => 'listing',
            'author' => $user_id,
            'post_status' => array('publish', 'pending', 'draft'),
            'posts_per_page' => -1
        );
        $query = new WP_Query($args);
        return $query->found_posts;
    }
    
    /**
     * Check listing limit when post status transitions
     */
    public function check_listing_limit_on_transition($new_status, $old_status, $post) {
        // Only for listing post type
        if ($post->post_type !== 'listing') {
            return;
        }
        
        // Skip for admins
        if (current_user_can('manage_options')) {
            return;
        }
        
        // Only check when transitioning TO publish or pending FROM auto-draft or draft
        if (($new_status === 'publish' || $new_status === 'pending') && 
            ($old_status === 'auto-draft' || $old_status === 'draft' || $old_status === 'new')) {
            
            $author_id = $post->post_author;
            
            // Get limits
            $user_limit = get_user_meta($author_id, 'listing_limit', true);
            $global_limit = get_option('solmatehub_default_listing_limit', 3);
            $effective_limit = !empty($user_limit) ? intval($user_limit) : intval($global_limit);
            
            // Count user's existing published and pending listings
            $args = array(
                'post_type' => 'listing',
                'author' => $author_id,
                'post_status' => array('publish', 'pending'),
                'posts_per_page' => -1,
                'exclude' => array($post->ID),
                'fields' => 'ids'
            );
            $query = new WP_Query($args);
            $current_count = $query->found_posts;
            
            // Check if limit exceeded
            if ($current_count >= $effective_limit) {
                // Store error in user meta
                update_user_meta($author_id, 'listing_limit_exceeded', array(
                    'post_id' => $post->ID,
                    'current' => $current_count,
                    'limit' => $effective_limit,
                    'time' => time()
                ));
            } else {
                // Clear any previous limit exceeded flag
                delete_user_meta($author_id, 'listing_limit_exceeded');
            }
        }
    }
    
    /**
     * Prevent publishing/pending if limit exceeded
     */
    public function prevent_exceeding_limit($data, $postarr) {
        // Only for listing post type
        if ($data['post_type'] !== 'listing') {
            return $data;
        }
        
        // Skip for admins
        if (current_user_can('manage_options')) {
            return $data;
        }
        
        // Get post author
        $author_id = isset($postarr['post_author']) ? $postarr['post_author'] : get_current_user_id();
        
        // Check if user has exceeded limit flag
        $limit_exceeded = get_user_meta($author_id, 'listing_limit_exceeded', true);
        
        if ($limit_exceeded && is_array($limit_exceeded)) {
            // Check if this is the post that exceeded limit
            if (isset($postarr['ID']) && $postarr['ID'] == $limit_exceeded['post_id']) {
                // Force it to stay as draft
                if ($data['post_status'] === 'publish' || $data['post_status'] === 'pending') {
                    $data['post_status'] = 'draft';
                    
                    // Set error message in transient
                    set_transient('solmatehub_limit_exceeded_' . $author_id, $limit_exceeded, 120);
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Display limit exceeded notice
     */
    public function display_limit_notices() {
        $user_id = get_current_user_id();
        
        // Skip for admins
        if (current_user_can('manage_options')) {
            return;
        }
        
        // Check for limit exceeded transient
        $limit_data = get_transient('solmatehub_limit_exceeded_' . $user_id);
        
        if ($limit_data && is_array($limit_data)) {
            ?>
            <div class="notice notice-error is-dismissible" style="border-left: 4px solid #dc3232; padding: 15px;">
                <h3 style="margin-top: 0; color: #dc3232;">⚠️ Listing Limit Reached!</h3>
                <p style="font-size: 14px;">
                    <strong>You have reached your listing limit (<?php echo $limit_data['current']; ?>/<?php echo $limit_data['limit']; ?>).</strong><br>
                    Your new listing has been saved as a <strong>Draft</strong> but cannot be published or submitted for approval.
                </p>
                <p style="font-size: 14px; margin-bottom: 10px;"><strong>What you can do:</strong></p>
                <ul style="margin-left: 25px; list-style: disc; font-size: 14px;">
                    <li><strong>Delete an existing listing</strong> to make room for this new one.</li>
                    <li><strong>Edit an existing listing</strong> instead of creating a new one.</li>
                    <li><strong>Contact the website administrator</strong> to request a limit increase.</li>
                </ul>
                <p style="margin-top: 15px; padding: 10px; background: #fff3cd; border-radius: 4px; font-size: 13px;">
                    💡 <strong>Tip:</strong> Go to <a href="<?php echo admin_url('edit.php?post_type=listing'); ?>" style="text-decoration: none; color: #2271b1; font-weight: bold;">All Listings</a> to manage your existing listings.
                </p>
            </div>
            <?php
            
            // Delete transient after showing
            delete_transient('solmatehub_limit_exceeded_' . $user_id);
        }
    }
    
    /**
     * Set listing to pending when first published
     */
    public function set_listing_pending_on_publish($new_status, $old_status, $post) {
        if ($post->post_type !== 'listing') {
            return;
        }
        
        // If it's a new listing being published
        if ($new_status === 'publish' && $old_status !== 'publish') {
            // Check if listing_status meta doesn't exist yet
            $listing_status = get_post_meta($post->ID, 'listing_status', true);
            if (empty($listing_status)) {
                update_post_meta($post->ID, 'listing_status', 'pending');
            }
        }
    }
    
    /**
     * Add meta box for listing status
     */
    public function add_listing_status_meta_box() {
        add_meta_box(
            'listing_status_meta_box',
            'Listing Approval Status',
            array($this, 'render_listing_status_meta_box'),
            'listing',
            'side',
            'high'
        );
        
        add_meta_box(
            'listing_featured_meta_box',
            'Featured, VIP & Verification',
            array($this, 'render_listing_features_meta_box'),
            'listing',
            'side',
            'high'
        );

         add_meta_box(
    'listing_verification_request_meta_box',
    'Photo Verification Request',
    array($this, 'render_verification_request_meta_box'),
    'listing',
    'side',
    'default'
);


    } 


        /**
 * Render verification request meta box
 */
public function render_verification_request_meta_box($post) {
    $is_verified = get_post_meta($post->ID, 'listing_verified', true);
    $verification_request = $this->get_verification_request($post->ID);
    
    ?>
    <div style="padding: 10px 0;">
        <?php if ($is_verified == '1') : ?>
            <!-- Already Verified -->
            <div style="padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; text-align: center;">
                <span style="font-size: 48px;">✅</span>
                <h3 style="color: #155724; margin: 10px 0 5px 0;">Verified!</h3>
                <p style="margin: 0; color: #155724; font-size: 13px;">This listing is verified.</p>
            </div>
            
        <?php elseif ($verification_request && $verification_request->status === 'pending') : ?>
            <!-- Pending Verification -->
            <div style="padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">
                <h4 style="margin: 0 0 10px 0; color: #856404;">⏳ Verification Pending</h4>
                <p style="margin: 0 0 10px 0; font-size: 13px; color: #856404;">
                    <strong>Code:</strong> <span style="font-size: 18px; font-weight: bold; color: #000;"><?php echo esc_html($verification_request->verification_code); ?></span>
                </p>
                <?php if ($verification_request->photo_url) : ?>
                    <p style="margin: 0; font-size: 12px; color: #856404;">
                        ✅ Photo submitted. Waiting for admin approval.
                    </p>
                <?php else : ?>
                    <a href="<?php echo admin_url('admin.php?page=solmatehub-verify-listing&listing_id=' . $post->ID); ?>" class="button button-primary" style="width: 100%; text-align: center; margin-top: 10px;">
                        📸 Upload Verification Photo
                    </a>
                <?php endif; ?>
            </div>
            
        <?php elseif ($verification_request && $verification_request->status === 'rejected') : ?>
            <!-- Rejected - Can Re-apply -->
            <div style="padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">
                <h4 style="margin: 0 0 10px 0; color: #721c24;">❌ Verification Rejected</h4>
                <?php if ($verification_request->rejection_reason) : ?>
                    <p style="margin: 0 0 10px 0; font-size: 12px; color: #721c24;">
                        <strong>Reason:</strong> <?php echo esc_html($verification_request->rejection_reason); ?>
                    </p>
                <?php endif; ?>
                <a href="#" class="button button-secondary solmatehub-request-verification" data-listing-id="<?php echo $post->ID; ?>" style="width: 100%; text-align: center;">
                    🔄 Request Verification Again
                </a>
            </div>
            
        <?php else : ?>
            <!-- Not Verified - Can Request -->
            <div style="padding: 15px; background: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 4px; text-align: center;">
                <span style="font-size: 32px;">📸</span>
                <h4 style="margin: 10px 0 5px 0; color: #004085;">Get Verified!</h4>
                <p style="margin: 0 0 15px 0; font-size: 12px; color: #004085;">
                    Increase trust by verifying this listing with a photo selfie.
                </p>
                <a href="#" class="button button-primary solmatehub-request-verification" data-listing-id="<?php echo $post->ID; ?>" style="width: 100%;">
                    ✅ Start Verification
                </a>
            </div>
        <?php endif; ?>
        
        <script>
        jQuery(document).ready(function($) {
            $('.solmatehub-request-verification').on('click', function(e) {
                e.preventDefault();
                var listingId = $(this).data('listing-id');
                
                if (confirm('Start verification process for this listing?')) {
                    $.post(ajaxurl, {
                        action: 'solmatehub_create_verification',
                        listing_id: listingId,
                        nonce: '<?php echo wp_create_nonce('solmatehub_verification'); ?>'
                    }, function(response) {
                        if (response.success) {
                            window.location.href = '<?php echo admin_url('admin.php?page=solmatehub-verify-listing&listing_id='); ?>' + listingId;
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    });
                }
            });
        });
        </script>
    </div>
    <?php
}


    
    /**
     * Render listing status meta box
     */
    public function render_listing_status_meta_box($post) {
        wp_nonce_field('listing_status_nonce', 'listing_status_nonce_field');
        
        $status = get_post_meta($post->ID, 'listing_status', true);
        $status = !empty($status) ? $status : 'pending';
        
        ?>
        <div style="padding: 10px 0;">
            <p><strong>Current Status:</strong></p>
            <select name="listing_status" id="listing_status" style="width: 100%;">
                <option value="pending" <?php selected($status, 'pending'); ?>>⏳ Pending Review</option>
                <option value="approved" <?php selected($status, 'approved'); ?>>✅ Approved</option>
                <option value="rejected" <?php selected($status, 'rejected'); ?>>❌ Rejected</option>
            </select>
            
            <p style="margin-top: 15px; padding: 10px; background: #f0f0f1; border-radius: 4px; font-size: 12px;">
                <strong>Note:</strong><br>
                • <strong>Pending:</strong> Waiting for admin approval<br>
                • <strong>Approved:</strong> Visible on website<br>
                • <strong>Rejected:</strong> Not visible, needs changes
            </p>
        </div>
        <?php
    }
    
    /**
     * Render listing features meta box
     */
    public function render_listing_features_meta_box($post) {
        wp_nonce_field('listing_features_nonce', 'listing_features_nonce_field');
        
        $is_featured = get_post_meta($post->ID, 'is_featured', true);
        $is_vip = get_post_meta($post->ID, 'listing_vip', true);
        $is_verified = get_post_meta($post->ID, 'listing_verified', true);
        
        ?>
        <div style="padding: 10px 0;">
            <p>
                <label style="display: flex; align-items: center; gap: 8px; padding: 8px; background: #fff9e6; border-radius: 4px; cursor: pointer;">
                    <input type="checkbox" name="is_featured" value="1" <?php checked($is_featured, '1'); ?> />
                    <span><strong>👑 Featured Listing</strong></span>
                </label>
                <small style="display: block; margin-top: 5px; margin-left: 8px;">Show in "Featured Profiles" section with crown icon</small>
            </p>
            
            <hr style="margin: 15px 0;">
            
            <p>
                <label style="display: flex; align-items: center; gap: 8px; padding: 8px; background: #f3e8ff; border-radius: 4px; cursor: pointer;">
                    <input type="checkbox" name="listing_vip" value="1" <?php checked($is_vip, '1'); ?> />
                    <span><strong>⭐ VIP Listing</strong></span>
                </label>
                <small style="display: block; margin-top: 5px; margin-left: 8px;">Show purple VIP star badge on this listing</small>
            </p>
            
            <hr style="margin: 15px 0;">
            
            <p>
                <label style="display: flex; align-items: center; gap: 8px; padding: 8px; background: #e0f2fe; border-radius: 4px; cursor: pointer;">
                    <input type="checkbox" name="listing_verified" value="1" <?php checked($is_verified, '1'); ?> />
                    <span><strong>✅ Verified Listing</strong></span>
                </label>
                <small style="display: block; margin-top: 5px; margin-left: 8px;">Show blue verification tick on this listing</small>
            </p>
            
            <p style="margin-top: 15px; padding: 8px; background: #fff3cd; border-radius: 4px; font-size: 11px;">
                <strong>⚠️ Note:</strong> In future, verification will require photo selfie with code. For now, you can manually verify.
            </p>
        </div>
        <?php
    }
    
    /**
     * Save listing status and features
     */
    public function save_listing_status($post_id) {
        // Check nonce
        if (!isset($_POST['listing_status_nonce_field']) || !wp_verify_nonce($_POST['listing_status_nonce_field'], 'listing_status_nonce')) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save listing status
        if (isset($_POST['listing_status'])) {
            update_post_meta($post_id, 'listing_status', sanitize_text_field($_POST['listing_status']));
        }
        
        // Save featured, VIP, and verified status
        if (isset($_POST['listing_features_nonce_field']) && wp_verify_nonce($_POST['listing_features_nonce_field'], 'listing_features_nonce')) {
            update_post_meta($post_id, 'is_featured', isset($_POST['is_featured']) ? '1' : '0');
            update_post_meta($post_id, 'listing_vip', isset($_POST['listing_vip']) ? '1' : '0');
            update_post_meta($post_id, 'listing_verified', isset($_POST['listing_verified']) ? '1' : '0');
        }
    }
    
    /**
     * Add custom columns to listing admin table
     */
    public function add_listing_columns($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            // Add status column after title
            if ($key === 'title') {
                $new_columns['listing_status'] = 'Status';
                $new_columns['listing_featured'] = 'Featured';
                $new_columns['listing_vip'] = 'VIP';
                $new_columns['listing_verified'] = 'Verified';
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Display custom column content
     */
    public function display_listing_columns($column, $post_id) {
        switch ($column) {
            case 'listing_status':
                $status = get_post_meta($post_id, 'listing_status', true);
                $status = !empty($status) ? $status : 'pending';
                
                if ($status === 'approved') {
                    echo '<span style="color: green; font-weight: bold;">✅ Approved</span>';
                } elseif ($status === 'rejected') {
                    echo '<span style="color: red; font-weight: bold;">❌ Rejected</span>';
                } else {
                    echo '<span style="color: orange; font-weight: bold;">⏳ Pending</span>';
                }
                break;
                
            case 'listing_featured':
                $is_featured = get_post_meta($post_id, 'is_featured', true);
                echo $is_featured == '1' ? '<span style="font-size: 18px;">👑</span>' : '-';
                break;
                
            case 'listing_vip':
                $is_vip = get_post_meta($post_id, 'listing_vip', true);
                echo $is_vip == '1' ? '<span style="font-size: 18px;">⭐</span>' : '-';
                break;
                
            case 'listing_verified':
                $is_verified = get_post_meta($post_id, 'listing_verified', true);
                echo $is_verified == '1' ? '<span style="font-size: 18px;">✅</span>' : '-';
                break;
        }
    }
    
    /**
     * Add Admin Menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'SolmateHub Settings',
            'SolmateHub',
            'manage_options',
            'solmatehub-settings',
            array($this, 'settings_page'),
            'dashicons-heart',
            3
        );
        
        // Pending listings submenu
        add_submenu_page(
            'solmatehub-settings',
            'Pending Listings',
            'Pending Listings',
            'manage_options',
            'solmatehub-pending',
            array($this, 'pending_listings_page')
        );
        
        // Settings submenu
        add_submenu_page(
            'solmatehub-settings',
            'Settings',
            'Settings',
            'manage_options',
            'solmatehub-global-settings',
            array($this, 'global_settings_page')
        );

         
// Verification page (hidden from menu, accessed via direct link)
add_submenu_page(
    null, // Hidden from menu
    'Verify Listing',
    'Verify Listing',
    'read', // Any logged-in user can access
    'solmatehub-verify-listing',
    array($this, 'verification_page')
);


    }  


  /**
 * Verification Page (CAMERA ONLY - Live Selfie Required)
 */
public function verification_page() {
    // Get listing ID from URL
    $listing_id = isset($_GET['listing_id']) ? intval($_GET['listing_id']) : 0;
    
    if (!$listing_id) {
        echo '<div class="wrap"><h1>Invalid Request</h1><p>No listing ID provided.</p></div>';
        return;
    }
    
    // Get listing
    $listing = get_post($listing_id);
    if (!$listing || $listing->post_type !== 'listing') {
        echo '<div class="wrap"><h1>Invalid Listing</h1><p>Listing not found.</p></div>';
        return;
    }
    
    // Check permission
    $user_id = get_current_user_id();
    $is_owner = ($listing->post_author == $user_id);
    $is_admin = current_user_can('manage_options');
    
    if (!$is_owner && !$is_admin) {
        echo '<div class="wrap"><h1>Permission Denied</h1><p>You can only verify your own listings.</p></div>';
        return;
    }
    
    // Get verification request
    $verification = $this->get_verification_request($listing_id);
    
    if (!$verification || $verification->status !== 'pending') {
        echo '<div class="wrap"><h1>No Pending Verification</h1><p>Please start a verification request first.</p></div>';
        return;
    }
    
    // Check if browser supports camera API
    $is_localhost = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false || strpos($_SERVER['HTTP_HOST'], '.local') !== false);
    $is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    
    ?>
    <div class="wrap" style="max-width: 900px; margin: 40px auto;">
        <h1 style="text-align: center; margin-bottom: 10px;">📸 Live Photo Verification</h1>
        <p style="text-align: center; color: #666; margin-bottom: 30px;">Listing: <strong><?php echo esc_html($listing->post_title); ?></strong></p>
        
        <!-- Security Notice -->
        <?php if (!$is_localhost && !$is_https) : ?>
        <div style="background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px; padding: 20px; margin-bottom: 30px;">
            <h3 style="margin-top: 0; color: #721c24;">⚠️ Camera Access Not Available</h3>
            <p style="color: #721c24; margin-bottom: 10px;">
                For security reasons, modern browsers only allow camera access on:
            </p>
            <ul style="color: #721c24; margin-left: 25px;">
                <li><strong>HTTPS websites</strong> (secure connection)</li>
                <li><strong>Localhost</strong> (development environment)</li>
            </ul>
            <p style="color: #721c24; margin: 15px 0 0 0;">
                <strong>Current URL:</strong> <code><?php echo $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']; ?></code><br>
                <strong>Status:</strong> <span style="color: #dc3545; font-weight: bold;">❌ Camera Not Allowed</span>
            </p>
            <p style="margin-top: 15px; padding: 10px; background: #fff3cd; border-radius: 4px; color: #856404;">
                <strong>Solution:</strong> Please contact the website administrator to enable HTTPS for camera access.
            </p>
        </div>
        <?php endif; ?>
        
        <!-- Verification Instructions -->
        <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 20px; margin-bottom: 30px;">
            <h2 style="margin-top: 0; color: #856404;">📋 Verification Steps</h2>
            <ol style="margin-left: 20px; color: #856404; line-height: 2;">
                <li>
                    <strong>Write this code on a plain white paper:</strong> 
                    <div style="font-size: 32px; font-weight: bold; color: #000; background: #fff; padding: 15px 25px; border-radius: 8px; display: inline-block; margin: 15px 0; border: 3px solid #ffc107; letter-spacing: 2px;">
                        <?php echo esc_html($verification->verification_code); ?>
                    </div>
                    <br><small style="color: #856404;">✍️ Use a black marker or pen for clear visibility</small>
                </li>
                <li><strong>Hold the paper next to your face</strong> - both your face and the code should be clearly visible</li>
                <li><strong>Click "Open Camera"</strong> button below and allow camera access when prompted</li>
                <li><strong>Take a live selfie</strong> - make sure the code is readable in the photo</li>
                <li><strong>Submit for admin approval</strong> - admin will verify manually</li>
            </ol>
            
            <div style="margin-top: 20px; padding: 15px; background: #fff; border-left: 4px solid #dc3545; border-radius: 4px;">
                <strong style="color: #dc3545;">🚫 Important Security Rules:</strong>
                <ul style="margin: 10px 0 0 20px; color: #721c24; line-height: 1.8;">
                    <li><strong>Only live camera photos are accepted</strong> - no file uploads allowed</li>
                    <li>Photos must be taken <strong>in real-time</strong> - not pre-taken</li>
                    <li><strong>AI-generated or edited photos will be rejected</strong></li>
                    <li>Your <strong>face and code must be clearly visible</strong> together</li>
                </ul>
            </div>
        </div>
        
        <?php if (!$verification->photo_url) : ?>
        
        <!-- Camera Section -->
        <div style="background: white; border: 2px solid #ddd; border-radius: 8px; padding: 30px;">
            
            <!-- Initial State -->
            <div id="camera-initial" style="text-align: center;">
                <div style="font-size: 80px; margin-bottom: 20px;">📷</div>
                <h3 style="margin: 0 0 15px 0;">Ready to Start Verification?</h3>
                <p style="color: #666; margin-bottom: 25px;">Click the button below to open your camera and take a live selfie.</p>
                <button id="start-camera-btn" class="button button-primary button-hero" style="font-size: 18px; padding: 15px 40px;">
                    📷 Open Camera
                </button>
            </div>
            
            <!-- Browser Check Error -->
            <div id="browser-check-error" style="display: none; background: #f8d7da; border: 1px solid #f5c6cb; padding: 20px; border-radius: 8px; text-align: center;">
                <div style="font-size: 60px; margin-bottom: 15px;">❌</div>
                <h3 style="color: #721c24; margin: 0 0 10px 0;">Camera Not Available</h3>
                <p id="browser-error-message" style="color: #721c24; margin-bottom: 15px;"></p>
                <div style="background: #fff; padding: 15px; border-radius: 4px; margin-top: 15px; text-align: left;">
                    <strong>Possible Solutions:</strong>
                    <ul style="margin: 10px 0 0 20px; color: #721c24;">
                        <li>Make sure you're on <strong>HTTPS</strong> or <strong>localhost</strong></li>
                        <li>Check if your browser supports camera access</li>
                        <li>Try a different browser (Chrome, Firefox, Edge recommended)</li>
                        <li>Make sure camera is not being used by another application</li>
                        <li>Check browser permissions and allow camera access</li>
                    </ul>
                </div>
            </div>
            
            <!-- Video Preview -->
            <div id="video-container" style="display: none; text-align: center;">
                <div style="margin-bottom: 20px;">
                    <video id="camera-preview" autoplay playsinline muted style="width: 100%; max-width: 600px; border: 4px solid #2271b1; border-radius: 12px; background: #000;"></video>
                </div>
                <p style="color: #666; margin-bottom: 20px;">
                    📸 Position your face and the code paper in the frame<br>
                    <small>Make sure both are clearly visible and in focus</small>
                </p>
                <button id="capture-photo-btn" class="button button-primary button-hero" style="font-size: 18px; padding: 15px 40px; margin-right: 10px;">
                    📸 Capture Photo
                </button>
                <button id="cancel-camera-btn" class="button" style="padding: 15px 30px;">Cancel</button>
            </div>
            
            <!-- Photo Preview -->
            <div id="photo-preview-container" style="display: none; text-align: center;">
                <h4 style="margin-top: 0;">Preview Your Photo:</h4>
                <div style="margin-bottom: 20px;">
                    <img id="photo-preview" style="width: 100%; max-width: 600px; border: 4px solid #46b450; border-radius: 12px;">
                </div>
                <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: left;">
                    <strong>✓ Please verify before submitting:</strong>
                    <ul style="margin: 10px 0 0 20px; line-height: 1.8;">
                        <li>Is your face clearly visible? ✓</li>
                        <li>Is the verification code clearly readable? ✓</li>
                        <li>Is the photo not blurry? ✓</li>
                        <li>Are both you and the code in the same photo? ✓</li>
                    </ul>
                </div>
                <button id="retake-photo-btn" class="button" style="padding: 12px 25px; margin-right: 10px;">
                    🔄 Retake Photo
                </button>
                <button id="submit-photo-btn" class="button button-primary button-hero" style="font-size: 18px; padding: 15px 40px;">
                    ✅ Submit for Approval
                </button>
            </div>
            
            <!-- Upload Progress -->
            <div id="upload-status" style="display: none; text-align: center; padding: 40px;">
                <div class="spinner is-active" style="float: none; margin: 0 auto 20px auto;"></div>
                <h4 style="margin: 0 0 10px 0;">Uploading Your Photo...</h4>
                <p style="color: #666; margin: 0;">Please wait, do not close this page.</p>
            </div>
            
        </div>
        
        <?php else : ?>
        
        <!-- Already Uploaded -->
        <div style="background: #d4edda; border: 2px solid #c3e6cb; border-radius: 8px; padding: 30px; text-align: center;">
            <div style="font-size: 80px; margin-bottom: 20px;">✅</div>
            <h2 style="color: #155724; margin: 0 0 15px 0;">Photo Submitted Successfully!</h2>
            <p style="color: #155724; margin-bottom: 25px; font-size: 16px;">
                Your verification photo is now waiting for admin review.<br>
                You will be notified once it's approved.
            </p>
            <div style="margin-bottom: 25px;">
                <img src="<?php echo esc_url($verification->photo_url); ?>" style="max-width: 500px; width: 100%; border: 3px solid #155724; border-radius: 12px;">
            </div>
            <a href="<?php echo get_edit_post_link($listing_id); ?>" class="button button-primary button-hero" style="padding: 12px 30px;">
                ← Back to Listing
            </a>
        </div>
        
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="<?php echo get_edit_post_link($listing_id); ?>" style="text-decoration: none; color: #666; font-size: 14px;">
                ← Back to Listing
            </a>
        </div>
    </div>
    
    <style>
        .spinner {
            background: url(<?php echo admin_url('images/spinner.gif'); ?>) no-repeat;
            background-size: 20px 20px;
            display: inline-block;
            width: 20px;
            height: 20px;
            vertical-align: middle;
        }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        let stream = null;
        let capturedPhoto = null;
        
        // Check browser support immediately
        function checkCameraSupport() {
            // Check if getUserMedia is supported
            if (!navigator.mediaDevices) {
                return {
                    supported: false,
                    message: 'Your browser does not support camera access. Please use Chrome, Firefox, or Edge browser.'
                };
            }
            
            if (!navigator.mediaDevices.getUserMedia) {
                return {
                    supported: false,
                    message: 'Camera API is not available. Please make sure you are on HTTPS or localhost.'
                };
            }
            
            return { supported: true };
        }
        
        // Start Camera
        $('#start-camera-btn').on('click', function() {
            const support = checkCameraSupport();
            
            if (!support.supported) {
                showBrowserError(support.message);
                return;
            }
            
            navigator.mediaDevices.getUserMedia({ 
                video: { 
                    facingMode: 'user',
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                } 
            })
            .then(function(mediaStream) {
                stream = mediaStream;
                const video = $('#camera-preview')[0];
                video.srcObject = stream;
                
                // Wait for video to be ready
                video.onloadedmetadata = function() {
                    video.play();
                };
                
                $('#camera-initial').hide();
                $('#browser-check-error').hide();
                $('#video-container').show();
            })
            .catch(function(err) {
                console.error('Camera error:', err);
                let errorMsg = 'Camera access denied. ';
                
                if (err.name === 'NotAllowedError') {
                    errorMsg += 'Please allow camera access in your browser settings and try again.';
                } else if (err.name === 'NotFoundError') {
                    errorMsg += 'No camera found on your device.';
                } else if (err.name === 'NotReadableError') {
                    errorMsg += 'Camera is already in use by another application.';
                } else if (err.name === 'NotSupportedError') {
                    errorMsg += 'Camera access requires HTTPS connection.';
                } else {
                    errorMsg += err.message;
                }
                
                showBrowserError(errorMsg);
            });
        });
        
        function showBrowserError(message) {
            $('#browser-error-message').text(message);
            $('#camera-initial').hide();
            $('#browser-check-error').show();
        }
        
        // Cancel Camera
        $('#cancel-camera-btn').on('click', function() {
            stopCamera();
            $('#video-container').hide();
            $('#camera-initial').show();
        });
        
        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
        }
        
        // Capture Photo
        $('#capture-photo-btn').on('click', function() {
            const video = $('#camera-preview')[0];
            
            // Create canvas
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            
            // Draw video frame to canvas
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            // Get image as base64
            capturedPhoto = canvas.toDataURL('image/jpeg', 0.92);
            
            // Show preview
            $('#photo-preview').attr('src', capturedPhoto);
            
            // Stop camera
            stopCamera();
            
            // Show preview
            $('#video-container').hide();
            $('#photo-preview-container').show();
        });
        
        // Retake Photo
        $('#retake-photo-btn').on('click', function() {
            capturedPhoto = null;
            $('#photo-preview-container').hide();
            $('#camera-initial').show();
        });
        
        // Submit Photo
        $('#submit-photo-btn').on('click', function() {
            if (!capturedPhoto) {
                alert('No photo captured. Please try again.');
                return;
            }
            
            if (!confirm('Are you sure you want to submit this photo for verification?')) {
                return;
            }
            
            $('#photo-preview-container').hide();
            $('#upload-status').show();
            
            // Convert base64 to blob
            fetch(capturedPhoto)
                .then(res => res.blob())
                .then(blob => {
                    const formData = new FormData();
                    formData.append('action', 'solmatehub_upload_verification_photo');
                    formData.append('verification_id', <?php echo $verification->id; ?>);
                    formData.append('verification_photo', blob, 'verification-' + Date.now() + '.jpg');
                    formData.append('nonce', '<?php echo wp_create_nonce('solmatehub_verification'); ?>');
                    
                    return $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        timeout: 30000
                    });
                })
                .then(function(response) {
                    if (response.success) {
                        alert('✅ Photo uploaded successfully!\n\nYour verification request is now pending admin approval. You will be notified once reviewed.');
                        window.location.reload();
                    } else {
                        throw new Error(response.data ? response.data.message : 'Upload failed');
                    }
                })
                .catch(function(error) {
                    alert('❌ Upload failed: ' + error.message + '\n\nPlease try again or contact support.');
                    $('#upload-status').hide();
                    $('#photo-preview-container').show();
                });
        });
        
        // Cleanup on page unload
        $(window).on('beforeunload', function() {
            stopCamera();
        });
    });
    </script>
    <?php

}



    
    /**
     * Settings Page Content
     */
    public function settings_page() {
        $total_listings = wp_count_posts('listing')->publish;
        $pending_count = $this->get_pending_count();
        $approved_count = $this->get_approved_count();
        
        ?>
        <div class="wrap">
            <h1>🏠 SolmateHub Dashboard</h1>
            <p>Welcome to SolmateHub! Manage your platform from here.</p>
            
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 30px;">
                <div style="background: white; padding: 20px; border-left: 4px solid #2271b1; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 10px 0; color: #2271b1;">Total Listings</h3>
                    <p style="font-size: 32px; font-weight: bold; margin: 0;"><?php echo $total_listings; ?></p>
                </div>
                
                <div style="background: white; padding: 20px; border-left: 4px solid #d63638; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 10px 0; color: #d63638;">⏳ Pending Review</h3>
                    <p style="font-size: 32px; font-weight: bold; margin: 0;"><?php echo $pending_count; ?></p>
                    <a href="<?php echo admin_url('admin.php?page=solmatehub-pending'); ?>" style="text-decoration: none; color: #2271b1;">View All →</a>
                </div>
                
                <div style="background: white; padding: 20px; border-left: 4px solid #00a32a; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 10px 0; color: #00a32a;">✅ Approved</h3>
                    <p style="font-size: 32px; font-weight: bold; margin: 0;"><?php echo $approved_count; ?></p>
                </div>
            </div>
            
            <div style="background: white; padding: 20px; margin-top: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2>Total Users: <?php echo count_users()['total_users']; ?></h2>
            </div>
        </div>
        <?php
    }
    
    /**
     * Pending Listings Page
     */
    public function pending_listings_page() {
        $args = array(
            'post_type' => 'listing',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'listing_status',
                    'value' => 'pending',
                    'compare' => '='
                )
            )
        );
        
        $pending_listings = new WP_Query($args);
        
        ?>
        <div class="wrap">
            <h1>⏳ Pending Listings (Awaiting Approval)</h1>
            
            <?php if ($pending_listings->have_posts()) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Location</th>
                            <th>Category</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($pending_listings->have_posts()) : $pending_listings->the_post(); 
                            $location = wp_get_post_terms(get_the_ID(), 'listing_location');
                            $category = wp_get_post_terms(get_the_ID(), 'listing_category');
                        ?>
                        <tr>
                            <td><strong><?php the_title(); ?></strong></td>
                            <td><?php the_author(); ?></td>
                            <td><?php echo !empty($location) ? $location[0]->name : '-'; ?></td>
                            <td><?php echo !empty($category) ? $category[0]->name : '-'; ?></td>
                            <td><?php echo get_the_date(); ?></td>
                            <td>
                                <a href="<?php echo get_edit_post_link(); ?>" class="button button-primary">Review & Approve</a>
                            </td>
                        </tr>
                        <?php endwhile; wp_reset_postdata(); ?>
                    </tbody>
                </table>
            <?php else : ?>
                <div style="background: white; padding: 40px; text-align: center; margin-top: 20px;">
                    <h2>🎉 All caught up!</h2>
                    <p>No pending listings at the moment.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Global Settings Page
     */
    public function global_settings_page() {
        // Save settings
        if (isset($_POST['solmatehub_save_settings']) && check_admin_referer('solmatehub_settings_nonce')) {
            $default_limit = intval($_POST['default_listing_limit']);
            if ($default_limit > 0) {
                update_option('solmatehub_default_listing_limit', $default_limit);
                echo '<div class="notice notice-success is-dismissible"><p>✅ Settings saved successfully!</p></div>';
            }
        }
        
        $current_limit = get_option('solmatehub_default_listing_limit', 3);
        
        ?>
        <div class="wrap">
            <h1>⚙️ Global Settings</h1>
            <p>Configure global platform settings here.</p>
            
            <form method="post" action="">
                <?php wp_nonce_field('solmatehub_settings_nonce'); ?>
                
                <div style="background: white; padding: 20px; margin-top: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); max-width: 600px;">
                    <h2>Listing Limits</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="default_listing_limit">Default Listing Limit</label>
                            </th>
                            <td>
                                <input type="number" name="default_listing_limit" id="default_listing_limit" value="<?php echo esc_attr($current_limit); ?>" class="regular-text" min="1" max="100" />
                                <p class="description">
                                    Default number of listings each adviser can create. (Current: <strong><?php echo $current_limit; ?></strong>)<br>
                                    You can override this for individual users in their profile settings.
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="solmatehub_save_settings" class="button button-primary" value="Save Settings" />
                    </p>
                </div>
            </form>
            
            <div style="background: #fff3cd; padding: 15px; margin-top: 20px; border-left: 4px solid #ffc107; max-width: 600px;">
                <h3 style="margin-top: 0;">ℹ️ How Listing Limits Work:</h3>
                <ul style="margin-left: 20px;">
                    <li><strong>Global Default:</strong> Applies to all advisers by default.</li>
                    <li><strong>Per-User Override:</strong> You can set custom limits for specific users in Users → Edit User.</li>
                    <li><strong>Automatic Check:</strong> When an adviser tries to create more listings than allowed, they'll be stopped automatically.</li>
                    <li><strong>Admin Bypass:</strong> Admins have unlimited listings.</li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * Helper: Get pending count
     */
    private function get_pending_count() {
        $args = array(
            'post_type' => 'listing',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'listing_status',
                    'value' => 'pending',
                    'compare' => '='
                )
            )
        );
        $query = new WP_Query($args);
        return $query->found_posts;
    }
    
    /**
     * Helper: Get approved count
     */
    private function get_approved_count() {
        $args = array(
            'post_type' => 'listing',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'listing_status',
                    'value' => 'approved',
                    'compare' => '='
                )
            )
        );
        $query = new WP_Query($args);
        return $query->found_posts;
    }
     /**
     * Check if user can create more listings (for frontend use)
     * Returns: array('can_create' => bool, 'current' => int, 'limit' => int, 'message' => string)
     */
    public function can_user_create_listing($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Admins can always create unlimited
        if (user_can($user_id, 'manage_options')) {
            return array(
                'can_create' => true,
                'current' => 0,
                'limit' => 999,
                'message' => 'Admin: Unlimited listings'
            );
        }
        
        // Get limits
        $user_limit = get_user_meta($user_id, 'listing_limit', true);
        $global_limit = get_option('solmatehub_default_listing_limit', 3);
        $effective_limit = !empty($user_limit) ? intval($user_limit) : intval($global_limit);
        
        // Count user's current listings
        $args = array(
            'post_type' => 'listing',
            'author' => $user_id,
            'post_status' => array('publish', 'pending', 'draft'),
            'posts_per_page' => -1,
            'fields' => 'ids'
        );
        $query = new WP_Query($args);
        $current_count = $query->found_posts;
        
        // Check if can create more
        $can_create = $current_count < $effective_limit;
        
        $message = '';
        if (!$can_create) {
            $message = "You have reached your listing limit ($current_count/$effective_limit). Please delete an existing listing or contact admin.";
        } else {
            $remaining = $effective_limit - $current_count;
            $message = "You can create $remaining more listing(s). ($current_count/$effective_limit used)";
        }
        
        return array(
            'can_create' => $can_create,
            'current' => $current_count,
            'limit' => $effective_limit,
            'message' => $message
        );
    }
    
    /**
     * Get instance (for calling from templates)
     */
    public static function get_instance() {
        static $instance = null;
        if (null === $instance) {
            $instance = new self();
        }
        return $instance;
    }

}

// Initialize the plugin and make it globally accessible
function solmatehub_core() {
    return SolmateHub_Core::get_instance();
}
solmatehub_core();