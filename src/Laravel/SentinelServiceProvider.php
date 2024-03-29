<?php

/**
 * Part of the Sentinel package.
 *
 * NOTICE OF LICENSE
 *
 * Licensed under the Cartalyst PSL License.
 *
 * This source file is subject to the Cartalyst PSL License that is
 * bundled with this package in the LICENSE file.
 *
 * @package    Sentinel
 * @version    2.0.1
 * @author     Cartalyst LLC
 * @license    Cartalyst PSL
 * @copyright  (c) 2011-2015, Cartalyst LLC
 * @link       http://cartalyst.com
 */

namespace Cartalyst\Sentinel\Laravel;

use Cartalyst\Sentinel\Activations\IlluminateActivationRepository;
use Cartalyst\Sentinel\Checkpoints\ActivationCheckpoint;
use Cartalyst\Sentinel\Checkpoints\ThrottleCheckpoint;
use Cartalyst\Sentinel\Cookies\IlluminateCookie;
use Cartalyst\Sentinel\Hashing\NativeHasher;
use Cartalyst\Sentinel\Persistences\IlluminatePersistenceRepository;
use Cartalyst\Sentinel\Reminders\IlluminateReminderRepository;
use Cartalyst\Sentinel\Roles\IlluminateRoleRepository;
use Cartalyst\Sentinel\Sentinel;
use Cartalyst\Sentinel\Sessions\IlluminateSession;
use Cartalyst\Sentinel\Throttling\IlluminateThrottleRepository;
use Cartalyst\Sentinel\Users\IlluminateUserRepository;
use Exception;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class SentinelServiceProvider extends ServiceProvider
{
    /**
     * {@inheritDoc}
     */
    public function boot()
    {
        $this->garbageCollect();
    }

    /**
     * {@inheritDoc}
     */
    public function register()
    {
        $this->prepareResources();
        $this->registerPersistences();
        $this->registerUsers();
        $this->registerRoles();
        $this->registerCheckpoints();
        $this->registerReminders();
        $this->registerSentinel();
        $this->setUserResolver();
    }

    /**
     * Prepare the package resources.
     *
     * @return void
     */
    protected function prepareResources()
    {
        // Publish config
        $config = realpath(__DIR__.'/../config/config.php');

        $this->mergeConfigFrom($config, 'cartalyst.sentinel');

        $this->publishes([
            $config => config_path('cartalyst.sentinel.php'),
        ], 'config');

        // Publish migrations
        $migrations = realpath(__DIR__.'/../migrations');

        $this->publishes([
            $migrations => $this->app->databasePath().'/migrations',
        ], 'migrations');
    }

    /**
     * Registers the persistences.
     *
     * @return void
     */
    protected function registerPersistences()
    {
        $this->registerSession();
        $this->registerCookie();

        $this->app['sentinel.persistence'] = $this->app->share(function ($app) {
            $config = $app['config']->get('cartalyst.sentinel');

            $model  = array_get($config, 'persistences.model');
            $single = array_get($config, 'persistences.single');
            $users  = array_get($config, 'users.model');

            if (class_exists($users) && method_exists($users, 'setPersistencesModel')) {
                forward_static_call_array([$users, 'setPersistencesModel'], [$model]);
            }

            return new IlluminatePersistenceRepository($app['sentinel.session'], $app['sentinel.cookie'], $model, $single);
        });
    }

    /**
     * Registers the session.
     *
     * @return void
     */
    protected function registerSession()
    {
        $this->app['sentinel.session'] = $this->app->share(function ($app) {
            $key = $app['config']->get('cartalyst.sentinel.session');

            return new IlluminateSession($app['session.store'], $key);
        });
    }

    /**
     * Registers the cookie.
     *
     * @return void
     */
    protected function registerCookie()
    {
        $this->app['sentinel.cookie'] = $this->app->share(function ($app) {
            $key = $app['config']->get('cartalyst.sentinel.cookie');

            return new IlluminateCookie($app['request'], $app['cookie'], $key);
        });
    }

    /**
     * Registers the users.
     *
     * @return void
     */
    protected function registerUsers()
    {
        $this->registerHasher();

        $this->app['sentinel.users'] = $this->app->share(function ($app) {
            $config = $app['config']->get('cartalyst.sentinel');

            $users        = array_get($config, 'users.model');
            $roles        = array_get($config, 'roles.model');
            $persistences = array_get($config, 'persistences.model');
            $permissions  = array_get($config, 'permissions.class');

            if (class_exists($roles) && method_exists($roles, 'setUsersModel')) {
                forward_static_call_array([$roles, 'setUsersModel'], [$users]);
            }

            if (class_exists($persistences) && method_exists($persistences, 'setUsersModel')) {
                forward_static_call_array([$persistences, 'setUsersModel'], [$users]);
            }

            if (class_exists($users) && method_exists($users, 'setPermissionsClass')) {
                forward_static_call_array([$users, 'setPermissionsClass'], [$permissions]);
            }

            return new IlluminateUserRepository($app['sentinel.hasher'], $app['events'], $users);
        });
    }

    /**
     * Registers the hahser.
     *
     * @return void
     */
    protected function registerHasher()
    {
        $this->app['sentinel.hasher'] = $this->app->share(function ($app) {
            return new NativeHasher;
        });
    }

    /**
     * Registers the roles.
     *
     * @return void
     */
    protected function registerRoles()
    {
        $this->app['sentinel.roles'] = $this->app->share(function ($app) {
            $config = $app['config']->get('cartalyst.sentinel');

            $model = array_get($config, 'roles.model');
            $users = array_get($config, 'users.model');

            if (class_exists($users) && method_exists($users, 'setRolesModel')) {
                forward_static_call_array([$users, 'setRolesModel'], [$model]);
            }

            return new IlluminateRoleRepository($model);
        });
    }

    /**
     * Registers the checkpoints.
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function registerCheckpoints()
    {
        $this->registerActivationCheckpoint();
        $this->registerThrottleCheckpoint();

        $this->app['sentinel.checkpoints'] = $this->app->share(function ($app) {
            $activeCheckpoints = $app['config']->get('cartalyst.sentinel.checkpoints');

            $checkpoints = [];

            foreach ($activeCheckpoints as $checkpoint) {
                if (! $app->offsetExists("sentinel.checkpoint.{$checkpoint}")) {
                    throw new InvalidArgumentException("Invalid checkpoint [$checkpoint] given.");
                }

                $checkpoints[$checkpoint] = $app["sentinel.checkpoint.{$checkpoint}"];
            }

            return $checkpoints;
        });
    }

    /**
     * Registers the activation checkpoint.
     *
     * @return void
     */
    protected function registerActivationCheckpoint()
    {
        $this->registerActivations();

        $this->app['sentinel.checkpoint.activation'] = $this->app->share(function ($app) {
            return new ActivationCheckpoint($app['sentinel.activations']);
        });
    }

    /**
     * Registers the activations.
     *
     * @return void
     */
    protected function registerActivations()
    {
        $this->app['sentinel.activations'] = $this->app->share(function ($app) {
            $config = $app['config']->get('cartalyst.sentinel');

            $model   = array_get($config, 'activations.model');
            $expires = array_get($config, 'activations.expires');

            return new IlluminateActivationRepository($model, $expires);
        });
    }

    /**
     * Registers the throttle checkpoint.
     *
     * @return void
     */
    protected function registerThrottleCheckpoint()
    {
        $this->registerThrottling();

        $this->app['sentinel.checkpoint.throttle'] = $this->app->share(function ($app) {
            return new ThrottleCheckpoint(
                $app['sentinel.throttling'],
                $app['request']->getClientIp()
            );
        });
    }

    /**
     * Registers the throttle.
     *
     * @return void
     */
    protected function registerThrottling()
    {
        $this->app['sentinel.throttling'] = $this->app->share(function ($app) {
            $model = $app['config']->get('cartalyst.sentinel.throttling.model');

            foreach (['global', 'ip', 'user'] as $type) {
                ${"{$type}Interval"} = $app['config']->get("cartalyst.sentinel.throttling.{$type}.interval");
                ${"{$type}Thresholds"} = $app['config']->get("cartalyst.sentinel.throttling.{$type}.thresholds");
            }

            return new IlluminateThrottleRepository(
                $model,
                $globalInterval,
                $globalThresholds,
                $ipInterval,
                $ipThresholds,
                $userInterval,
                $userThresholds
            );
        });
    }

    /**
     * Registers the reminders.
     *
     * @return void
     */
    protected function registerReminders()
    {
        $this->app['sentinel.reminders'] = $this->app->share(function ($app) {
            $config = $app['config']->get('cartalyst.sentinel');

            $model   = array_get($config, 'reminders.model');
            $expires = array_get($config, 'reminders.expires');

            return new IlluminateReminderRepository($app['sentinel.users'], $model, $expires);
        });
    }

    /**
     * Registers sentinel.
     *
     * @return void
     */
    protected function registerSentinel()
    {
        $this->app['sentinel'] = $this->app->share(function ($app) {
            $sentinel = new Sentinel(
                $app['sentinel.persistence'],
                $app['sentinel.users'],
                $app['sentinel.roles'],
                $app['sentinel.activations'],
                $app['events']
            );

            if (isset($app['sentinel.checkpoints'])) {
                foreach ($app['sentinel.checkpoints'] as $key => $checkpoint) {
                    $sentinel->addCheckpoint($key, $checkpoint);
                }
            }

            $sentinel->setActivationRepository($app['sentinel.activations']);
            $sentinel->setReminderRepository($app['sentinel.reminders']);

            $sentinel->setRequestCredentials(function () use ($app) {
                $request = $app['request'];

                $login = $request->getUser();
                $password = $request->getPassword();

                if ($login === null && $password === null) {
                    return;
                }

                return compact('login', 'password');
            });

            $sentinel->creatingBasicResponse(function () {
                $headers = ['WWW-Authenticate' => 'Basic'];

                return new Response('Invalid credentials.', 401, $headers);
            });

            return $sentinel;
        });

        $this->app->alias('sentinel', 'Cartalyst\Sentinel\Sentinel');
    }

    /**
     * {@inheritDoc}
     */
    public function provides()
    {
        return [
            'sentinel.session',
            'sentinel.cookie',
            'sentinel.persistence',
            'sentinel.hasher',
            'sentinel.users',
            'sentinel.roles',
            'sentinel.activations',
            'sentinel.checkpoint.activation',
            'sentinel.throttling',
            'sentinel.checkpoint.throttle',
            'sentinel.checkpoints',
            'sentinel.reminders',
            'sentinel',
        ];
    }

    /**
     * Garbage collect activations and reminders.
     *
     * @return void
     */
    protected function garbageCollect()
    {
        $config = $this->app['config']->get('cartalyst.sentinel.activations.lottery');

        $this->sweep($this->app['sentinel.activations'], $config);

        $config = $this->app['config']->get('cartalyst.sentinel.reminders.lottery');

        $this->sweep($this->app['sentinel.reminders'], $config);
    }

    /**
     * Sweep expired codes.
     *
     * @param  mixed  $repository
     * @param  array  $lottery
     * @return void
     */
    protected function sweep($repository, $lottery)
    {
        if ($this->configHitsLottery($lottery)) {
            try {
                $repository->removeExpired();
            } catch (Exception $e) {
            }
        }
    }

    /**
     * Determine if the configuration odds hit the lottery.
     *
     * @param  array  $lottery
     * @return bool
     */
    protected function configHitsLottery(array $lottery)
    {
        return mt_rand(1, $lottery[1]) <= $lottery[0];
    }

    /**
     * Sets the user resolver on the request class.
     *
     * @return void
     */
    protected function setUserResolver()
    {
        $this->app['request']->setUserResolver(function () {
            return $this->app['sentinel']->getUser();
        });
    }
}
