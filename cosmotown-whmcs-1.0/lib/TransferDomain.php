<?php
/**
 * @copyright Sharapov A. <alexander@sharapov.biz>
 * @link      http://www.sharapov.biz/
 * @license   https://www.gnu.org/licenses/gpl-3.0.en.html GNU General Public License
 * Date: 15.05.2023
 * Time: 09:57
 */

namespace WHMCS\Module\Registrar\Cosmotown;

class TransferDomain extends ApiClient
{
    /**
     * @throws \Exception
     */
    public function execute()
    {
        $payload = array(
            "items" => [
                [
                    "name" => $this->params["domainname"],
                    "authCode" => base64_encode($this->params["transfersecret"])
                ]
            ]
        );

        $this->call('transferdomains', $payload);
    }
}
