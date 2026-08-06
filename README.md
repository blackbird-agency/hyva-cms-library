# Blackbird_HyvaCmsLibrary

[![Latest Stable Version](https://img.shields.io/packagist/v/blackbird/hyva-cms-library.svg?style=flat-square)](https://packagist.org/packages/blackbird/hyva-cms-library)
[![License: MIT](https://img.shields.io/github/license/blackbird-agency/hyva-cms-library.svg)](./LICENSE)

A reusable Hyvä CMS component library for Magento 2, providing extra custom field types and CMS elements on top of [Hyvä Commerce CMS](https://docs.hyva.io/).

No manipulations required, instant use after installation.

[Components](#components) •
[Installation](#installation) •
[Usage](#usage)

## Components

### Custom field types

- **File uploader** : upload and manage files (e.g. videos) directly from a CMS element configuration.
- **Icon picker** : pick an SVG icon from a predefined set to use in a CMS element.

### CMS elements

- **Incrementer** : an animated counter, with configurable value, unit, label and animation speed.
- **Mosaic** : an image mosaic block with optional link, CTA, icon and clickable overlay.
- **Newsletter** : a newsletter subscription form with configurable title and subtitle.
- **Video** : a video banner with autoplay, loop, lazyload and automatic poster extraction (via FFMpeg).

## Installation

> ### Requirements
>
> - PHP 8.2 or above
> - Magento 2.4 or above
> - [Hyvä Commerce CMS module](https://docs.hyva.io/) 1.2.1 or above
> - FFMpeg and FFProbe installed locally, see [ffmpeg.org/download](https://ffmpeg.org/download.html) (used for video poster auto extraction)

#### **Downlaod the Package:**


```
composer require blackbird/hyva-cms-library
```

Move the `tailwind/increment.css` file into your theme's `tailwind/components/` folder.

### Install the Module

Go to your Magento root directory, then run the following Magento commands:

**If you are in production mode, do not forget to recompile and redeploy the static resources, or to use the `--keep-generated` option.**

```shell
bin/magento module:enable Blackbird_CSPManager
bin/magento setup:upgrade
bin/magento cache:flush
```

## Usage

Once the module is installed, the CMS elements described in [Components](#components) become available directly in the Hyvä CMS builder, each under its own category, with no further configuration required.

The file uploader and icon picker are custom field types made available for use by any CMS element configuration, in this module or in your own.

## Support

For further information, contact us:
- by email: hello@bird.eu
- or by form: [https://black.bird.eu/en/contacts/](https://black.bird.eu/contacts/)

---

## Authors

- **Blackbird Team** - *Maintainer* - [They're awesome!](https://github.com/blackbird-agency)

---

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

***That's all folks!***