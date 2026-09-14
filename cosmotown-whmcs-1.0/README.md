# About

The module allows you to register and manage domains with Cosmotown.

# Supported Features

| Feature                 | Status |
|-------------------------|--------|
| Register                | yes    |
| Transfer                | yes    |
| Renew                   | yes    |
| Registrar Lock          | yes    |
| Update Nameservers      | yes    |
| Update WHOIS            | no     |
| Get EPP Code            | no     |
| Register Nameservers    | no     |
| Email Forwarding        | no     |
| Domain Release          | no     |
| Domain Sync Script      | yes    |
| Premium Domains         | no     |
| Transfer Out Automation | no     |

# Installation

1. Download plugin archive.
2. Using file manager go to WHMCS plugin directory `/YOUR_WHMCS_INSTALLATION_PATH/modules/registrars`.
3. Create a directory named `cosmotown` for the plugin.
4. Copy the plugin files to this new directory. The target path should be like this `/YOUR_WHMCS_INSTALLATION_PATH/modules/registrars/cosmotown`.
5. Open WHMCS Admin Area to activate and start using the plugin.

# Activation

To activate and begin using the Cosmotown registrar module:

1. Log in to the WHMCS Admin Area.
2. Go to Configuration > System Settings > [Domain Registrars](https://docs.whmcs.com/Domain_Registrars) or, prior to WHMCS 8.0, Setup > Products/Services > Domain Registrars.
3. Find Cosmotown in the list.
4. Click Activate.
5. Enter your Cosmotown Reseller API Key.
6. Click Save Changes.

# Automatic Registration

WHMCS allows you to set up automatic domain registration on a per-extension basis, enabling you to use different registrars for different TLDs.

To enable automatic registration, see [Domain Pricing](https://docs.whmcs.com/Domain_Pricing).

# Automatic Domain Synchronization

This module supports automatic domain synchronization for syncing expiry dates and status changes for incoming transfers.

To use this, enable Domain Sync Enabled in the [Domains](https://docs.whmcs.com/Domains_Tab) tab at Configuration > System Settings > General Settings or, prior to WHMCS 8.0, Setup > General Settings. You must also make certain to configure the [Domain Sync Cron](https://docs.whmcs.com/Crons#Domain_Sync_Cron).
