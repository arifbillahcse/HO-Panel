<?php
/**
 * @copyright Sharapov A. <alexander@sharapov.biz>
 * @link      http://www.sharapov.biz/
 * @license   https://www.gnu.org/licenses/gpl-3.0.en.html GNU General Public License
 * Date: 13.09.2022
 * Time: 17:51
 */

namespace WHMCS\Module\Registrar\Cosmotown;

/**
 * Sample Registrar Module Simple API Client.
 *
 * A simple API Client for communicating with an external API endpoint.
 */
abstract class ApiClient
{
    protected $results = array();
    protected $params = array();
    protected $apiKey;
    protected $apiUrl;

    public function __construct($params)
    {
        $this->apiKey = $params['APIKey'];
        switch($params['PluginMode']) {
            case COSMOTOWN_PLUGIN_MODE_TEST_1:
                $this->apiUrl = COSMOTOWN_API_TEST_1_URL;
                break;
            case COSMOTOWN_PLUGIN_MODE_TEST_2:
                $this->apiUrl = COSMOTOWN_API_TEST_2_URL;
                break;
            default:
                $this->apiUrl = COSMOTOWN_API_LIVE_URL;
        }
        $this->params = $params;
    }

    /**
     * Make external API call to registrar API.
     *
     * @param string $action
     * @param array $payload
     * @param string $method
     * @return void
     * @throws \Exception Connection error
     */
    protected function call(string $action, array $payload = [], string $method = 'POST'): void
    {
        $payload = json_encode($payload);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . $action);
        $headers = [
            'X-API-TOKEN: ' . $this->apiKey,
            'Content-Type: application/json'
        ];
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            $headers[] = 'Content-Length: ' . strlen($payload);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 100);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new \Exception('Connection Error: ' . curl_errno($ch) . ' - ' . curl_error($ch));
        }
        curl_close($ch);

        /**
         * Log module call.
         *
         * @param string $module The name of the module
         * @param string $action The name of the action being performed
         * @param string|array $requestString The input parameters for the API call
         * @param string|array $responseData The response data from the API call
         * @param string|array $processedData The resulting data after any post processing (eg. json decode, xml decode, etc...)
         * @param array $replaceVars An array of strings for replacement
         */
        if($response) {
            $this->results = $this->processResponse($response);
            if ($this->results === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Bad response received from API');
            }
        }

        logModuleCall(
            'CosmotownRegistrar',
            $this->apiUrl . $action,
            $payload,
            $response,
            $this->results
        );
    }

    /**
     * Process API response.
     *
     * @param string $response
     *
     * @return array
     */
    public function processResponse($response): array
    {
        return json_decode($response, true);
    }

    /**
     * Get response results
     *
     * @return array
     */
    public function getResults(): array
    {
        return $this->results;
    }
}
