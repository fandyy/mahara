<?php
/**
 * Mahara: Electronic portfolio, weblog, resume builder and social networking
 * Copyright (C) 2012 Catalyst IT
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    mahara
 * @subpackage auth-browserid
 * @author     Francois Marier <francois@catalyst.net.nz>
 */

defined('INTERNAL') || die();
require_once(get_config('docroot') . 'auth/lib.php');
require_once(get_config('docroot') . 'lib/institution.php');

class AuthBrowserid extends Auth {
    public function __construct($id = null) {
        $this->has_instance_config = true;
        $this->type = 'browserid';

        $this->config['weautocreateusers'] = 1;
        if (!empty($id)) {
            return $this->init($id);
        }
        return true;
    }

    public function init($id) {
        $this->ready = parent::init($id);
        return $this->ready;
    }

    public function authenticate_user_account($user, $password) {
        // Authentication is done elsewhere in Javascript
        return false;
    }

    public function can_auto_create_users() {
        // The normal user auto creation process doesn't work for this backend
        return false;
    }

    public function create_new_user($email) {
        if (!$this->config['weautocreateusers']) {
            return null;
        }

        if (record_exists('artefact_internal_profile_email', 'email', $email)) {
            throw new AccountAutoCreationException("Another user account has already claimed the email address '$email'.");
        }

        if (record_exists('usr', 'username', $email)) {
            throw new AccountAutoCreationException("Another user account has already claimed the email address '$email' as a username.");
        }

        // Personal details are currently not provided by the BrowserID API.
        $user->username = $email;
        $user->firstname = '';
        $user->lastname = '';
        $user->email = $email;

        // no need for a password on BrowserID accounts
        $user->password = '';
        $user->passwordchange = 0;
        $user->authinstance = $this->instanceid;

        $user->id = create_user($user, array(), $this->institution);

        if (get_config('usersuniquebyusername')) {
            $user->join_institution($this->institution);
        }

        return $user;
    }
}

class PluginAuthBrowserid extends PluginAuth {

    private static $default_config = array(
        'weautocreateusers' => 1,
    );

    public static function has_config() {
        return false;
    }

    public static function get_config_options() {
        return array();
    }

    public static function has_instance_config() {
        return true;
    }

    public static function is_usable() {
        return extension_loaded('curl');
    }

    public static function get_instance_config_options($institution, $instance = 0) {

        if ($instance > 0) {
            $current_config = get_records_menu('auth_instance_config', 'instance', $instance, '', 'field, value');

            if ($current_config == false) {
                $current_config = array();
            }

            foreach (self::$default_config as $key => $value) {
                if (array_key_exists($key, $current_config)) {
                    self::$default_config[$key] = $current_config[$key];
                }
            }
        }

        $elements = array(
            'instance' => array(
                'type'  => 'hidden',
                'value' => $instance,
            ),
            'institution' => array(
                'type'  => 'hidden',
                'value' => $institution,
            ),
            'authname' => array(
                'type'  => 'hidden',
                'value' => 'browserid',
            ),
            'instancename' => array(
                'type'  => 'hidden',
                'value' => 'BrowserID',
            ),
            'authname' => array(
                'type'  => 'hidden',
                'value' => 'browserid',
            ),
            'weautocreateusers' => array(
                'type'         => 'checkbox',
                'title'        => get_string('weautocreateusers', 'auth'),
                'defaultvalue' => self::$default_config['weautocreateusers'],
                'help'         => true
            ),
        );

        return array(
            'elements' => $elements,
            'renderer' => 'table'
        );
    }

    public static function save_config_options($values, $form) {

        $authinstance = new stdClass();

        if ($values['instance'] > 0) {
            $values['create'] = false;
            $current = get_records_assoc('auth_instance_config', 'instance', $values['instance'], '', 'field, value');
            $authinstance->id = $values['instance'];
        }
        else {
            $values['create'] = true;
            $lastinstance = get_records_array('auth_instance', 'institution', $values['institution'], 'priority DESC', '*', '0', '1');

            if ($lastinstance == false) {
                $authinstance->priority = 0;
            }
            else {
                $authinstance->priority = $lastinstance[0]->priority + 1;
            }
        }

        $authinstance->institution  = $values['institution'];
        $authinstance->authname     = $values['authname'];
        $authinstance->instancename = $values['instancename'];

        if ($values['create']) {
            $values['instance'] = insert_record('auth_instance', $authinstance, 'id', true);
        }
        else {
            update_record('auth_instance', $authinstance, array('id' => $values['instance']));
        }

        if (empty($current)) {
            $current = array();
        }

        self::$default_config = array('weautocreateusers' => $values['weautocreateusers']);

        foreach(self::$default_config as $field => $value) {
            $record = new stdClass();
            $record->instance = $values['instance'];
            $record->field = $field;
            $record->value = $value;

            if ($values['create'] || !array_key_exists($field, $current)) {
                insert_record('auth_instance_config', $record);
            }
            else {
                update_record('auth_instance_config', $record, array('instance' => $values['instance'], 'field' => $field));
            }
        }

        return $values;
    }

    /**
     * Add a BrowserID link/button.
     */
    public static function login_form_elements() {
        return array(
            'loginbrowserid' => array(
                'value' => '<div class="login-externallink"><a href="javascript:window.browserid_login()">' . get_string('login', 'auth.browserid') . '</a></div>'
            )
        );
    }

    /**
     * Load all of the Javascript needed to retrieve BrowserIDs from
     * the browser.
     */
    public static function login_form_js() {
        $wwwroot = get_config('wwwroot');
        return <<< EOF
<script src="https://browserid.org/include.js" type="text/javascript"></script>

<form id="browserid-form" action="{$wwwroot}auth/browserid/login.php" method="post">
<input id="browserid-assertion" type="hidden" name="assertion" value="">
<input style="display: none" type="submit">
</form>

<script type="text/javascript">
function browserid_login() {
    navigator.id.getVerifiedEmail(function(assertion) {
        if (assertion) {
            document.getElementById('browserid-assertion').setAttribute('value', assertion);
            document.getElementById('browserid-form').submit();
        }
   });
}
</script>
EOF;
    }

    public static function need_basic_login_form() {
        return false;
    }
}

class BrowserIDUser extends LiveUser {
    public function login($email) {
        $sql = "SELECT
                    a.id, i.name AS institutionname
                FROM
                    {auth_instance} a
                JOIN
                    {institution} i ON a.institution = i.name
                WHERE
                    a.authname = 'browserid' AND
                    i.suspended = 0";
        $authinstances = get_records_sql_array($sql, null);
        if (!$authinstances) {
            throw new ConfigException('The BrowserID authentication plugin is not enabled in any active institution.');
        }

        foreach ($authinstances as $authinstance) {
            $auth = AuthFactory::create($authinstance->id);

            $institutionjoin = '';
            $institutionwhere = '';
            $sqlvalues = array($email);
            if ($authinstance->institutionname != 'mahara') {
                // Make sure that user is in the right institution
                $institutionjoin = 'JOIN {usr_institution} ui ON ui.usr = u.id';
                $institutionwhere = 'AND ui.institution = ?';
                $sqlvalues[] = $authinstance->institutionname;
            }

            $sql = "SELECT
                        u.*,
                        " . db_format_tsfield('u.expiry', 'expiry') . ",
                        " . db_format_tsfield('u.lastlogin', 'lastlogin') . ",
                        " . db_format_tsfield('u.lastlastlogin', 'lastlastlogin') . ",
                        " . db_format_tsfield('u.lastaccess', 'lastaccess') . ",
                        " . db_format_tsfield('u.suspendedctime', 'suspendedctime') . ",
                        " . db_format_tsfield('u.ctime', 'ctime') . "
                    FROM
                        {usr} u
                    JOIN
                        {artefact_internal_profile_email} a ON a.owner = u.id
                    $institutionjoin
                    WHERE
                        a.verified = 1 AND
                        a.email = ?
                    $institutionwhere";
            $user = get_record_sql($sql, $sqlvalues);
            if (!$user) {
                continue; // skip to the next auth_instance
            }

            if (is_site_closed($user->admin)) {
                return false;
            }
            ensure_user_account_is_active($user);

            $this->authenticate($user, $auth->instanceid);
            return true;
        }

        // TODO: pick the right authinstance for creating a new user (make sure !$institution->isFull(), default to "No institution")
        if ($user = $auth->create_new_user($email)) {
            $this->authenticate($user, $auth->instanceid);
        }
        else {
            throw new AuthUnknownUserException("A user account with an email address of '$email' was not found in any of the institutions where BrowserID is enabled.");
        }
    }
}