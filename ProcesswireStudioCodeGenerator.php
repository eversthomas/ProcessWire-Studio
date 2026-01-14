<?php namespace ProcessWire;

/**
 * ProcessWire Studio Code Generator
 * Generates PHP code snippets for ProcessWire templates
 */
class ProcesswireStudioCodeGenerator extends Wire {

	/**
	 * Get all available templates (excluding system templates)
	 *
	 * @return Template[] Array of Template objects
	 */
	public function getAvailableTemplates() {
		/** @var Templates $templates */
		$templates = $this->wire('templates');

		$list = [];
		foreach($templates as $tpl) {
			if(!$tpl instanceof Template) continue;
			if($tpl->flags & Template::flagSystem) continue;
			$list[] = $tpl;
		}

		// Sort alphabetically by label (fallback: name)
		usort($list, function(Template $a, Template $b) {
			$al = (string) ($a->label ?: $a->name);
			$bl = (string) ($b->label ?: $b->name);
			return strcasecmp($al, $bl);
		});

		return $list;
	}

	/**
	 * Get all fields from a template with metadata
	 *
	 * @param int $templateId
	 * @return array[] Field information arrays
	 * @throws WireException
	 */
	public function getTemplateFields($templateId) {
		$templateId = (int) $templateId;
		if($templateId < 1) return [];

		/** @var Template $template */
		$template = $this->wire('templates')->get($templateId);
		if(!$template || !$template->id) {
			throw new WireException('Template not found.');
		}

		$out = [];
		$fg = $template->fieldgroup;
		if(!$fg) return [];

		foreach($fg as $field) {
			if(!$field instanceof Field) continue;

			$type = '';
			try {
				$type = $field->type ? $field->type->className() : '';
			} catch(\Throwable $e) {
				$type = '';
			}

			$out[] = [
				'id' => (int) $field->id,
				'name' => (string) $field->name,
				'label' => (string) $field->label,
				'type' => (string) $type,
				'description' => (string) $field->description,
				'required' => (bool) $field->required,
				'collapsed' => (int) $field->collapsed,
			];
		}

		return $out;
	}

	/**
	 * Generate PHP code for selected fields
	 *
	 * @param int $templateId
	 * @param array $selectedFields Field names to generate code for
	 * @return string Generated PHP code (full PHP block)
	 */
	public function generateCode($templateId, array $selectedFields) {
		$templateId = (int) $templateId;

		/** @var Template $template */
		$template = $this->wire('templates')->get($templateId);
		if(!$template || !$template->id) return '';

		/** @var Sanitizer $sanitizer */
		$sanitizer = $this->wire('sanitizer');

		// Sanitize requested field names defensively
		$selected = [];
		foreach($selectedFields as $f) {
			$f = $sanitizer->fieldName((string) $f);
			if($f) $selected[] = $f;
		}
		$selected = array_values(array_unique($selected));
		if(!count($selected)) return '';

		// Only allow fields that belong to the template (security + correctness)
		$fieldsByName = [];
		if($template->fieldgroup) {
			foreach($template->fieldgroup as $field) {
				if($field instanceof Field) $fieldsByName[$field->name] = $field;
			}
		}

		$code  = "<?php\n";
		$code .= "// Generated code for template: {$template->name}\n";
		$code .= "\$sanitizer = wire('sanitizer');\n";
		$code .= "\$modules = wire('modules');\n\n";

		foreach($selected as $fieldName) {

			if($fieldName === 'title') {
				$code .= "// BEGIN field: title\n";
				$code .= $this->generateTitleFieldCode('$page');
				$code .= "// END field: title\n\n";
				continue;
			}

			if(!isset($fieldsByName[$fieldName])) continue;

			$field = $fieldsByName[$fieldName];
			if(!$field instanceof Field) continue;

			$type = $field->type ? $field->type->className() : '';
			$fieldLabel = $field->label ?: $field->name;

			$code .= "// BEGIN field: {$fieldName} ({$fieldLabel})\n";

			switch($type) {
				case 'FieldtypeText':
				case 'FieldtypeTextarea':
				case 'FieldtypePageTitle':
					$code .= $this->generateTextFieldCode($field, '$page');
					break;

				case 'FieldtypeImage':
					$code .= $this->generateImageFieldCode($field, '$page');
					break;

				case 'FieldtypePage':
					$code .= $this->generatePageFieldCode($field, '$page');
					break;

				case 'FieldtypeRepeater':
					$code .= $this->generateRepeaterFieldCode($field, '$page');
					break;

				case 'FieldtypeOptions':
					$code .= $this->generateOptionsFieldCode($field, '$page');
					break;

				case 'FieldtypeDatetime':
					$code .= $this->generateDatetimeFieldCode($field, '$page');
					break;

				case 'FieldtypeURL':
					$code .= $this->generateUrlFieldCode($field, '$page');
					break;

				case 'FieldtypeEmail':
					$code .= $this->generateEmailFieldCode($field, '$page');
					break;

				case 'FieldtypeInteger':
				case 'FieldtypeFloat':
					$code .= $this->generateNumberFieldCode($field, '$page');
					break;

				case 'FieldtypeCheckbox':
					$code .= $this->generateCheckboxFieldCode($field, '$page');
					break;

				default:
					$code .= $this->generateGenericFieldCode($field, '$page');
					break;
			}

			$code .= "// END field: {$fieldName}\n\n";
		}

		return $code;
	}

	/**
	 * Generate code for the title field
	 *
	 * @param string $pageVar
	 * @return string
	 */
	protected function generateTitleFieldCode($pageVar = '$page') {
		return <<<PHP
<?= \$sanitizer->entities({$pageVar}->title) ?>

PHP;
	}

	/**
	 * Generate code for a single text field
	 *
	 * Detects HTML/RTE fields and handles them appropriately
	 *
	 * @param Field $field
	 * @param string $pageVar
	 * @return string
	 */
	protected function generateTextFieldCode(Field $field, $pageVar = '$page') {
		$fieldName = $field->name;
		$type = $field->type ? $field->type->className() : 'FieldtypeText';

		// Detect HTML/RTE:
		// 1) contentType flag (standard for FieldtypeTextarea)
		// 2) fallback: inputfield class (TinyMCE/CKEditor/etc.)
		$contentType = (int) ($field->get('contentType') ?? 0);
		$isHtml = false;

		if($type === 'FieldtypeTextarea') {
			// FieldtypeTextarea::contentTypeHTML is usually 1
			if(defined('\\ProcessWire\\FieldtypeTextarea::contentTypeHTML')) {
				$isHtml = ($contentType === \ProcessWire\FieldtypeTextarea::contentTypeHTML);
			} else {
				$isHtml = ($contentType === 1);
			}
		}

		if(!$isHtml) {
			try {
				// Some inputfields need a Page context; using a blank page is sufficient for detection.
				$inputfield = $field->getInputfield(new Page());
				if($inputfield) {
					$ifClass = $inputfield->className();
					if(stripos($ifClass, 'TinyMCE') !== false || stripos($ifClass, 'CKEditor') !== false) {
						$isHtml = true;
					}
				}
			} catch(\Throwable $e) {
				// ignore, keep $isHtml as-is
			}
		}

		if($isHtml) {
			return <<<PHP
<?php
if(\$modules->isInstalled('MarkupHTMLPurifier')) {
    \$purifier = \$modules->get('MarkupHTMLPurifier');
    echo \$purifier->purify({$pageVar}->{$fieldName});
} else {
    echo {$pageVar}->{$fieldName}; // WARNING: raw HTML output
}
?>

PHP;
		}

		return <<<PHP
<?= \$sanitizer->entities({$pageVar}->{$fieldName}) ?>

PHP;
	}

	/**
	 * Generate code for an image field
	 *
	 * @param Field $field
	 * @param string $pageVar
	 * @return string
	 */
	protected function generateImageFieldCode(Field $field, $pageVar = '$page') {
		$fieldName = $field->name;
		$maxFiles = (int) ($field->get('maxFiles') ?? 0);
		$isMultiple = ($maxFiles != 1);

		if($isMultiple) {
			return <<<PHP
<?php foreach({$pageVar}->{$fieldName} as \$image): ?>
<?php
    \$resized = \$image->size(800, 600);
    \$alt = \$image->description ?: ({$pageVar}->title ?: 'Image');
?>
<img src="<?= \$sanitizer->url(\$resized->url) ?>" 
     alt="<?= \$sanitizer->entities(\$alt) ?>" 
     width="<?= \$resized->width ?>" 
     height="<?= \$resized->height ?>" 
     loading="lazy">
<?php endforeach; ?>

PHP;
		}

		return <<<PHP
<?php
\$image = {$pageVar}->{$fieldName};
if(\$image instanceof Pageimages && \$image->count()) {
    \$image = \$image->first();
}
if(\$image instanceof Pageimage):
    \$resized = \$image->size(800, 600);
    \$alt = \$image->description ?: ({$pageVar}->title ?: 'Image');
?>
<img src="<?= \$sanitizer->url(\$resized->url) ?>" 
     alt="<?= \$sanitizer->entities(\$alt) ?>" 
     width="<?= \$resized->width ?>" 
     height="<?= \$resized->height ?>" 
     loading="lazy">
<?php endif; ?>

PHP;
	}

	/**
	 * Generate code for a page reference field
	 *
	 * @param Field $field
	 * @param string $pageVar
	 * @return string
	 */
	protected function generatePageFieldCode(Field $field, $pageVar = '$page') {
		$fieldName = $field->name;
		$deref = (int) ($field->get('derefAsPage') ?? 0);

		// In PW3: derefAsPage = 1 means single Page, otherwise PageArray
		$isMultiple = ($deref !== 1);

		if($isMultiple) {
			return <<<PHP
<?php foreach({$pageVar}->{$fieldName} as \$item): ?>
    <?php \$itemUrl = \$sanitizer->url(\$item->url); ?>
    <?php \$itemTitle = \$sanitizer->entities(\$item->title); ?>
    // Use: \$itemUrl and \$itemTitle
<?php endforeach; ?>

PHP;
		}

		return <<<PHP
<?php
\$itemUrl = \$sanitizer->url({$pageVar}->{$fieldName}->url);
\$itemTitle = \$sanitizer->entities({$pageVar}->{$fieldName}->title);
?>
// Use: \$itemUrl and \$itemTitle

PHP;
	}

	/**
	 * Generate code for a repeater field
	 *
	 * @param Field $field
	 * @param string $pageVar
	 * @return string
	 */
	protected function generateRepeaterFieldCode(Field $field, $pageVar = '$page') {
		$fieldName = $field->name;

		$subfields = [];
		$repeaterTemplate = null;

		// Resolve repeater template robustly
		if($field->type && method_exists($field->type, 'getRepeaterTemplate')) {
			try {
				$tpl = $field->type->getRepeaterTemplate($field);

				if($tpl instanceof Template) {
					$repeaterTemplate = $tpl;
				} else {
					// $tpl may be ID or name depending on PW version/setup
					$repeaterTemplate = $this->wire('templates')->get($tpl);
				}
			} catch(\Throwable $e) {
				$repeaterTemplate = null;
			}
		}

		// Collect all subfields with their types
		if($repeaterTemplate instanceof Template && $repeaterTemplate->id && $repeaterTemplate->fieldgroup) {
			foreach($repeaterTemplate->fieldgroup as $subfield) {
				if(!$subfield instanceof Field) continue;
				// Include all fields, even title and name (they're often used)
				$type = '';
				try {
					$type = $subfield->type ? $subfield->type->className() : '';
				} catch(\Throwable $e) {
					$type = '';
				}
				$subfields[] = [
					'field' => $subfield,
					'name' => $subfield->name,
					'type' => $type,
				];
			}
		}

		// Generate code for each subfield
		$subfieldCode = '';
		foreach($subfields as $subfieldData) {
			$subfield = $subfieldData['field'];
			$subfieldName = $subfieldData['name'];
			$subfieldType = $subfieldData['type'];
			$subfieldLabel = $subfield->label ?: $subfieldName;

			$subfieldCode .= "    // Subfield: {$subfieldName} ({$subfieldLabel})\n";

			// Generate code based on field type (using $item instead of $page)
			switch($subfieldType) {
				case 'FieldtypeText':
				case 'FieldtypeTextarea':
				case 'FieldtypePageTitle':
					$subfieldCode .= $this->indentCode($this->generateTextFieldCode($subfield, '$item'), 4);
					break;

				case 'FieldtypeImage':
					$subfieldCode .= $this->indentCode($this->generateImageFieldCode($subfield, '$item'), 4);
					break;

				case 'FieldtypePage':
					$subfieldCode .= $this->indentCode($this->generatePageFieldCode($subfield, '$item'), 4);
					break;

				case 'FieldtypeOptions':
					$subfieldCode .= $this->indentCode($this->generateOptionsFieldCode($subfield, '$item'), 4);
					break;

				case 'FieldtypeDatetime':
					$subfieldCode .= $this->indentCode($this->generateDatetimeFieldCode($subfield, '$item'), 4);
					break;

				case 'FieldtypeURL':
					$subfieldCode .= $this->indentCode($this->generateUrlFieldCode($subfield, '$item'), 4);
					break;

				case 'FieldtypeEmail':
					$subfieldCode .= $this->indentCode($this->generateEmailFieldCode($subfield, '$item'), 4);
					break;

				case 'FieldtypeInteger':
				case 'FieldtypeFloat':
					$subfieldCode .= $this->indentCode($this->generateNumberFieldCode($subfield, '$item'), 4);
					break;

				case 'FieldtypeCheckbox':
					$subfieldCode .= $this->indentCode($this->generateCheckboxFieldCode($subfield, '$item'), 4);
					break;

				default:
					$subfieldCode .= $this->indentCode($this->generateGenericFieldCode($subfield, '$item'), 4);
					break;
			}
			$subfieldCode .= "\n";
		}

		// If no subfields found, provide a basic structure
		if(empty($subfieldCode)) {
			$subfieldCode = "    // Add your repeater item fields here\n";
		}

		return <<<PHP
<?php foreach({$pageVar}->{$fieldName} as \$item): ?>
{$subfieldCode}<?php endforeach; ?>

PHP;
	}

	/**
	 * Generate code for options/select fields
	 *
	 * @param Field $field
	 * @param string $pageVar
	 * @return string
	 */
	protected function generateOptionsFieldCode(Field $field, $pageVar = '$page') {
		$fieldName = $field->name;

		return <<<PHP
<?php
\${$fieldName} = {$pageVar}->{$fieldName};
if(\${$fieldName} instanceof SelectableOption):
    \$optionTitle = \$sanitizer->entities(\${$fieldName}->title);
    // Use: \$optionTitle
elseif(\${$fieldName} instanceof SelectableOptionArray):
    foreach(\${$fieldName} as \$option):
        \$optionTitle = \$sanitizer->entities(\$option->title);
        // Use: \$optionTitle
    endforeach;
endif;
?>

PHP;
	}

	/**
	 * Generate code for datetime field
	 *
	 * @param Field $field
	 * @param string $pageVar
	 * @return string
	 */
	protected function generateDatetimeFieldCode(Field $field, $pageVar = '$page') {
		$fieldName = $field->name;

		return <<<PHP
<?php
\$date = {$pageVar}->{$fieldName};
\$dateFormatted = \$date ? (function_exists('wireDate') ? wireDate('F j, Y', \$date) : date('F j, Y', (int) \$date)) : '';
\$dateISO = \$date ? (function_exists('wireDate') ? wireDate('c', \$date) : date('c', (int) \$date)) : '';
?>
// Use: \$dateFormatted (formatted) and \$dateISO (ISO 8601)

PHP;
	}

	/**
	 * Generate code for URL field
	 *
	 * @param Field $field
	 * @param string $pageVar
	 * @return string
	 */
	protected function generateUrlFieldCode(Field $field, $pageVar = '$page') {
		$fieldName = $field->name;

		return <<<PHP
<?php
\$url = \$sanitizer->url({$pageVar}->{$fieldName});
\$urlDisplay = \$sanitizer->entities({$pageVar}->{$fieldName});
?>
// Use: \$url (safe URL) and \$urlDisplay (display text)

PHP;
	}

	/**
	 * Generate code for email field
	 *
	 * @param Field $field
	 * @param string $pageVar
	 * @return string
	 */
	protected function generateEmailFieldCode(Field $field, $pageVar = '$page') {
		$fieldName = $field->name;

		return <<<PHP
<?php
\$email = \$sanitizer->email({$pageVar}->{$fieldName});
\$emailDisplay = \$sanitizer->entities({$pageVar}->{$fieldName});
?>
// Use: \$email (safe email) and \$emailDisplay (display text)

PHP;
	}

	/**
	 * Generate code for number fields (integer/float)
	 *
	 * @param Field $field
	 * @param string $pageVar
	 * @return string
	 */
	protected function generateNumberFieldCode(Field $field, $pageVar = '$page') {
		$fieldName = $field->name;
		$type = $field->type ? $field->type->className() : 'FieldtypeInteger';

		if($type === 'FieldtypeFloat') {
			return <<<PHP
<?php \$number = number_format((float) {$pageVar}->{$fieldName}, 2); ?>
// Use: \$number

PHP;
		}

		return <<<PHP
<?php \$number = (int) {$pageVar}->{$fieldName}; ?>
// Use: \$number

PHP;
	}

	/**
	 * Generate code for checkbox field
	 *
	 * @param Field $field
	 * @param string $pageVar
	 * @return string
	 */
	protected function generateCheckboxFieldCode(Field $field, $pageVar = '$page') {
		$fieldName = $field->name;

		return <<<PHP
<?php \$checked = {$pageVar}->{$fieldName} ? true : false; ?>
// Use: \$checked (boolean)

PHP;
	}

	/**
	 * Generic fallback for unsupported field types
	 *
	 * @param Field $field
	 * @param string $pageVar
	 * @return string
	 */
	protected function generateGenericFieldCode(Field $field, $pageVar = '$page') {
		$fieldName = $field->name;
		$type = $field->type ? $field->type->className() : 'UnknownFieldtype';

		return <<<PHP
<?php
\${$fieldName} = {$pageVar}->{$fieldName};
if(is_scalar(\${$fieldName})):
    \${$fieldName}Value = \$sanitizer->entities((string) \${$fieldName});
    // Use: \${$fieldName}Value
endif;
?>

PHP;
	}

	/**
	 * Normalize text for safe one-line PHP comments.
	 *
	 * @param string $text
	 * @return string
	 */
	protected function normalizeComment($text) {
		$text = (string) $text;
		$text = str_replace(["\r\n", "\r", "\n"], ' ', $text);
		$text = preg_replace('/\s+/', ' ', $text);
		return trim($text);
	}

	/**
	 * Indent code by specified number of spaces
	 *
	 * @param string $code
	 * @param int $spaces
	 * @return string
	 */
	protected function indentCode($code, $spaces = 4) {
		$indent = str_repeat(' ', $spaces);
		$lines = explode("\n", $code);
		$indented = [];
		foreach($lines as $line) {
			if(trim($line) !== '') {
				$indented[] = $indent . $line;
			} else {
				$indented[] = '';
			}
		}
		return implode("\n", $indented);
	}
}
