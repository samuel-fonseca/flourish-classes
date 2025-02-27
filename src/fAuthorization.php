<?php
/**
 * Allows defining and checking user authentication via ACLs, authorization levels or a simple logged in/not logged in scheme.
 *
 * @copyright  Copyright (c) 2007-2010 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 *
 * @see       http://flourishlib.com/fAuthorization
 *
 * @version    1.0.0b5
 * @changes    1.0.0b5  Added ::getLoginPage() [wb, 2010-03-09]
 * @changes    1.0.0b4  Updated class to use new fSession API [wb, 2009-10-23]
 * @changes    1.0.0b3  Updated class to use new fSession API [wb, 2009-05-08]
 * @changes    1.0.0b2  Fixed a bug with using named IP ranges in ::checkIP() [wb, 2009-01-10]
 * @changes    1.0.0b   The initial implementation [wb, 2007-06-14]
 */
class fAuthorization
{
    // The following constants allow for nice looking callbacks to static methods
    public const addNamedIPRange = 'fAuthorization::addNamedIPRange';

    public const checkACL = 'fAuthorization::checkACL';

    public const checkAuthLevel = 'fAuthorization::checkAuthLevel';

    public const checkIP = 'fAuthorization::checkIP';

    public const checkLoggedIn = 'fAuthorization::checkLoggedIn';

    public const destroyUserInfo = 'fAuthorization::destroyUserInfo';

    public const getLoginPage = 'fAuthorization::getLoginPage';

    public const getRequestedURL = 'fAuthorization::getRequestedURL';

    public const getUserACLs = 'fAuthorization::getUserACLs';

    public const getUserAuthLevel = 'fAuthorization::getUserAuthLevel';

    public const getUserToken = 'fAuthorization::getUserToken';

    public const requireACL = 'fAuthorization::requireACL';

    public const requireAuthLevel = 'fAuthorization::requireAuthLevel';

    public const requireLoggedIn = 'fAuthorization::requireLoggedIn';

    public const reset = 'fAuthorization::reset';

    public const setAuthLevels = 'fAuthorization::setAuthLevels';

    public const setLoginPage = 'fAuthorization::setLoginPage';

    public const setRequestedURL = 'fAuthorization::setRequestedURL';

    public const setUserACLs = 'fAuthorization::setUserACLs';

    public const setUserAuthLevel = 'fAuthorization::setUserAuthLevel';

    public const setUserToken = 'fAuthorization::setUserToken';

    /**
     * The valid auth levels.
     *
     * @var array
     */
    private static $levels;

    /**
     * The login page.
     *
     * @var string
     */
    private static $login_page;

    /**
     * Named IP ranges.
     *
     * @var array
     */
    private static $named_ip_ranges = [];

    /**
     * Forces use as a static class.
     *
     * @return fAuthorization
     */
    private function __construct()
    {
    }

    /**
     * Adds a named IP address or range, or array of addresses and/or ranges.
     *
     * This method allows ::checkIP() to be called with a name instead of the
     * actual IPs.
     *
     * @param string $name      The name to give the IP addresses/ranges
     * @param mixed  $ip_ranges This can be string (or array of strings) of the IPs or IP ranges to restrict to - please see ::checkIP() for format details
     */
    public static function addNamedIPRange($name, $ip_ranges): void
    {
        self::$named_ip_ranges[$name] = $ip_ranges;
    }

    /**
     * Checks to see if the logged in user meets the requirements of the ACL specified.
     *
     * @param string $resource   The resource we are checking permissions for
     * @param string $permission The permission to require from the user
     *
     * @return bool If the user has the required permissions
     */
    public static function checkACL($resource, $permission)
    {
        if (self::getUserACLs() === null) {
            return false;
        }

        $acls = self::getUserACLs();

        if (! isset($acls[$resource]) && ! isset($acls['*'])) {
            return false;
        }

        if (isset($acls[$resource])) {
            if (in_array($permission, $acls[$resource]) || in_array('*', $acls[$resource])) {
                return true;
            }
        }

        if (isset($acls['*'])) {
            if (in_array($permission, $acls['*']) || in_array('*', $acls['*'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks to see if the logged in user has the specified auth level.
     *
     * @param string $level The level to check against the logged in user's level
     *
     * @return bool If the user has the required auth level
     */
    public static function checkAuthLevel($level)
    {
        if (self::getUserAuthLevel()) {
            self::validateAuthLevel(self::getUserAuthLevel());
            self::validateAuthLevel($level);

            $user_number = self::$levels[self::getUserAuthLevel()];
            $required_number = self::$levels[$level];

            if ($user_number >= $required_number) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks to see if the user is from the IPs or IP ranges specified.
     *
     * The `$ip_ranges` parameter can be either a single string, or an array of
     * strings, each of which should be in one of the following formats:
     *
     *  - A single IP address:
     *   - 192.168.1.1
     *   - 208.77.188.166
     *  - A CIDR range
     *   - 192.168.1.0/24
     *   - 208.77.188.160/28
     *  - An IP/subnet mask combination
     *   - 192.168.1.0/255.255.255.0
     *   - 208.77.188.160/255.255.255.240
     *
     * @param mixed $ip_ranges A string (or array of strings) of the IPs or IP ranges to restrict to - see method description for details
     *
     * @return bool If the user is coming from (one of) the IPs or ranges specified
     */
    public static function checkIP($ip_ranges)
    {
        // Check to see if a named IP range was specified
        if (is_string($ip_ranges) && isset(self::$named_ip_ranges[$ip_ranges])) {
            $ip_ranges = self::$named_ip_ranges[$ip_ranges];
        }

        // Get the remote IP and remove any IPv6 to IPv4 mapping
        $user_ip = str_replace('::ffff:', '', $_SERVER['REMOTE_ADDR']);
        $user_ip_long = ip2long($user_ip);

        settype($ip_ranges, 'array');

        foreach ($ip_ranges as $ip_range) {
            if (strpos($ip_range, '/') === false) {
                $ip_range .= '/32';
            }

            [$range_ip, $range_mask] = explode('/', $ip_range);

            if (strlen($range_mask) < 3) {
                $mask_long = pow(2, 32) - pow(2, 32 - $range_mask);
            } else {
                $mask_long = ip2long($range_mask);
            }

            $range_ip_long = ip2long($range_ip);

            if (($range_ip_long & $mask_long) != $range_ip_long) {
                $proper_range_ip = long2ip($range_ip_long & $mask_long);

                throw new fProgrammerException(
                    'The range base IP address specified, %1$s, is invalid for the CIDR range or subnet mask provided (%2$s). The proper IP is %3$s.',
                    $range_ip,
                    '/'.$range_mask,
                    $proper_range_ip
                );
            }

            if (($user_ip_long & $mask_long) == $range_ip_long) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks to see if the user has an auth level or ACLs defined.
     *
     * @return bool If the user is logged in
     */
    public static function checkLoggedIn()
    {
        if (fSession::get(__CLASS__.'::user_auth_level', null) !== null
            || fSession::get(__CLASS__.'::user_acls', null) !== null
            || fSession::get(__CLASS__.'::user_token', null) !== null) {
            return true;
        }

        return false;
    }

    /**
     * Destroys the user's auth level and/or ACLs.
     */
    public static function destroyUserInfo(): void
    {
        fSession::delete(__CLASS__.'::user_auth_level');
        fSession::delete(__CLASS__.'::user_acls');
        fSession::delete(__CLASS__.'::user_token');
        fSession::delete(__CLASS__.'::requested_url');
    }

    /**
     * Returns the login page set via ::setLoginPage().
     *
     * @return string The login page users are redirected to if they don't have the required authorization
     */
    public static function getLoginPage()
    {
        return self::$login_page;
    }

    /**
     * Returns the URL requested before the user was redirected to the login page.
     *
     * @param bool   $clear       If the requested url should be cleared from the session after it is retrieved
     * @param string $default_url The default URL to return if the user was not redirected
     *
     * @return string The URL that was requested before they were redirected to the login page
     */
    public static function getRequestedURL($clear, $default_url = null)
    {
        $requested_url = fSession::get(__CLASS__.'::requested_url', $default_url);
        if ($clear) {
            fSession::delete(__CLASS__.'::requested_url');
        }

        return $requested_url;
    }

    /**
     * Gets the ACLs for the logged in user.
     *
     * @return array The logged in user's ACLs
     */
    public static function getUserACLs()
    {
        return fSession::get(__CLASS__.'::user_acls', null);
    }

    /**
     * Gets the authorization level for the logged in user.
     *
     * @return string The logged in user's auth level
     */
    public static function getUserAuthLevel()
    {
        return fSession::get(__CLASS__.'::user_auth_level', null);
    }

    /**
     * Gets the value that was set as the user token, `NULL` if no token has been set.
     *
     * @return mixed The user token that had been set, `NULL` if none
     */
    public static function getUserToken()
    {
        return fSession::get(__CLASS__.'::user_token', null);
    }

    /**
     * Redirect the user to the login page if they do not have the permissions required.
     *
     * @param string $resource   The resource we are checking permissions for
     * @param string $permission The permission to require from the user
     *
     * @return void
     */
    public static function requireACL($resource, $permission)
    {
        self::validateLoginPage();

        if (self::checkACL($resource, $permission)) {
            return;
        }

        self::redirect();
    }

    /**
     * Redirect the user to the login page if they do not have the auth level required.
     *
     * @param string $level The level to check against the logged in user's level
     *
     * @return void
     */
    public static function requireAuthLevel($level)
    {
        self::validateLoginPage();

        if (self::checkAuthLevel($level)) {
            return;
        }

        self::redirect();
    }

    /**
     * Redirect the user to the login page if they do not have an auth level or ACLs.
     *
     * @return void
     */
    public static function requireLoggedIn()
    {
        self::validateLoginPage();

        if (self::checkLoggedIn()) {
            return;
        }

        self::redirect();
    }

    /**
     * Resets the configuration of the class.
     *
     * @internal
     */
    public static function reset(): void
    {
        self::$levels = null;
        self::$login_page = null;
        self::$named_ip_ranges = [];
    }

    /**
     * Sets the authorization levels to use for level checking.
     *
     * @param array $levels An associative array of `(string) {level} => (integer) {value}`, for each level
     */
    public static function setAuthLevels($levels): void
    {
        self::$levels = $levels;
    }

    /**
     * Sets the login page to redirect users to.
     *
     * @param string $url The URL of the login page
     */
    public static function setLoginPage($url): void
    {
        self::$login_page = $url;
    }

    /**
     * Sets the restricted URL requested by the user.
     *
     * @param string $url The URL to save as the requested URL
     */
    public static function setRequestedURL($url): void
    {
        fSession::set(__CLASS__.'::requested_url', $url);
    }

    /**
     * Sets the ACLs for the logged in user.
     *
     * Array should be formatted like:
     *
     * {{{
     * array (
     *     (string) {resource name} => array(
     *         (mixed) {permission}, ...
     *     ), ...
     * )
     * }}}
     *
     * The resource name or the permission may be the single character `'*'`
     * which acts as a wildcard.
     *
     * @param array $acls The logged in user's ACLs - see method description for format
     */
    public static function setUserACLs($acls): void
    {
        fSession::set(__CLASS__.'::user_acls', $acls);
        fSession::regenerateID();
    }

    /**
     * Sets the authorization level for the logged in user.
     *
     * @param string $level The logged in user's auth level
     */
    public static function setUserAuthLevel($level): void
    {
        self::validateAuthLevel($level);
        fSession::set(__CLASS__.'::user_auth_level', $level);
        fSession::regenerateID();
    }

    /**
     * Sets some piece of information to use to identify the current user.
     *
     * @param mixed $token The user's token. This could be a user id, an email address, a user object, etc.
     */
    public static function setUserToken($token): void
    {
        fSession::set(__CLASS__.'::user_token', $token);
        fSession::regenerateID();
    }

    /**
     * Redirects the user to the login page.
     */
    private static function redirect(): void
    {
        self::setRequestedURL(fURL::getWithQueryString());
        fURL::redirect(self::$login_page);
    }

    /**
     * Makes sure auth levels have been set, and that the specified auth level is valid.
     *
     * @param string $level The level to validate
     */
    private static function validateAuthLevel($level = null): void
    {
        if (self::$levels === null) {
            throw new fProgrammerException(
                'No authorization levels have been set, please call %s',
                __CLASS__.'::setAuthLevels()'
            );
        }
        if ($level !== null && ! isset(self::$levels[$level])) {
            throw new fProgrammerException(
                'The authorization level specified, %1$s, is invalid. Must be one of: %2$s.',
                $level,
                implode(', ', array_keys(self::$levels))
            );
        }
    }

    /**
     * Makes sure a login page has been defined.
     */
    private static function validateLoginPage(): void
    {
        if (self::$login_page === null) {
            throw new fProgrammerException(
                'No login page has been set, please call %s',
                __CLASS__.'::setLoginPage()'
            );
        }
    }
}

/*
 * Copyright (c) 2007-2010 Will Bond <will@flourishlib.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
