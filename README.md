# LeuchtfeuerCompanyTimelineBundle

## Overview
This plugin adds a history tab to Mautic companies, containing a timeline of related events.

It is part of the "ABM" suite of plugins that extends Mautic capabilities for working with Companies.

## Requirements for this release
> [!TIP]
> Other releases of this plugin may cover different Mautic versions!
- Mautic 5.x (minimum 5.1)
- PHP 8.1 or higher
- ABM Plugins "Company Segments", "Company Tags", "Company Points"

## Installation
### Composer
This plugin can be installed through composer.

### Manual Installation
Alternatively, it can be installed manually, following the usual steps:
- Download the plugin
- Unzip to the Mautic `plugins` directory
- Rename folder to `LeuchtfeuerCompanyTimelineBundle`
- In the Mautic backend, go to the `Plugins` page as an administrator
- Click on the `Install/Upgrade Plugins` button to install the Plugin.
  OR
- If you have shell access, execute `php bin\console cache:clear` and `php bin\console mautic:plugins:reload` to install the plugins.

## Configuration
1. Go to `Plugins` page
2. Click on the `Company Timeline` plugin
3. ENABLE the plugin

## Usage
- Navigate to a company record in Mautic.
- The history tab will show all tracked activities and events for the company.

## Known Issues

## Troubleshooting
Make sure you have not only installed but also enabled the Plugin.

If things are still funny, please try

`php bin/console cache:clear`

and

`php bin/console mautic:assets:generate`

## Change log
- [Releases](https://github.com/Leuchtfeuer/mautic-CompanyTimeline-bundle/releases)

## Future Ideas

## Sponsoring & Commercial Support
We are continuously improving our plugins. If you are requiring priority support or custom features, please contact us at mautic-plugins@leuchtfeuer.com.

## Credits
* @biozshock
* @ekkeguembel
* @JonasLudwig1998
* @lenonleite
* @LeonOltmanns
* @MadlenF
* @PatrickJenkner
* @patrykgruszka

## Author
Leuchtfeuer Digital Marketing GmbH

Please raise any issues in GitHub.

For all other things, please email mautic-plugins@Leuchtfeuer.com

## Support
For issues or feature requests, please open an issue in the [Mautic GitHub repository](https://github.com/Leuchtfeuer/mautic-CompanyTimeline-bundle/issues).

## License
“This plugin is licensed under the MIT License. See the `LICENSE` file for more details.”
