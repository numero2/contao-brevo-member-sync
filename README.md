Contao Brevo Member Sync Bundle
=======================

[![](https://img.shields.io/packagist/v/numero2/contao-brevo-member-sync.svg?style=flat-square)](https://packagist.org/packages/numero2/contao-brevo-member-sync) [![](https://img.shields.io/badge/License-LGPL%20v3-blue.svg?style=flat-square)](http://www.gnu.org/licenses/lgpl-3.0)

About
--

Adds the possibility to synchronize the Contao members with [Brevo](https://brevo.com/). Therefore you have to configure the registration and personal data module. Or configure the Brevo fields inside the member groups. Then the members will be automatically synced to Brevo after a change in the backend or start a manual sync after selecting some members. Every sync in the backend will only use the configuration from the member groups.

System requirements
--

* [Contao 4.9](https://github.com/contao/core) or newer

Installation
--

* Install via Contao Manager or Composer (`composer require numero2/contao-brevo-member-sync`)
* Run a database update via the Contao-Installtool or using the [contao:migrate](https://docs.contao.org/dev/reference/commands/) command.

Configuration
--

Activate the "Sync" checkbox in the Brevo legend within the suitable modules and then configure the required fields. Or activate the "Sync with member groups" and configure the Brevo fields inside the member groups.
