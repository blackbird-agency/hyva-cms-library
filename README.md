# Blackbird_HyvaCmsLibrary

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
You should have ffmeg for the module to work. To install it use
```composer require php-ffmpeg/php-ffmpeg```

### Enable module

Go to your Magento root directory and run the following magento command:

```php bin/magento setup:upgrade```
