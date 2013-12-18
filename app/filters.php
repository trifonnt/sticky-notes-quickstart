<?php

/*
|--------------------------------------------------------------------------
| Application & Route Filters
|--------------------------------------------------------------------------
|
| Below you will find the "before" and "after" events for the application
| which may be used to do any work before or after a request into your
| application. Here you may also register your custom route filters.
|
*/

App::before(function($request)
{
	//
});


App::after(function($request, $response)
{
	//
});

/*
|--------------------------------------------------------------------------
| Authentication Filters
|--------------------------------------------------------------------------
|
| The following filters are used to verify that the user of the current
| session is logged into this application. The "basic" filter easily
| integrates HTTP Basic authentication for quick, simple checking.
|
*/

Route::filter('auth', function()
{
	if (Auth::guest())
	{
		return Redirect::guest('user/login');
	}
});


Route::filter('auth.basic', function()
{
	return Auth::basic();
});

/*
|--------------------------------------------------------------------------
| Authentication Config Filters
|--------------------------------------------------------------------------
|
| The following filters are used to verify that DB based auth is enabled.
| An additional reg allowed check is performed for POST to register page.
|
*/

Route::filter('auth.config', function()
{
	$auth = Site::config('auth');

	if ($auth->method != 'db')
	{
		App::abort(401);
	}

	if (Request::segment(2) == 'register' AND ! $auth->dbAllowReg)
	{
		App::abort(401);
	}
});

/*
|--------------------------------------------------------------------------
| Guest Filter
|--------------------------------------------------------------------------
|
| The "guest" filter is the counterpart of the authentication filters as
| it simply checks that the current user is not logged in. A redirect
| response will be issued if they are, which you may freely change.
|
*/

Route::filter('guest', function()
{
	if (Auth::check())
	{
		return Redirect::to('/');
	}
});

/*
|--------------------------------------------------------------------------
| CSRF Protection Filter
|--------------------------------------------------------------------------
|
| The CSRF filter is responsible for protecting your application against
| cross-site request forgery attacks. If this special token in a user
| session does not match the one given in this request, we'll bail.
|
| CSRF protection is not applied to API POST requests.
|
*/

Route::filter('csrf', function()
{
	if (Request::segment(1) != 'api')
	{
		if (Session::token() != Input::get('_token'))
		{
			throw new Illuminate\Session\TokenMismatchException;
		}
	}
});

/*
|--------------------------------------------------------------------------
| Admin only filter
|--------------------------------------------------------------------------
|
| This filter validates that the logged in user is an administrator.
|
*/

Route::filter('admin', function()
{
	if (Auth::guest() OR ! Auth::user()->admin)
	{
		App::abort(401);
	}
});

/*
|--------------------------------------------------------------------------
| Private site filter
|--------------------------------------------------------------------------
|
| This filter validates whether the site is set as private (i.e. disallows
| public pastes) and if so, it throws a 401 for the list routes
|
*/

Route::filter('private', function()
{
	if (Site::config('general')->privateSite)
	{
		App::abort(401);
	}
});

/*
|--------------------------------------------------------------------------
| Numeric paste ID filter
|--------------------------------------------------------------------------
|
| This filter gets a paste by its numeric ID. This is here purely for
| backward compatibility as 0.4 and older versions had an optional / did
| not have a alphanumeric URLkey.
|
*/

Route::filter('numeric', function()
{
	$key = Request::segment(1);

	$hash = Request::segment(2);

	if (is_numeric($key) AND $key <= Site::config('general')->preMigrate)
	{
		$paste = Paste::findOrFail($key);

		return Redirect::to("{$paste->urlkey}/{$hash}");
	}
});

/*
|--------------------------------------------------------------------------
| Setup validation filter
|--------------------------------------------------------------------------
|
| This filter checks if Sticky Notes is marked as installed.
|
| The following checks are done:
|  - If the main table does not exist, it is a fresh install
|  - If the main table is there, but versions mismatch, it is an update
|  - If main table is there and versions match, we should get out of setup
|
*/

Route::filter('installed', function()
{
	try
	{
		$installed = Schema::hasTable('main');
	}
	catch (Exception $e)
	{
		$installed = FALSE;
	}

	// Set the installed flag so it is accessible everywhere
	Session::set('global.installed', $installed);

	// Now we get the app and DB versions
	// If there is no version data in the DB, the function will return 0
	$appVersion = System::version(Config::get('app.version'));

	$dbVersion = System::version(Site::config('general')->version);

	// We clear the cache to verify if there is a version mismatch
	// This usually should not be required but we do this to avoid the
	// update screen from popping up when we the user updates the
	// sticky-notes code
	if ($appVersion > $dbVersion)
	{
		Cache::flush();

		$dbVersion = System::version(Site::config('general')->version);
	}

	// Redirect to setup pages based on version checks
	if (Request::segment(1) != 'setup')
	{
		Session::forget('setup.stage');

		Session::forget('setup.updating');

		Session::forget('setup.version');

		// Redirect to the installer
		if ( ! $installed)
		{
			return Redirect::to('setup/install');
		}

		// Redirect to the updater, with the exception of the login page
		else if ($appVersion > $dbVersion AND Request::segment(2) != 'login')
		{
			return Redirect::to('setup/update');
		}
	}

	// Only admins can access this page
	// We check for dbVersion as 0.4 will not support the Auth functions
	else if (Request::segment(2) == 'update' AND $dbVersion > 0 AND (Auth::guest() OR ! Auth::user()->admin))
	{
		App::abort(503);
	}

	// You should not be here!
	else if ($installed AND $appVersion == $dbVersion AND ! Session::has('setup.stage'))
	{
		return Redirect::to('/');
	}
});
