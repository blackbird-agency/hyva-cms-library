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
- **Icon picker** : pick an SVG icon from a modal browsing three sources — the icons shipped
  with this module, the icons of the project theme (`<theme>/web/svg/`) and the native Hyvä
  Lucide set — with a name search across all of them.

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
bin/magento module:enable Blackbird_HyvaCmsLibrary
bin/magento setup:upgrade
bin/magento cache:flush
```

## Usage

Once the module is installed, the CMS elements described in [Components](#components) become available directly in the Hyvä CMS builder, each under its own category, with no further configuration required.

The file uploader and icon picker are custom field types made available for use by any CMS element configuration, in this module or in your own.

### Icon picker

The picker offers two sources, listed in this order:

| Section | Value | Read from |
|---|---|---|
| `Theme icons` | `theme/my-icon` | `<theme>/web/svg/`, theme inheritance included |
| `All icons` | `lucide/settings` | the native Hyvä Lucide set |

The icons bundled with this module are **deliberately not offered**. They are still rendered,
so content already storing `library/star` — or a bare legacy value such as `star` — keeps
displaying correctly on the storefront. In the admin the field shows `Unknown icon` for such a
value, which is the intended signal that the icon is no longer selectable and should be
replaced. `Model\Config\Source\IconPicker` also still exposes them, for backward
compatibility only.

#### Overriding an icon from a theme

A theme can replace the artwork of an icon shipped by a module, using Magento's standard
module-file-in-theme fallback. Note the path: it is not `<theme>/web/svg/`, which is where a
theme's *own* icons live, but a module-scoped folder inside the theme.

To replace `lucide/settings`, put your file at
`<theme>/Hyva_Theme/web/svg/lucide/settings.svg`.

An overridden icon **moves to the `Theme icons` section** and leaves `All icons`, because that
is where an editor expects to find their project's own artwork.

**Its stored value does not change.** An icon overridden at
`<theme>/Hyva_Theme/web/svg/lucide/settings.svg` is still addressed as `lucide/settings`, never
as `theme/settings` — the latter would point at `<theme>/web/svg/settings.svg`, which does not
exist. So the `Theme icons` section legitimately contains entries whose value is not prefixed
`theme/`.

One consequence worth knowing: an override file whose name matches no Lucide icon is ignored.
It overrides nothing, and it does not create a new entry — that would invent a native icon
which does not exist.

A component may recommend icons to the content editor. They are displayed first, under a
`Recommended icons` heading, and removed from their own section to avoid duplicates:

```json
"icon": {
  "type": "custom_type",
  "custom_type": "icon_picker",
  "label": "Icon",
  "config": {
    "recommended_icons": ["lucide/truck", "lucide/shield-check"]
  }
}
```

The list is a hint, not a restriction: every other icon stays reachable. Entries that do not
resolve are ignored.

#### Cache

The icon list is cached in Hyvä's own `hyva_cms` cache type, alongside the CMS component
definitions, rather than in a type of its own. After adding, removing or overriding an SVG:

```shell
bin/magento cache:clean hyva_cms
```

While working on a theme's icons, disable the type so every request reads the SVGs from disk:

```shell
bin/magento cache:disable hyva_cms
```

Sharing Hyvä's cache type cuts both ways: cleaning `hyva_cms` also drops the icon list, and
cleaning the icon list also drops the component definitions. In exchange there is no extra
entry in *Cache Management*, and the type is enabled on install by Hyvä's own data patch.

The `theme/` source is enumerated from the theme configured for the **default store view**. On
a multi-store setup where store views use different themes, an icon offered by one store view's
theme may not exist in another's, and selecting it for content shown on both will fail to
render on the store view that lacks the file.

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
