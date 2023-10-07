<?php
/*
Plugin Name: LightForm
Description: Create a custom contact form for your website.
Version: 1.0
Author: Dimitris Liaropoulos
*/

// Include PHPMailer using namespaces
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once plugin_dir_path(__FILE__) . 'phpmailer/PHPMailer.php';
require_once plugin_dir_path(__FILE__) . 'phpmailer/SMTP.php';
require_once plugin_dir_path(__FILE__) . 'phpmailer/Exception.php';


// Add this code at the beginning of your main plugin file to handle the delete logs action
if (isset($_POST['custom_contact_form_delete_logs']) && $_POST['custom_contact_form_delete_logs'] == '1') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_contact_form_log';

    // Delete all log entries
    $wpdb->query("TRUNCATE TABLE $table_name");

    // Redirect back to the settings page after deleting logs
    $redirect_url = admin_url('admin.php?page=smtp-settings');
    wp_redirect($redirect_url);
    exit;
}


// Enqueue your plugin's CSS file
function custom_contact_form_enqueue_styles() {
    // Get the URL of your plugin's directory
    $plugin_url = plugin_dir_url(__FILE__);

    // Enqueue the CSS file
    wp_enqueue_style('custom-contact-form-styles', $plugin_url . 'custom-contact-form.css');
}
add_action('wp_enqueue_scripts', 'custom_contact_form_enqueue_styles');

// Function to generate the contact form HTML
function custom_contact_form_html() {

    $form_title = get_option('form_title', 'Contact Form');

    // Generate two random numbers for the CAPTCHA
    $num1 = rand(1, 10);
    $num2 = rand(1, 10);
    $sum = $num1 + $num2;


    ob_start(); // Start output buffering
    ?>
    <div class="custom-contact-form">
        <h2><?php echo esc_html($form_title); ?></h2>
        <form action="<?php echo esc_url(get_permalink()); ?>" method="post">
            <div class="form-group">
                <label for="name">Name:</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="subject">Subject:</label>
                <input type="text" id="subject" name="subject" required>
            </div>
            <div class="form-group">
                <label for="message">Message:</label>
                <textarea id="message" name="message" rows="4" required></textarea>
            </div>
            <div class="form-group">
                <label for="captcha">Math Question: What is <?php echo $num1; ?> + <?php echo $num2; ?>?</label>
                <input type="text" id="captcha" name="captcha" required>
            </div>
            <div class="form-group">
                <input type="submit" name="submit" value="Submit">
            </div>
            <input type="hidden" name="num1" value="<?php echo $num1; ?>">
            <input type="hidden" name="num2" value="<?php echo $num2; ?>">
        </form>
    </div>

    <?php
    return ob_get_clean(); // Return the buffered content as a string
}

// Shortcode to display the contact form
function custom_contact_form_shortcode() {
    $form_html = custom_contact_form_html(); // Get the form HTML
    return $form_html; // Return the HTML as the shortcode output
}
add_shortcode('custom_contact_form', 'custom_contact_form_shortcode');

// Function to handle form submission and send email
function custom_contact_form_handle_submission() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {

        // Check CAPTCHA
        $captcha = isset($_POST['captcha']) ? intval($_POST['captcha']) : 0;
        $num1 = isset($_POST['num1']) ? intval($_POST['num1']) : 0;
        $num2 = isset($_POST['num2']) ? intval($_POST['num2']) : 0;

        if ($captcha !== ($num1 + $num2)) {
            echo '<p class="error">CAPTCHA verification failed. Please try again.</p>';
            return; // Don't process the form if CAPTCHA is incorrect
        }

        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $subject = sanitize_text_field($_POST['subject']);
        $message = sanitize_textarea_field($_POST['message']);
        
        // Create a PHPMailer instance
        $mailer = new PHPMailer(true); // Enable exceptions
        
        try {
            // Set SMTP settings from WordPress options
            $mailer->isSMTP();
            $mailer->Host = get_option('smtp_host');
            $mailer->SMTPAuth = true;
            $mailer->Username = get_option('smtp_username');
            $mailer->Password = get_option('smtp_password');
            $mailer->SMTPSecure = get_option('smtp_secure');
            $mailer->Port = get_option('smtp_port');
            
            // Set email parameters
            $mailer->setFrom($email, $name);
            $mailer->addAddress('dimliarop@gmail.com'); // Recipient email address
            $mailer->Subject = $subject;
            $mailer->Body = $message;
            
            // Send the email
            $mailer->send();
            if ($mailer->send()) {
                // Log email sent
                global $wpdb;
                $table_name = $wpdb->prefix . 'custom_contact_form_log';
                $wpdb->insert(
                    $table_name,
                    array(
                        'email_sent' => 1,
                        'time_sent' => current_time('mysql'),
                    ),
                    array('%d', '%s')
                );

                echo '<p class="success">Message sent successfully!</p>';
            } else {
                echo '<p class="error">Error sending message: ' . $mailer->ErrorInfo . '</p>';
            }
        } catch (Exception $e) {
            echo '<p class="error">Error sending message: ' . $e->getMessage() . '</p>';
        }
    }
}

// Hook to handle form submission
add_action('init', 'custom_contact_form_handle_submission');

// Create the admin menu item
function custom_contact_form_menu() {
    add_menu_page('LightForm', 'LightForm', 'manage_options', 'smtp-settings', 'custom_contact_form_settings_page');
}


// Function to display the log table in admin panel
function custom_contact_form_display_log($page = 1, $per_page = 3) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_contact_form_log';

    $offset = ($page - 1) * $per_page;

    $logs = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM $table_name ORDER BY time_sent DESC LIMIT %d, %d", $offset, $per_page),
        ARRAY_A
    );

    echo '<h2>Email Sending Log</h2>';
    echo '<table class="widefat">';
    echo '<thead><tr><th>ID</th><th>Email Sent</th><th>Time Sent</th></tr></thead>';
    echo '<tbody>';

    foreach ($logs as $log) {
        echo '<tr>';
        echo '<td>' . esc_html($log['id']) . '</td>';
        echo '<td>' . ($log['email_sent'] ? 'Yes' : 'No') . '</td>';
        echo '<td>' . esc_html($log['time_sent']) . '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';

    $total_logs = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");

    // Calculate total pages
    $total_pages = ceil($total_logs / $per_page);

    // Display pagination links
    echo '<div class="pagination">';
    echo paginate_links(array(
        'base' => admin_url('admin.php?page=smtp-settings') . '%_%',
        'format' => '&paged=%#%',
        'current' => $page,
        'total' => $total_pages,
    ));
    echo '</div>';
}

// Function to render the settings page
function custom_contact_form_settings_page() {
    $current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
    ?>
    <div class="wrap">
        <h2>SMTP Settings for LightForm</h2>
        <p>This plugin is made by Dimitris Liaropoulos</p>
        <form method="post" action="options.php">
            <?php
            settings_fields('custom-contact-form-smtp-settings');
            do_settings_sections('custom-contact-form-smtp-settings');
            submit_button();
            ?>
        </form>
        <h2>Email Sending Log</h2>
        <form method="post" action="">
            <input type="hidden" name="custom_contact_form_delete_logs" value="1">
            <?php submit_button('Delete All Logs', 'delete', 'delete_logs_button'); ?>
        </form>
        <?php 
        // Add the button for downloading CSV
        echo '<form method="post" action="">';
        echo '<input type="hidden" name="custom_contact_form_download_logs" value="1">';
        submit_button('Download Logs as CSV', 'primary', 'download_csv_button');
        echo '</form>';
        ?>
        <?php custom_contact_form_display_log($current_page); ?>

    </div>
    <?php
}

add_action('admin_init', 'custom_contact_form_handle_csv_download');
function custom_contact_form_handle_csv_download() {
    if (isset($_POST['custom_contact_form_download_logs']) && $_POST['custom_contact_form_download_logs'] == '1') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'custom_contact_form_log';

        $logs = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);

        if (!empty($logs)) {
            // Generate CSV content
            $csv_data = "ID,Email Sent,Time Sent\n";
            foreach ($logs as $log) {
                $csv_data .= "{$log['id']},{$log['email_sent']},{$log['time_sent']}\n";
            }

            // Set HTTP headers for download
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="email_logs.csv"');

            // Output the CSV data
            echo $csv_data;
            exit;
        }
    }
}


// Hook to initialize and render settings
add_action('admin_init', 'custom_contact_form_initialize_settings');
add_action('admin_init', 'custom_contact_form_render_settings');

// Function to initialize SMTP settings
function custom_contact_form_initialize_settings() {
    register_setting('custom-contact-form-smtp-settings', 'smtp_host');
    register_setting('custom-contact-form-smtp-settings', 'smtp_username');
    register_setting('custom-contact-form-smtp-settings', 'smtp_password');
    register_setting('custom-contact-form-smtp-settings', 'smtp_secure');
    register_setting('custom-contact-form-smtp-settings', 'smtp_port');
}

// Function to add input fields to the settings page
function custom_contact_form_render_settings() {
    add_settings_section('smtp_settings_section', 'SMTP Settings', null, 'custom-contact-form-smtp-settings');
    add_settings_field('smtp_host', 'SMTP Host', 'custom_contact_form_render_host', 'custom-contact-form-smtp-settings', 'smtp_settings_section');
    add_settings_field('smtp_username', 'SMTP Username', 'custom_contact_form_render_username', 'custom-contact-form-smtp-settings', 'smtp_settings_section');
    add_settings_field('smtp_password', 'SMTP Password', 'custom_contact_form_render_password', 'custom-contact-form-smtp-settings', 'smtp_settings_section');
    add_settings_field('smtp_secure', 'SMTP Encryption', 'custom_contact_form_render_secure', 'custom-contact-form-smtp-settings', 'smtp_settings_section');
    add_settings_field('smtp_port', 'SMTP Port', 'custom_contact_form_render_port', 'custom-contact-form-smtp-settings', 'smtp_settings_section');
}

// Functions to render the input fields
function custom_contact_form_render_host() {
    echo '<input type="text" name="smtp_host" value="' . esc_attr(get_option('smtp_host')) . '">';
}

function custom_contact_form_render_username() {
    echo '<input type="text" name="smtp_username" value="' . esc_attr(get_option('smtp_username')) . '">';
}

function custom_contact_form_render_password() {
    echo '<input type="password" name="smtp_password" value="' . esc_attr(get_option('smtp_password')) . '">';
}

function custom_contact_form_render_secure() {
    $secure = get_option('smtp_secure');
    echo '<select name="smtp_secure">';
    echo '<option value="none" ' . selected($secure, 'none', false) . '>None</option>';
    echo '<option value="ssl" ' . selected($secure, 'ssl', false) . '>SSL</option>';
    echo '<option value="tls" ' . selected($secure, 'tls', false) . '>TLS</option>';
    echo '</select>';
}

function custom_contact_form_render_port() {
    echo '<input type="text" name="smtp_port" value="' . esc_attr(get_option('smtp_port')) . '">';
}

// Hook to add the admin menu item
add_action('admin_menu', 'custom_contact_form_menu');

// Hook to initialize and render settings
add_action('admin_init', 'custom_contact_form_initialize_settings');
add_action('admin_init', 'custom_contact_form_render_settings');
?>

<?php
// Function to create a log table during plugin activation
function custom_contact_form_create_log_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_contact_form_log';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        email_sent boolean NOT NULL,
        time_sent datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}

// Hook to create the log table during plugin activation
register_activation_hook( __FILE__, 'custom_contact_form_create_log_table' );

// Function to remove the log table during plugin deactivation
function custom_contact_form_remove_log_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_contact_form_log';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}

// Hook to remove the log table during plugin deactivation
register_deactivation_hook( __FILE__, 'custom_contact_form_remove_log_table' );


?>
