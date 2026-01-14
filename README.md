# ProcessWire Studio

Developer toolbox for ProcessWire CMS. A comprehensive collection of tools to streamline development workflows.

## Features

### Code Generator
Generate PHP code snippets for ProcessWire templates based on selected fields. Choose a template, select the fields you need, and get ready-to-use code output. Also includes manual template file creation tool for generating template skeleton files.

### Data Page Lister
Transform data container pages into tabular views instead of the standard edit form. Configure which templates should display as tables, customize field selection (automatic or manual), and control pagination settings. Automatically hides child pages in the page tree and renames "Edit" to "Table" for better UX.

### SEO & Assets
CSS and JavaScript minification tool. Automatically detects CSS files in `/site/templates/styles/` and JS files in `/site/templates/scripts/`, then minifies them with one click. Uses the MatthiasMullie\Minify library for optimal compression with a fallback method if the library is unavailable.

### Patterns
Pattern library for common ProcessWire code patterns (coming soon).

### Settings
Development settings for template file management:
- **Auto-create template files**: Automatically generate PHP files when new templates are created
- **Backup before overwriting**: Create backups when updating existing template files
- **Skeleton types**: Choose from minimal, basic, UIkit, or Markup Regions templates
- **HTML head inclusion**: Option to include complete HTML head sections
- **Additional regions**: Select header, sidebar, and footer regions to include
- **Minification output**: Toggle to use minified CSS/JS files in frontend

## Installation

1. Copy the module to `/site/modules/ProcesswireStudio/`
2. Install via **Modules** page in ProcessWire admin
3. Access via **Setup > ProcessWire Studio**
4. Grant the `processwire-studio` permission to users who should access it

## Requirements

- ProcessWire 3.0.200+
- PHP 7.4+

## Usage

### Code Generator
1. Navigate to **Code Generator** tab
2. Select a template from the dropdown
3. Click "Load Fields"
4. Select the fields you need
5. Click "Generate Code" to get PHP code output
6. Use the "Manual Template File Creation" section to create template skeleton files

### Data Page Lister
1. Go to **Data Page Lister** tab
2. Select templates that should display as tables
3. Configure global settings (page size, help text, tree behavior)
4. Set template-specific field selection modes
5. Save settings

### SEO & Assets
1. Open **SEO** tab
2. View list of CSS and JavaScript files
3. Click "Minify" button for any file
4. Minified versions are saved as `.min.css` or `.min.js`
5. Enable minification output in Settings to use minified files automatically

### Settings
Configure development preferences in the **Settings** tab. All settings are saved to module configuration and persist across sessions.

## Credits

- **Minification**: Uses [MatthiasMullie\Minify](https://github.com/matthiasmullie/minify) library by Matthias Mullie
- **Development**: Created with assistance from Cursor AI and Claude AI

## License

GPL 3.0

## Version

1.0.0 - Initial Release
