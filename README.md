# Leuchtfeuer Identity-Sync
Allow sync of Mautic lead identity from external systems (e.g. CMS login) through a control-pixel with query-parameter.

## Requirements
- Mautic 5.x (minimum 5.1)
- PHP 8.1 or higher

## Installation
### Composer
This plugin can be installed through composer.

### Manual install
Alternatively, it can be installed manually, following the usual steps:

* Download the plugin
* Unzip to the Mautic `plugins` directory
* Rename folder to `LeuchtfeuerIdentitySyncBundle`

-
* In the Mautic backend, go to the `Plugins` page as an administrator
* Click on the `Install/Upgrade Plugins` button to install the Plugin.

OR

* If you have shell access, execute `php bin\console cache:clear` and `php bin\console mautic:plugins:reload` to install the plugins.

## Configuration
The plugin has feature-settings to specify the contact-fields for identification:
1. Primary parameter (required): set the contact-field which is used as query-parameter for identification (e.g. `email`). Only fields are shown where field-setting "Is Unique Identifier" is true.
2. Secondary parameter (optional): specify an additional query-parameter which is used in combination with the first one to enforce identification.

## Usage
1. Embed the control-pixel to the template of an external CMS where a customer is identified, e.g. after a successful login.
2. Pass the identifier (e.g. the email-address) as query parameter to the control-pixel: `https://my-mautic.site/mcontrol.gif?email=my-customer@domain.net`.
3. Optional: pass additional attributes or custom-fields of Mautic contacts to the control-pixel to update them
   (make sure that these attributes are set to "Publicly updatable"!): `mcontrol.gif?email=my-customer@domain.net&title=Sir&my_custom_field=Demo-Value`. 

## Author and Contact
Leuchtfeuer Digital Marketing GmbH

Please raise any issues in GitHub.

For all other things, please email mautic-plugins@Leuchtfeuer.com