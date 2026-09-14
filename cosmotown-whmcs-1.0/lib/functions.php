<?php
/**
 * @copyright Sharapov A. <alexander@sharapov.biz>
 * @link      http://www.sharapov.biz/
 * @license   https://www.gnu.org/licenses/gpl-3.0.en.html GNU General Public License
 * Date: 14.09.2022
 * Time: 08:46
 */

use WHMCS\Domain\Registrar\Domain;

/**
 * Register a domain.
 *
 * Attempt to register a domain with the domain registrar.
 *
 * This is triggered when the following events occur:
 * * Payment received for a domain registration order
 * * When a pending domain registration order is accepted
 * * Upon manual request by an admin user
 *
 * @param  array  $params  common module parameters
 *
 * @return array
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 */
function cosmotown_RegisterDomain($params)
{
    try {
        $api = new \WHMCS\Module\Registrar\Cosmotown\RegisterDomain($params);
        $api->execute();

        return array(
          'success' => true,
        );

    } catch (\Exception $e) {
        return array(
          'error' => $e->getMessage(),
        );
    }
}

function cosmotown_RenewDomain($params)
{
    try {
        $api = new \WHMCS\Module\Registrar\Cosmotown\RenewDomain($params);
        $api->execute();

        return array(
          'success' => true,
        );

    } catch (\Exception $e) {
        return array(
          'error' => $e->getMessage(),
        );
    }
}

function cosmotown_TransferDomain($params)
{
    try {
        $api = new \WHMCS\Module\Registrar\Cosmotown\TransferDomain($params);
        $api->execute();

        return array(
          'success' => true,
        );

    } catch (\Exception $e) {
        return array(
          'error' => $e->getMessage(),
        );
    }
}

/**
 * Sync Domain Status & Expiration Date.
 *
 * Domain syncing is intended to ensure domain status and expiry date
 * changes made directly at the domain registrar are synced to WHMCS.
 * It is called periodically for a domain.
 *
 * @param  array  $params  common module parameters
 *
 * @return array
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 */
function cosmotown_Sync($params)
{
    try {
        $api = new \WHMCS\Module\Registrar\Cosmotown\Sync($params);
        return $api->execute();
    } catch (\Exception $e) {
        return array(
          'error' => $e->getMessage(),
        );
    }
}

/**
 * Sync Domain Transfer
 *
 * @param  array  $params  common module parameters
 *
 * @return array
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 */
function cosmotown_TransferSync($params)
{
    try {
        $api = new \WHMCS\Module\Registrar\Cosmotown\TransferSync($params);
        return $api->execute();
    } catch (\Exception $e) {
        return array(
          'error' => $e->getMessage(),
        );
    }
}

/**
 * @param $params
 * @return Domain|array
 */
function cosmotown_GetDomainInformation($params)
{
    try {
        $defaultNameservers = ['ndns1.cosmotown.com', 'ndns2.cosmotown.com'];
        $api = new \WHMCS\Module\Registrar\Cosmotown\Domain($params);
        $domain = $api->execute();
        $nameservers = [];
        foreach ($domain['nameservers'] as $i => $ns) {
            $nameservers['ns'.($i + 1)] = $ns;
            if ($i == 4) {
                break;
            }
        }
        if (empty($nameservers)) {
            $nameservers = $defaultNameservers;
        }
        $d = (new Domain);
        $d->setDomain($domain['domain'])
          ->setNameservers($nameservers)
          ->setTransferLock((bool)$domain['locked'])
          ->setTransferLockExpiryDate(null);

        $expDate = \WHMCS\Carbon::createFromFormat('Y-m-d H:i:s', $domain['expiration_date']);
        $nowDate = \WHMCS\Carbon::now();

        if($expDate->format('Ymd') < $nowDate->format('Ymd')) {
            $d->setRegistrationStatus(Domain::STATUS_EXPIRED);
        } else {
            $d->setRegistrationStatus(($domain['status'])?Domain::STATUS_ACTIVE:Domain::STATUS_INACTIVE);
        }
        $d->setExpiryDate($expDate);

        return $d;
    } catch (\Exception $e) {
        return array(
          'error' => $e->getMessage(),
        );
    }
}

/**
 * @param $params
 * @return string|array
 */
function cosmotown_GetRegistrarLock($params)
{
    try {
        $api = new \WHMCS\Module\Registrar\Cosmotown\Domain($params);
        $domain = $api->execute();

        return $domain['locked'] ? "locked" : "unlocked";
    } catch (\Exception $e) {
        return array(
          'error' => $e->getMessage(),
        );
    }
}

function cosmotown_SaveRegistrarLock($params)
{
    try {
        $api = new \WHMCS\Module\Registrar\Cosmotown\SaveDomainRegistrarLock($params);
        $api->execute();

        return array(
          'success' => true,
        );
    } catch (\Exception $e) {
        return array(
          'error' => $e->getMessage(),
        );
    }
}

/**
 * @param $params
 * @return array
 */
function cosmotown_GetNameservers($params)
{
    try {
        $api = new \WHMCS\Module\Registrar\Cosmotown\Domain($params);
        $results = $api->execute();
        $result = [];
        foreach ($results['nameservers'] as $i => $ns) {
            $result['ns'.($i + 1)] = $ns;
            if ($i == 4) {
                break;
            }
        }

        return $result;

    } catch (\Exception $e) {
        return array(
          'error' => $e->getMessage(),
        );
    }
}

/**
 * @param $params
 * @return array
 */
function cosmotown_SaveNameservers($params)
{
    try {
        $api = new \WHMCS\Module\Registrar\Cosmotown\SaveDomainNameservers($params);
        $api->execute();

        return array(
          'success' => true,
        );
    } catch (\Exception $e) {
        return array(
          'error' => $e->getMessage(),
        );
    }
}

