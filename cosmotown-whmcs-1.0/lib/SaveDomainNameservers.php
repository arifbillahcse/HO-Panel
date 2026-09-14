<?php
/**
 * @copyright Sharapov A. <alexander@sharapov.biz>
 * @link      http://www.sharapov.biz/
 * @license   https://www.gnu.org/licenses/gpl-3.0.en.html GNU General Public License
 * Date: 13.09.2022
 * Time: 17:51
 */

namespace WHMCS\Module\Registrar\Cosmotown;

class SaveDomainNameservers extends ApiClient
{
    /**
     * @throws \Exception
     */
    public function execute()
    {
        $nameservers = [];
        if (!empty($this->params['ns1'])) {
            $nameservers[] = $this->params['ns1'];
        }
        if (!empty($this->params['ns2'])) {
            $nameservers[] = $this->params['ns2'];
        }
        if (!empty($this->params['ns3'])) {
            $nameservers[] = $this->params['ns3'];
        }
        if (!empty($this->params['ns4'])) {
            $nameservers[] = $this->params['ns4'];
        }
        if (!empty($this->params['ns5'])) {
            $nameservers[] = $this->params['ns5'];
        }
        $payload = array(
            "domain" => $this->params["domainname"],
            "nameservers" => $nameservers
        );
        $this->call('savedomainnameservers', $payload);
    }
}
