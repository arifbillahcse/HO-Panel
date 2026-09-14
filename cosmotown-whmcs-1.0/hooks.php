<?php

use WHMCS\Config\Setting;

require_once __DIR__ . '/constants.php';

/**
 * Register a hook with WHMCS.
 *
 * add_hook(string $hookPointName, int $priority, string|array|Closure $function)
 */
add_hook('AdminHomeWidgets', 1, function () {
    return new CosmotownRegistrarModuleWidget();
});

/*
add_hook('AdminAreaFooterOutput', 1, function($vars) {
    return <<<HTML
<b>This is a custom output on the footer</b>
<script type="text/javascript">
    //custom javascript here
</script>
HTML;
});
*/
/**
 * Sample Registrar Module Admin Dashboard Widget.
 *
 * @see https://developers.whmcs.com/addon-modules/admin-dashboard-widgets/
 */
class CosmotownRegistrarModuleWidget extends \WHMCS\Module\AbstractWidget
{
    protected $title = 'Cosmotown Registrar';
    protected $description = '';
    protected $weight = 150;
    protected $columns = 1;
    protected $cache = false;
    protected $cacheExpiry = 120;
    protected $requiredPermission = '';

    /**
     * @return array
     */
    public function getData()
    {
        return array();
    }

    /**
     * @return string|null
     */
    private function getApiKey(): ?string
    {
        $apiKey = decrypt(get_query_val("tblregistrars", "value", "registrar = 'cosmotown' AND setting = 'APIKey'"));
        return !empty($apiKey) ? $apiKey : null;
    }

    /**
     * @return string|null
     */
    private function getPluginMode(): ?string
    {
        $mode = decrypt(get_query_val("tblregistrars", "value", "registrar = 'cosmotown' AND setting = 'PluginMode'"));
        return !empty($mode) ? $mode : null;
    }

    /**
     * @return string
     */
    private function getApiUrl(): string
    {
        $mode = $this->getPluginMode();
        switch ($mode) {
            case COSMOTOWN_PLUGIN_MODE_TEST_1:
                $apiUrl = COSMOTOWN_API_TEST_1_URL;
                break;
            case COSMOTOWN_PLUGIN_MODE_TEST_2:
                $apiUrl = COSMOTOWN_API_TEST_2_URL;
                break;
            case COSMOTOWN_PLUGIN_MODE_TEST_3:
                $apiUrl = COSMOTOWN_API_TEST_3_URL;
                break;
            default:
                $apiUrl = COSMOTOWN_API_LIVE_URL;
        }

        return $apiUrl;
    }

    /**
     * @param $data
     * @return string
     */
    public function generateOutput($data): string
    {
        $version = COSMOTOWN_PLUGIN_VERSION;
        $apiKey = $this->getApiKey();
        //$apiUrl = $this->getApiUrl();
        $html = <<<EOF
<style>
.widget-cosmotownregistrarmodulewidget .footer {
    margin: 20px 0 0 0;
    padding: 6px 12px 3px 12px;
    border-top: 1px solid #eee;
    text-align: center;
    font-size: 12px;
    margin: 5px 10px;
    color: #999;
}
.widget-cosmotownregistrarmodulewidget .footer a {
    text-decoration: underline;
    padding: 0 6px;
}
.widget-cosmotownregistrarmodulewidget .footer .plugin-version {
    float: right;
    color: #959595;
    font-size: .9em;
}
</style>
EOF;
        /*
        Note! would like to add a form to check out the domain availability on Cosmotown,
        but for some reason wasn't able to respond with Cors Headers from Cosmotown reseller api.
        Need to sort out with issue on cosmotown reseller api at first.

        $html .= "
<script>
    document.addEventListener('DOMContentLoaded', function(event) {
        document.getElementById('cosmotown_check_domain_form').addEventListener('submit', function(e) {
            e.preventDefault();
            let options = {
              method: 'POST',
              headers: {
                  'X-API-TOKEN': '$apiKey',
                  'Content-Type': 'application/json',
                  'Accept': 'application/json'
              },
              mode: 'cors',
              body: JSON.stringify({domains: [document.getElementById('cosmotown_domain').value]})
            };

            fetch('/modules/registrars/cosmotown/json.php', options)
            .then(response => response.json())
            .then(body => {
              // Do something with body
            });
                }, false);
            });
</script>
";*/
        /*$html .= <<<EOF
<div class="widget-content-padded">
    <form id="cosmotown_check_domain_form" method="post" action="">
        <div class="form-group">
            <label for="cosmotown_domain">Check Domain Price</label>
            <input type="text" class="form-control" id="cosmotown_domain" value="dhgyt.com" name="cosmotown_domain" placeholder="" autofocus="" required>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Check</button>
    </form>
</div>
EOF;*/

        $html .= '<div class="widget-content-padded">'.(!empty(($apiKey))?'Api key is provided. You can register a domains with Cosmotown':'Api key is not provided. Please set up it <a href="/admin/configregistrars.php?#cosmotown">here</a>.').'</div>';

$html .= <<<EOF
<div class="footer">
    <a href="https://cosmotown.com/main/dashboard" target="_blank">View My Account</a>
    <a href="https://cosmotown.zendesk.com/hc/en-us" target="_blank">Get Support</a>
    <div class="plugin-version">$version</div>
</div>
EOF;
        return $html;
    }
}
