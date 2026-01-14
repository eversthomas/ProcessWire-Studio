<?php namespace ProcessWire;

/**
 * ProcessWire Studio Settings Handler
 * Manages minification, template file creation, and development settings
 */
class ProcesswireStudioSettings extends Wire {

	/**
	 * Get all templates that don't have template files yet
	 *
	 * @return Template[]
	 */
	public function getTemplatesWithoutFiles() {
		$templates = $this->wire('templates');
		$config = $this->wire('config');

		$result = [];
		foreach($templates as $tpl) {
			if(!$tpl instanceof Template) continue;

			// Skip system/admin templates
			if($tpl->flags & Template::flagSystem) continue;
			if($tpl->name === 'admin') continue;

			$filename = $tpl->name . '.php';
			$filepath = $config->paths->templates . $filename;

			if(!is_file($filepath)) {
				$result[] = $tpl;
			}
		}

		return $result;
	}

	/**
	 * Create a template file for a given template
	 *
	 * @param Template $template
	 * @param string $skeletonType 'minimal' | 'basic' | 'uikit' | 'markup-regions'
	 * @param bool $createBackup
	 * @param bool $includeHead Include HTML head section (for non-Markup Regions usage)
	 * @param array $regions Array of regions to include: 'header', 'sidebar', 'footer'
	 * @return bool Success
	 */
	public function createTemplateFile(
		Template $template, 
		$skeletonType = 'basic', 
		$createBackup = true,
		$includeHead = false,
		$regions = []
	) {
		$config = $this->wire('config');

		$filename = $template->name . '.php';
		$filepath = $config->paths->templates . $filename;

		// Create backup if file exists
		if(is_file($filepath) && $createBackup) {
			$backupPath = $filepath . '.backup.' . date('Y-m-d-His');
			if(!@copy($filepath, $backupPath)) {
				$this->error($this->_('Could not create backup of existing file.'));
				return false;
			}
		}

		// Generate skeleton code
		$code = $this->generateSkeleton($template, $skeletonType, $includeHead, $regions);

		// Write file
		if(@file_put_contents($filepath, $code) === false) {
			$this->error($this->_('Could not write template file: ') . $filename);
			return false;
		}

		@chmod($filepath, 0644);
		$this->message($this->_('Template file created: ') . $filename);

		return true;
	}

	/**
	 * Generate skeleton code based on type
	 *
	 * @param Template $template
	 * @param string $type
	 * @param bool $includeHead
	 * @param array $regions
	 * @return string PHP code
	 */
	protected function generateSkeleton(
		Template $template, 
		$type = 'basic', 
		$includeHead = false,
		$regions = []
	) {
		$name = $template->name;
		$label = $template->label ?: $name;

		switch($type) {
			case 'minimal':
				return $this->skeletonMinimal($name, $label, $includeHead, $regions);

			case 'uikit':
				return $this->skeletonUikit($name, $label, $template, $includeHead, $regions);

			case 'markup-regions':
				return $this->skeletonMarkupRegions($name, $label, $template, $includeHead, $regions);

			case 'basic':
			default:
				return $this->skeletonBasic($name, $label, $template, $includeHead, $regions);
		}
	}

	/**
	 * Minimal skeleton (just basics)
	 */
	protected function skeletonMinimal($name, $label, $includeHead = false, $regions = []) {
		$comment = $this->getProcessWireComment($name, $label);
		
		if($includeHead) {
			return <<<PHP
{$comment}
?><!DOCTYPE html>
<html lang="de">
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo \$page->title; ?></title>
</head>
<body>

<!-- Your code here -->

</body>
</html>
PHP;
		} else {
			return <<<PHP
{$comment}
<!-- Your code here -->
PHP;
		}
	}

	/**
	 * Basic skeleton with Markup Regions support
	 */
	protected function skeletonBasic($name, $label, Template $template, $includeHead = false, $regions = []) {
		$fields = $this->getTemplateFieldsList($template);
		$comment = $this->getProcessWireComment($name, $label, $fields);
		
		$out = $comment . "\n\n";
		
		if($includeHead) {
			$out .= "?><!DOCTYPE html>\n";
			$out .= "<html lang=\"de\">\n";
			$out .= "<head id=\"html-head\">\n";
			$out .= "\t<meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\" />\n";
			$out .= "\t<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
			$out .= "\t<title><?php echo \$page->title; ?></title>\n";
			$out .= "</head>\n";
			$out .= "<body id=\"html-body\">\n\n";
		}
		
		// Header region
		if(in_array('header', $regions)) {
			$out .= "<header id=\"header\">\n";
			$out .= "\t<!-- Header content -->\n";
			$out .= "</header>\n\n";
		}
		
		// Sidebar region
		if(in_array('sidebar', $regions)) {
			$out .= "<aside id=\"sidebar\">\n";
			$out .= "\t<!-- Sidebar content -->\n";
			$out .= "</aside>\n\n";
		}
		
		// Main content (always included)
		$out .= "<main id=\"main\">\n";
		$out .= "\t\n";
		$out .= "\t<!-- Your content here -->\n";
		$out .= "\t\n";
		$out .= "</main>\n";
		
		// Footer region
		if(in_array('footer', $regions)) {
			$out .= "\n<footer id=\"footer\">\n";
			$out .= "\t<!-- Footer content -->\n";
			$out .= "</footer>\n";
		}
		
		if($includeHead) {
			$out .= "\n</body>\n";
			$out .= "</html>\n";
		}
		
		return $out;
	}

	/**
	 * UIkit skeleton with Markup Regions support
	 */
	protected function skeletonUikit($name, $label, Template $template, $includeHead = false, $regions = []) {
		$fields = $this->getTemplateFieldsList($template);
		$comment = $this->getProcessWireComment($name, $label, $fields);
		
		$out = $comment . "\n\n";
		
		if($includeHead) {
			$out .= "?><!DOCTYPE html>\n";
			$out .= "<html lang=\"de\">\n";
			$out .= "<head id=\"html-head\">\n";
			$out .= "\t<meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\" />\n";
			$out .= "\t<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
			$out .= "\t<title><?php echo \$page->title; ?></title>\n";
			$out .= "\t<!-- UIkit CSS -->\n";
			$out .= "\t<link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/uikit@3.16.14/dist/css/uikit.min.css\">\n";
			$out .= "</head>\n";
			$out .= "<body id=\"html-body\">\n\n";
		}
		
		// Header region
		if(in_array('header', $regions)) {
			$out .= "<header id=\"header\" class=\"uk-section uk-section-primary\">\n";
			$out .= "\t<div class=\"uk-container\">\n";
			$out .= "\t\t<!-- Header content -->\n";
			$out .= "\t</div>\n";
			$out .= "</header>\n\n";
		}
		
		// Sidebar region
		if(in_array('sidebar', $regions)) {
			$out .= "<aside id=\"sidebar\" class=\"uk-section\">\n";
			$out .= "\t<div class=\"uk-container\">\n";
			$out .= "\t\t<!-- Sidebar content -->\n";
			$out .= "\t</div>\n";
			$out .= "</aside>\n\n";
		}
		
		// Main content (always included)
		$out .= "<main id=\"main\" class=\"uk-section\">\n";
		$out .= "\t<div class=\"uk-container\">\n";
		$out .= "\t\t\n";
		$out .= "\t\t<h1 class=\"uk-heading-medium\"><?php echo \$page->title; ?></h1>\n";
		$out .= "\t\t\n";
		$out .= "\t\t<!-- Your content here -->\n";
		$out .= "\t\t\n";
		$out .= "\t</div>\n";
		$out .= "</main>\n";
		
		// Footer region
		if(in_array('footer', $regions)) {
			$out .= "\n<footer id=\"footer\" class=\"uk-section uk-section-secondary\">\n";
			$out .= "\t<div class=\"uk-container\">\n";
			$out .= "\t\t<!-- Footer content -->\n";
			$out .= "\t</div>\n";
			$out .= "</footer>\n";
		}
		
		if($includeHead) {
			$out .= "\n\t<!-- UIkit JS -->\n";
			$out .= "\t<script src=\"https://cdn.jsdelivr.net/npm/uikit@3.16.14/dist/js/uikit.min.js\"></script>\n";
			$out .= "\t<script src=\"https://cdn.jsdelivr.net/npm/uikit@3.16.14/dist/js/uikit-icons.min.js\"></script>\n";
			$out .= "\n</body>\n";
			$out .= "</html>\n";
		}
		
		return $out;
	}

	/**
	 * Markup Regions skeleton (optimized for Markup Regions workflow)
	 */
	protected function skeletonMarkupRegions($name, $label, Template $template, $includeHead = false, $regions = []) {
		$fields = $this->getTemplateFieldsList($template);
		$comment = $this->getProcessWireComment($name, $label, $fields, true);
		
		$out = $comment . "\n\n";
		
		if($includeHead) {
			$out .= "?><!DOCTYPE html>\n";
			$out .= "<html lang=\"de\">\n";
			$out .= "<head id=\"html-head\">\n";
			$out .= "\t<meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\" />\n";
			$out .= "\t<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
			$out .= "\t<title><?php echo \$page->title; ?></title>\n";
			$out .= "</head>\n";
			$out .= "<body id=\"html-body\">\n\n";
		}
		
		// Header region
		if(in_array('header', $regions)) {
			$out .= "<header id=\"header\">\n";
			$out .= "\t<!-- Header content -->\n";
			$out .= "</header>\n\n";
		}
		
		// Sidebar region
		if(in_array('sidebar', $regions)) {
			$out .= "<aside id=\"sidebar\">\n";
			$out .= "\t<!-- Sidebar content -->\n";
			$out .= "</aside>\n\n";
		}
		
		// Main content (always included)
		$out .= "<main id=\"main\">\n";
		$out .= "\t\n";
		$out .= "\t<!-- Your content here -->\n";
		$out .= "\t\n";
		$out .= "</main>\n";
		
		// Footer region
		if(in_array('footer', $regions)) {
			$out .= "\n<footer id=\"footer\">\n";
			$out .= "\t<!-- Footer content -->\n";
			$out .= "</footer>\n";
		}
		
		if($includeHead) {
			$out .= "\n</body>\n";
			$out .= "</html>\n";
		}
		
		return $out;
	}

	/**
	 * Get ProcessWire-conform comment header for template files
	 *
	 * @param string $name Template name
	 * @param string $label Template label
	 * @param string $fields Comma-separated list of fields (optional)
	 * @param bool $markupRegions Whether Markup Regions are used
	 * @return string Comment block
	 */
	protected function getProcessWireComment($name, $label, $fields = '', $markupRegions = false) {
		$comment = "<?php namespace ProcessWire;\n\n";
		$comment .= "// Template file for pages using the \"{$name}\" template\n";
		$comment .= "// " . str_repeat('-', 50) . "\n";
		
		if($markupRegions) {
			$comment .= "// The #main element in this file will replace the #main element in _main.php\n";
			$comment .= "// when the Markup Regions feature is enabled, as it is by default.\n";
			$comment .= "// You can also append to (or prepend to) the #main element, and much more.\n";
			$comment .= "// See the Markup Regions documentation:\n";
			$comment .= "// https://processwire.com/docs/front-end/output/markup-regions/\n";
		}
		
		if($fields) {
			$comment .= "//\n";
			$comment .= "// Available fields: {$fields}\n";
		}
		
		$comment .= "\n?>";
		
		return $comment;
	}

	/**
	 * Get comma-separated list of template fields
	 */
	protected function getTemplateFieldsList(Template $template) {
		$fields = [];
		if($template->fieldgroup) {
			foreach($template->fieldgroup as $field) {
				if($field instanceof Field) {
					$fields[] = $field->name;
				}
			}
		}
		return implode(', ', $fields);
	}
}
