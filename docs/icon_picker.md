# Icon Picker

A custom field type for the Hyvä CMS liveview editor. It lets a content editor pick an SVG icon
from a modal that browses the icons of the project theme and of the icon sets the project has
enabled, and it resolves the chosen value when a CMS component renders it.

This document covers how it works, how to use the field in your own components, and how to add
your own icon sets.

## Contents

- [How icons are sourced](#how-icons-are-sourced)
- [Using the field in a component](#using-the-field-in-a-component)
- [Rendering a picked value in a CMS component template](#rendering-a-picked-value-in-a-cms-component-template)
- [Configuration](#configuration)
- [Theme icons and overrides](#theme-icons-and-overrides)
- [Adding your own icon set](#adding-your-own-icon-set)
- [Multi-theme behaviour and badges](#multi-theme-behaviour-and-badges)
- [Graceful degradation](#graceful-degradation)
- [Cache](#cache)
- [Troubleshooting](#troubleshooting)

## How icons are sourced

The modal groups icons into sections, in this order:

| Section | Stored value | Read from |
|---|---|---|
| `Recommended icons` | any of the values below | the component's own `recommended_icons` list |
| `Theme icons` | `theme/my-icon` | `<theme>/web/svg/`, theme inheritance included |
| `Lucide` | `lucide/settings` | `Hyva_Theme`, `view/base/web/svg/lucide` |
| `Heroicons Outline` | `heroicons/outline/star` | `Hyva_Theme`, `view/frontend/web/svg/heroicons/outline` |
| `Heroicons Solid` | `heroicons/solid/star` | `Hyva_Theme`, `view/frontend/web/svg/heroicons/solid` |

A recommended icon is shown in `Recommended icons` **and** stays in its own section. Duplicates
are intentional: an editor who looks for an icon where it normally lives should find it there.

The icons bundled with this module (`library/star`) are still resolvable and still render, but
they are deliberately not listed. A bare legacy value such as `star` also still renders.

### Value contract

A stored value is a namespaced string. The namespace decides how the value resolves:

| Value | Resolves to |
|---|---|
| `theme/<name>` | `<theme>/web/svg/<name>.svg` |
| `lucide/<name>` | `Hyva_Theme::svg/lucide/<name>.svg` |
| `heroicons/outline/<name>` | `Hyva_Theme::svg/heroicons/outline/<name>.svg` |
| `heroicons/solid/<name>` | `Hyva_Theme::svg/heroicons/solid/<name>.svg` |
| `library/<name>` | this module's own `view/frontend/web/svg/<name>.svg` |
| `<name>` (no namespace) | inherited resolution, kept for legacy content |

Values are stable across versions. Upgrading the module never requires migrating content.

## Using the field in a component

Declare a field of type `custom_type` with `custom_type: icon_picker` in your component
definition:

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

`recommended_icons` is optional. It is a hint, never a restriction: every other icon stays
reachable through the modal, and the recommended ones remain listed in their own section too.

Entries that do not resolve are ignored, and so are entries pointing at a set the project has
disabled. Recommending an icon the project has excluded would contradict the configuration.

## Rendering a picked value in a CMS component template

**This module does not change how icons are rendered in your templates.** For an icon you choose
yourself while writing a template, keep using Hyvä's native view models exactly as you do today:

```php
$heroicons = $viewModels->require(\Hyva\Theme\ViewModel\HeroiconsSolid::class);
echo $heroicons->renderHtml('star', 'w-6 h-6', 24, 24);
```

There is one narrow exception, and it is the only place this module's view model belongs: the
template of a **Hyvä CMS component** that owns an `icon_picker` field, when it renders the value
the content editor picked.

That value is namespaced (`heroicons/solid/star`, `theme/my-icon`, `lucide/settings`) precisely
because the editor may pick from any source. Hyvä's native view models each resolve against a
single fixed prefix, so none of them can resolve such a value on its own. This module's
`SvgIcons` reads the namespace and dispatches to the right one:

```php
use Blackbird\HyvaCmsLibrary\ViewModel\SvgIcons;

/** @var SvgIcons $svgIcons */
$svgIcons = $viewModels->require(SvgIcons::class);
$icon = $block->getData('icon');
```

```php
<?php if ($icon): ?>
    <?= /** @noEscape */ $svgIcons->renderHtml($icon, 'w-6 h-6', 24, 24, []) ?>
<?php endif ?>
```

So the rule is about the **origin of the value**, not about the template:

| What you are rendering | Use |
|---|---|
| an icon name you wrote in the template yourself | Hyvä's native view models, unchanged |
| a value read from an `icon_picker` field | this module's `SvgIcons` |

A component template may legitimately do both: Hyvä's `LucideIcons` for its own decorative
icons, and this module's `SvgIcons` for the one the editor picked. The `newsletter` component
shipped here is an example of the first case, the `mosaic` component of the second.

## Configuration

`Stores > Configuration > Hyvä Commerce > Hyvä CMS > Icon Picker > Icon Sets Offered In The
Picker`

Config path `hyva_cms/icon_picker/icon_sets`. A multiselect, default `Lucide`, global scope only.
It selects which sets the modal offers.

Two properties matter:

- **It filters the picker, never the rendering.** An icon already stored on a piece of content
  keeps displaying in the admin field and keeps rendering on the storefront even after its set
  is removed from the selection.
- **It takes effect immediately**, with no `cache:clean`. The filter is applied when the icon
  list is read, and the enabled sets are deliberately not part of the cache key.

`Theme icons` are never filtered by this setting. They are the project's own icons, not a set to
opt in or out of.

## Theme icons and overrides

A theme contributes icons in two distinct ways, and the difference matters.

### Its own icons

Drop an SVG in `<theme>/web/svg/`. It appears in the `Theme icons` section and is stored as
`theme/<name>`. Theme inheritance applies, and a child theme's file wins over its parent's.

### Overriding an icon of a set

Use Magento's standard module-file-in-theme fallback, under the set's own path:

```
<theme>/Hyva_Theme/web/svg/lucide/settings.svg
<theme>/Hyva_Theme/web/svg/heroicons/solid/star.svg
```

Note the path. It is **not** `<theme>/web/svg/<set>/`, which is where a theme's own icons live.
Hyvä's icon renderer only falls back to that folder when the asset is not found under the
module's path, which never happens for an override since it is named after an icon that already
exists there.

An overridden icon:

- **stays in its set's section**, it does not move to `Theme icons`;
- keeps its stored value unchanged.

An override carries no badge by itself. Replacing an icon's artwork is a deliberate project
decision, not something the editor needs warning about. Badges are reserved for what actually
differs between the store views (see [Multi-theme behaviour and badges](#multi-theme-behaviour-and-badges)).

An override file whose name matches no native icon of the set is ignored. It overrides nothing
and creates no entry, since that would invent an icon the library does not have.

## Adding your own icon set

A set is declared once, in `di.xml`. Nothing else is required: the picker lists it and the
storefront renderer resolves it from the same declaration.

Say your project ships a `Vendor_Icons` module with SVGs in
`view/frontend/web/svg/feather/`, and you want them stored as `feather/<name>`:

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">
    <type name="Blackbird\HyvaCmsLibrary\Model\Icon\SetRegistry">
        <arguments>
            <argument name="setDefinitions" xsi:type="array">
                <item name="feather" xsi:type="array">
                    <item name="label" xsi:type="string">Feather</item>
                    <item name="module" xsi:type="string">Vendor_Icons</item>
                    <item name="path" xsi:type="string">/view/frontend/web/svg/feather</item>
                    <item name="value_prefix" xsi:type="string">feather</item>
                    <item name="asset_prefix" xsi:type="string">Vendor_Icons::svg/feather</item>
                    <item name="sort_order" xsi:type="number">40</item>
                </item>
            </argument>
        </arguments>
    </type>
</config>
```

### The six keys

| Key | Purpose |
|---|---|
| array key (`feather`) | the set code. Must match `[a-zA-Z0-9_]+`, because it becomes part of a cache id. A code with a dash or a dot is skipped. |
| `label` | the section title in the modal. Passed through `__()`, so it is translatable. |
| `module` | the module whose directory is walked to list the icons. |
| `path` | the directory to list, relative to the module root. Not recursive: only the SVGs directly inside it are listed. |
| `value_prefix` | the namespace written into stored values, so `feather/wind` here. |
| `asset_prefix` | the asset path the storefront renderer resolves against. |
| `sort_order` | position of the section among the other sets. |

### Why both `path` and `asset_prefix`

They serve two different Magento mechanisms and are deliberately declared separately rather than
derived from one another:

- `path` is walked on the **filesystem** to enumerate what the modal offers.
- `asset_prefix` goes through the **asset repository** to render one icon, which is what brings
  theme fallback and therefore theme overrides.

**`asset_prefix` must match the first segment of `value_prefix`.** The renderer receives
everything after that first segment, so:

| `value_prefix` | `asset_prefix` | value | resolved asset |
|---|---|---|---|
| `feather` | `Vendor_Icons::svg/feather` | `feather/wind` | `Vendor_Icons::svg/feather/wind.svg` |
| `heroicons/solid` | `Hyva_Theme::svg/heroicons` | `heroicons/solid/star` | `Hyva_Theme::svg/heroicons/solid/star.svg` |

The second row is how two sets share one namespace: `heroicons/outline` and `heroicons/solid`
are separate sections in the modal, with separate directories, but a single renderer resolves
both. Give them separate sections whenever their icons share file names, otherwise the editor
cannot tell two identically named tiles apart.

### After declaring a set

1. Flush the configuration cache so the new declaration is read.
2. Clean `hyva_cms` so the icon list is rebuilt (see [Cache](#cache)).
3. Enable the set in `Icon Sets Offered In The Picker`. New sets are **not** enabled by default.

Theme overrides work on your set with no extra step, at
`<theme>/<your module>/web/svg/<value_prefix>/<name>.svg`.

### Declaring a renderer explicitly

You do not need to, but you can. An entry in the `renderers` argument of
`Blackbird\HyvaCmsLibrary\ViewModel\SvgIcons`, keyed by namespace, takes precedence over the one
derived from the registry. Use it when a namespace needs options the registry does not express.

One trap if you do: never give such a renderer a `pathPrefixMapping`. Hyvä consults that map
against the first segment of the icon name, so a key named after one of your sub-directories
would hijack the resolution.

## Multi-theme behaviour and badges

The picker enumerates the icons of the themes of the store views the edited content belongs to.
When the content covers all store views, or when the context cannot be determined, it falls back
to every active store view.

On a project with a single theme, this is invisible. On a multi-theme project, the modal shows the
**union** of those themes' icons rather than only what they have in common, and flags whatever
will not render identically everywhere. Hovering a badge, or focusing the tile with the keyboard,
shows one tooltip line per badge, tinted like the badge it explains.

Two severities, because the consequences are not comparable:

| Badge | Meaning | Consequence on the storefront |
|---|---|---|
| **Red** (crossed circle) | the icon does not exist at all in one or more themes | **nothing renders** on those store views |
| **Amber** (warning triangle) | the icon exists everywhere but not with the same drawing | the icon renders, with different artwork per store view |

Red is the dangerous one, and it only ever appears on `Theme icons`: an icon belonging to a set
always exists in its module, and an override changes its drawing without ever removing it.

The three messages:

| Badge | Message |
|---|---|
| Red | `Missing from the theme X. Nothing will render on its store views.` |
| Amber | `Only the theme X replaces it. Other store views show the native Lucide icon.` |
| Amber | `The artwork shown here comes from X. Other themes use a different drawing.` |

A single tile can carry both: absent from one theme, and drawn differently by the two that do
have it.

The badges are a warning, not a restriction. The icon stays selectable, because a value cannot
vary per store view in the Hyvä content model: one field holds one value for every store view.
The editor who wants consistency picks an icon that carries no badge at all.

When several themes provide the same icon, the tile shows the artwork of the first theme in the
context, which is the theme of the store view with the lowest id. That is what the second amber message
names, so the editor knows the drawing in front of them belongs to one theme in particular.

## Graceful degradation

A stored value may point at artwork the current theme does not carry. That is legitimate on a
multi-theme install, and it is exactly what the red badge warns about.

In that case the icon **renders as nothing**, and a debug line is logged. It never raises an
error. Hyvä's own icon renderer would throw `Asset\File\NotFoundException`, which would take the
whole page down on the store views whose theme lacks the file.

This mirrors how Hyvä's own selectors behave: a product disabled on a store view simply drops
out of its slider instead of breaking the page.

## Cache

The icon list is cached in Hyvä's own `hyva_cms` cache type, alongside the CMS component
definitions, with **one entry per icon set plus one for the theme icons**. Each entry is keyed on
the resolved theme context, on a schema version, and on the admin interface locale, since the
badge messages are translated before being cached.

After adding, removing or overriding an SVG:

```shell
bin/magento cache:clean hyva_cms
```

While working on a theme's icons, disable the type so every request reads the SVGs from disk:

```shell
bin/magento cache:disable hyva_cms
```

Sharing Hyvä's cache type cuts both ways: cleaning `hyva_cms` also drops the icon list, and
cleaning the icon list also drops the component definitions. In exchange there is no extra entry
in *Cache Management*, and the type is enabled on install by Hyvä's own data patch.

The enabled sets are **not** part of the cache key, which is what lets the configuration take
effect without a cache clean.

## Troubleshooting

**A new set does not appear in the modal.** It is not enabled by default: select it in
`Icon Sets Offered In The Picker`. If it is enabled and still absent, the declared `path` is
probably wrong or empty, and an unreadable directory yields no icons rather than an error.

**A set appears but its icons render as nothing on the storefront.** The `asset_prefix` does not
resolve. Check that it matches the first segment of `value_prefix`, and look for the
`Icon "..." could not be rendered` debug line, which carries the underlying reason.

**An override is ignored.** Its file name matches no icon of the set. Compare it with the file
names in the set's own directory.

**The admin field shows `Unknown icon`.** The stored value resolves to nothing at all, in any
set or in the legacy paths. It usually means the value was written by hand, or that the icon it
named has been deleted from the theme.

**Changes to the picker's own CSS do not show up.** The stylesheet is served from a versioned
static URL, so the browser keeps its copy until that version changes. Hard reload the editor, or
redeploy static content.
