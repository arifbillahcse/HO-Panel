<?php
/**
 * @copyright Sharapov A. <alexander@sharapov.biz>
 * @link      http://www.sharapov.biz/
 * @license   https://www.gnu.org/licenses/gpl-3.0.en.html GNU General Public License
 * Date: 13.09.2022
 * Time: 17:49
 */

namespace WHMCS\Module\Registrar\Cosmotown;

class TransferSync extends ApiClient
{
    /**
     * @throws \Exception
     */
    public function execute()
    {
        $values = [
          'completed' => false,
          'error' => 'In process'
        ];

        $this->call('domainstatus', [
          "domains" => [
            $this->params["domainname"]
          ]
        ]);

        $results = $this->getResults();
        foreach ($results as $result) {
            if ($result['domain'] == $this->params["domainname"]) {
                if ($result['registration_status'] == 'COMPLETE') {
                    $values['completed'] = true;
                } else {
                    $values['completed'] = false;
                    $values['error'] = $result['message'];
                }
            }
        }

        return $values;
    }
}
