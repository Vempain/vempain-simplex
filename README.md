# Vempain Simplex

Vempain Simplex is a web site application used to display the web pages created and maintained by the Vempain Admin system.

## Installation

Either download the latest Vempain Simplex release package from the [releases page](https://vempain.poltsi.fi/download), or alternatively clone the repository
and copy the src folder to your web server. There is no building involved.

Once you have the files, you need to perform the following actions:

### Set up the database

The database is set up as a part of the [Vempain Admin system](https://vempain.poltsi.fi/info). Vempain Simplex connects to this database.

### Configure the application

The application configuration file is located in the [src/lib](src/lib/config.php.template) folder and is called `config.php`. Copy the `config.php.template`
file to `config.php` and set the `set_me_up` values.

### Set up the web server

The web server must be configured to serve the Vempain Simplex files. The web server must be able to serve PHP files. Suggested web server is Apache or Nginx.

#### Apache

You need to add the following snippet to your site specific Apache configuration:

```apache
    RewriteEngine on

    LogLevel rewrite:notice

    # If the request file does exists in the site root, then serve it, end handling
    RewriteCond ${my_site_root}%{REQUEST_URI} -f
    RewriteRule ^.*$ - [L]

    # Else pass to the index.php
    RewriteRule ^.*$ ${my_site_root}/index.php [L]
```

The `${my_site_root}` is the root of your site. This is the folder where the `index.php` file is located.

What this does is it checks if the requested file exists in the site root. If it does, it serves it. If it does not, it passes the request to the `index.php`
file. In this way you can upload files (as Vempain Admin does) to the site directory, and they will be accessible directly. The `index.php` file will handle
the requests that are not files and those requests that end with the configured file descriptor (see `$CONFIG['file_descriptor']` in `config.php`) will be
served as pages from the database.

#### Nginx

You need to add the following snippet to your site specific Nginx configuration:

```nginx
    location / {
        try_files $uri $uri/ /index.php?$args;
    }
```

See description for Apache above.