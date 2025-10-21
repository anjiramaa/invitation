<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Addons module routes
 * Version: 2025-10-19_v1
 */

$route['addons'] = 'addons/index';
$route['addons/create'] = 'addons/create';
$route['addons/edit/(:num)'] = 'addons/edit/$1';
$route['addons/delete/(:num)'] = 'addons/delete/$1';
$route['addons/upload-snippet/(:num)'] = 'addons/upload_snippet/$1';
$route['addons/upload-assets/(:num)'] = 'addons/upload_assets/$1';
$route['addons/preview/(:num)'] = 'addons/preview/$1';

/**
 * API endpoint to get addon data for a template (used by templates preview)
 * e.g. GET addons/api/template/bela-agung
 */
$route['addons/api/template/(:any)'] = 'addons/api_get_by_template_slug/$1';
