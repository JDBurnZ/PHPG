PHPG
====

A PostgreSQL database interface library written in PHP specifically designed to confront and solve a majority of long-standing issues with PHP's native PostgreSQL driver.

Much of the underlying functionality utilizes PHP's native PostgreSQL driver to provide the best performance and reliability available.

= Automatic Detection & Transformation =
* PostgreSQL Arrays (ANY data-type) to native PHP Arrays.
* PostgreSQL Hstores to native PHP Associative Arrays.
* PostgreSQL Geometric data-types (lseg, point, polygon, etc) to native PHP Arrays.
* PostgreSQL Dates / Timestamps to native PHP DateTime Objects.
* ... And many more!

= Requirements =
* TODO: This script has only been developed and tested in:
* PHP 5.3.3
* PostgreSQL 8.4.8

In theory, this should work with:
* PostgreSQL 6.5 or later. 8.0 or later for full PostgreSQL feature support.
* PHP 5.0 or later.

