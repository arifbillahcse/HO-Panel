<?php
/**
 * @copyright Sharapov A. <alexander@sharapov.biz>
 * @link      http://www.sharapov.biz/
 * @license   https://www.gnu.org/licenses/gpl-3.0.en.html GNU General Public License
 * Date: 13.09.2022
 * Time: 17:51
 */

namespace WHMCS\Module\Registrar\Cosmotown;

class RegisterDomain extends ApiClient
{
    /**
     * @throws \Exception
     */
    public function execute()
    {
        $payload = array(
            "coupon_id" => $this->params['PromotionCode'],
            "items" => [
                [
                    "name" => $this->params["domainname"],
                    "years" => (int)$this->params["regperiod"]
                ]
            ]
        );

        $this->call('registerdomains', $payload);
    }
}
