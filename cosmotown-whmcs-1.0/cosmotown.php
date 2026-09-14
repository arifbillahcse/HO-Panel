<?php
/**
 * @copyright Sharapov A. <alexander@sharapov.biz>
 * @link      http://www.sharapov.biz/
 * @license   https://www.gnu.org/licenses/gpl-3.0.en.html GNU General Public License
 * Date: 13.09.2022
 * Time: 17:51
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Domains\DomainLookup\ResultsList;
use WHMCS\Domains\DomainLookup\SearchResult;
use WHMCS\Module\Registrar\Cosmotown\ApiClient;

require_once __DIR__ .'/constants.php';
require_once __DIR__ .'/lib/functions.php';

/**
 * Define module related metadata
 *
 * Provide some module information including the display name and API Version to
 * determine the method of decoding the input values.
 *
 * @return array
 */
function cosmotown_MetaData()
{
    return array(
        'DisplayName' => 'Cosmotown',
        'APIVersion' => '1.0',
    );
}

/**
 * Define registrar configuration options.
 *
 * The values you return here define what configuration options
 * we store for the module. These values are made available to
 * each module function.
 *
 * You can store an unlimited number of configuration settings.
 * The following field types are supported:
 *  * Text
 *  * Password
 *  * Yes/No Checkboxes
 *  * Dropdown Menus
 *  * Radio Buttons
 *  * Text Areas
 *
 * @return array
 */
function cosmotown_getConfigArray()
{
    $pluginModeOptions = [
        COSMOTOWN_PLUGIN_MODE_LIVE,
        // Hide test domains for non-dev environments
        //COSMOTOWN_PLUGIN_MODE_TEST_1,
        //COSMOTOWN_PLUGIN_MODE_TEST_2,
        COSMOTOWN_PLUGIN_MODE_TEST_3
    ];
    /*
    if($_SERVER['SERVER_NAME'] == 'whmcs.cosmotown3.com' or $_SERVER['SERVER_ADDR'] == '::1') {
        $pluginModeOptions[] = COSMOTOWN_PLUGIN_MODE_TEST_1;
        $pluginModeOptions[] = COSMOTOWN_PLUGIN_MODE_TEST_2;
    }*/

    return [
        // Friendly display name for the module
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'Cosmotown Registrar',
        ],
        'APIKey' => [
            'FriendlyName' => 'API Key',
            'Type' => 'password',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter your API key here. To get your api key, go to My Account section in cosmotown.com, then click Reseller API link on the left hand side. C/p the key here. DON\'T include your password.',
        ],
        'PluginMode' => [
            'FriendlyName' => 'Plugin Mode',
            'Type' => 'radio',
            'Options' => implode(",", $pluginModeOptions),
            'Description' => 'Choose plugin behavior',
        ],
    ];
}

/**
 * Client Area Custom Button Array.
 *
 * Allows you to define additional actions your module supports.
 * In this example, we register a Push Domain action which triggers
 * the `cosmotown_push` function when invoked.
 *
 * @return array
 */
function cosmotown_ClientAreaCustomButtonArray()
{
    return array(
        'Push Domain' => 'push',
    );
}

/**
 * Client Area Allowed Functions.
 *
 * Only the functions defined within this function or the Client Area
 * Custom Button Array can be invoked by client level users.
 *
 * @return array
 */
function cosmotown_ClientAreaAllowedFunctions()
{
    return array(
        'Push Domain' => 'push',
    );
}

/**
 * Example Custom Module Function: Push
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function cosmotown_push($params)
{
    // user defined configuration values
    $userIdentifier = $params['APIUsername'];
    $apiKey = $params['APIKey'];
    $testMode = $params['TestMode'];
    $accountMode = $params['AccountMode'];
    $emailPreference = $params['EmailPreference'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // Perform custom action here...

    return 'Not implemented';
}

/**
 * Client Area Output.
 *
 * This function renders output to the domain details interface within
 * the client area. The return should be the HTML to be output.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return string HTML Output
 */
function cosmotown_ClientArea($params)
{
    $output = '
        <div class="alert alert-info">
            Your custom HTML output goes here...
        </div>
    ';

    return $output;
}
