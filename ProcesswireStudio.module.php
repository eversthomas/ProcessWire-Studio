<?php namespace ProcessWire;

/**
 * ProcessWire Studio (Process module)
 *
 * Provides a developer toolbox UI in the ProcessWire admin.
 */
class ProcesswireStudio extends Process implements Module, ConfigurableModule {

	/**
	 * Constructor: set default module settings
	 */
	public function __construct() {
		parent::__construct();
		
		// Settings defaults
		$this->set('enableMinification', false);      // CSS/JS Minification an/aus
		$this->set('autoCreateTemplateFiles', false); // Template-Files automatisch erstellen
		$this->set('templateFileBackup', true);       // Backup vor Überschreiben
		$this->set('templateSkeletonType', 'basic');  // 'basic' | 'uikit' | 'minimal' | 'markup-regions'
		$this->set('templateIncludeHead', false);     // HTML Head einfügen (für non-Markup Regions)
		$this->set('templateRegions', []);            // Zusätzliche Regions: 'header', 'sidebar', 'footer'
		
		// DataPageLister defaults
		$this->set('dataPageListerTemplates', []);   // Array von Template-Namen
		$this->set('dataPageListerConfigs', []);      // Template-spezifische Konfigurationen
		$this->set('dataPageListerPageSize', 50);    // Pagination
		$this->set('dataPageListerShowHelp', true);  // Hilfe anzeigen
		$this->set('dataPageListerHideChildren', true); // Kinder im Tree ausblenden
		$this->set('dataPageListerRenameEdit', true); // "Bearbeiten" → "Tabelle"
		$this->set('dataPageListerShowView', false);  // "Ansehen"-Button
	}


	/**
	 * Module information
	 *
	 * @return array
	 */
	public static function getModuleInfo() {
		return array(
			'title' => 'ProcessWire Studio',
			'version' => 100, // v1.0.0
			'summary' => 'Developer toolbox for ProcessWire',
			'author' => 'ChatGPT',
			'icon' => 'code',
			'requires' => 'ProcessWire>=3.0.200',
			'page' => array(
				'name' => 'processwire-studio',
				'parent' => 'setup',
				'title' => 'ProcessWire Studio',
			),
			'autoload' => true,
			'permission' => 'processwire-studio',
			'permissions' => array(
				'processwire-studio' => 'Access ProcessWire Studio',
			),
		);
	}

	/**
	 * Init hook (load assets and internal class files)
	 *
	 * @return void
	 */
	public function init() {
		parent::init();

		// Load saved module config into runtime properties
		$this->loadSettings();

		// Auto-create template files when enabled
		if($this->autoCreateTemplateFiles) {
			$this->addHookAfter('Templates::saved', $this, 'hookTemplateCreated');
		}

		// DataPageLister Hooks
		$this->initDataPageLister();
	}

	/**
	 * Initialisiert DataPageLister Hooks
	 */
	protected function initDataPageLister() {
		$config = $this->wire('modules')->getConfig($this);
		$enabledTemplates = $config['dataPageListerTemplates'] ?? [];
		
		if(empty($enabledTemplates)) return;

		// Lade DataPageLister-Klassen
		require_once __DIR__ . '/ProcesswireStudioDataPageLister.php';
		require_once __DIR__ . '/ProcesswireStudioDataPageListerTree.php';
		require_once __DIR__ . '/ProcesswireStudioDataPageListerFilter.php';
		require_once __DIR__ . '/ProcesswireStudioDataPageListerRender.php';

		$lister = $this->wire(new ProcesswireStudioDataPageLister());

		// Hook: Ersetze Edit-Form komplett durch Tabelle
		$this->addHookAfter('ProcessPageEdit::buildForm', function(HookEvent $event) use ($lister) {
			$process = $event->object;
			$page = $process->getPage();
			if(!$page || !$page->id) return;

			if(!$lister->isDataContainer($page)) return;

			$childTemplates = $lister->getChildTemplates($page);
			if(!count($childTemplates)) return;

			$fieldNames = $lister->selectDisplayFields($page, $childTemplates);

			[$selector, $active, $allowed] = ProcesswireStudioDataPageListerFilter::buildSelector(
				$page,
				$fieldNames,
				$this->input,
				$this->sanitizer
			);

			$config = $this->wire('modules')->getConfig($this);
			$pageSize = (int)($config['dataPageListerPageSize'] ?? 50);
			$pageNum = max(1, (int) $this->sanitizer->int($this->input->get('pg')));
			$start = ($pageNum - 1) * $pageSize;

			$items = $this->pages->find("$selector, start=$start, limit=$pageSize");
			$total = $this->pages->count($selector);

			/** @var InputfieldForm $form */
			$form = $event->return;
			
			// Entferne ALLE bestehenden Felder (komplett ersetzen)
			// Da removeAll() nicht existiert, entfernen wir alle Felder einzeln
			$allFields = $form->getAll();
			foreach($allFields as $field) {
				$form->remove($field);
			}
			
			// Füge nur die Tabelle hinzu
			$box = $this->modules->get('InputfieldMarkup');
			$box->name = 'data_page_lister';
			$box->label = $this->_('Daten-Übersicht');
			$box->collapsed = Inputfield::collapsedNever;
			$box->value = ProcesswireStudioDataPageListerRender::overview(
				$page,
				$fieldNames,
				$items,
				$total,
				$pageNum,
				$pageSize,
				$active,
				$childTemplates,
				$allowed,
				$this->config->urls->admin,
				(bool)($config['dataPageListerShowHelp'] ?? true),
				(bool)($config['dataPageListerShowView'] ?? false)
			);

			$form->add($box);
			$event->return = $form;
		});

		// PageTree: Kinder ausblenden
		if($config['dataPageListerHideChildren'] ?? true) {
			$this->addHookBefore('Page::listable', function(HookEvent $event) use ($lister) {
				ProcesswireStudioDataPageListerTree::hookPageListable($lister, $event);
			});
		}

		// PageTree: "Bearbeiten" → "Tabelle"
		if($config['dataPageListerRenameEdit'] ?? true) {
			$this->addHookAfter('ProcessPageListRender::getPageActions', function(HookEvent $event) use ($lister) {
				ProcesswireStudioDataPageListerTree::hookPageListActionsRename($lister, $event);
			});
		}
	}

	/**
	 * Load module config settings into object properties (with defaults).
	 */
	protected function loadSettings() {
		$data = $this->wire('modules')->getConfig($this);
		if(!is_array($data)) $data = [];

		$this->enableMinification = !empty($data['enableMinification']);
		$this->autoCreateTemplateFiles = !empty($data['autoCreateTemplateFiles']);
		$this->templateFileBackup = array_key_exists('templateFileBackup', $data) ? (bool) $data['templateFileBackup'] : true;

		$type = isset($data['templateSkeletonType']) ? (string) $data['templateSkeletonType'] : 'basic';
		$type = $this->wire('sanitizer')->name($type);
		if(!in_array($type, ['minimal', 'basic', 'uikit', 'markup-regions'], true)) $type = 'basic';
		$this->templateSkeletonType = $type;

		$this->templateIncludeHead = !empty($data['templateIncludeHead']);
		$this->templateRegions = isset($data['templateRegions']) && is_array($data['templateRegions']) ? $data['templateRegions'] : [];

		// DataPageLister settings
		$this->dataPageListerTemplates = isset($data['dataPageListerTemplates']) && is_array($data['dataPageListerTemplates']) ? $data['dataPageListerTemplates'] : [];
		$this->dataPageListerConfigs = isset($data['dataPageListerConfigs']) && is_array($data['dataPageListerConfigs']) ? $data['dataPageListerConfigs'] : [];
		$this->dataPageListerPageSize = isset($data['dataPageListerPageSize']) ? (int) $data['dataPageListerPageSize'] : 50;
		$this->dataPageListerShowHelp = !isset($data['dataPageListerShowHelp']) || !empty($data['dataPageListerShowHelp']);
		$this->dataPageListerHideChildren = !isset($data['dataPageListerHideChildren']) || !empty($data['dataPageListerHideChildren']);
		$this->dataPageListerRenameEdit = !isset($data['dataPageListerRenameEdit']) || !empty($data['dataPageListerRenameEdit']);
		$this->dataPageListerShowView = !empty($data['dataPageListerShowView']);
	}

	/**
	 * Hook: Auto-create template file when a template is saved.
	 * Only creates a file if it doesn't exist yet (never overwrites).
	 */
	public function hookTemplateCreated(HookEvent $event) {
		$template = $event->arguments(0);
		if(!$template instanceof Template) return;

		// Skip system templates
		if($template->flags & Template::flagSystem) return;

		$filename = $template->name . '.php';
		$filepath = $this->config->paths->templates . $filename;

		// Don't overwrite existing files
		if(is_file($filepath)) return;

		$settingsFile = __DIR__ . '/ProcesswireStudioSettings.php';
		if(!is_file($settingsFile)) return;

		require_once($settingsFile);
		$settings = $this->wire(new ProcesswireStudioSettings());

		$settings->createTemplateFile(
			$template,
			$this->templateSkeletonType,
			$this->templateFileBackup,
			$this->templateIncludeHead,
			(array)$this->templateRegions
		);
	}


	/**
	 * Main process execution (renders tab navigation + content)
	 *
	 * @return string
	 * @throws WirePermissionException
	 */
	public function ___execute() {

		// Permission check
		if(!$this->user->hasPermission('processwire-studio')) {
			throw new WirePermissionException($this->_('You do not have permission to access ProcessWire Studio.'));
		}

		// AJAX action router
		$action = $this->sanitizer->name((string) $this->input->get('action'));
		if($action === 'generate') {
			return $this->executeAjaxGenerate();
		}
		if($action === 'minify') {
			return $this->executeAjaxMinify();
		}

		// Load assets only for the Studio admin page
		$baseUrl = $this->wire('config')->urls($this);
		$this->wire('config')->styles->add($baseUrl . 'assets/studio.css');
		// Prism.js for syntax highlighting
		$this->wire('config')->styles->add('https://cdn.jsdelivr.net/npm/prismjs@1.29.0/themes/prism-tomorrow.min.css');
		$this->wire('config')->scripts->add('https://cdn.jsdelivr.net/npm/prismjs@1.29.0/components/prism-core.min.js');
		$this->wire('config')->scripts->add('https://cdn.jsdelivr.net/npm/prismjs@1.29.0/plugins/autoloader/prism-autoloader.min.js');
		$this->wire('config')->scripts->add($baseUrl . 'assets/studio.js');

		try {
			$tab = (string) $this->input->get('tab');
			$tab = $this->sanitizer->name($tab);
			if(!$tab) $tab = 'code-generator';

			$tabs = array(
				'code-generator' => $this->_('Code Generator'),
				'patterns'       => $this->_('Patterns'),
				'data-page-lister' => $this->_('Data Page Lister'),
				'seo'            => $this->_('SEO'),
				'settings'       => $this->_('Settings'),
			);

			if(!isset($tabs[$tab])) $tab = 'code-generator';

			$wrapper = new InputfieldWrapper();

			$nav = $this->modules->get('InputfieldMarkup');
			$nav->label = $this->_('ProcessWire Studio');
			$nav->value = $this->renderTabs($tabs, $tab);
			$wrapper->add($nav);

			$content = $this->modules->get('InputfieldMarkup');
			$content->value = '<div class="pw-studio-content">' . $this->getTabContent($tab) . '</div>';
			$wrapper->add($content);

			return $wrapper->render();

		} catch(\Exception $e) {
			$this->log->save('processwire-studio', 'Execute error: ' . $e->getMessage());
			return '<div class="pw-studio-error uk-alert uk-alert-danger">' . $this->sanitizer->entities($e->getMessage()) . '</div>';
		}
	}

	/**
	 * Render tab navigation
	 *
	 * @param array $tabs
	 * @param string $activeTab
	 * @return string
	 */
	function renderTabs(array $tabs, $activeTab) {
		$out = '<div class="pw-studio-tabs">';

		foreach($tabs as $key => $label) {
			$isActive = ($key === $activeTab);
			$class = 'pw-studio-tab' . ($isActive ? ' active' : '');
			$url = './?tab=' . rawurlencode($key);

			$out .= sprintf(
				'<a class="%s" href="%s">%s</a>',
				$this->sanitizer->entities($class),
				$this->sanitizer->entities($url),
				$this->sanitizer->entities($label)
			);
		}

		$out .= '</div>';
		return $out;
	}

	/**
	 * Return content for the active tab.
	 *
	 * @param string $tab
	 * @return string
	 */
	protected function getTabContent($tab) {
		switch($tab) {
			case 'patterns':
				return $this->executePatterns();
			case 'data-page-lister':
				return $this->executeDataPageLister();
			case 'seo':
				return $this->executeSEO();
			case 'settings':
				return $this->executeSettings();
			case 'code-generator':
			default:
				return $this->executeCodeGenerator();
		}
	}

	/**
	 * Tab handler: Code Generator
	 *
	 * @return string
	 */
	public function executeCodeGenerator() {

		$csrf = $this->session->CSRF;

		// Process manual template file creation
		if($this->input->post('submit_create_file')) {
			if(!$csrf->validate()) {
				$this->error($this->_('Invalid CSRF token.'));
			} else {
				$templateId = (int) $this->input->post('create_template_file');
				if($templateId) {
					$template = $this->wire('templates')->get($templateId);
					if($template && $template->id) {
						$settingsFile = __DIR__ . '/ProcesswireStudioSettings.php';
						if(is_file($settingsFile)) {
							require_once($settingsFile);
							$settings = $this->wire(new ProcesswireStudioSettings());
							$settings->createTemplateFile(
								$template,
								$this->templateSkeletonType,
								$this->templateFileBackup,
								$this->templateIncludeHead,
								(array)$this->templateRegions
							);
						} else {
							$this->error($this->_('Missing file: ProcesswireStudioSettings.php'));
						}
					}
				}
			}
		}

		// Lazy-load Code Generator class
        $codeGenFile = __DIR__ . '/ProcesswireStudioCodeGenerator.php';
        
        $fqcn = __NAMESPACE__ . '\\ProcesswireStudioCodeGenerator';
        
        if(!class_exists($fqcn) && is_file($codeGenFile)) {
        	require_once($codeGenFile);
        }
        
        if(!class_exists($fqcn)) {
        	return '<div class="uk-alert uk-alert-danger"><p>' .
        		$this->sanitizer->entities($this->_('Code Generator class is not available.')) .
        	'</p></div>';
        }
        
        $generator = $this->wire(new ProcesswireStudioCodeGenerator());
        $templateId = (int) $this->input->get('tpl');

		// CSRF token for AJAX POSTs
		$csrf = $this->session->CSRF;
		$tokenName = $csrf->getTokenName();
		$tokenValue = $csrf->getTokenValue();

		$out = '<div class="pw-studio-codegen">';

		// 1. Template Selection
		$out .= '<div class="uk-card uk-card-default uk-card-body uk-margin">';
		$out .= '<h3 class="uk-card-title">' . $this->sanitizer->entities($this->_('Select Template')) . '</h3>';
		$out .= '<form method="get" action="./" class="uk-form-stacked">';
		$out .= '<input type="hidden" name="tab" value="code-generator">';
		$out .= '<div class="uk-margin">';
		$out .= '<select name="tpl" class="uk-select uk-width-medium">';
		$out .= '<option value="">' . $this->sanitizer->entities($this->_('Choose a template...')) . '</option>';

		try {
			$templates = $generator->getAvailableTemplates();
			foreach($templates as $tpl) {
				$selected = ($tpl->id === $templateId) ? ' selected' : '';
				$label = $tpl->label ?: $tpl->name;

				$out .= '<option value="' . (int) $tpl->id . '"' . $selected . '>'
					. $this->sanitizer->entities($label) . ' (' . $this->sanitizer->entities($tpl->name) . ')'
					. '</option>';
			}
		} catch(\Exception $e) {
			$this->log->save('processwire-studio', 'Template list error: ' . $e->getMessage());
		}

		$out .= '</select>';
		$out .= '</div>';
		$out .= '<div class="uk-margin">';
		$out .= '<button type="submit" class="uk-button uk-button-primary">'
			. $this->sanitizer->entities($this->_('Load Fields'))
			. '</button>';
		$out .= '</div></div>';
		$out .= '</form>';
		$out .= '</div>';

		// 2. Field Selection
		if($templateId) {
			try {
				$fields = $generator->getTemplateFields($templateId);

				if(count($fields)) {
					$out .= '<div class="uk-card uk-card-default uk-card-body uk-margin">';
					$out .= '<h3 class="uk-card-title">' . $this->sanitizer->entities($this->_('Select Fields')) . '</h3>';

					$out .= '<form id="codegen-form" method="post" action="./?action=generate" class="uk-form-stacked pw-studio-generator-form" data-token-name="' . $this->sanitizer->entities($tokenName) . '" data-token-value="' . $this->sanitizer->entities($tokenValue) . '">';

					$out .= '<input type="hidden" name="template_id" value="' . (int) $templateId . '">';
					$out .= '<input type="hidden" name="' . $this->sanitizer->entities($tokenName) . '" value="' . $this->sanitizer->entities($tokenValue) . '">';

					$out .= '<div class="pw-studio-fields uk-grid-small uk-child-width-1-2@m" uk-grid>';
					foreach($fields as $field) {
						$out .= '<label class="uk-margin-small">';
						$out .= '<input class="uk-checkbox" type="checkbox" name="fields[]" value="' . $this->sanitizer->entities($field['name']) . '"> ';
						$out .= $this->sanitizer->entities($field['label']) . ' <span class="uk-text-meta">(' . $this->sanitizer->entities($field['type']) . ')</span>';
						$out .= '</label>';
					}
					$out .= '</div>';

					$out .= '<div class="uk-margin-top">';
					$out .= '<button id="generate-code-btn" type="submit" class="uk-button uk-button-primary">'
						. $this->sanitizer->entities($this->_('Generate Code'))
						. '</button>';
					$out .= '</div>';

					$out .= '</form>';

					$out .= '<div id="code-output" class="uk-margin-top" style="display:none;">';
					$out .= '<label class="uk-form-label">' . $this->sanitizer->entities($this->_('Generated Code')) . '</label>';
					$out .= '<pre class="pw-studio-code-wrapper"><code id="generated-code" class="language-php"></code></pre>';
					$out .= '<div class="uk-margin-small-top">';
					$out .= '<button id="copy-code-btn" type="button" class="uk-button uk-button-default pw-studio-copy">'
						. $this->sanitizer->entities($this->_('Copy to Clipboard'))
						. '</button>';
					$out .= '</div>';
					$out .= '</div>';

					$out .= '</div>';

				} else {
					$out .= '<div class="uk-alert uk-alert-warning"><p>' .
						$this->sanitizer->entities($this->_('No fields found for this template.')) .
					'</p></div>';
				}

			} catch(\Exception $e) {
				$this->log->save('processwire-studio', 'Field list error: ' . $e->getMessage());
				$out .= '<div class="uk-alert uk-alert-danger"><p>' .
					$this->sanitizer->entities($e->getMessage()) .
				'</p></div>';
			}
		}

		// Template Tools Section
		$out .= '<hr class="uk-divider-icon">';
		$out .= $this->renderManualTemplateCreator();

		$out .= '</div>';
		return $out;
	}

	/**
	 * AJAX handler: Generate code
	 *
	 * @return void
	 */
	public function executeAjaxGenerate() {

    	// Always return JSON
    	header('Content-Type: application/json; charset=utf-8');
    
    	try {
    		// Must be POST
    		if(!$this->input->requestMethod('POST')) {
    			echo json_encode(['success' => false, 'error' => 'Invalid request method (POST required).']);
    			exit;
    		}
    
    		// Permission
    		if(!$this->user->hasPermission('processwire-studio')) {
    			echo json_encode(['success' => false, 'error' => 'Permission denied.']);
    			exit;
    		}
    
    		// CSRF
    		if(!$this->session->CSRF->validate()) {
    			echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
    			exit;
    		}
    
    		// Load generator class
    		$codeGenFile = __DIR__ . '/ProcesswireStudioCodeGenerator.php';
    		$fqcn = __NAMESPACE__ . '\\ProcesswireStudioCodeGenerator';
    
    		if(!class_exists($fqcn) && is_file($codeGenFile)) {
    			require_once($codeGenFile);
    		}
    		if(!class_exists($fqcn)) {
    			echo json_encode(['success' => false, 'error' => 'Code Generator class is not available (autoload/require failed).']);
    			exit;
    		}
    
    		$templateId = (int) $this->input->post('template_id');
    		if(!$templateId) {
    			// Some older JS might post 'tpl' instead:
    			$templateId = (int) $this->input->post('tpl');
    		}
    
    		$selectedFields = $this->input->post('fields');
    		if(!is_array($selectedFields)) $selectedFields = [];
    
    		$selectedFields = array_map([$this->sanitizer, 'fieldName'], $selectedFields);
    
    		$generator = $this->wire(new ProcesswireStudioCodeGenerator());
    		$code = $generator->generateCode($templateId, $selectedFields);
    
    		echo json_encode(['success' => true, 'code' => $code]);
    		exit;
    
    	} catch(\Throwable $e) {
    		// Return real error instead of "no content"
    		$this->log->save('processwire-studio', 'AJAX generate error: ' . $e->getMessage());
    
    		echo json_encode([
    			'success' => false,
    			'error' => $e->getMessage(),
    			'file' => basename($e->getFile()),
    			'line' => $e->getLine()
    		]);
    		exit;
    	}
    }

	/**
	 * AJAX handler: Minify CSS/JS files
	 *
	 * @return void (exits with JSON)
	 */
	public function executeAjaxMinify() {
		header('Content-Type: application/json; charset=utf-8');
		
		try {
			// Must be POST
			if(!$this->input->requestMethod('POST')) {
				echo json_encode(['success' => false, 'error' => 'Invalid request method (POST required).']);
				exit;
			}
			
			// Permission
			if(!$this->user->hasPermission('processwire-studio')) {
				echo json_encode(['success' => false, 'error' => 'Permission denied.']);
				exit;
			}
			
			// CSRF
			if(!$this->session->CSRF->validate()) {
				echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
				exit;
			}
			
			// Load minifier class
			$minifierFile = __DIR__ . '/ProcesswireStudioMinifier.php';
			$fqcn = __NAMESPACE__ . '\\ProcesswireStudioMinifier';
			
			if(!class_exists($fqcn) && is_file($minifierFile)) {
				require_once($minifierFile);
			}
			if(!class_exists($fqcn)) {
				echo json_encode(['success' => false, 'error' => 'Minifier class is not available.']);
				exit;
			}
			
			$minifier = $this->wire(new ProcesswireStudioMinifier());
			
			$type = $this->sanitizer->name((string) $this->input->post('type')); // 'css' or 'js'
			$file = $this->sanitizer->filename((string) $this->input->post('file'));
			
			if(!in_array($type, ['css', 'js'], true)) {
				echo json_encode(['success' => false, 'error' => 'Invalid file type.']);
				exit;
			}
			
			if(!$file) {
				echo json_encode(['success' => false, 'error' => 'No file specified.']);
				exit;
			}
			
			// Build full path
			$config = $this->wire('config');
			$basePath = ($type === 'css') ? $config->paths->templates . 'styles/' : $config->paths->templates . 'scripts/';
			$sourcePath = $basePath . $file;
			
			// Security: ensure file is within templates directory
			$realSourcePath = realpath($sourcePath);
			$realBasePath = realpath($basePath);
			if(!$realSourcePath || strpos($realSourcePath, $realBasePath) !== 0) {
				echo json_encode(['success' => false, 'error' => 'Invalid file path.']);
				exit;
			}
			
			// Minify
			if($type === 'css') {
				$result = $minifier->minifyCss($sourcePath);
			} else {
				$result = $minifier->minifyJs($sourcePath);
			}
			
			echo json_encode($result);
			exit;
			
		} catch(\Throwable $e) {
			$this->log->save('processwire-studio', 'AJAX minify error: ' . $e->getMessage());
			
			echo json_encode([
				'success' => false,
				'error' => $e->getMessage(),
				'file' => basename($e->getFile()),
				'line' => $e->getLine()
			]);
			exit;
		}
	}

	/**
	 * Tab handler: Patterns
	 *
	 * @return string
	 */
	public function executePatterns() {
		return $this->sanitizer->entities($this->_('Pattern Library coming soon...'));
	}

	/**
	 * Tab handler: Field Analyzer
	 *
	 * @return string
	 */
	public function executeFieldAnalyzer() {
		return $this->sanitizer->entities($this->_('Field Analyzer coming soon...'));
	}

	/**
	 * Tab handler: SEO & Assets
	 *
	 * @return string
	 */
	public function executeSEO() {
		$out = '<div class="pw-studio-seo">';
		
		// Minify Tool
		$out .= $this->renderMinifyTool();
		
		// Hier können später weitere SEO-Tools hinzugefügt werden
		// z.B. Meta-Tag Generator, Sitemap Tools, etc.
		
		$out .= '</div>';
		return $out;
	}

	/**
	 * Tab handler: Data Page Lister
	 *
	 * @return string
	 */
	public function executeDataPageLister() {
		$csrf = $this->session->CSRF;

		// Process form submission
		if($this->input->post('submit_dpl_settings')) {
			$this->saveDataPageListerSettings();
		}

		$out = '<div class="pw-studio-dpl">';

		$out .= '<div class="uk-card uk-card-default uk-card-body uk-margin-bottom">';
		$out .= '<h3 class="uk-card-title">' . $this->sanitizer->entities($this->_('Data Page Lister Konfiguration')) . '</h3>';
		$out .= '<p class="uk-text-muted">' . $this->sanitizer->entities($this->_('Wähle die Templates aus, für deren Container-Seiten die tabellarische Übersicht aktiviert werden soll. Nur Administratoren können diese Einstellungen ändern.')) . '</p>';

		$out .= '<form method="post" action="./?tab=data-page-lister" class="uk-form-stacked">';
		$out .= $this->renderCsrfInput();

		// Template-Auswahl (Multi-Select)
		$out .= '<div class="uk-margin">';
		$out .= '<label class="uk-form-label" for="dpl_templates">' . $this->sanitizer->entities($this->_('Aktivierte Templates')) . '</label>';
		$out .= '<div class="uk-form-controls">';
		$out .= '<select name="dpl_templates[]" id="dpl_templates" class="uk-select" multiple size="10">';

		$templates = $this->wire('templates');
		$enabledTemplates = $this->dataPageListerTemplates ?? [];

		foreach($templates as $tpl) {
			if($tpl->flags & Template::flagSystem) continue;
			$selected = in_array($tpl->name, $enabledTemplates, true) ? 'selected' : '';
			$label = $tpl->label ?: $tpl->name;
			$out .= '<option value="' . $this->sanitizer->entities($tpl->name) . '" ' . $selected . '>';
			$out .= $this->sanitizer->entities($label) . ' (' . $this->sanitizer->entities($tpl->name) . ')';
			$out .= '</option>';
		}

		$out .= '</select>';
		$out .= '<div class="uk-text-meta uk-margin-small-top">';
		$out .= $this->sanitizer->entities($this->_('Halte Strg/Cmd gedrückt, um mehrere Templates auszuwählen.'));
		$out .= '</div>';
		$out .= '</div>';
		$out .= '</div>';

		// Globale Einstellungen
		$out .= '<div class="uk-margin">';
		$out .= '<label class="uk-form-label" for="dpl_page_size">' . $this->sanitizer->entities($this->_('Seitengröße (Pagination)')) . '</label>';
		$out .= '<div class="uk-form-controls">';
		$out .= '<input class="uk-input uk-width-small" type="number" name="dpl_page_size" id="dpl_page_size" value="' . (int)$this->dataPageListerPageSize . '" min="10" max="200">';
		$out .= '</div>';
		$out .= '</div>';

		$out .= '<div class="uk-margin">';
		$out .= '<label><input class="uk-checkbox" type="checkbox" name="dpl_show_help" value="1" ' . ($this->dataPageListerShowHelp ? 'checked' : '') . '> ';
		$out .= $this->sanitizer->entities($this->_('Hilfe-Hinweis anzeigen')) . '</label>';
		$out .= '</div>';

		$out .= '<div class="uk-margin">';
		$out .= '<label><input class="uk-checkbox" type="checkbox" name="dpl_hide_children" value="1" ' . ($this->dataPageListerHideChildren ? 'checked' : '') . '> ';
		$out .= $this->sanitizer->entities($this->_('Kinder im Seitenbaum ausblenden')) . '</label>';
		$out .= '</div>';

		$out .= '<div class="uk-margin">';
		$out .= '<label><input class="uk-checkbox" type="checkbox" name="dpl_rename_edit" value="1" ' . ($this->dataPageListerRenameEdit ? 'checked' : '') . '> ';
		$out .= $this->sanitizer->entities($this->_('Im Seitenbaum „Bearbeiten" → „Tabelle" umbenennen')) . '</label>';
		$out .= '</div>';

		$out .= '<div class="uk-margin">';
		$out .= '<label><input class="uk-checkbox" type="checkbox" name="dpl_show_view" value="1" ' . ($this->dataPageListerShowView ? 'checked' : '') . '> ';
		$out .= $this->sanitizer->entities($this->_('„Ansehen"-Button in Aktionen anzeigen')) . '</label>';
		$out .= '</div>';

		// Template-spezifische Konfigurationen
		$out .= '<hr class="uk-divider-icon">';
		$out .= '<h4>' . $this->sanitizer->entities($this->_('Template-spezifische Einstellungen')) . '</h4>';
		$out .= '<p class="uk-text-muted">' . $this->sanitizer->entities($this->_('Für jedes aktivierte Template können individuelle Einstellungen vorgenommen werden.')) . '</p>';

		$configs = $this->dataPageListerConfigs ?? [];
		foreach($enabledTemplates as $tplName) {
			$tpl = $templates->get($tplName);
			if(!$tpl || !$tpl->id) continue;

			$tplConfig = $configs[$tplName] ?? [];
			$mode = $tplConfig['mode'] ?? 'auto';
			$numFields = isset($tplConfig['numFields']) ? (int)$tplConfig['numFields'] : 5;
			$fieldSelectionMode = $tplConfig['fieldSelectionMode'] ?? 'firstN';
			$fields = $tplConfig['fields'] ?? '';

			$out .= '<div class="uk-card uk-card-secondary uk-card-body uk-margin-small">';
			$out .= '<h5>' . $this->sanitizer->entities($tpl->label ?: $tpl->name) . '</h5>';

			$out .= '<div class="uk-margin-small">';
			$out .= '<label class="uk-form-label">' . $this->sanitizer->entities($this->_('Feldauswahl-Modus')) . '</label>';
			$out .= '<div class="uk-form-controls">';
			$out .= '<select name="dpl_config[' . $this->sanitizer->entities($tplName) . '][mode]" class="uk-select uk-width-medium">';
			$out .= '<option value="auto" ' . ($mode === 'auto' ? 'selected' : '') . '>' . $this->sanitizer->entities($this->_('Automatisch (erste N Felder)')) . '</option>';
			$out .= '<option value="manual" ' . ($mode === 'manual' ? 'selected' : '') . '>' . $this->sanitizer->entities($this->_('Manuell (Felder auflisten)')) . '</option>';
			$out .= '</select>';
			$out .= '</div>';
			$out .= '</div>';

			$out .= '<div class="uk-margin-small" data-show-if="dpl_config[' . $this->sanitizer->entities($tplName) . '][mode]=auto">';
			$out .= '<label class="uk-form-label">' . $this->sanitizer->entities($this->_('Anzahl Felder')) . '</label>';
			$out .= '<div class="uk-form-controls">';
			$out .= '<input class="uk-input uk-width-small" type="number" name="dpl_config[' . $this->sanitizer->entities($tplName) . '][numFields]" value="' . $numFields . '" min="1" max="20">';
			$out .= '</div>';
			$out .= '</div>';

			$out .= '<div class="uk-margin-small" data-show-if="dpl_config[' . $this->sanitizer->entities($tplName) . '][mode]=auto">';
			$out .= '<label class="uk-form-label">' . $this->sanitizer->entities($this->_('Auswahl-Modus')) . '</label>';
			$out .= '<div class="uk-form-controls">';
			$out .= '<select name="dpl_config[' . $this->sanitizer->entities($tplName) . '][fieldSelectionMode]" class="uk-select uk-width-medium">';
			$out .= '<option value="firstN" ' . ($fieldSelectionMode === 'firstN' ? 'selected' : '') . '>' . $this->sanitizer->entities($this->_('Erste N Felder')) . '</option>';
			$out .= '<option value="common" ' . ($fieldSelectionMode === 'common' ? 'selected' : '') . '>' . $this->sanitizer->entities($this->_('Schnittmenge aller Kind-Templates')) . '</option>';
			$out .= '</select>';
			$out .= '</div>';
			$out .= '</div>';

			$out .= '<div class="uk-margin-small" data-show-if="dpl_config[' . $this->sanitizer->entities($tplName) . '][mode]=manual">';
			$out .= '<label class="uk-form-label">' . $this->sanitizer->entities($this->_('Felder (komma-separiert)')) . '</label>';
			$out .= '<div class="uk-form-controls">';
			$out .= '<textarea class="uk-textarea" name="dpl_config[' . $this->sanitizer->entities($tplName) . '][fields]" rows="3" placeholder="date, summary, author, tags">' . $this->sanitizer->entities($fields) . '</textarea>';
			$out .= '<div class="uk-text-meta">' . $this->sanitizer->entities($this->_('Liste der anzuzeigenden Feldnamen, z.B.: date, summary, author, tags')) . '</div>';
			$out .= '</div>';
			$out .= '</div>';

			$out .= '</div>';
		}

		$out .= '<div class="uk-margin-top">';
		$out .= '<button type="submit" name="submit_dpl_settings" value="1" class="uk-button uk-button-primary">';
		$out .= $this->sanitizer->entities($this->_('Einstellungen speichern'));
		$out .= '</button>';
		$out .= '</div>';

		$out .= '</form>';
		$out .= '</div>';

		$out .= '</div>';

		return $out;
	}

	/**
	 * Speichert DataPageLister-Einstellungen
	 */
	protected function saveDataPageListerSettings() {
		if(!$this->session->CSRF->validate()) {
			$this->error($this->_('Invalid CSRF token.'));
			return;
		}

		$templates = $this->input->post('dpl_templates');
		if(!is_array($templates)) $templates = [];
		$templates = array_map([$this->sanitizer, 'name'], $templates);

		$this->dataPageListerTemplates = $templates;
		$this->dataPageListerPageSize = (int) $this->input->post('dpl_page_size');
		if($this->dataPageListerPageSize < 10) $this->dataPageListerPageSize = 10;
		if($this->dataPageListerPageSize > 200) $this->dataPageListerPageSize = 200;

		$this->dataPageListerShowHelp = (bool) $this->input->post('dpl_show_help');
		$this->dataPageListerHideChildren = (bool) $this->input->post('dpl_hide_children');
		$this->dataPageListerRenameEdit = (bool) $this->input->post('dpl_rename_edit');
		$this->dataPageListerShowView = (bool) $this->input->post('dpl_show_view');

		// Template-spezifische Konfigurationen
		$configs = $this->input->post('dpl_config');
		$savedConfigs = [];
		if(is_array($configs)) {
			foreach($configs as $tplName => $config) {
				$tplName = $this->sanitizer->name($tplName);
				if(!in_array($tplName, $templates, true)) continue;

				$savedConfigs[$tplName] = [
					'mode' => $this->sanitizer->name($config['mode'] ?? 'auto'),
					'numFields' => isset($config['numFields']) ? (int)$config['numFields'] : 5,
					'fieldSelectionMode' => $this->sanitizer->name($config['fieldSelectionMode'] ?? 'firstN'),
					'fields' => isset($config['fields']) ? $this->sanitizer->text($config['fields']) : ''
				];
			}
		}
		$this->dataPageListerConfigs = $savedConfigs;

		$data = [
			'enableMinification' => $this->enableMinification,
			'autoCreateTemplateFiles' => $this->autoCreateTemplateFiles,
			'templateFileBackup' => $this->templateFileBackup,
			'templateSkeletonType' => $this->templateSkeletonType,
			'templateIncludeHead' => $this->templateIncludeHead,
			'templateRegions' => $this->templateRegions,
			'dataPageListerTemplates' => $this->dataPageListerTemplates,
			'dataPageListerConfigs' => $this->dataPageListerConfigs,
			'dataPageListerPageSize' => $this->dataPageListerPageSize,
			'dataPageListerShowHelp' => $this->dataPageListerShowHelp,
			'dataPageListerHideChildren' => $this->dataPageListerHideChildren,
			'dataPageListerRenameEdit' => $this->dataPageListerRenameEdit,
			'dataPageListerShowView' => $this->dataPageListerShowView,
		];

		$this->wire('modules')->saveConfig($this, $data);
		$this->message($this->_('Data Page Lister Einstellungen gespeichert.'));
		$this->loadSettings();
	}

	/**
	 * Tab handler: Settings (refactored with InputfieldWrapper)
	 *
	 * @return string
	 */
	public function executeSettings() {
		// Process settings form submission
		if($this->input->post('submit_settings')) {
			$this->saveSettings();
		}

		// Build form using InputfieldWrapper (ProcessWire Best Practice)
		$form = $this->modules->get('InputfieldForm');
		$form->method = 'post';
		$form->action = './?tab=settings';
		$form->attr('class', 'uk-form-stacked');

		// Section: Minification Output
		$fieldset = $this->modules->get('InputfieldFieldset');
		$fieldset->label = $this->_('CSS/JS Minification Output');
		$fieldset->collapsed = Inputfield::collapsedNever;

		$f = $this->modules->get('InputfieldCheckbox');
		$f->name = 'enableMinification';
		$f->label = $this->_('Use minified CSS/JS files in frontend output');
		$f->description = $this->_('When enabled, minified versions (.min.css, .min.js) will be loaded instead of original files. Make sure to minify your files first using the SEO & Assets tab.');
		$f->checked = $this->enableMinification ? '1' : '';
		$fieldset->add($f);

		$form->add($fieldset);

		// Section: Template File Management
		$fieldset2 = $this->modules->get('InputfieldFieldset');
		$fieldset2->label = $this->_('Template File Management');
		$fieldset2->collapsed = Inputfield::collapsedNever;

		$f = $this->modules->get('InputfieldCheckbox');
		$f->name = 'autoCreateTemplateFiles';
		$f->label = $this->_('Auto-create template files when new templates are created');
		$f->description = $this->_('Automatically generates a PHP file in /site/templates/ when you create a new template.');
		$f->checked = $this->autoCreateTemplateFiles ? '1' : '';
		$fieldset2->add($f);

		$f = $this->modules->get('InputfieldCheckbox');
		$f->name = 'templateFileBackup';
		$f->label = $this->_('Create backup before overwriting existing files');
		$f->checked = $this->templateFileBackup ? '1' : '';
		$fieldset2->add($f);

		$f = $this->modules->get('InputfieldSelect');
		$f->name = 'templateSkeletonType';
		$f->label = $this->_('Skeleton Type');
		$f->addOption('minimal', $this->_('Minimal (basic PHP structure)'));
		$f->addOption('basic', $this->_('Basic (with common sections)'));
		$f->addOption('uikit', $this->_('UIkit (full layout with UIkit classes)'));
		$f->addOption('markup-regions', $this->_('Markup Regions (optimized for Markup Regions)'));
		$f->value = $this->templateSkeletonType;
		$fieldset2->add($f);

		$f = $this->modules->get('InputfieldCheckbox');
		$f->name = 'templateIncludeHead';
		$f->label = $this->_('Include HTML head section');
		$f->description = $this->_('When enabled, the generated template will include a complete HTML head section. Disable this if you use Markup Regions with _main.php.');
		$f->checked = $this->templateIncludeHead ? '1' : '';
		$fieldset2->add($f);

		// Regions checkboxes
		$f = $this->modules->get('InputfieldCheckboxes');
		$f->name = 'templateRegions';
		$f->label = $this->_('Additional Regions');
		$f->description = $this->_('Select additional regions to include in generated templates. The main region is always included.');
		$f->addOption('header', $this->_('Header'));
		$f->addOption('sidebar', $this->_('Sidebar'));
		$f->addOption('footer', $this->_('Footer'));
		$f->value = (array)$this->templateRegions;
		$fieldset2->add($f);

		$form->add($fieldset2);

		// Submit button
		$submit = $this->modules->get('InputfieldSubmit');
		$submit->name = 'submit_settings';
		$submit->value = $this->_('Save Settings');
		$submit->attr('class', 'uk-button uk-button-primary');
		$form->add($submit);

		// Render form
		$out = '<div class="pw-studio-settings">';
		$out .= '<div class="uk-card uk-card-default uk-card-body uk-margin">';
		$out .= '<h3 class="uk-card-title">' . $this->sanitizer->entities($this->_('Development Settings')) . '</h3>';
		$out .= $form->render();
		$out .= '</div>';
		$out .= '</div>';

		return $out;
	}

	/**
	 * Render CSS/JS minification tool
	 *
	 * @return string
	 */
	protected function renderMinifyTool() {
		// Load minifier to get file list
		$minifierFile = __DIR__ . '/ProcesswireStudioMinifier.php';
		$fqcn = __NAMESPACE__ . '\\ProcesswireStudioMinifier';
		
		if(!class_exists($fqcn) && is_file($minifierFile)) {
			require_once($minifierFile);
		}
		
		$assets = ['css' => [], 'js' => []];
		if(class_exists($fqcn)) {
			$minifier = $this->wire(new ProcesswireStudioMinifier());
			$assets = $minifier->getAssetFiles();
		}
		
		$csrf = $this->session->CSRF;
		$tokenName = $csrf->getTokenName();
		$tokenValue = $csrf->getTokenValue();
		
		$out = '<div class="uk-card uk-card-default uk-card-body uk-margin">';
		$out .= '<h3 class="uk-card-title">' . $this->sanitizer->entities($this->_('CSS/JS Minification Tool')) . '</h3>';
		$out .= '<p>' . $this->sanitizer->entities($this->_('Minify your CSS and JavaScript files. Minified versions will be saved as .min.css and .min.js files.')) . '</p>';
		
		// CSS Files
		if(!empty($assets['css'])) {
			$out .= '<div class="uk-margin">';
			$out .= '<h4>' . $this->sanitizer->entities($this->_('CSS Files')) . '</h4>';
			$out .= '<div class="uk-overflow-auto">';
			$out .= '<table class="uk-table uk-table-small uk-table-divider">';
			$out .= '<thead><tr>';
			$out .= '<th>' . $this->sanitizer->entities($this->_('File')) . '</th>';
			$out .= '<th>' . $this->sanitizer->entities($this->_('Size')) . '</th>';
			$out .= '<th>' . $this->sanitizer->entities($this->_('Status')) . '</th>';
			$out .= '<th>' . $this->sanitizer->entities($this->_('Action')) . '</th>';
			$out .= '</tr></thead><tbody>';
			
			foreach($assets['css'] as $file) {
				$out .= '<tr>';
				$out .= '<td><code>' . $this->sanitizer->entities($file['name']) . '</code></td>';
				$out .= '<td>' . $this->sanitizer->entities($minifier->formatFileSize($file['size'])) . '</td>';
				$out .= '<td>';
				if($file['minified']) {
					$out .= '<span class="uk-label uk-label-success">' . $this->sanitizer->entities($this->_('Minified')) . '</span>';
				} else {
					$out .= '<span class="uk-label">' . $this->sanitizer->entities($this->_('Not minified')) . '</span>';
				}
				$out .= '</td>';
				$out .= '<td>';
				$out .= '<button type="button" class="uk-button uk-button-small uk-button-default pw-minify-btn" ';
				$out .= 'data-type="css" data-file="' . $this->sanitizer->entities($file['name']) . '">';
				$out .= $this->sanitizer->entities($this->_('Minify'));
				$out .= '</button>';
				$out .= '</td>';
				$out .= '</tr>';
			}
			
			$out .= '</tbody></table>';
			$out .= '</div>';
			$out .= '</div>';
		} else {
			$out .= '<div class="uk-margin">';
			$out .= '<p class="uk-text-muted">' . $this->sanitizer->entities($this->_('No CSS files found in /site/templates/styles/')) . '</p>';
			$out .= '</div>';
		}
		
		// JS Files
		if(!empty($assets['js'])) {
			$out .= '<div class="uk-margin">';
			$out .= '<h4>' . $this->sanitizer->entities($this->_('JavaScript Files')) . '</h4>';
			$out .= '<div class="uk-overflow-auto">';
			$out .= '<table class="uk-table uk-table-small uk-table-divider">';
			$out .= '<thead><tr>';
			$out .= '<th>' . $this->sanitizer->entities($this->_('File')) . '</th>';
			$out .= '<th>' . $this->sanitizer->entities($this->_('Size')) . '</th>';
			$out .= '<th>' . $this->sanitizer->entities($this->_('Status')) . '</th>';
			$out .= '<th>' . $this->sanitizer->entities($this->_('Action')) . '</th>';
			$out .= '</tr></thead><tbody>';
			
			foreach($assets['js'] as $file) {
				$out .= '<tr>';
				$out .= '<td><code>' . $this->sanitizer->entities($file['name']) . '</code></td>';
				$out .= '<td>' . $this->sanitizer->entities($minifier->formatFileSize($file['size'])) . '</td>';
				$out .= '<td>';
				if($file['minified']) {
					$out .= '<span class="uk-label uk-label-success">' . $this->sanitizer->entities($this->_('Minified')) . '</span>';
				} else {
					$out .= '<span class="uk-label">' . $this->sanitizer->entities($this->_('Not minified')) . '</span>';
				}
				$out .= '</td>';
				$out .= '<td>';
				$out .= '<button type="button" class="uk-button uk-button-small uk-button-default pw-minify-btn" ';
				$out .= 'data-type="js" data-file="' . $this->sanitizer->entities($file['name']) . '">';
				$out .= $this->sanitizer->entities($this->_('Minify'));
				$out .= '</button>';
				$out .= '</td>';
				$out .= '</tr>';
			}
			
			$out .= '</tbody></table>';
			$out .= '</div>';
			$out .= '</div>';
		} else {
			$out .= '<div class="uk-margin">';
			$out .= '<p class="uk-text-muted">' . $this->sanitizer->entities($this->_('No JavaScript files found in /site/templates/scripts/')) . '</p>';
			$out .= '</div>';
		}
		
		// Code example
		$out .= '<hr class="uk-divider-small">';
		$out .= '<div class="uk-margin">';
		$out .= '<h4>' . $this->sanitizer->entities($this->_('Integration in _main.php')) . '</h4>';
		$out .= '<p class="uk-text-meta">' . $this->sanitizer->entities($this->_('Use this code in your _main.php to conditionally load minified files:')) . '</p>';
		$out .= '<pre class="uk-background-muted uk-padding-small"><code class="language-php">';
		$out .= htmlspecialchars('<?php
// CSS - Check if minification is enabled and minified file exists
$studio = $modules->get(\'ProcesswireStudio\');
$useMinified = $studio && $studio->enableMinification && file_exists($config->paths->templates . \'styles/main.min.css\');
$cssFile = $useMinified ? \'styles/main.min.css\' : \'styles/main.css\';
?><link rel="stylesheet" type="text/css" href="<?php echo $config->urls->templates . $cssFile; ?>" />

<?php
// JavaScript - Check if minification is enabled and minified file exists
$useMinified = $studio && $studio->enableMinification && file_exists($config->paths->templates . \'scripts/main.min.js\');
$jsFile = $useMinified ? \'scripts/main.min.js\' : \'scripts/main.js\';
?><script src="<?php echo $config->urls->templates . $jsFile; ?>"></script>', ENT_QUOTES, 'UTF-8');
		$out .= '</code></pre>';
		$out .= '</div>';
		
		// CSRF token for AJAX
		$out .= '<input type="hidden" id="pw-minify-csrf-name" value="' . $this->sanitizer->entities($tokenName) . '">';
		$out .= '<input type="hidden" id="pw-minify-csrf-value" value="' . $this->sanitizer->entities($tokenValue) . '">';
		
		$out .= '</div>'; // .uk-card
		
		return $out;
	}

	/**
	 * Render manual template file creation tool
	 *
	 * @return string
	 */
	protected function renderManualTemplateCreator() {
		$out = '<div class="uk-card uk-card-default uk-card-body uk-margin">';
		$out .= '<h3 class="uk-card-title">' . $this->sanitizer->entities($this->_('Manual Template File Creation')) . '</h3>';
		$out .= '<p>' . $this->sanitizer->entities($this->_('Create template files manually for existing templates.')) . '</p>';

		$out .= '<form method="post" action="./?tab=code-generator" class="uk-form-stacked">';
		$out .= $this->renderCsrfInput();
		$out .= '<input type="hidden" name="id" value="' . (int) $this->page->id . '">';

		$out .= '<div class="uk-margin">';
		$out .= '<label class="uk-form-label">' . $this->sanitizer->entities($this->_('Select Template')) . '</label>';
		$out .= '<div class="uk-form-controls uk-flex uk-flex-middle" style="gap:10px;">';
		$out .= '<select name="create_template_file" class="uk-select uk-width-expand">';
		$out .= '<option value="">' . $this->sanitizer->entities($this->_('Choose a template...')) . '</option>';

		$settingsFile = __DIR__ . '/ProcesswireStudioSettings.php';
		if(is_file($settingsFile)) {
			require_once($settingsFile);
			$settings = $this->wire(new ProcesswireStudioSettings());
			$templatesWithoutFiles = $settings->getTemplatesWithoutFiles();

			foreach($templatesWithoutFiles as $tpl) {
				$label = $tpl->label ?: $tpl->name;
				$out .= '<option value="' . (int) $tpl->id . '">' . $this->sanitizer->entities($label) . ' (' . $this->sanitizer->entities($tpl->name) . ')</option>';
			}
		} else {
			$out .= '<option value="">' . $this->sanitizer->entities($this->_('Settings class missing.')) . '</option>';
		}

		$out .= '</select>';
		$out .= '<button type="submit" name="submit_create_file" value="1" class="uk-button uk-button-default">';
		$out .= $this->sanitizer->entities($this->_('Create File'));
		$out .= '</button>';
		$out .= '</div>';
		$out .= '</div>';

		$out .= '</form>';
		$out .= '</div>';

		return $out;
	}

	/**
	 * Save settings from form into module config
	 * Optimized: Only saves own settings, preserves others from current config
	 */
	protected function saveSettings() {
		if(!$this->session->CSRF->validate()) {
			$this->error($this->_('Invalid CSRF token.'));
			return;
		}

		// Get current config to preserve other settings
		$currentConfig = $this->wire('modules')->getConfig($this);
		if(!is_array($currentConfig)) $currentConfig = [];

		// Update only our own settings
		$currentConfig['enableMinification'] = (bool) $this->input->post('enableMinification');
		$currentConfig['autoCreateTemplateFiles'] = (bool) $this->input->post('autoCreateTemplateFiles');
		$currentConfig['templateFileBackup'] = (bool) $this->input->post('templateFileBackup');

		$type = (string) $this->input->post('templateSkeletonType');
		$type = $this->sanitizer->name($type);
		if(!in_array($type, ['minimal', 'basic', 'uikit', 'markup-regions'], true)) $type = 'basic';
		$currentConfig['templateSkeletonType'] = $type;

		$currentConfig['templateIncludeHead'] = (bool) $this->input->post('templateIncludeHead');

		// Process regions checkboxes (InputfieldCheckboxes returns array)
		$regions = $this->input->post('templateRegions');
		if(!is_array($regions)) $regions = [];
		$regions = array_map([$this->sanitizer, 'name'], $regions);
		$regions = array_intersect($regions, ['header', 'sidebar', 'footer']); // Security: only allow valid regions
		$currentConfig['templateRegions'] = $regions;

		// Save config
		$this->wire('modules')->saveConfig($this, $currentConfig);
		$this->message($this->_('Settings saved successfully.'));

		// Update hook runtime behavior for current request
		$this->loadSettings();
	}

	/**
	 * Render CSRF hidden input for forms
	 *
	 * @return string
	 */
	protected function renderCsrfInput() {
		$csrf = $this->session->CSRF;
		$tokenName = $csrf->getTokenName();
		$tokenValue = $csrf->getTokenValue();

		return '<input type="hidden" name="' . $this->sanitizer->entities($tokenName) . '" value="' . $this->sanitizer->entities($tokenValue) . '">';
	}

	/**
	 * Provide module configuration inputfields (required for ConfigurableModule).
	 *
	 * @param array $data Current module config data
	 * @return InputfieldWrapper
	 */
	public function getModuleConfigInputfields(array $data) {
		$inputfields = new InputfieldWrapper();

		$info = $this->modules->get('InputfieldMarkup');
		$info->label = $this->_('Configuration');
		$info->value = '<p>' . $this->sanitizer->entities($this->_('Settings are managed in the Settings tab inside ProcessWire Studio.')) . '</p>';
		$inputfields->add($info);

		return $inputfields;
	}

	/**
	 * Install routine (create admin page + permission)
	 *
	 * @return void
	 */
	public function ___install() {
		$pages = $this->wire('pages');
		$config = $this->wire('config');
		$permissions = $this->wire('permissions');

		$perm = $permissions->get('processwire-studio');
		if(!$perm || !$perm->id) {
			$perm = new Permission();
			$perm->name = 'processwire-studio';
			$perm->title = 'ProcessWire Studio';
			$perm->save();
		}

		$page = $pages->get("template=admin, name=processwire-studio");
		if(!$page->id) {
			$page = new Page();
			$page->template = 'admin';
			$page->parent = $pages->get($config->adminRootPageID)->child('name=setup');
			$page->title = 'ProcessWire Studio';
			$page->name = 'processwire-studio';
			$page->process = $this;
			$page->save();
		}

		$this->message($this->_('ProcessWire Studio installed successfully.'));
		$this->log->save('processwire-studio', 'Installed module and created admin page/permission.');
	}

	/**
	 * Uninstall routine (remove admin page + permission)
	 *
	 * @return void
	 */
	public function ___uninstall() {
		$pages = $this->wire('pages');
		$permissions = $this->wire('permissions');

		$page = $pages->get("template=admin, name=processwire-studio");
		if($page->id) {
			$page->delete();
		}

		$perm = $permissions->get('processwire-studio');
		if($perm && $perm->id) {
			$perm->delete();
		}

		$this->message($this->_('ProcessWire Studio uninstalled successfully.'));
		$this->log->save('processwire-studio', 'Uninstalled module and removed admin page/permission.');
	}
}
