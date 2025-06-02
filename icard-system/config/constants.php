<?php
/**
 * Application Constants
 * 
 * This file contains all the constant values used throughout the application.
 */

// User Roles
define('ROLE_ADMIN', 'admin');
define('ROLE_CONTROLLING_OFFICER', 'co');
define('ROLE_DEALER', 'dealer');
define('ROLE_AWO', 'awo');

// Employee Categories
define('EMP_CATEGORY_GAZETTED', 'gazetted');
define('EMP_CATEGORY_NON_GAZETTED', 'non_gazetted');

// Application Statuses
define('APP_STATUS_DRAFT', 'draft');
define('APP_STATUS_SUBMITTED', 'submitted');
define('APP_STATUS_CO_PENDING', 'co_pending');
define('APP_STATUS_DEALER_PENDING', 'dealer_pending');
define('APP_STATUS_AWO_PENDING', 'awo_pending');
define('APP_STATUS_APPROVED', 'approved');
define('APP_STATUS_REJECTED', 'rejected');

// I-Card Statuses
define('ICARD_ACTIVE', 'active');
define('ICARD_REVOKED', 'revoked');
define('ICARD_LOST', 'lost');
define('ICARD_UPDATED', 'updated');

// Request Types
define('REQUEST_UPDATE', 'update');
define('REQUEST_LOST', 'lost');
define('REQUEST_REVOKE', 'revoke');

// Pagination
define('ITEMS_PER_PAGE', 20);

// I-Card Number Prefix
define('ICARD_PREFIX', 'ERKPAW/');
