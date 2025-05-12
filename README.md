# Article Full Text FreshRSS Extension

This FreshRSS extension uses the [Fivefilters Readability.php library](https://github.com/fivefilters/readability.php) to extract the full text of an article. Most importantly, it does **not** require any docker containers or external services to work.

## Features
- Extracts full article content using [Readability.php library by Fivefilters](https://github.com/fivefilters/readability.php)
- Works entirely client-side (no external services required)
- Preserves original formatting when possible

## Requirements
- PHP 8.1+, ext-dom, ext-xml, and ext-mbstring

## Installation

### Step-by-Step Installation Guide

1. **Download the extension**:
   - Clone this repository or download the ZIP file and extract it
   - The extension folder should be named `af_readability`

2. **Copy to FreshRSS extensions folder**:
   - Place the `af_readability` folder in your FreshRSS `extensions` directory
   - Typical paths:
     - Docker: `/usr/share/freshrss/extensions/`
     - Manual install: `/path/to/FreshRSS/extensions/`

3. **Set proper permissions**:
   - Ensure the web server has read access to the extension files
   - Example: `chown -R www-data:www-data /path/to/extensions/af_readability`

## Activation

1. **Log in** to your FreshRSS instance as an administrator
2. **Navigate** to the "System configuration" section in the admin panel
3. **Click** on the "Extensions" tab
4. **Find** "Af_Readability" in the list of available extensions
5. **Click** the toggle switch to enable the extension
6. **Click** "Save" to apply changes

## Configuration

### Enabling the plugin for a feed

1. After activation, go back to the "Extensions" tab in System configuration
2. Find "Af_Readability" in the list of active extensions
3. Click the gear/settings icon next to the extension name
4. Activate the checkbox for the feeds for which you want the plugin to fetch full article contents
5. Click "Save" to apply your settings

## Usage Notes

- The extension processes **NEW** articles when they are first fetched
- Existing articles won't be automatically reprocessed

To reprocess existing articles:
  1. Go to "Manage" → "Archiving" → "Delete all articles" (consider this step carefully)
  2. Refresh your feeds to fetch articles again
- "Clear cache" won't reprocess existing articles

## Troubleshooting

- If articles aren't showing full text:
  - Ensure the extension is properly activated
  - Try deleting and refetching articles as described above
  - Check FreshRSS protocol for error messages related to the plugin

## Based on:
- [ttrss-af_readability](https://gitlab.tt-rss.org/tt-rss/plugins/ttrss-af-readability) for Tiny Tiny RSS by Andrew Dolgov
- [xExtension-Readable](https://github.com/printfuck/xExtension-Readable) for FreshRSS by printfuck

[Official FreshRSS documentation](https://freshrss.github.io/FreshRSS/en/developers/03_Backend/05_Extensions.html) on writing extensions.
