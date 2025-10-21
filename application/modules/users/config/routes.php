<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Module routes for users
 * Version: 2025-10-14_v1
 */

$route['users'] = 'users/index';
$route['users/create'] = 'users/create';
$route['users/edit/(:num)'] = 'users/edit/$1';
$route['users/delete/(:num)'] = 'users/delete/$1';

/* API routes */
$route['users/api/list'] = 'users/api_list';
$route['users/api/get/(:num)'] = 'users/api_get/$1';
$route['users/api/create'] = 'users/api_create';
$route['users/api/update/(:num)'] = 'users/api_update/$1';
$route['users/api/delete/(:num)'] = 'users/api_delete/$1';
