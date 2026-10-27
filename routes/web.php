<?php
/** @var Router $router */
$router->get('/', 'PageController@home');
$router->get('/studio', 'PageController@studio');
$router->get('/services', 'PageController@services');
$router->get('/work', 'WorkController@index');
$router->get('/work/{slug}', 'WorkController@show');
$router->get('/team/{slug}', 'TeamController@show');
$router->get('/insights', 'InsightsController@index');
$router->get('/insights/{slug}', 'InsightsController@show');
$router->get('/sitemap.xml', 'SeoController@sitemap');
$router->get('/robots.txt', 'SeoController@robots');
$router->get('/contact', 'ContactController@show');
$router->post('/contact', 'ContactController@submit');

// ---- Admin. Every route needs a signed-in user; AdminOnly adds an administrator check. All POSTs get CSRF automatically. ----
$A = ['Auth']; $AA = ['Auth', 'AdminOnly'];
$router->get('/admin/login', 'AuthController@showLogin');
$router->post('/admin/login', 'AuthController@login');
$router->post('/admin/logout', 'AuthController@logout', $A);
$router->get('/admin', 'AdminController@dashboard', $A);

$router->get('/admin/media', 'MediaController@index', $A);
$router->post('/admin/media/upload', 'MediaController@upload', $A);
$router->post('/admin/media/{id}/alt', 'MediaController@alt', $A);
$router->post('/admin/media/{id}/delete', 'MediaController@delete', $AA);

$router->get('/admin/settings', 'SettingsController@show', $AA);
$router->post('/admin/settings', 'SettingsController@save', $AA);

$router->get('/admin/projects', 'ProjectAdminController@index', $A);
$router->get('/admin/projects/new', 'ProjectAdminController@newForm', $A);
$router->post('/admin/projects/create', 'ProjectAdminController@save', $A);
$router->get('/admin/projects/{id}/edit', 'ProjectAdminController@edit', $A);
$router->post('/admin/projects/{id}/save', 'ProjectAdminController@save', $A);
$router->post('/admin/projects/{id}/status', 'ProjectAdminController@status', $A);
$router->post('/admin/projects/{id}/delete', 'ProjectAdminController@delete', $AA);

$router->get('/admin/team', 'TeamAdminController@index', $A);
$router->get('/admin/team/new', 'TeamAdminController@newForm', $A);
$router->post('/admin/team/create', 'TeamAdminController@save', $A);
$router->get('/admin/team/{id}/edit', 'TeamAdminController@edit', $A);
$router->post('/admin/team/{id}/save', 'TeamAdminController@save', $A);
$router->post('/admin/team/{id}/active', 'TeamAdminController@active', $A);
$router->post('/admin/team/{id}/delete', 'TeamAdminController@delete', $AA);

$router->get('/admin/services', 'ServiceAdminController@index', $A);
$router->get('/admin/services/new', 'ServiceAdminController@newForm', $A);
$router->post('/admin/services/create', 'ServiceAdminController@save', $A);
$router->get('/admin/services/{id}/edit', 'ServiceAdminController@edit', $A);
$router->post('/admin/services/{id}/save', 'ServiceAdminController@save', $A);
$router->post('/admin/services/{id}/active', 'ServiceAdminController@active', $A);
$router->post('/admin/services/{id}/delete', 'ServiceAdminController@delete', $AA);

$router->get('/admin/insights', 'InsightAdminController@index', $A);
$router->get('/admin/insights/new', 'InsightAdminController@newForm', $A);
$router->post('/admin/insights/create', 'InsightAdminController@save', $A);
$router->get('/admin/insights/{id}/edit', 'InsightAdminController@edit', $A);
$router->post('/admin/insights/{id}/save', 'InsightAdminController@save', $A);
$router->post('/admin/insights/{id}/status', 'InsightAdminController@status', $A);
$router->post('/admin/insights/{id}/delete', 'InsightAdminController@delete', $AA);

$router->get('/admin/homepage', 'HomepageAdminController@show', $A);
$router->post('/admin/homepage', 'HomepageAdminController@save', $A);

$router->get('/admin/enquiries', 'EnquiryAdminController@index', $A);
$router->get('/admin/enquiries/{id}', 'EnquiryAdminController@show', $A);
$router->post('/admin/enquiries/{id}/update', 'EnquiryAdminController@update', $A);
