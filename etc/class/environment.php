<?php
// include and require should find files in this directory and its banks subdirectory
set_include_path(__DIR__ . ':' . __DIR__ . '/banks');

// PHP should treat strings as UTF8
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

class Env {
	public static string $DocRoot;

	public static function Initialize() {
		// CONTEXT_DOCUMENT_ROOT is set when an alias or similar is used, which makes
		// DOCUMENT_ROOT incorrect for this purpose.  assume the presence of an alias
		// means we're one level deep.
		self::$DocRoot = isset($_SERVER['CONTEXT_PREFIX']) && isset($_SERVER['CONTEXT_DOCUMENT_ROOT']) && $_SERVER['CONTEXT_PREFIX'] ? dirname($_SERVER['CONTEXT_DOCUMENT_ROOT']) : $_SERVER['DOCUMENT_ROOT'];
	}
}
