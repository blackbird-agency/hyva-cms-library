# Polybel — Blackbird_HyvaCmsLibrary

Reusable Hyvä CMS component library: file upload and icon picker custom field types, incrementer and video CMS elements.

## Prerequisites

- PHP 8.2 or above
- Magento 2.4 or above
- Hyva commerce module csm 1.2.1 or above
- ffmeg loccally install in /bin/

## Setup

Install via composer:
```composer require blackbird/hyva-cms-library```

## Install the module

### Styles
Move the tailwind/increment.css file into your tailwind/components/ folder.

### Install ffmeg
Install ffmeg locally install in /bin/ https://ffmpeg.org/, in prod install ffmpeg binary on server

### Enable module

Go to your Magento root directory and run the following magento command:

```php bin/magento setup:upgrade```
