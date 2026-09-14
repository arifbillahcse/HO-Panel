<?php
/**
 * @copyright Sharapov A. <alexander@sharapov.biz>
 * @link      http://www.sharapov.biz/
 * @license   https://www.gnu.org/licenses/gpl-3.0.en.html GNU General Public License
 * Date: 13.09.2022
 * Time: 17:49
 */

namespace WHMCS\Module\Registrar\Cosmotown;

class Domain extends ApiClient
{
    /**
     * @throws \Exception
     */
    public function execute()
    {
        $this->call('domaininfo?domain=' . $this->params["domainname"], [], 'GET');
        $result = $this->getResults();
        if (isset($result['domain'])) {
            return $result;
        } else {
            return null;
        }
    }
}
