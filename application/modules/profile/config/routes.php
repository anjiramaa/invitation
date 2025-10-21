<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Profile module routes
 * Version: 2025-10-15_v1
 */

$route['profile'] = 'profile/index';
$route['profile/change-password'] = 'profile/change_password';
$route['profile/api/change-password'] = 'profile/api_change_password';
