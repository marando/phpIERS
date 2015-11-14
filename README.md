phpIERS
=======
phpIERS is a PHP package that simplifies obtaining values Earth's rotation values published by the International Earth Rotation and Reference Systems Service. 


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
Delta T can be interpolated for any date from the year 1657 to present, and future dates can be predicted up to ten years into the future. All values returned represent seconds of time

```php
IERS::jd(2451545.5)->deltaT();  // Result: 63.829474585665
IERS::jd(2351545.5)->deltaT();  // Result: 19.251735262674
```

#### UT1-UTC (dut1)
The value of UT1-UTC (dut1) is returned in seconds of time:
```php
IERS::jd(2451545.5)->dut1();  // Result: 0.354633
IERS::jd(2457545.5)->dut1();  // Result: 0.1725287
```

#### TAI-UTC (Leap Seconds)
The total number of leap seconds accrued to the specified date can be obtained as shown:
```php
IERS::jd(2451545.5)->leapSec();  // Result: 33
IERS::jd(2457545.5)->leapSec();  // Result: 36
```

#### Celestial Pole Offset (X and Y)
The celestial pole offsets `X` and `Y` are returned as seconds of arc:
```php
IERS::jd(2457545.5)->x();  // Result: 0.086606
IERS::jd(2457545.5)->y();  // Result: 0.012592
```
















