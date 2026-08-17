## About Tracker
Tracker is an open-source time tracking solution, designed to be flawlessly integrated with your infrastructure. 
Superpowered with features like built-in screenshot capture and activity detection, it's a great instrument to boost 
your team's performance straight to the top.

#### We have our own [Container Registry](https://git.amazingcat.net/Tracker/core/app/container_registry/9?orderBy=NAME&sort=desc), the images are hosted on GitLab


### Screenshots
|           Dashboard           |           Project report           |
|:-----------------------------:|:----------------------------------:|
| ![](./examples/dashboard.jpg) | ![](./examples/project_report.jpg) |

### Demo
The demo app is available here: [demo.Tracker.app](https://demo.Tracker.app) 

## Install Tracker
[Installation manual](https://docs.Tracker.app/#/en/getting-started/?id=requirements) on the documentation website.

```
composer install
php artisan key:generate
yarn
```

After should be edited `.env` file (e.g. for DB connection), look at `.env.example` for examples

```
php artisan migrate --seed --seeder=InitialSeeder
```

App will not start without seeding of InitialSeeder

After seeding it, run `php artisan Tracker:make:admin` and you will be able to login with following credentials
```
admin@Tracker.app
password
```

### Run Local server

local server by default will be run as <http://127.0.0.1:8000>

```
php artisan serve
yarn dev
```

### Generate IDE helpers

```
composer dumphelpers
composer dumperd
```

## Links

https://git.amazingcat.net/Tracker/desktop/desktop-application – Tracker Desktop Application. You can also download the built app for
any OS from the [official site](https://Tracker.app/desktop/).

### Documentation

You can find the Tracker documentation [on the website](https://docs.Tracker.app).

Checkout the [Getting Started](https://docs.Tracker.app/#/en/getting-started/) page for a quick overview.

### Questions

For questions and support please use the [Github Discussions](https://github.com/orgs/Tracker-app/discussions). 

