<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Module routes for auth
 * Version: 2025-10-14_v2
 */

$route['login'] = 'auth/login';
$route['logout'] = 'auth/logout';
$route['register'] = 'auth/register';

$route['auth/api/login'] = 'auth/api_login';
$route['auth/api/logout'] = 'auth/api_logout';
$route['auth/api/register'] = 'auth/api_register';

$route['auth/captcha_json'] = 'auth/captcha_json';
