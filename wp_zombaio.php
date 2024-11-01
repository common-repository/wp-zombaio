<?php

/*
 * Plugin Name: WP Zombaio
 * Plugin URI: http://barrycarlyon.co.uk/wordpress/wordpress-plugins/wp-zombaio/
 * Description: Catches Information from the Adult Payment Gateway Zombaio and acts accordingly
 * Author: Barry Carlyon
 * Version: 1.0.6.4.dev
 * Author URI: http://barrycarlyon.co.uk/wordpress/
 */

class wp_zombaio {
    function __construct($proc = FALSE) {
        if (isset($_GET['bcreset'])) {
            delete_option('wp_zombaio');
        }

        $this->setup();
        if ($proc) {
            return;
        }

        if (is_admin()) {
            // admin options interface
            $this->admin();
            return;
        }
        $this->form_index = 0;
        $this->frontend();
        return;
    }

    private function setup() {
        // user.delete codes
        $this->delete_codes = array(
            '',
            __('Satisfied Customer (just moving on)', 'wp-zombaio'),
            __('Income Issues', 'wp-zombaio'),
            __('Spouse called in about charge', 'wp-zombaio'),
            __('Minor user card', 'wp-zombaio'),
            __('Only interested in trial subscription', 'wp-zombaio'),
            __('Did not read terms and conditions', 'wp-zombaio'),
            __('Not satisfied with content', 'wp-zombaio'),
            __('Not receiving replies from Webmaster', 'wp-zombaio'),
            __('Password problems', 'wp-zombaio'),
            __('Unable to load content fast enough', 'wp-zombaio'),
            __('Other', 'wp-zombaio'),
        );
        // rebill success
        $this->rebill_codes = array(
            __('Declined, no more retries', 'wp-zombaio'),//user.delete will be sent
            __('Approved', 'wp-zombaio'),
            __('Declined, retrying in 5 days', 'wp-zombaio'),
        );
        // app a
        $this->chargeback_codes = array(
            '30'    => __('CB - Services/Merchandise Not Received', 'wp-zombaio'),
            '41'    => __('Cancelled Recurring Transaction', 'wp-zombaio'),
            '53'    => __('Not as Described or Defective', 'wp-zombaio'),
            '57'    => __('Fraudulent Multiple Drafts', 'wp-zombaio'),
            '73'    => __('Expired Card', 'wp-zombaio'),
            '74'    => __('Late Presentment', 'wp-zombaio'),
            '75'    => __('Cardholder Does Not Recognize', 'wp-zombaio'),
            '83'    => __('Fraudulent Transaction - Card Absent Environment', 'wp-zombaio'),
            '85'    => __('Card Not Processed', 'wp-zombaio'),
            '86'    => __('Altered Amount/Paid by Other Means', 'wp-zombaio'),
            '93'    => __('Risk Identification Service', 'wp-zombaio'),
            '101'   => __('Zombaio - Not as Described or Defective', 'wp-zombaio'),
            '102'   => __('Zombaio - No access to website (Script problem or Site down)', 'wp-zombaio'),
        );

        $this->chargeback_liability_code = array(
            '',
            __('Merchange is liable for the chargeback', 'wp-zombaio'),
            __('Card Issuer is liable for the chargeback (3D Secure)', 'wp-zombaio'),
            __('Zombaio is liable for the chargeback (Fraud Insurance)', 'wp-zombaio'),
        );
        // decline codes
        // app b
        $this->decline_codes = array(
            'B01'   => __('Declined by Issuing Bank', 'wp-zombaio'),
            'B02'   => __('Card Expired', 'wp-zombaio'),
            'B03'   => __('Card Lost of Stolen', 'wp-zombaio'),
            'B04'   => __('Card on Negative List', 'wp-zombaio'),//international blacklist

            'F01'   => __('Blocked by Anti Fraud System Level1 - Velocity', 'wp-zombaio'),
            'F02'   => __('Blocked by Anti Fraud System Level2 - Geo Technology', 'wp-zombaio'),
            'F03'   => __('Blocked by Anti Fraud System Level3 - Blacklist', 'wp-zombaio'),
            'F04'   => __('Blocked by Anti Fraud System Level4 - Bayesian probability', 'wp-zombaio'),
            'F05'   => __('Blocked by Anti Fraud System Level5 - Other', 'wp-zombaio'),

            'H01'   => __('3D Secure - Failed to Authenticate', 'wp-zombaio'),

            'E01'   => __('Merchange Account Closed or Suspended', 'wp-zombaio'),
            'E02'   => __('Routing Error', 'wp-zombaio'),
            'E03'   => __('General Error', 'wp-zombaio'),
        );


        $this->init();
        add_action('init', array($this, 'post_types'));
        add_action('plugins_loaded', array($this, 'detect'));
        add_action('widgets_init', array($this, 'widgets_init'));
        add_filter('wp_authenticate_user', array($this, 'wp_authenticate_user'), 10, 2);

        load_plugin_textdomain('wp-zombaio', false, basename(dirname(__FILE__)), '/languages');

        return;
    }
    public function init() {
        $options = get_option('wp_zombaio', FALSE);
        if (!$options) {
            $options = new stdClass();
            $options->site_id = '';
            $options->gw_pass = '';
            $options->bypass_ipn_ip_verification = FALSE;
            $options->delete = TRUE;
            $options->wizard = FALSE;

            $options->redirect_target_enable = FALSE;
            $options->redirect_target = '';
            $options->redirect_home_page = FALSE;

            $options->seal_code = '';

            $options->bypass_ipn_ip_verification = FALSE;
            $options->raw_logs = FALSE;

            $this->options = $options;
            $this->saveoptions();
        }
        $this->options = $options;
        return;
    }
    private function saveoptions() {
        return update_option('wp_zombaio', $this->options);
    }

    public function post_types() {
        register_post_type(
            'wp_zombaio',
            array(
                'label'                 => __('Zombaio Log', 'wp-zombaio'),
                'labels'                => array(
                    'name' => __('Zombaio Log', 'wp-zombaio'),
                    'singular_name' => __('Zombaio Log', 'wp-zombaio'),
                    'add_new' => __('Add New', 'wp-zombaio'),
                    'add_new_item' => __('Add New Zombaio Log', 'wp-zombaio'),
                    'edit_item' => __('Edit Zombaio Log', 'wp-zombaio'),
                    'new_item' => __('New Zombaio Log', 'wp-zombaio'),
                    'all_items' => __('All Zombaio Logs', 'wp-zombaio'),
                    'view_item' => __('View Zombaio Log', 'wp-zombaio'),
                    'search_items' => __('Search Zombaio Logs', 'wp-zombaio'),
                    'not_found' =>  __('No Zombaio Logs found', 'wp-zombaio'),
                    'not_found_in_trash' => __('No Zombaio Logs found in Trash', 'wp-zombaio'),
                    'parent_item_colon' => '',
                    'menu_name' => __('Zombaio Log', 'wp-zombaio')
                ),
                'public'                => ($this->options->raw_logs ? TRUE : FALSE),
                'supports'              => array(
                    'title',
                    'editor',
                    'custom-fields',
                ),
                'has_archive'           => false,
                'publicly_queryable'    => false,
                'exclude_from_search'   => true,
                'can_export'            => false,
                'menu_icon'             => plugin_dir_url(__FILE__) . 'img/zombaio_icon.png',
            )
        );

        register_post_status('user_add', array(
            'label' => __('User Add', 'wp-zombaio'),
            'public' => TRUE,
            'exclude_from_search' => TRUE,
            'show_in_admin_all_list' => TRUE,
            'show_in_admin_status_list' => TRUE,
            'label_count' => _n_noop( 'User Add <span class="count">(%s)</span>', 'User Add <span class="count">(%s)</span>', 'wp-zombaio'),
        ));
        register_post_status('user_delete', array(
            'label' => __('User Delete', 'wp-zombaio'),
            'public' => FALSE,
            'exclude_from_search' => TRUE,
            'show_in_admin_all_list' => TRUE,
            'show_in_admin_status_list' => TRUE,
            'label_count' => _n_noop( 'User Delete <span class="count">(%s)</span>', 'User Delete <span class="count">(%s)</span>', 'wp-zombaio'),
        ));
        register_post_status('user_addcredits', array(
            'label' => __('User Add Credits', 'wp-zombaio'),
            'public' => FALSE,
            'exclude_from_search' => TRUE,
            'show_in_admin_all_list' => TRUE,
            'show_in_admin_status_list' => TRUE,
            'label_count' => _n_noop( 'User Add Credits <span class="count">(%s)</span>', 'User Add Credits <span class="count">(%s)</span>', 'wp-zombaio'),
        ));
        register_post_status('rebill', array(
            'label' => __('User Rebill', 'wp-zombaio'),
            'public' => FALSE,
            'exclude_from_search' => TRUE,
            'show_in_admin_all_list' => TRUE,
            'show_in_admin_status_list' => TRUE,
            'label_count' => _n_noop( 'User Rebill <span class="count">(%s)</span>', 'User Rebill <span class="count">(%s)</span>', 'wp-zombaio'),
        ));
        register_post_status('chargeback', array(
            'label' => __('Chargeback Report', 'wp-zombaio'),
            'public' => FALSE,
            'exclude_from_search' => TRUE,
            'show_in_admin_all_list' => FALSE,
            'show_in_admin_status_list' => TRUE,
            'label_count' => _n_noop( 'Charge Back Report <span class="count">(%s)</span>', 'Charge Back Report <span class="count">(%s)</span>', 'wp-zombaio'),
        ));
        register_post_status('declined', array(
            'label' => __('Card Declined Report', 'wp-zombaio'),
            'public' => FALSE,
            'exclude_from_search' => TRUE,
            'show_in_admin_all_list' => FALSE,
            'show_in_admin_status_list' => TRUE,
            'label_count' => _n_noop( 'Card Declined Report <span class="count">(%s)</span>', 'Card Declined Report <span class="count">(%s)</span>', 'wp-zombaio'),
        ));

        return;
    }

    private function admin() {
        add_action('admin_notices', array($this, 'admin_notices'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_head', array($this, 'admin_head'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

        // meta box
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_post'));
    }
    public function admin_notices() {
        $page = isset($_GET['page']) ? $_GET['page'] : '';
        $do = isset($_REQUEST['do']) ? $_REQUEST['do'] : '';
        if (!$this->options->wizard && $page != 'wp_zombaio' && $do != 'wizard') {
            if (isset($_REQUEST['wp_zombaio']) && $_REQUEST['wp_zombaio'] == 'dismisswizard') {
                $this->options->wizard = TRUE;
                $this->saveoptions();
                return;
            }
            // offer wizard
            $wizard_prompt = '<div id="wp_zombaio_wizard" style="display: block; clear: both; background: #000000; color: #9F9F9F; margin-top: 10px; margin-right: 15px; padding: 12px 10px; font-size: 12px;">';
            $wizard_prompt .= sprintf(__('Run the <a href="%s">WP Zombaio Install Wizard?</a>', 'wp-zombaio'), admin_url('admin.php?page=wp_zombaio&do=wizard'));
            $wizard_prompt .= '<a href="' . admin_url('admin.php?page=wp_zombaio&do=wizarddismiss') . '" style="float: right;">' . __('Dismiss Wizard', 'wp-zombaio') . '</a>';
            $wizard_prompt .= '</div>';
            echo $wizard_prompt;
        }
        if (get_option('users_can_register')) {
            $alert = '<div id="message" class="error">';
            $alert .= __('<p>You have <i>Anyone can register</i> enabled, this means people can join your site without Paying!</p>', 'wp-zombaio');
            $alert .= '</div>';
            echo $alert;
        }
    }

    /**
    Post meta Boxes
    */
    public function add_meta_boxes() {
        add_meta_box(
            'wp_zombaio_redirect_disable',
            __('WP Zomabio Allow Logged Out/Disable User Access', 'wp-zombaio'),
            array($this, 'wp_zombaio_redirect_disable'),
            '',
            'side',
            'high'
        );
    }
    public function save_post($post_id) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
            return;
        if (!$_POST) {
            return;
        }
        if ( !wp_verify_nonce( $_POST['wp_zombaio_postmeta'], plugin_basename( __FILE__ ) ) )
            return;
        if ('page' == $_POST['post_type']) {
            if ( !current_user_can( 'edit_page', $post_id ) )
                return;
        } else {
            if ( !current_user_can( 'edit_post', $post_id ) )
                return;
        }
        // auth ok

        update_post_meta($post_id, 'wp_zombaio_redirect_disable', $_POST['wp_zombaio_redirect_disable']);
    }
    public function wp_zombaio_redirect_disable($post) {
        wp_nonce_field( plugin_basename( __FILE__ ), 'wp_zombaio_postmeta' );

        if (!strlen($wp_zombaio_redirect_disable = get_post_meta($post->ID, 'wp_zombaio_redirect_disable', TRUE))) {
            $wp_zombaio_redirect_disable = 0;
        }

        echo '<table style="display: block;">';

        echo '<tr>'
            . '<th valign="top"><label for="wp_zombaio_redirect_disable">' . __('Allow Access to All', 'wp_zombaio') . '</label></th>'
            . '<td style="width: 150px; text-align: center;">
                <select name="wp_zombaio_redirect_disable">
                    <option value="0" ' . ($wp_zombaio_redirect_disable ? '' : 'selected="selected"') . '>' . __('No', 'wp-zombaio') . '</option>
                    <option value="1" ' . ($wp_zombaio_redirect_disable ? 'selected="selected"' : '') . '>' . __('Yes', 'wp-zombaio') . '</option>
                </select>
                </td>'
            . '</tr>';
        echo '</table>';
    }

    /**
    User admin interface
    */
    public function admin_menu() {
        if (!$this->options->wizard) {
            add_menu_page('WP Zombaio', 'WP Zombaio', 'activate_plugins', 'wp_zombaio', array($this, 'admin_page'), plugin_dir_url(__FILE__) . 'img/zombaio_icon.png');
            add_submenu_page('wp_zombaio', __('Guide', 'wp-zombaio'), __('Guide', 'wp-zombaio'), 'activate_plugins', 'wp_zombaio_guide', array($this, 'admin_page_guide'));
            add_submenu_page('wp_zombaio', __('Logs', 'wp-zombaio'), __('Logs', 'wp-zombaio'), 'activate_plugins', 'wp_zombaio_logs', array($this, 'admin_page_logs'));
        } else {
            add_menu_page('WP Zombaio', 'WP Zombaio', 'activate_plugins', 'wp_zombaio_logs', array($this, 'admin_page_logs'), plugin_dir_url(__FILE__) . 'img/zombaio_icon.png');
            add_submenu_page('wp_zombaio_logs', __('Guide', 'wp-zombaio'), __('Guide', 'wp-zombaio'), 'activate_plugins', 'wp_zombaio_guide', array($this, 'admin_page_guide'));
            add_submenu_page('wp_zombaio_logs', __('Settings', 'wp-zombaio'), __('Settings', 'wp-zombaio'), 'activate_plugins', 'wp_zombaio', array($this, 'admin_page'));
        }
    }

    public function admin_head() {
        echo '<style type="text/css">';
        if (isset($_GET['page']) && substr($_GET['page'], 0, 10) == 'wp_zombaio') {
            echo '
#wp_zombaio .wp_zombaio_admin {
    border: 1px solid #D3D3D3;
    -webkit-border-radius: 10px;
    -moz-border-radius: 10px;
    border-radius: 10px;
    margin: 5px;
    background: #FBFBFB;
}
#wp_zombaio .wp_zombaio_admin h2 {
    font-weight: 700;
    color: #000000;
    background: #D3D3D3;
    -webkit-border-top-left-radius: 10px;
    -webkit-border-top-right-radius: 10px;
    -moz-border-radius-topleft: 10px;
    -moz-border-radius-topright: 10px;
    border-top-left-radius: 10px;
    border-top-right-radius: 10px;
    display: block;
    padding-left: 20px;
}
#wp_zombaio .wp_zombaio_admin .wp_zombaio_inner { margin: 10px; }
#wp_zombaio_logo { margin-top: 50px; float: right; }
#wp_zombaio .wp_zombaio_admin ul.disc { list-style: disc; margin-left: 20px; }
#wp_zombaio .wp_zombaio_admin #message { margin: 20px; }
#wp_zombaio_transaction_logs { width: 100%; }
#wp_zombaio_transaction_logs th, #wp_zombaio_transaction_logs td { border-right: 1px solid #9F9F9F; border-bottom: 1px solid #9F9F9F; }
#wp_zombaio_transaction_logs th, #wp_zombaio_transaction_logs tfoot tr td { border-top: 1px solid #9F9F9F; background: #808080; }
#wp_zombaio_transaction_logs td { padding: 0px 5px; }
#wp_zombaio_transaction_logs tr th:first-child, #wp_zombaio_transaction_logs tr td:first-child { border-left: 1px solid #9F9F9F; text-align: center; }
.wp_zombaio_inner .ui-widget-content { background: #FBFBFB; }

#wp_zombaio_transaction_logs tr:nth-child(even) { background: #D3D3D3 }
#wp_zombaio_transaction_logs tr:nth-child(odd) { background: #CFDBF3; }
#wp_zombaio_transaction_logs tbody tr:hover { cursor: pointer; background: #87DEA4; }
';
    }
echo '
#icon-edit.icon32-posts-wp_zombaio {
    background-image: url(\'' .  plugin_dir_url(__FILE__) . 'img/zombaio_icon_big.png\');
    background-position: 0px 0px;
}
</style>
';
    }
    public function admin_enqueue_scripts() {
        if (isset($_GET['page']) && substr($_GET['page'], 0, 10) == 'wp_zombaio') {
            wp_enqueue_script('jquery-ui-dialog');
            wp_enqueue_script('jquery-ui-tabs');
            wp_enqueue_style('jquery-ui-css', 'http://jquery-ui.googlecode.com/svn/tags/latest/themes/base/jquery.ui.all.css');
        }
    }

    /**
    Utilty admin
    */
    private function admin_page_top($title = 'WP Zombaio', $logo = TRUE) {
        echo '<div class="wrap" id="wp_zombaio">';

        echo '<div class="wp_zombaio_admin">';
        if ($logo) {
            echo '<img src="' . plugin_dir_url(__FILE__) . 'img/zombaio-logo.png" alt="' . __('Zomabio Logo', 'wp-zombaio') . '" id="wp_zombaio_logo" />';
        }

        echo '<h2>' . $title . '</h2>';
        echo '<div class="wp_zombaio_inner">';
    }
    private function admin_page_bottom() {
        echo '</div></div></div>';
    }
    private function admin_page_spacer($title = '') {
        echo '</div></div>';
        echo '<br />';
        echo '<div class="wp_zombaio_admin">';
        echo '<h2>';
        if ($title) {
            echo $title;
        }
        echo '&nbsp;</h2>';
        echo '<div class="wp_zombaio_inner">';
    }

    /**
    Main Settings Page
    */
    public function admin_page() {
        $this->admin_page_top();

        $do = isset($_REQUEST['do']) ? $_REQUEST['do'] : FALSE;
        $step = $nextstep = FALSE;

        if ($do == 'wizard') {
            $this->options->wizard = false;
            $this->saveoptions();
        }

        if ($this->options->wizard) {
            echo '<form action="' . admin_url('admin.php?page=wp_zombaio&do=wizard') . '" method="post" style="float: right; clear: right;">
                <p class="submit"><input type="submit" value="' . __('Run Wizard Again', 'wp-zombaio') . '" class="button-secondary" /></p>
            </form>';
        }

        echo '<form method="post" action="' . admin_url('admin.php?page=wp_zombaio') . '">';

        if ($do == 'wizard') {
            $step = isset($_REQUEST['step']) ? $_REQUEST['step'] : 0;
            $nextstep = $step;
            switch ($step) {
                case '3':
                    $this->options->wizard = TRUE;
                    $this->saveoptions();
                    echo '<div id="message" class="updated"><p>' . __('All Done, you are ready to go', 'wp-zombaio') . '</p></div>';
                    echo __('<p>You can now review the current options and change advanced options</p>', 'wp-zombaio');
                    $do = FALSE;
                    break;
                case '2':
                    $gw_pass = isset($_REQUEST['gw_pass']) ? $_REQUEST['gw_pass'] : FALSE;
                    if (!$gw_pass) {
                        echo '<div id="message" class="error"><p>' . __('You Need to enter your Zombaio GW Pass', 'wp-zombaio') . '</p></div>';
                    } else {
                        $this->options->gw_pass = $gw_pass;
                        $this->saveoptions();

                        echo __('<p>Now the final step</p><p>Update the <strong>Postback URL (ZScript)</strong> to the following:</p>', 'wp-zombaio');
                        echo '<input type="text" name="postbackurl" value="' . site_url() . '" />';
                        echo sprintf(__('<p>Then Press Validate</p>'
                            . '<p>Zombaio will then Validate the Settings, and if everything is correct, should say Successful and save the URL</p>'
                            . '<p>If not, please <a href="%s">Click Here</a> and we will restart the Wizard to confirm your settings</p>'
                            . '<p>If everything worked, just hit Submit below</p>', 'wp-zombaio'), admin_url('admin.php?page=wp_zombaio&do=wizard'));

                        $nextstep = 3;
                        break;
                    }
                case '1':
                    if ($step == 1) {
                        $site_id = isset($_REQUEST['site_id']) ? $_REQUEST['site_id'] : FALSE;
                        if (!$site_id) {
                            echo '<div id="message" class="error"><p>' . __('You Need to enter your Site ID', 'wp-zombaio') . '</p></div>';
                        } else {
                            $this->options->site_id = $site_id;
                            $this->saveoptions();

                            echo __('<p>Next we need to setup the Zombaio -&gt; Communications</p>'
                                . '<p>In Website Management, select Settings</p>'
                                . '<p>Copy and Enter the <strong>Zombaio GW Pass</strong> below</p>', 'wp-zombaio');
                            echo '<label for="gw_pass">' . __('Zombaio GW Pass:', 'wp-zombaio') . ' <input type="text" name="gw_pass" id="gw_pass" value="' . $this->options->gw_pass . '" /></label>';
                            $nextstep = 2;
                            break;
                        }
                    }
                case '0':
                default:
                    echo __('<p>This Wizard will Guide you thru the Zombaio Setup</p>'
                        . '<p>First your will need a Zombaio Account</p>'
                        . '<p>And to have added your Website under Website Management</p>'
                        . '<p>This will give you a <strong>Site ID</strong>, enter that now:</p>', 'wp-zombaio');
                    echo '<label for="site_id">' . __('Site ID:', 'wp-zombaio') . ' <input type="text" name="site_id" id="site_id" value="' . $this->options->site_id . '" /></label>';
                    $nextstep = 1;
            }
            echo '<input type="hidden" name="step" value="' . $nextstep . '" />';
            echo '<input type="hidden" name="do" value="' . $do . '" />';
        }

        if (!$do) {
            if ($_POST && !$nextstep) {
                foreach ($_POST as $item => $value) {
                    $value = isset($this->options->$item) ? $this->options->$item : '';
                    $value = isset($_POST[$item]) ? $_POST[$item] : $value;
                    $this->options->$item = stripslashes($value);
                }
                $this->saveoptions();
                echo '<div id="message" class="updated"><p>' . __('Settings Updated', 'wp-zombaio') , '</p></div>';
            }

            echo '<p>' . __('For Reference, your Zombaio Postback URL (ZScript) should be set to', 'wp-zombaio') . ' <input type="text" name="postbackurl" value="' . site_url() . '" /></p>';
            echo '<table>';

            echo '<tr><td style="width: 200px;"></td><td><h3>' . __('Standard Settings', 'wp-zombaio') . '</h3></td></tr>';
            echo '<tr><th valign="top"><label for="site_id">' . __('Site ID:', 'wp-zombaio') . '</label></th><td><input type="text" name="site_id" id="site_id" value="' . $this->options->site_id . '" /></label></td></tr>';
            echo '<tr><th valign="top"><label for="gw_pass">' . __('Zombaio GW Pass', 'wp-zombaio') . '</label></th><td><input type="text" name="gw_pass" id="gw_pass" value="' . $this->options->gw_pass . '" /></label></td></tr>';

            /**
            Advanced
            */

            echo '<tr><td></td><td><h3>' . __('Advanced Settings', 'wp-zombaio') . '</h3></td></tr>';
            echo '<tr><th valign="top">' . __('Delete Action', 'wp-zombaio') . '</th><td valign="top">';
                echo '<select name="delete">
                    <option value="1" ' . ($this->options->delete ? 'selected="selected"' : '') . '>' . __('Delete User Account', 'wp-zombaio') . '</option>
                    <option value="0" ' . ($this->options->delete ? '' : 'selected="selected"') . '>' . __('Block User Access', 'wp-zombaio') . '</option>
                </select>
                <h4>' . __('What to do when Zombaio Calls User Delete', 'wp-zombaio') . '</h4>
                </td></tr>';
            echo '<tr><th valign="top">' . __('Bypass IPN Verfication', 'wp-zombaio') . '</th><td valign="top">';
                echo '<select name="bypass_ipn_ip_verification">
                    <option value="0" ' . ($this->options->bypass_ipn_ip_verification ? '' : 'selected="selected"') . '>' . __('No', 'wp-zombaio') . '</option>
                    <option value="1" ' . ($this->options->bypass_ipn_ip_verification ? 'selected="selected"' : '') . '>' . __('Yes', 'wp-zombaio') . '</option>
                </select>
                <h4>' . __('We Validate the Zombaio IP against a Known list, You can bypass this if needed', 'wp-zombaio') . '</h4>
                </td></tr>';

            /**
            Login Block
            */
            echo '<tr><td></td><td><h3>' . __('Login Control', 'wp-zombaio') . '</h3></td></tr>';
            echo '<tr><td></td>
                <td>' . __('In order to Protect your Membership Site, we need to Force Users to Login, (or register)', 'wp-zombaio') . '</td></tr>
            <tr><th valign="top"><label for="redirect_target_enable">' . __('Enable Login Required', 'wp-zombaio') . '</label></th>
                <td><select name="redirect_target_enable">
                    <option value="1" ' . ($this->options->redirect_target_enable ? 'selected="selected"' : '') . '>' . __('On', 'wp-zombaio') . '</option>
                    <option value="0" ' . ($this->options->redirect_target_enable ? '' : 'selected="selected"') . '>' . __('Off', 'wp-zombaio') . '</option>
                </select></td></tr>
            <tr><th valign="top"><label for="redirect_target">' . __('Redirect Target - Page Title', 'wp-zombaio') . '</label></th>
                <td>' . __('Where shall we send them? (Leave blank for the default WP Login)', 'wp-zombaio') , '<br />';

            wp_dropdown_pages(array(
                'name' => 'redirect_target',
                'echo' => 1,
                'show_option_none' => __( '&mdash; Select &mdash;' ),
                'option_none_value' => '0',
                'selected' => $this->options->redirect_target
            ));

            echo sprintf(__('<p>See the <a href="%s">Guide</a> on usage</p>', 'wp-zombaio'), '?page=wp_zombaio_guide')
                    . '</td></tr>
            ';

            echo '
            <tr><th valign="top"><label for="redirect_home_page">' . __('Redirect off the home page?', 'wp-zombaio') . '</label></th>
                <td><select name="redirect_home_page">
                    <option value="1" ' . ($this->options->redirect_home_page ? 'selected="selected"' : '') . '>' . __('On', 'wp-zombaio') . '</option>
                    <option value="0" ' . ($this->options->redirect_home_page ? '' : 'selected="selected"') . '>' . __('Off', 'wp-zombaio') . '</option>
                </select></td></tr>
            ';

            /**
            Extras
            */

            echo '<tr><td></td><td><h3>' . __('Extras', 'wp-zombaio') . '</h3></td></tr>';
            echo '<tr><th valign="top"><label for="seal_code">' . __('Seal Code:', 'wp-zombaio') . '</label></th>
                <td><textarea name="seal_code" id="seal_code" style="width: 500px;" rows="10">' . $this->options->seal_code . '</textarea>
                <h4>' . sprintf(__('This field&#39;s contents are shown when using the [zombaio_seal] shortcode, or its widget</h4><p>See the <a href="%s">Guide</a> on where to get your Seal', 'wp-zombaio'), '?page=wp_zombaio_guide') . '</td></tr>';

            echo '<tr><th valign="top"><label for="raw_logs">' . __('Raw Logs/Editor', 'wp-zombaio') . '</label></th>
                <td><select name="raw_logs">
                    <option value="1" ' . ($this->options->raw_logs ? 'selected="selected"' : '') . '>' . __('On', 'wp-zombaio') . '</option>
                    <option value="0" ' . ($this->options->raw_logs ? '' : 'selected="selected"') . '>' . __('Off', 'wp-zombaio') . '</option>
                </select></td></tr>';

            echo '</table>';
        }

        echo '<p class="submit"><input type="submit" class="button-primary" value="' . __('Submit', 'wp-zombaio') . '" /></p>';

        echo '</form>';

        $this->admin_page_bottom();
    }

    /**
    Logs Page
    */
    public function admin_page_logs() {
        $this->admin_page_top(__('Transaction Logs', 'wp-zombaio'), FALSE);

        $states = array('user_add', 'user_delete', 'user_addcredits', 'rebill', 'chargeback', 'declined');
        $translations = array(
            'user_add'          => __('User Add', 'wp-zombaio'),
            'user_delete'       => __('User Delete', 'wp-zombaio'),
            'user_addcredits'   => __('User Add Credits', 'wp-zombaio'),
            'rebill'            => __('Rebill', 'wp-zombaio'),
            'chargeback'        => __('Chargeback', 'wp-zombaio'),
            'declined'          => __('Declined', 'wp-zombaio'),
        );

        echo '<div id="wp_zombaio_tabs">';
        echo '<ul>';
        $totals = array();
        foreach ($states as $state) {
            $count = count(get_posts(array('post_type' => 'wp_zombaio', 'post_status' => $state, 'numberposts' => -1)));
            $totals[$state] = $count;
            echo '<li><a href="#wp_zombaio_' . $state . '">' . $translations[$state] . ' (' . $count . ')</a></li>';
        }
        echo '</ul>';

        $limit = 20;

        foreach ($states as $state) {
            $offset = (isset($_REQUEST['wp_zombaio_' . $state . '_offset']) ? $_REQUEST['wp_zombaio_' . $state . '_offset'] : 0);

            echo '<div id="wp_zombaio_' . $state . '" style="height: 500px;">';
            $posts = get_posts(array(
                'post_type' => 'wp_zombaio',
                'post_status' => $state,
                'numberposts'   => $limit,
                'offset'        => $offset,
            ));
            echo '<p style="text-align: center; display: block;">' . __('Click a row to view the Full Log', 'wp-zombaio') , '</p>';
            echo '<table id="wp_zombaio_transaction_logs">';
            echo '
            <thead>
            <tr>
                <th>' . __('Log ID', 'wp-zombaio') , '</th>
                ';

                switch ($state) {
                    case 'user_add':
                        echo '<th>' . __('User', 'wp-zombaio') , '</th>';
                        echo '<th>' . __('Amount', 'wp-zombaio') , '</th>';
                        echo '<th>' . __('Transaction ID', 'wp-zombaio') , '</th>';
                        echo '<th>' . __('Subscription ID', 'wp-zombaio') , '</th>';
                        echo '<th>' . __('Pricing ID', 'wp-zombaio') , '</th>';
                        break;
                    case 'user_delete':
                        echo '<th>' . __('User', 'wp-zombaio') , '</th>';
                        echo '<th>' . __('Delete Reason', 'wp-zombaio') . '</th>';
                        break;
                    case 'user_addcredits':
                        echo '<th>' . __('User', 'wp-zombaio') , '</th>';
                        echo '<th>' . __('Amount', 'wp-zombaio') , '</th>';
                        echo '<th>' . __('Transaction ID', 'wp-zombaio') , '</th>';
                        echo '<th>' . __('Pricing ID', 'wp-zombaio') , '</th>';
                        break;
                    case 'rebill':
                        echo '<th>' . __('User', 'wp-zombaio') , '</th>';
                        echo '<th>' . __('Amount', 'wp-zombaio') , '</th>';
                        echo '<th>' . __('Transaction ID', 'wp-zombaio') , '</th>';
                        echo '<th>' . __('Subscription ID', 'wp-zombaio') , '</th>';
                        echo '<th>' . __('Status', 'wp-zombaio') , '</th>';
                        echo '<th>' . __('Retry', 'wp-zombaio') , '</th>';
                        break;
                    case 'chargeback':
                        echo '<th>' . __('User', 'wp-zombaio') . '</th>';
                        echo '<th>' . __('Amount', 'wp-zombaio') . '</th>';
                        echo '<th>' . __('Reason', 'wp-zombaio') . '</th>';
                        echo '<th>' . __('Liability', 'wp-zombaio') . '</th>';
                    case 'declined':
                        echo '<th>' . __('Amount', 'wp-zombaio') , '</th>';
                        echo '<th>' . __('Transaction ID', 'wp-zombaio') , '</th>';
                        echo '<th>' . __('Reason', 'wp-zombaio') , '</th>';
                        break;
                }

                echo '<th>' . __('Log Time', 'wp-zombaio') , '</th>';

                echo '
            </tr>
            </thead>
            <tbody>';
            foreach ($posts as $post) {
                echo '<tr class="renderRawLog">';
                echo '<td>' . $post->ID . '</td>';

                $json = get_post_meta($post->ID, 'json_packet', TRUE);
                $json = json_decode($json);

                switch ($state) {
                    case 'user_add':
                        echo '<td>';
                        if ($user_id = get_post_meta($post->ID, 'user_id', TRUE)) {
                            $user = get_user_by('id', $user_id);
                            if (!$user) {
                                echo __('Unreg: ', 'wp-zombaio') . ' ' . $json->username;
                            } else {
                                echo '(' . $user_id . ') ' . $user->display_name . '<br />' . $user->user_email;
                            }
                        } else {
                            echo __('Unreg: ', 'wp-zombaio') . ' ' . $json->username;
                        }
                        echo '</td>';
                        echo '<td>';
                        if ($amount = get_post_meta($post->ID, 'amount', TRUE)) {
                            echo $json->Amount_Currency . ' ' . $amount;
                        }
                        echo '</td>';
                        echo '<td>' . $json->TRANSACTION_ID . '</td>';
                        echo '<td>' . $json->SUBSCRIPTION_ID . '</td>';
                        echo '<td>' . $json->PRICING_ID . '</td>';
                        break;
                    case 'user_delete':
                        echo '<td>';
                        if ($user_id = get_post_meta($post->ID, 'user_id', TRUE)) {
                            $user = get_user_by('id', $user_id);
                            if (!$user) {
                                echo __('Unreg: ', 'wp-zombaio') . ' ' . $json->username;
                            } else {
                                echo '(' . $user_id . ') ' . $user->display_name . '<br />' . $user->user_email;
                            }
                        } else {
                            echo __('Unreg: ', 'wp-zombaio') . ' ' . $json->username;
                        }
                        echo '</td>';
                        echo '<td>' . (isset($this->delete_codes[$json->ReasonCode]) ? $json->ReasonCode . ' - ' . $this->delete_codes[$json->ReasonCode] : 'Unknown') . '</td>';
                        break;
                    case 'user_addcredits':
                        echo '<td>';
                        if ($user_id = get_post_meta($post->ID, 'user_id', TRUE)) {
                            $user = get_user_by('id', $user_id);
                            if (!$user) {
                                echo __('Unreg: ', 'wp-zombaio') . ' ' . $json->username;
                            } else {
                                echo '(' . $user_id . ') ' . $user->display_name . '<br />' . $user->user_email;
                            }
                        } else {
                            echo __('Unreg: ', 'wp-zombaio') . ' ' . $json->username;
                        }
                        echo '</td>';
                        echo '<td>';
                        if ($amount = get_post_meta($post->ID, 'amount', TRUE)) {
                            echo $json->Amount_Currency . ' ' . $amount;
                        }
                        echo '</td>';
                        echo '<td>' . $json->TRANSACTION_ID . '</td>';
                        echo '<td>' . $json->PRICING_ID . '</td>';
                        break;
                    case 'rebill':
                        echo '<td>';
                        if ($user_id = get_post_meta($post->ID, 'user_id', TRUE)) {
                            $user = get_user_by('id', $user_id);
                            if (!$user) {
                                echo __('Unreg: ', 'wp-zombaio') . ' ' . $json->username;
                            } else {
                                echo '(' . $user_id . ') ' . $user->display_name . '<br />' . $user->user_email;
                            }
                        } else {
                            echo __('Unreg: ', 'wp-zombaio') . ' ' . $json->username;
                        }
                        echo '</td>';
                        echo '<td>';
                        if ($amount = get_post_meta($post->ID, 'amount', TRUE)) {
                            echo $json->Amount_Currency . ' ' . $amount;
                        }
                        echo '</td>';
                        echo '<td>' . $json->TRANSACTION_ID . '</td>';
                        echo '<td>' . $json->SUBSCRIPTION_ID . '</td>';
                        echo '<td>' . $json->Success . '</td>';
                        echo '<td>' . $json->Retries . '</td>';
                        break;
                    case 'chargeback':
                        echo '<td>';
                        if ($user_id = get_post_meta($post->ID, 'user_id', TRUE)) {
                            $user = get_user_by('id', $user_id);
                            if (!$user) {
                                echo __('Unreg: ', 'wp-zombaio') . ' ' . $json->username;
                            } else {
                                echo '(' . $user_id . ') ' . $user->display_name . '<br />' . $user->user_email;
                            }
                        } else {
                            echo __('Unreg: ', 'wp-zombaio') . ' ' . $json->username;
                        }
                        echo '</td>';
                        echo '<td>';
                        if ($amount = get_post_meta($post->ID, 'amount', TRUE)) {
                            echo $json->Amount_Currency . ' ' . $amount;
                        }
                        echo '</td>';
                        echo '<td>' . (isset($this->chargeback_codes[$json->ReasonCode]) ? $json->ReasonCode . ' - ' . $this->chargeback_codes[$json->ReasonCode] : 'Unknown') . '</td>';
                        echo '<td>' . (isset($this->chargeback_liability_code[$json->LiabilityCode]) ? $json->LiabilityCode . ' - ' . $this->chargeback_liability_code[$json->LiabilityCode] : 'Unknown') . '</td>';
                        break;
                    case 'declined':
                        echo '<td>';
                        if ($amount = get_post_meta($post->ID, 'amount', TRUE)) {
                            echo $json->Amount_Currency . ' ' . $amount;
                        }
                        echo '</td>';
                        echo '<td>' . $json->TRANSACTION_ID . '</td>';
                        echo '<td>' . (isset($this->decline_codes[$json->ReasonCode]) ? $json->ReasonCode . ' - ' . $this->decline_codes[$json->ReasonCode] : 'Unknown') . '</td>';
                        break;
                }

                echo '<td>' . $post->post_date . '</td>';

                echo '<td class="renderRawLogData" style="display: none;">' . get_post_meta($post->ID, 'logmessage', TRUE) . '<br /><br /><textarea style="width: 400px;" rows="30" readonly="readonly">' . $post->post_content . '</textarea></td>';

                echo '</tr>';
            }
            echo '</tbody><tfoot>';

            // pagination
            echo '<tr><td colspan="20" style="text-align: center;">';
            if ($offset >= $limit) {
                echo '<a href="?page=wp_zombaio_logs&wp_zombaio_' . $state . '_offset=' . ($offset - $limit) . '#wp_zombaio_' . $state . '" class="alignleft">' . __('Back', 'wp-zombaio') , '</a>';
            }
            echo (($offset / $limit) + 1) . '/' . ceil($totals[$state] / $limit);
            if (count($posts) == $limit) {
                echo '<a href="?page=wp_zombaio_logs&wp_zombaio_' . $state . '_offset=' . ($offset + $limit) . '#wp_zombaio_' . $state . '" class="alignright">' . __('Forward', 'wp-zombaio') , '</a>';
            }
            echo '</td></tr>';
            // end
            echo '</tfoot></table>';

            echo '</div>';
        }
        echo '</div>';

        echo '
<script type="text/javascript">
jQuery(document).ready(function() {
    jQuery(\'#wp_zombaio_tabs\').tabs();
    jQuery(\'.renderRawLog\').click(function() {
        jQuery(this).find(\'.renderRawLogData\').dialog({width: 440, modal: true});
    });
});
</script>
';

        $this->admin_page_bottom();
    }

    /**
    Guide Page
    */
    function admin_page_guide() {
        $this->admin_page_top();

        echo __('
<h3>Zombaio Join Form</h3>
<p>Zombaio supports two methods of joining your website</p>
<p>Under Website Management, when viewing the Settings for your Site, the <strong>Zombaio Join Form Template</strong> has two options</p>
<h4>Option 1 - Simple Credit Card Template</h4>
<p>This method means on your site, for each pricing structure, you&#39;ll need multiple Join Form Widgets/Shortcodes, on your WordPress blog, to direct the user to Pay</p>
<p>This is good for when you want to make a designed entry/lading page on your blog/site</p>
<h4>Option 2 - e-Ticket Selection Template</h4>
<p>With this method, any Join Form URL, results in the same form</p>
<p>When arriving at Zombaio, users can then choose which Subscription/Package to join, so in this case you only need a single Join Form Widget/Shortcode on your WordPress Blog</p>
<p>The choice is up to you</p>

<h3>Google Analytics</h3>
<p>If you add your GA Property ID, you can track if users are navigating thru your site, and leaving before paying</p>
<p>This will be handy when considering your pricing structure</p>

<h3>Join Form Image</h3>
<p>It is a good idea to upload your Site Logo to Zombaio for use on Join Forms, helps remind users that whilst on the Zombaio Website paying, they are joining your Website!</p>
', 'wp-zombaio');

$this->admin_page_spacer(__('Login Control', 'wp-zombaio'));

        echo __('
<p>With Login Control enabled, only Logged In Users (and thus people who have paid), can access your content</p>
<p>You can choose where users are directed to, the default is the WP-Login.php page, but since WP Zomabio does not hook/work on the standard registration page, users cannot sign up from here</p>
<p>So normally you shoule have <i>Anyone can Register</i> disabled</p>
<p>You can pick a page to send users to, this page should containt a Login Form and a Register Form (or at least a link to your Zombaio Join Form)</p>
<p>Zombaio Rules (or at least advice) that you should put a Zombaio Seal on your site somewhere, this is a good page to place it</p>
<p>You can do something like:</p>', 'wp-zombaio');
        echo '<textarea readonly="readonly" style="width: 600px;" rows="2">[zombaio_join align="left" width="300" join_url="JOINURL" buttonalign="center"]PRICING[/zombaio_join]
[zombaio_seal align="right"]</textarea>';
        echo __('
<p>Swap JOINURL for your Zombaio Pricing Structure, Join Form URL, and PRICING for some Introdutory Text, such as the Price and Membership Terms</p>
<p>That makes a nice Registration Landing Page</p>
', 'wp-zombaio');
        echo sprintf(__('You can see an <a href="%s" target="_blank">example here</a>', 'wp-zombaio'), 'http://dev.barrycarlyon.co.uk/?page_id=69');

$this->admin_page_spacer(__('Site Seal', 'wp-zombaio'));

        echo sprintf(__('
<p>You can get your Seal Code as follows:
    <ul class="disc">
        <li>Login to <a href="%s">ZOA</a></li>
        <li>Navigate to:
            <ul class="disc">
                <li>Tools</li>
                <li>Pricing Structure</li>
            </ul>
        </li>
        <li>Then Manage/Edit any Pricing Structure for your site</li>
        <li>Then in the HTML Button Field, copy everything from and including &lt;!-- START ZOMBAIO SEAL CODE --&gt; to &lt;!-- END ZOMBAIO SEAL CODE --&gt;</li>
        <li>Paste that into this field</li>
        <li><a href="#nowhere" onclick="%s">View a ScreenShot/Example</a>, the Seal Code is Hightlighted</li>
    </ul>
</p>', 'wp-zombaio'), 'https://secure.zombaio.com/ZOA/', 'jQuery(\'#wp_zombaio_sealshot\').dialog({width: 720});');

echo '
<div id="wp_zombaio_sealshot" style="display: none;">
    <img src="' . plugin_dir_url(__FILE__) . 'img/seal_code.png" alt="' . __('Seal Screenshot', 'wp-zombaio') . '" style="border: 1px solid #000000;" />
</div>
';

$this->admin_page_spacer(__('Running a Membership Site', 'wp-zombaio'));

echo sprintf(__('<p>For more thoughts and advice on Running a membership site, check out the <a href="%s" target="_blank">Your Members Blog</a></p>', 'wp-zombaio'), 'http://blog.yourmembers.co.uk/');

$this->admin_page_spacer(__('Caching, (Plugins or Otherwise) and CloudFlare', 'wp-zombaio'));

echo sprintf(__('<p>If you are running anything Caching related, (which you probably shouldn&#39;t on a <a href="%s" target="_blank">membership site</a>), you may need to Whitelist Zombaios Notifications IP Addresses, so they bypass the potential block</p>', 'wp-zombaio'), 'http://blog.yourmembers.co.uk/2012/your-members-and-caching/');
echo sprintf(__('<h4>Known Caching Plugins and How to Bypass</h4>

<ul class="disc">
<li><strong>CloudFlare Whitelisting</strong>, visit <a href="%s" target="_blank">Threat Control</a> add Custom Rule, and Trust all the Zombaio IP&#39;s</li>
<li><strong>Misc</strong>, generally you should allow all Query Strings containing <i>ZombaioGWPass</i> to Bypass any and all Caching</li>
</ul>

<h5>Want to add a Caching Bypass Solution to this list, <a href="%s">Drop me a Line</a></h5>

<h4>Zombaio IPs</h4>
<p>You can load/fetch the Current Zombaio Known IP Addresses</p>
', 'wp-zombaio'), 'https://www.cloudflare.com/threat-control', 'http://barrycarlyon.co.uk/wordpress/contact/');

echo '<a href="#load" id="loadips" class="button-secondary">' . __('Load IP&#39;s', 'wp-zombaio') . '</a> ' . __('or', 'wp-zombaio') . ' <a href="#load" id="loadcsvips" class="button-secondary">' . __('Load CSV List of IP&#39;s', 'wp-zombaio') . '</a>';

echo '
<div id="loadipsoutput"></div>

<script type="text/javascript">
jQuery(document).ready(function() {
    jQuery(\'#loadips\').click(function() {
        jQuery.get(\'' . home_url('?wp_zombaio_ips=1') . '\', function(data) {
            jQuery(\'#loadipsoutput\').html(data).dialog({height: 400});
        })
    });
    jQuery(\'#loadcsvips\').click(function() {
        jQuery.get(\'' . home_url('?wp_zombaio_ips=1&csv=1') . '\', function(data) {
            jQuery(\'#loadipsoutput\').html(data).dialog({height: 240});
        })
    });
});
</script>
';

        $this->admin_page_bottom();
    }

    /**
    Payment Processor
    */
    public function detect() {
        if (isset($_GET['wp_zombaio_ips']) && $_GET['wp_zombaio_ips'] == 1) {
            $ips = $this->load_ipn_ips();
            if (isset($_GET['csv']) && $_GET['csv'] == 1) {
                echo '<textarea style="width: 270px;" rows="10" readonly="readonly">' . implode(',', $ips) . '</textarea>';
                exit;
            }
            echo '<ul>';
            foreach ($ips as $ip) {
                echo '<li><input type="text" readonly="readonly" value="' . $ip . '" size="15" /></li>';
            }
            echo '</ul>';
            exit;
        }
        $wp_zombaio = new wp_zombaio(TRUE);
        $wp_zombaio->process();
    }

    private function process() {
        $this->init();

        $gw_pass = isset($_GET['ZombaioGWPass']) ? $_GET['ZombaioGWPass'] : FALSE;
        if (!$gw_pass) {
            return;
        }
        if ($gw_pass != $this->options->gw_pass) {
            header('HTTP/1.0 401 Unauthorized');
            echo '<h1>Zombaio Gateway 1.1</h1><h3>Authentication failed. GW Pass</h3>';
            exit;
        }

        if (!$this->verify_ipn_ip()) {
            header('HTTP/1.0 403 Forbidden');
            echo '<h1>Zombaio Gateway 1.1</h1><h3>Authentication failed, you are not Zombaio.</h3>';
            exit;
        }

        $user_id = false;
        $username = isset($_GET['username']) ? $_GET['username'] : FALSE;

        // verify site ID
        $site_id = isset($_GET['SITE_ID']) ? $_GET['SITE_ID'] : (isset($_GET['SiteID']) ? $_GET['SiteID'] : FALSE);
        if (!$site_id || $site_id != $this->options->site_id) {
            if (substr($username, 0, 4) == 'Test') {
                // test mode
                header('HTTP/1.1 200 OK');
                echo 'OK';
                exit;
            }
            header('HTTP/1.0 401 Unauthorized');
            echo '<h1>Zombaio Gateway 1.1</h1><h3>Authentication failed. Site ID MisMatch</h3>';
            exit;
        }

        $action = isset($_GET['Action']) ? $_GET['Action'] : FALSE;
        if (!$action) {
            header('HTTP/1.0 401 Unauthorized');
            echo '<h1>Zombaio Gateway 1.1</h1><h3>Authentication failed. No Action</h3>';
            exit;
        }

        $logid = $this->log();
        $logmsg = '';

        $action = strtolower($action);
        switch ($action) {
            case 'user.add': {
                $subscription_id = isset($_GET['SUBSCRIPTION_ID']) ? $_GET['SUBSCRIPTION_ID'] : FALSE;
                if (!$subscription_id) {
                    header('HTTP/1.0 401 Unauthorized');
                    echo '<h1>Zombaio Gateway 1.1</h1><h3>Authentication failed. No Sub</h3>';
                    exit;
                }

                $email = $_GET['EMAIL'];
                $fname = $_GET['FIRSTNAME'];
                $lname = $_GET['LASTNAME'];
                $password = $_GET['password'];

                $user_id = username_exists($username);
                if (!$user_id) {
                    $email_test = is_email($email);
                    if ($email_test == $email) {
                        $user_id = email_exists($email);
                        if (!$user_id) {
                            $user_id = wp_create_user( $username, $password, $email );
                            if (!is_wp_error($user_id)) {
                                $logmsg = 'User Created OK';
                            } else {
                                // error
                                $logmsg = 'User Create: Fail ' . $user_id->get_error_message();
                            }
                        } else {
                            // email exists
                            $logmsg = 'User Create: Email Exists, Activating User';
                        }
                    } else {
                        // invalid/empty email
                        $logmsg = 'User Create: Failed ' . $email_test;
                    }
                } else {
                    // username exists
                    $logmsg = 'User Create: UserName Exists, Activating User';
                }

                if ($user_id) {
                    update_user_meta($user_id, 'wp_zombaio_delete', FALSE);
                    update_user_meta($user_id, 'wp_zombaio_subscription_id', $subscription_id);
                    update_user_meta($user_id, 'first_name', $fname);
                    update_user_meta($user_id, 'last_name', $lname);
                } else {
                    // epic fail
                    echo 'ERROR';
                    exit;
                }
                break;
            }
            case 'user.delete': {
                $user = get_user_by('login', $username);
                if (!$user) {
                    echo 'USER_DOES_NOT_EXIST';
                    exit;
                }
                // delete of suspend?
                if ($this->options->delete == TRUE) {
                    include('./wp-admin/includes/user.php');
                    wp_delete_user($user->ID);
                    // could test for deleted and return ERROR if needed
                    $logmsg = 'User was deleted';
                } else {
                    update_user_meta($user->ID, 'wp_zombaio_delete', TRUE);
                    $logmsg ='User was suspended';
                }
                break;
            }
            case 'rebill': {
                $subscription_id = isset($_GET['SUBSCRIPTION_ID']) ? $_GET['SUBSCRIPTION_ID'] : FALSE;
                if (!$subscription_id) {
                    header('HTTP/1.0 401 Unauthorized');
                    echo '<h1>Zombaio Gateway 1.1</h1><h3>Authentication failed. Rebill No SUB ID</h3>';
                    exit;
                }

                //get user ID by subscription ID
                global $wpdb;
                $query = 'SELECT user_id FROM ' . $wpdb->usermeta . ' WHERE meta_key = \'wp_zombaio_subscription_id\' AND meta_value = \'' . $subscription_id . '\'';

                $user_id = $wpdb->get_var($query);
                if (!$user_id) {
                    echo 'USER_DOES_NOT_EXIST';
                    exit;
                } else {
                    $success = ym_GET('Success', 0);
                    // 0 FAIL 2 FAIL retry in 5 days
                    if ($success == 1) {
                        // all good
                        update_user_meta($user_id, 'wp_zombaio_delete', FALSE);
                    } else {
                        if ($success) {
                            $logmsg = 'Rebill Charge Failed: Retry in 5 Days';
                        } else {
                            $logmsg = 'Rebill Charge Failed: REASON CODE';
                        }
                    }
                }
                $logmsg = 'User rebilled cleared';
                break;
            }
            case 'chargeback': {
                $logmsg = 'A Chargeback Occured';
                break;
            }
            case 'declined': {
                $subscription_id = isset($_GET['SUBSCRIPTION_ID']) ? $_GET['SUBSCRIPTION_ID'] : FALSE;
                if ($subscription_id) {
                    //get user ID by subscription ID
                    global $wpdb;
                    $query = 'SELECT user_id FROM ' . $wpdb->usermeta . ' WHERE meta_key = \'wp_zombaio_subscription_id\' AND meta_value = \'' . $subscription_id . '\'';

                    $user_id = $wpdb->get_var($query);
                    // should fire a user.delete after true fail

//                  if (!$user_id) {
//                      echo 'USER_DOES_NOT_EXIST';
//                      exit;
//                  }
                    $logmsg = 'User Card Rebill was Declined';
                } else {
                    $user_id = '';
                    $logmsg = 'User Card was Declined';
                }
                break;
            }
            case 'user.addcredits': {
            }
            default: {
                header('HTTP/1.0 401 Unauthorized');
                echo '<h1>Zombaio Gateway 1.1</h1><h3>Authentication failed. No Idea</h3>';
                exit;
            }
        }

        // log result
        $this->logresult($logid, $logmsg, $user_id);
        $this->notifyadmin($logid, $logmsg);

        echo 'OK';

        // emit hook
        do_action('wp_zombaio_process', $action, $_REQUEST, $user_id, $username, $logid);

        exit;
    }

    private function abort() {
    }

    private function log() {
        $username = isset($_GET['username']) ? ' - ' . $_GET['username'] : '';
        $post = array(
            'post_title'        => 'Zombaio ' . $_GET['Action'] . $username,
            'post_type'         => 'wp_zombaio',
            'post_status'       => (isset($_GET['Action']) ? str_replace('.', '_', strtolower($_GET['Action'])) : 'unknown'),
            'post_content'      => print_r($_GET, TRUE),
        );
        $r = @wp_insert_post($post);

        update_post_meta($r, 'json_packet', json_encode($_GET));

        return $r;
    }
    private function logresult($logid, $logmsg, $user_id) {
        update_post_meta($logid, 'logmessage' , $logmsg);
        update_post_meta($logid, 'user_id', $user_id);
        if (isset($_GET['Amount'])) {
            update_post_meta($logid, 'amount', $_GET['Amount']);
        }
        return;
    }

    private function notifyadmin($logid, $logmsg) {
        // notify admin
        $subject = 'WP Zombaio: Payment Result';
        $message = 'A Payment has been processed' . "\n"
            . 'The Result was: ' . $logmsg . "\n"
            . 'Full Log: ' . print_r($_GET, TRUE) . "\n"
            . 'Love WP Zombaio';
        @wp_mail(get_option('admin_email'), $subject, $message);
        return;
    }

    private function verify_ipn_ip() {
        if ($this->options->bypass_ipn_ip_verification) {
            return TRUE;
        }
        $ip = $_SERVER['REMOTE_ADDR'];

        $ips = $this->load_ipn_ips();
        if ($ips) {
            if (in_array($ip, $ips)) {
                return TRUE;
            }
        }
        return FALSE;
    }
    /**
    utility
    */
    private function load_ipn_ips() {
        $request = new WP_Http;
        $data = $request->request('http://www.zombaio.com/ip_list.txt');
        $data = explode('|', $data['body']);
        return $data;
    }

    /**
    widgets
    */
    public function widgets_init() {
        register_widget('wp_zombaio_widget');
        register_widget('wp_zombaio_seal');
        register_widget('wp_zombaio_login');
//      register_widget('wp_zombaio_registerlogin');
    }


    /**
    FrontEnd
    */
    public function frontend() {
        add_shortcode('zombaio_seal', array($this, 'shortcode_zombaio_seal'));
        add_shortcode('zombaio_join', array($this, 'zombaio_join'));
        add_shortcode('zombaio_login', array($this, 'zombaio_login'));

        add_action('wp_enqueue_scripts', array($this, 'wp_enqueue_scripts'));

        add_action('template_redirect', array($this, 'template_redirect'));
    }

    /**
    login block
    */
    public function template_redirect() {
        if (!is_user_logged_in() && $this->options->redirect_target_enable) {
            $redirect = false;

            if ($this->options->redirect_target) {
                $target = $this->options->redirect_target;
                $target_url = get_permalink($target);
            } else {
                $target = true;
                $target_url = home_url('wp-login.php');
            }

            // valid target?
            if ($target) {
                // dev hook is page excluded?
                if (is_home() && !$this->options->redirect_home_page) {
                    $target = false;
                } else if (is_singular() || is_single() || is_page()) {
                    $meta = get_post_meta(get_the_ID(), 'wp_zombaio_redirect_disable', true);
                    if ($meta == '1') {
                        $target = false;
                    }
                }

                if ($target) {
                    if ($target === true) {
                        $redirect = true;
                    } else if (is_singular() || is_single() || is_page()) {
                        if (get_the_ID() != $target) {
                            $redirect = true;
                        }
                    } else {
                        $redirect = true;
                    }

                    if ($redirect) {
                        header('Location: ' . $target_url);
                        exit;
                    }
                }
            }
        }
    }
    public function wp_authenticate_user($user) {
        if (get_user_meta($user->ID, 'wp_zombaio_delete', true)) {
            $err = new WP_Error();
            $err->add('wp_zombaio_error', 'Zombaio Failed Rebill');
            return $err;
        }
        return $user;
    }

    /**
    script
    */
    public function wp_enqueue_scripts() {
        wp_enqueue_style('wp_zombaio_css', plugin_dir_url(__FILE__) . basename(__FILE__) . '?do=css');
    }

    /**
    ShortCodes
    */
    public function shortcode_zombaio_seal($args = array()) {
        $align = isset($args['align']) ? 'align' . $args['align'] : 'aligncenter';
        return '<div class="' . $align . '" style="width: 130px;">' . $this->options->seal_code . '</div>';
    }
    public function zombaio_join($args = array(), $content = '') {
        if (is_user_logged_in()) {
            return '';
        }

        $this->form_index++;

        $html = '';

        $join_url = isset($args['join_url']) ? $args['join_url'] : FALSE;

        if ($join_url) {
            if (FALSE !== strpos($join_url, 'zombaio.com')) {
                list($crap, $zombaio, $com_crap, $id, $zom) = explode('.', $args['join_url']);
            } else {
                $id = $args['join_url'];
            }

            if ($id) {
                $align = isset($args['align']) ? 'align' . $args['align'] : 'aligncenter';
                $style = isset($args['width']) ? 'width: ' . $args['width'] . 'px;' : '';
                $buttonalign = isset($args['buttonalign']) ? 'align' . $args['buttonalign'] : 'alignright';

                $html .= '<form action="https://secure.zombaio.com/?' . $this->options->site_id . '.' . $id . '.ZOM" method="post" class="wp_zombaio_form ' . $align . '" style="' . $style . '">';

                $html .= '<label for="email' . $this->form_index . '">Email: <input type="email" id="email' . $this->form_index . '" name="email" /></label>';
                $html .= '<label for="username' . $this->form_index . '">Username: <input type="text" id="username' . $this->form_index . '" name="username" /></label>';
                $html .= '<label for="password' . $this->form_index . '">Password: <input type="password" id="password' . $this->form_index . '" name="password" /></label>';

                $html .= '<p class="jointext">' . $content . '</p>';

                $submit = isset($args['submit']) && $args['submit'] ? $args['submit'] : 'Join';

                $html .= '<div class="' . $buttonalign . '" style="width: 50px;">';
                $html .= '<input type="submit" name="zomPay" value="' . $submit . '" />';
                $html .= '</div>';
                $html .= '</form>';
            }
        }

        return $html;
    }

    public function zombaio_login() {
        return wp_login_form(array('echo' => FALSE));
    }
}

$do = isset($_GET['do']) ? $_GET['do'] : FALSE;
if ($do == 'css') {
    header('Content-Type: text/css');
    echo '
.wp_zombaio_form, .wp_zombaio_form label, .wp_zombaio_form p { display: block; clear: both; }
.wp_zombaio_form label input { float: right; }
.wp_zombaio_form .jointext { text-align: center; margin-bottom: 0px; }
';
    exit;
}

new wp_zombaio();

/**
widget
*/
class wp_zombaio_widget extends wp_widget {
    function wp_zombaio_widget() {
        $this->widgetclassname = 'widget_wp_zombaio_widget';

        $widget_ops = array('classname' => $this->widgetclassname, 'description' => __('Use this widget to add a Join Form to your SideBar', 'wp-zombaio'));
        $this->WP_Widget($this->widgetclassname, __('WP Zombaio Join Widget', 'wp-zombaio'), $widget_ops);
        $this->alt_option_name = $this->widgetclassname;

        add_action('save_post', array(&$this, 'flush_widget_cache'));
        add_action('deleted_post', array(&$this, 'flush_widget_cache'));
        add_action('switch_theme', array(&$this, 'flush_widget_cache'));

        parent::__construct(false, __('WP Zombaio Join Widget', 'wp-zombaio'));
    }

    function widget($args, $instance) {
        if (is_user_logged_in()) {
            return '';
        }
        $cache = wp_cache_get($this->widgetclassname, 'widget');
        if (!is_array($cache)) {
            $cache = array();
        }
        if (!isset($args['widget_id'])) {
            $args['widget_id'] = null;
        }
        if (isset($cache[$args['widget_id']])) {
            echo $cache[$args['widget_id']];
            return;
        }

        ob_start();
        extract($args);
        extract($instance);

        echo $before_widget;
        echo $before_title . $title . $after_title;
        echo do_shortcode('[zombaio_join join_url="' . $join_url . '" submit="' . $submit . '"]' . $message . '[/zombaio_join]');
        echo $after_widget;

        $cache[$args['widget_id']] = ob_get_flush();
        wp_cache_set($this->widgetclassname, $cache, 'widget');
    }

    function update($new_instance, $old_instance) {
        $instance = $old_instance;
        $instance['title'] = $new_instance['title'];
        $instance['join_url'] = $new_instance['join_url'];
        $instance['submit'] = $new_instance['submit'];
        $instance['message'] = $new_instance['message'];
        return $instance;
    }
    function form($instance) {
        $defaults = array(
            'title'     => 'Join the Site',
            'join_url'  => '',
            'submit'    => 'Join',
            'message'   => '',
        );
        $instance = wp_parse_args((array)$instance, $defaults);

        echo '<p>';
        echo '<label for="' . $this->get_field_id('title') . '">' . __('Title:', 'wp-zombaio') . '</label>';
        echo '<input class="widefat" type="text" name="' . $this->get_field_name('title') . '" id="' . $this->get_field_id('title') . '" value="' . $instance['title'] . '" />';
        echo '</p>';
        echo '<p>';
        echo '<label for="' . $this->get_field_id('join_url') . '">' . __('Join Form URL:', 'wp-zombaio') . '</label>';
        echo '<input class="widefat" type="text" name="' . $this->get_field_name('join_url') . '" id="' . $this->get_field_id('join_url') . '" value="' . $instance['join_url'] . '" />';
        echo '</p>';
        echo '<p>';
        echo '<label for="' . $this->get_field_id('message') . '">' . __('Intro Text:', 'wp-zombaio') . '</label>';
        echo '<input class="widefat" type="text" name="' . $this->get_field_name('message') . '" id="' . $this->get_field_id('message') . '" value="' . $instance['message'] . '" />';
        echo '</p>';
        echo '<p>';
        echo '<label for="' . $this->get_field_id('submit') . '">' . __('Submit Button:', 'wp-zombaio') . '</label>';
        echo '<input class="widefat" type="text" name="' . $this->get_field_name('submit') . '" id="' . $this->get_field_id('submit') . '" value="' . $instance['submit'] . '" />';
        echo '</p>';
    }

    function flush_widget_cache() {
        wp_cache_delete($this->widgetclassname, 'widget');
    }
}

class wp_zombaio_seal extends wp_widget {
    function wp_zombaio_seal() {
        $this->widgetclassname = 'widget_wp_zombaio_seal';

        $widget_ops = array('classname' => $this->widgetclassname, 'description' => __('Use this widget to add a Zombaio Site Seal to your SideBar', 'wp-zombaio'));
        $this->WP_Widget($this->widgetclassname, __('WP Zombaio Seal Widget', 'wp-zombaio'), $widget_ops);
        $this->alt_option_name = $this->widgetclassname;

        add_action('save_post', array(&$this, 'flush_widget_cache'));
        add_action('deleted_post', array(&$this, 'flush_widget_cache'));
        add_action('switch_theme', array(&$this, 'flush_widget_cache'));

        parent::__construct(false, __('WP Zombaio Seal Widget', 'wp-zombaio'));
    }

    function widget($args, $instance) {
        $cache = wp_cache_get($this->widgetclassname, 'widget');
        if (!is_array($cache)) {
            $cache = array();
        }
        if (!isset($args['widget_id'])) {
            $args['widget_id'] = null;
        }
        if (isset($cache[$args['widget_id']])) {
            echo $cache[$args['widget_id']];
            return;
        }

        ob_start();
        extract($args);
        extract($instance);

        echo $before_widget;
        echo do_shortcode('<br /><center>[zombaio_seal]</center>');
        echo $after_widget;

        $cache[$args['widget_id']] = ob_get_flush();
        wp_cache_set($this->widgetclassname, $cache, 'widget');
    }

    function flush_widget_cache() {
        wp_cache_delete($this->widgetclassname, 'widget');
    }
}

class wp_zombaio_login extends wp_widget {
    function wp_zombaio_login() {
        $this->widgetclassname = 'widget_wp_zombaio_login';

        $widget_ops = array('classname' => $this->widgetclassname, 'description' => __('Use this widget to add a Login Form to your SideBar', 'wp-zombaio'));
        $this->WP_Widget($this->widgetclassname, __('WP Zombaio Login Widget', 'wp-zombaio'), $widget_ops);
        $this->alt_option_name = $this->widgetclassname;

        add_action('save_post', array(&$this, 'flush_widget_cache'));
        add_action('deleted_post', array(&$this, 'flush_widget_cache'));
        add_action('switch_theme', array(&$this, 'flush_widget_cache'));

        parent::__construct(false, __('WP Zombaio Login Widget', 'wp-zombaio'));
    }

    function widget($args, $instance) {
        $cache = wp_cache_get($this->widgetclassname, 'widget');
        if (!is_array($cache)) {
            $cache = array();
        }
        if (!isset($args['widget_id'])) {
            $args['widget_id'] = null;
        }
        if (isset($cache[$args['widget_id']])) {
            echo $cache[$args['widget_id']];
            return;
        }

        ob_start();
        extract($args);
        extract($instance);

        echo $before_widget;
        echo $before_title . $title . $after_title;
        wp_login_form();
        echo $after_widget;

        $cache[$args['widget_id']] = ob_get_flush();
        wp_cache_set($this->widgetclassname, $cache, 'widget');
    }

    function update($new_instance, $old_instance) {
        $instance = $old_instance;
        $instance['title'] = $new_instance['title'];
        return $instance;
    }
    function form($instance) {
        $defaults = array(
            'title'     => 'Login',
        );
        $instance = wp_parse_args((array)$instance, $defaults);

        echo '<p>';
        echo '<label for="' . $this->get_field_id('title') . '">' . __('Title:', 'wp-zombaio') . '</labe>';
        echo '<input class="widefat" type="text" name="' . $this->get_field_name('title') . '" id="' . $this->get_field_id('title') . '" value="' . $instance['title'] . '" />';
        echo '</p>';
    }

    function flush_widget_cache() {
        wp_cache_delete($this->widgetclassname, 'widget');
    }
}
