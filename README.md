phpIERS
=======
phpIERS is a PHP package that simplifies obtaining Earth rotation values published by the International Earth Rotation and Reference Systems Service.


Installation
------------
### With Composer

```
$ composer require marando/php-iers
```


Usage
-----

### Creating an Instance
You can create an instance from a standard of modified Julian day, as well as the current time:
```php
IERS::jd(2451545.5);  // Julian Day
IERS::mjd(51545.5);   // Modified Julian Day
IERS::now();          // Current time
```


### Delta T (ΔT)
Delta T can be interpolated for any date from the year 1657 to present, and future dates can be predicted up to ten years into the future. All values returned represent seconds of time

```php
IERS::jd(2451545.5)->deltaT();  // Result: 63.829474585665
IERS::jd(2351545.5)->deltaT();  // Result: 19.251735262674
```

### UT1-UTC (dut1)
The value of UT1-UTC (dut1) is returned in seconds of time:
```php
IERS::jd(2451545.5)->dut1();  // Result: 0.354633
IERS::jd(2457545.5)->dut1();  // Result: 0.1725287
```

### TAI-UTC (Leap Seconds)
The total number of leap seconds accrued as of the specified date can be obtained as shown:
```php
IERS::jd(2451545.5)->leapSec();  // Result: 33
IERS::jd(2457545.5)->leapSec();  // Result: 36
```

### Celestial Pole Offset (X and Y)
The celestial pole offsets `X` and `Y` are returned as seconds of arc:
```php
IERS::jd(2457545.5)->x();  // Result: 0.086606
IERS::jd(2457545.5)->y();  // Result: 0.012592
```

### Updating Local IERS Data
The package comes with a cached set of IERS data that will only be updated when you manually call the following update function:

```php
IERS::update()
```

This allows you to control when the data update occurs to avoid lengthy delays in your code when updates are being downloaded. It's up to you when and how you want to handle updating, but a good idea is to set up a late night cron job to perform the update process.
