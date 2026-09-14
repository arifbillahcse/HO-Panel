<?php
/**
 * @copyright Sharapov A. <alexander@sharapov.biz>
 * @link      http://www.sharapov.biz/
 * @license   https://www.gnu.org/licenses/gpl-3.0.en.html GNU General Public License
 * Date: 13.09.2022
 * Time: 17:49
 */

namespace WHMCS\Module\Registrar\Cosmotown;

class Sync extends ApiClient
{
    /**
     * @throws \Exception
     */
    public function execute()
    {
        $this->call('listdomains?domain=' . $this->params["domainname"], [], 'GET');

        $result = $this->getResults();
        if (isset($result['domains'][0]['domain'])) {
            $expDate = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $result['domains'][0]['expiration_date']);
            $nowDate = \Carbon\Carbon::now();
            return array(
                'active' => ($expDate->format('Ymd') > $nowDate->format('Ymd')),
                'cancelled' => false,
                'transferredAway' => false,
                'expirydate' => $expDate->format('Y-m-d'),
                'expired' => ($expDate->format('Ymd') < $nowDate->format('Ymd')),
            );
        } else {
            return array(
                'error' => 'Domain ' . $this->params["domainname"] . ' not found in this registrar.' // The error message returned here will be returned within the Domain Synchronisation Report Email
            );
        }
    }
}
