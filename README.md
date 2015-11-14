phpIERS
=======
phpIERS is a PHP package that simplifies obtaining values related to the Earth's rotation that are published in International Earth Rotation and Reference Systems Service (IERS) bulletins. 


Installation
------------
#### With Composer

```
$ composer require marando/php-iers
```


Usage
-----

#### Creating an Instance
You can create an instance from a standard of modified Julian day, as well as the current time:
```php
IERS::jd(2451545.5);  // Julian Day
IERS::mjd(51545.5);   // Modified Julian Day
IERS::now();          // Current time
```

### Available Values

Below is a list of values phpIERS can interpolate:

 Value                         | Method
-------------------------------|----------------
 DeltaT (ΔT)                   | `deltaT()`
 UT1-UTC (dut1)                | `dut1()`
 TAI-UTC (leap seconds)        | `leapSec()`
 X and Y celestial pole offset | `x()` and `y()`

#### Delta T (ΔT)
Delta T can be interpolated for any date from the year 1657 to the present, and future dates can be predicted up to ten years into the future:

```php
IERS::jd(2451545.5)->deltaT();  // Result 63.829474585665 sec.
IERS::jd(2351545.5)->deltaT();  // Result 19.251735262674 sec.
```

####





















