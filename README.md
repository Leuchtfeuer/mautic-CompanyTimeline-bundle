Here is a sample `README.md` for `plugins/LeuchtfeuerCompanyTimelineBundle`, modeled after the structure and style typically found in `plugins/LeuchfeuerCompanySegment/Readme`.

---

# CompanyTimelineBundle

## Overview

The **CompanyTimelineBundle** plugin adds timeline functionality for company entities in Mautic. It enables tracking and displaying company-related activities, events, and interactions in a timeline view.

## Features

- Displays a timeline of company activities.
- Integrates with Mautic company entities.
- Customizable event types and display options.

## Installation

1. Copy the `LeuchtfeuerCompanyTimelineBundle` folder to your Mautic `plugins` directory.
2. Clear the cache:
   ```
   php bin/console cache:clear
   ```
3. Go to **Settings > Plugins** in the Mautic admin panel and click **Install/Update Plugins**.

## Usage

- Navigate to a company record in Mautic.
- The timeline tab will show all tracked activities and events for the company.
- Configure event types and display settings in the plugin configuration.

## Configuration

- Go to **Settings > Plugins > CompanyTimelineBundle**.
- Set up event types, filters, and display preferences as needed.

## Support

For issues or feature requests, please open an issue in the [Mautic GitHub repository](https://github.com/mautic/mautic/issues).

## License

This plugin is released under the MIT License.

---

Replace or expand sections as needed for your specific implementation.