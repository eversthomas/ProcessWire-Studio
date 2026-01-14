<?php
/**
 * Autoloader for MatthiasMullie\Minify library
 * 
 * Loads all necessary classes in correct order
 */

// Base Exception (deprecated but still used)
require_once __DIR__ . '/matthiasmullie/minify/src/Exception.php';

// Exception classes (must be loaded before classes that use them)
require_once __DIR__ . '/matthiasmullie/minify/src/Exceptions/BasicException.php';
require_once __DIR__ . '/matthiasmullie/minify/src/Exceptions/FileImportException.php';
require_once __DIR__ . '/matthiasmullie/minify/src/Exceptions/IOException.php';
require_once __DIR__ . '/matthiasmullie/minify/src/Exceptions/PatternMatchException.php';

// Path Converter Interface (must be loaded before Converter)
require_once __DIR__ . '/matthiasmullie/path-converter/src/ConverterInterface.php';
require_once __DIR__ . '/matthiasmullie/path-converter/src/NoConverter.php';
require_once __DIR__ . '/matthiasmullie/path-converter/src/Converter.php';

// Minify base class
require_once __DIR__ . '/matthiasmullie/minify/src/Minify.php';

// Minify implementations
require_once __DIR__ . '/matthiasmullie/minify/src/CSS.php';
require_once __DIR__ . '/matthiasmullie/minify/src/JS.php';