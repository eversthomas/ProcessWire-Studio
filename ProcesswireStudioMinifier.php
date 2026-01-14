<?php namespace ProcessWire;

/**
 * ProcessWire Studio Minifier
 * Handles CSS and JS file minification using MatthiasMullie\Minify or fallback
 */
class ProcesswireStudioMinifier extends Wire {

	/**
	 * Minify a CSS file
	 *
	 * @param string $sourcePath Full path to source CSS file
	 * @param string $targetPath Full path to target minified file (optional, auto-generated if not provided)
	 * @return array ['success' => bool, 'message' => string, 'target' => string]
	 */
	public function minifyCss($sourcePath, $targetPath = null) {
		if(!is_file($sourcePath)) {
			return [
				'success' => false,
				'message' => $this->_('Source file not found: ') . basename($sourcePath),
				'target' => ''
			];
		}

		// Auto-generate target path if not provided
		if($targetPath === null) {
			$pathInfo = pathinfo($sourcePath);
			$targetPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.min.' . $pathInfo['extension'];
		}

		// Try to use MatthiasMullie\Minify if available
		if($this->hasMinifyLibrary()) {
			try {
				$minifier = new \MatthiasMullie\Minify\CSS($sourcePath);
				$minifier->minify($targetPath);
				
				return [
					'success' => true,
					'message' => $this->_('CSS file minified successfully: ') . basename($targetPath),
					'target' => $targetPath
				];
			} catch(\Exception $e) {
				return [
					'success' => false,
					'message' => $this->_('Minification error: ') . $e->getMessage(),
					'target' => ''
				];
			}
		}

		// Fallback: Simple minification
		return $this->minifyCssFallback($sourcePath, $targetPath);
	}

	/**
	 * Minify a JavaScript file
	 *
	 * @param string $sourcePath Full path to source JS file
	 * @param string $targetPath Full path to target minified file (optional, auto-generated if not provided)
	 * @return array ['success' => bool, 'message' => string, 'target' => string]
	 */
	public function minifyJs($sourcePath, $targetPath = null) {
		if(!is_file($sourcePath)) {
			return [
				'success' => false,
				'message' => $this->_('Source file not found: ') . basename($sourcePath),
				'target' => ''
			];
		}

		// Auto-generate target path if not provided
		if($targetPath === null) {
			$pathInfo = pathinfo($sourcePath);
			$targetPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.min.' . $pathInfo['extension'];
		}

		// Try to use MatthiasMullie\Minify if available
		if($this->hasMinifyLibrary()) {
			try {
				$minifier = new \MatthiasMullie\Minify\JS($sourcePath);
				$minifier->minify($targetPath);
				
				return [
					'success' => true,
					'message' => $this->_('JavaScript file minified successfully: ') . basename($targetPath),
					'target' => $targetPath
				];
			} catch(\Exception $e) {
				return [
					'success' => false,
					'message' => $this->_('Minification error: ') . $e->getMessage(),
					'target' => ''
				];
			}
		}

		// Fallback: Simple minification
		return $this->minifyJsFallback($sourcePath, $targetPath);
	}

	/**
	 * Check if MatthiasMullie\Minify library is available
	 *
	 * @return bool
	 */
	protected function hasMinifyLibrary() {
		// Try to load the library
		$vendorPath = __DIR__ . '/vendor/autoload.php';
		if(is_file($vendorPath)) {
			require_once $vendorPath;
		}

		return class_exists('\MatthiasMullie\Minify\CSS') && class_exists('\MatthiasMullie\Minify\JS');
	}

	/**
	 * Fallback CSS minification (simple approach)
	 *
	 * @param string $sourcePath
	 * @param string $targetPath
	 * @return array
	 */
	protected function minifyCssFallback($sourcePath, $targetPath) {
		$css = file_get_contents($sourcePath);
		if($css === false) {
			return [
				'success' => false,
				'message' => $this->_('Could not read source file.'),
				'target' => ''
			];
		}

		// Remove comments (but preserve /*! ... */ comments)
		$css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
		
		// Remove whitespace
		$css = preg_replace('/\s+/', ' ', $css);
		
		// Remove spaces around specific characters
		$css = preg_replace('/\s*([{}:;,])\s*/', '$1', $css);
		
		// Remove trailing semicolons
		$css = preg_replace('/;}/', '}', $css);
		
		// Trim
		$css = trim($css);

		if(@file_put_contents($targetPath, $css) === false) {
			return [
				'success' => false,
				'message' => $this->_('Could not write minified file.'),
				'target' => ''
			];
		}

		return [
			'success' => true,
			'message' => $this->_('CSS file minified (fallback method): ') . basename($targetPath),
			'target' => $targetPath
		];
	}

	/**
	 * Fallback JavaScript minification (simple approach)
	 *
	 * @param string $sourcePath
	 * @param string $targetPath
	 * @return array
	 */
	protected function minifyJsFallback($sourcePath, $targetPath) {
		$js = file_get_contents($sourcePath);
		if($js === false) {
			return [
				'success' => false,
				'message' => $this->_('Could not read source file.'),
				'target' => ''
			];
		}

		// Remove single-line comments (but preserve //! comments)
		$js = preg_replace('~//[^!\n\r]*~', '', $js);
		
		// Remove multi-line comments (but preserve /*! ... */ comments)
		$js = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $js);
		
		// Remove whitespace
		$js = preg_replace('/\s+/', ' ', $js);
		
		// Remove spaces around specific characters (careful with regex)
		$js = preg_replace('/\s*([{}();,\[\]])\s*/', '$1', $js);
		
		// Trim
		$js = trim($js);

		if(@file_put_contents($targetPath, $js) === false) {
			return [
				'success' => false,
				'message' => $this->_('Could not write minified file.'),
				'target' => ''
			];
		}

		return [
			'success' => true,
			'message' => $this->_('JavaScript file minified (fallback method): ') . basename($targetPath),
			'target' => $targetPath
		];
	}

	/**
	 * Get all CSS and JS files in templates directory
	 *
	 * @return array ['css' => [], 'js' => []]
	 */
	public function getAssetFiles() {
		$config = $this->wire('config');
		$templatesPath = $config->paths->templates;
		
		$result = [
			'css' => [],
			'js' => []
		];

		// Find CSS files in styles/ directory
		$stylesPath = $templatesPath . 'styles/';
		if(is_dir($stylesPath)) {
			$files = glob($stylesPath . '*.css');
			foreach($files as $file) {
				// Skip already minified files
				if(strpos(basename($file), '.min.css') !== false) continue;
				
				$result['css'][] = [
					'path' => $file,
					'name' => basename($file),
					'size' => filesize($file),
					'minified' => is_file(str_replace('.css', '.min.css', $file)),
					'minified_path' => str_replace('.css', '.min.css', $file)
				];
			}
		}

		// Find JS files in scripts/ directory
		$scriptsPath = $templatesPath . 'scripts/';
		if(is_dir($scriptsPath)) {
			$files = glob($scriptsPath . '*.js');
			foreach($files as $file) {
				// Skip already minified files
				if(strpos(basename($file), '.min.js') !== false) continue;
				
				$result['js'][] = [
					'path' => $file,
					'name' => basename($file),
					'size' => filesize($file),
					'minified' => is_file(str_replace('.js', '.min.js', $file)),
					'minified_path' => str_replace('.js', '.min.js', $file)
				];
			}
		}

		return $result;
	}

	/**
	 * Format file size for display
	 *
	 * @param int $bytes
	 * @return string
	 */
	public function formatFileSize($bytes) {
		if($bytes >= 1048576) {
			return number_format($bytes / 1048576, 2) . ' MB';
		} elseif($bytes >= 1024) {
			return number_format($bytes / 1024, 2) . ' KB';
		}
		return $bytes . ' bytes';
	}
}
