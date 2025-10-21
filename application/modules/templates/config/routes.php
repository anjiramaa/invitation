<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * templates module routes
 * Version: 2025-10-18_v1
 */

$route['templates'] = 'templates/index';
$route['templates/create'] = 'templates/create';
$route['templates/upload-zip/(:num)'] = 'templates/upload_zip/$1';
$route['templates/edit/(:num)'] = 'templates/edit/$1';
$route['templates/delete/(:num)'] = 'templates/delete/$1';
$route['templates/preview/(:num)'] = 'templates/preview/$1';
$route['templates/download-asset/(:num)'] = 'templates/download_asset/$1';
