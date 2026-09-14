<?php
/**
 * @copyright Sharapov A. <alexander@sharapov.biz>
 * @link      http://www.sharapov.biz/
 * @license   https://www.gnu.org/licenses/gpl-3.0.en.html GNU General Public License
 * Date: 13.09.2022
 * Time: 17:51
 */

namespace WHMCS\Module\Registrar\Cosmotown;

class SaveDomainRegistrarLock extends ApiClient
{
    /**
     * @throws \Exception
     */
    public function execute()
    {
        $this->call('domaininfo?domain=' . $this->params["domainname"], [], 'GET');
        $result = $this->getResults();
        $payload = array(
            "domain" => $this->params["domainname"],
            "options" => [
                "enable_private_whois" => $result['whois_privacy'],
                "lock_domain" => (("locked" == $this->params['lockenabled']) ? true : false),
                "enable_auto_billing" => $result['auto_billing']
            ]
        );
        $this->call('domaininfo', $payload);
    }
}
