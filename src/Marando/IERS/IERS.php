<?php

/*
 * Copyright (C) 2015 Ashley Marando
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

namespace Marando\IERS;

/**
 * Provides IERS bulletin data.
 *
 * @property float $jd  Julian day count of instance.
 * @property float $mjd Modified Julian day count of instance.
 */
class IERS
{
    //--------------------------------------------------------------------------
    // Constants
    //--------------------------------------------------------------------------

    /**
     * Directory IRES data is stored in.
     */
    const STORAGE_DIR = 'data';

    /**
     * How many rows of data to use when interpolating results.
     */
    const INTERP_C = 5;

    /**
     * Minimum hourly interval IERS servers may be queried for new data.
     */
    const UPDATE_INTVL_H = 0.25;

    /**
     * Data files needed by this class.
     */
    const FILES = [
      'deltat.data',           // ΔΤ (TT-UT) predictions
      'deltat.preds',          // ΔΤ (TT-UT) predictions
      'finals.all',            // Final values for x, y, and UT1-UTC (dut1)
      'historic_deltat.data',  // Historic ΔΤ (TDT-UT1)
      'tai-utc.dat',           // Leap second file (TAI-UTC)
      'readme',                // Readme
      'readme.finals',         //    files
    ];

    /**
     * A list of IERS servers and their home paths.
     */
    const SERVERS = [
      ['domain' => 'maia.usno.navy.mil', 'path' => '/ser7/'],
      ['domain' => 'toshi.nofs.navy.mil', 'path' => '/ser7/'],
      ['domain' => 'cddis.gsfc.nasa.gov', 'path' => '/pub/products/iers'],
    ];

    //--------------------------------------------------------------------------
    // Constructors
    //--------------------------------------------------------------------------

    /**
     * Creates a new instance from a Julian day count.
     *
     * @param float $jd
     */
    public function __construct($jd)
    {
        $this->jd = $jd;
    }

    // // // Static

    /**
     * Creates a new instance from a Julian day count.
     *
     * @param  float $jd
     *
     * @return static
     */
    public static function jd($jd)
    {
        return new static($jd);
    }

    /**
     * Creates a new instance from a Modified Julian day count.
     *
     * @param  float $mjd
     *
     * @return static
     */
    public static function mjd($mjd)
    {
        return new static(2400000.5 + $mjd);
    }

    /**
     * Creates a new instance at the current data and time.
     *
     * @return static
     */
    public static function now()
    {
        $jd = unixtojd(time()) + microtime(true) - time();

        return new static($jd);
    }

    //----------------------------------------------------------------------------
    // Properties
    //----------------------------------------------------------------------------

    /**
     * Julian day count of this instance.
     *
     * @var float
     */
    private $jd;

    public function __get($name)
    {
        switch ($name) {
            case 'jd':
                return $this->{$name};

            case 'mjd':
                return $this->jd - 2400000.5;
        }
    }

    //----------------------------------------------------------------------------
    // Functions
    //----------------------------------------------------------------------------

    /**
     * Interpolates the value of UT1-UTC (dut1) in seconds.
     *
     * @return float|boolean Returns false on error.
     */
    public function dut1()
    {
        // Load file
        $file = new \SplFileObject($this->storage('finals.all'));

        // Get instance MJD, and MJD at line 0
        $mjdQ = $this->mjd;
        $mjd0 = (int)substr($file->getCurrentLine(), 7, 8);

        // Check for requested MJD before first date
        if ($mjdQ < $mjd0) {
            return false;
        }

        // Determine nearest center pointer
        $p = $mjdQ - $mjd0 - 1;

        // Fix for values within |n| of lower bound
        if ($p < static::INTERP_C && $p > -static::INTERP_C) {
            $p = static::INTERP_C;
        }

        $mLine = count(file($file->getRealPath())) - 1;
        if ($p > $mLine) {
            throw new \Exception("No dut data for JD={$this->jd}.");
        }

        // Compile dataset
        $ds = [];
        for ($i = $p - static::INTERP_C; $i < $p + static::INTERP_C; $i++) {
            $file->seek($i);
            $line = $file->getCurrentLine();

            // Parse data from line
            $mjd   = substr($line, 7, 8);
            $dut1f = substr($line, 156, 9);
            $dut1p = substr($line, 59, 9);

            // Use final value first, if not present use prediction
            $dut1 = trim($dut1f) ? $dut1f : $dut1p;

            // Check if no dut1 data, error
            if (trim($dut1) == '') {
                return false;
            }

            // Add the data
            $ds[$i - $p + static::INTERP_C]['x'] = (float)$mjd;
            $ds[$i - $p + static::INTERP_C]['y'] = (float)$dut1;
        }

        // Interpolate dut1
        return $this->lagrangeInterp($mjdQ, $ds);
    }

    /**
     * Interpolates the celestial pole offset value of x in seconds of arc.
     *
     * @return float|boolean Returns false on error.
     */
    public function x()
    {
        // Load file
        $file = new \SplFileObject($this->storage('finals.all'));

        // Get instance MJD, and MJD at line 0
        $mjdQ = $this->mjd;
        $mjd0 = (int)substr($file->getCurrentLine(), 7, 8);

        // Check for requested MJD before first date
        if ($mjdQ < $mjd0) {
            return false;
        }

        // Determine nearest center pointer
        $p = $mjdQ - $mjd0 - 1;

        // Fix for values within |n| of lower bound
        if ($p < static::INTERP_C && $p > -static::INTERP_C) {
            $p = static::INTERP_C;
        }

        // Compile dataset
        $ds = [];
        for ($i = $p - static::INTERP_C; $i < $p + static::INTERP_C; $i++) {
            $file->seek($i);
            $line = $file->getCurrentLine();

            // Parse data from line
            $mjd = substr($line, 7, 8);
            $xf  = substr($line, 136, 9);
            $xp  = substr($line, 18, 9);

            // Use final value first, if not present use prediction
            $x = trim($xf) ? $xf : $xp;

            // Check if no dut1 data, error
            if (trim($x) == '') {
                return false;
            }

            // Add the data
            $ds[$i - $p + static::INTERP_C]['x'] = (float)$mjd;
            $ds[$i - $p + static::INTERP_C]['y'] = (float)$x;
        }

        // Interpolate pole x
        return $this->lagrangeInterp($mjdQ, $ds);
    }

    /**
     * Interpolates the celestial pole offset value of y in seconds of arc.
     *
     * @return float|boolean Returns false on error.
     */
    public function y()
    {
        // Load file
        $file = new \SplFileObject($this->storage('finals.all'));

        // Get instance MJD, and MJD at line 0
        $mjdQ = $this->mjd;
        $mjd0 = (int)substr($file->getCurrentLine(), 7, 8);

        // Check for requested MJD before first date
        if ($mjdQ < $mjd0) {
            return false;
        }

        // Determine nearest center pointer
        $p = $mjdQ - $mjd0 - 1;

        // Fix for values within |n| of lower bound
        if ($p < static::INTERP_C && $p > -static::INTERP_C) {
            $p = static::INTERP_C;
        }

        // Compile dataset
        $ds = [];
        for ($i = $p - static::INTERP_C; $i < $p + static::INTERP_C; $i++) {
            $file->seek($i);
            $line = $file->getCurrentLine();

            // Parse data from line
            $mjd = substr($line, 7, 8);
            $yf  = substr($line, 146, 9);
            $yp  = substr($line, 27, 9);

            // Use final value first, if not present use prediction
            $y = trim($yf) ? $yf : $yp;

            // Check if no dut1 data, error
            if (trim($y) == '') {
                return false;
            }

            // Add the data
            $ds[$i - $p + static::INTERP_C]['x'] = (float)$mjd;
            $ds[$i - $p + static::INTERP_C]['y'] = (float)$y;
        }

        // Interpolate pole y
        return $this->lagrangeInterp($mjdQ, $ds);
    }

    /**
     * Interpolates the value of delta T (ΔΤ)
     *
     * @return float|boolean Returns false on error.
     */
    public function deltaT()
    {
        // Historic ΔT
        if ($this->jd < 2441714.5) {
            return $this->deltaT_historic();
        }

        // Predictive ΔT
        if ($this->jd > $this->deltaT_lastJD()) {
            return $this->deltaT_predict();
        }

        // Base ΔT data file
        return $this->deltaT_base();
    }

    /**
     * Finds the number of leap seconds as of the instance's date.
     *
     * @return float
     */
    public function leapSec()
    {
        // Load data file
        $file = new \SplFileObject($this->storage('tai-utc.dat'));

        $taiUTC = 0;
        while ($file->valid()) {
            $line = $file->getCurrentLine();

            // Check for empty line
            if (trim($line) == '') {
                break;
            }

            // Get JD and leap seconds as of that JD
            $jd = (float)substr($line, 17, 9);

            // If the leap second JD exceeds or is = to this instance, break
            if ($jd > $this->jd) {
                break;
            }

            // Within range, use current leap seconds
            $taiUTC = (float)substr($line, 38, 10);
        }

        // Return leap seconds
        return $taiUTC;
    }

    /**
     * Performs an update of the local IERS data.
     *
     * @return array|bool An array of files that were updated.
     */
    public static function update()
    {
        $iers         = IERS::now();
        $filesUpdated = $iers->performUpdate();

        return $filesUpdated;
    }

    // // // Private

    /**
     * Finds the final Julian day count in the deltat.data file
     *
     * @return float
     */
    private function deltaT_lastJD()
    {
        // Seek to maximum line
        $mLine = count(file($this->storage('deltat.data'))) - 1;
        $file  = new \SplFileObject($this->storage('deltat.data'));
        $file->seek($mLine);

        // Get the line until it's not empty stepping back 1 line if so
        $line = $file->getCurrentLine();
        while (trim($line) == '') {
            $file->seek($mLine);
            $line = $file->getCurrentLine();
            $mLine--;
        }

        $mLine--;
        $file->seek($mLine);
        $line = $file->getCurrentLine();

        // YMD -> JD and return
        $y = (int)substr($line, 1, 4);
        $m = (int)substr($line, 6, 2);
        $d = (int)substr($line, 9, 2);
        static::iauCal2jd($y, $m, $d, $djm0, $djm);

        return $djm0 + $djm;
    }

    /**
     * Interpolates the value of ΔΤ for a historic date
     *
     * @return float|boolean Returns false if an error has occured
     */
    private function deltaT_historic()
    {
        // Get calendar date
        static::iauJd2cal(2400000.5, $this->mjd, $y, $m, $d, $fd);

        // Load file and get max line
        $file  = new \SplFileObject($this->storage('historic_deltat.data'));
        $mLine = count(file($file->getRealPath())) - 1;

        // Determine pointer, return false if out of range
        $p = ($y - 1657) * 2 + 2;
        if ($p < 0) {
            return false;
        }

        // Reset pointer if within INTERP_COUNT range on lower bound
        if ($p < static::INTERP_C) {
            $p = static::INTERP_C;
        }

        // Reset pointer if within INTERP_COUNTn range on upper bound
        if ($p + static::INTERP_C > $mLine) {
            $p = $mLine - static::INTERP_C;
        }

        // Compile dataset
        $ds = [];
        for ($i = $p - static::INTERP_C; $i < $p + static::INTERP_C; $i++) {
            // Seek pointer and get line
            $file->seek($i);
            $line = $file->getCurrentLine();

            // Year and ΔΤ value as float
            $y  = (float)substr($line, 0, 8);
            $ΔT = (float)substr($line, 13, 6);

            // Determine month, and compute JD
            $m  = intval($y) == $y ? 1 : 6;
            $jd = static::iauCal2jd((int)$y, $m, 1, $djm0, $djm);

            // Insert data
            $ds[$i - $p + static::INTERP_C]['x'] = $djm0 + $djm;
            $ds[$i - $p + static::INTERP_C]['y'] = $ΔT;
        }

        // Interpolate value of ΔT
        return $this->lagrangeInterp($this->jd, $ds);
    }

    /**
     * Interpolates the value of ΔΤ for a current date
     *
     * @return float|boolean Returns false if an error has occured
     */
    private function deltaT_base()
    {
        // Get Julian day count
        static::iauJd2cal(2400000.5, $this->mjd, $iy, $im, $id, $fd);

        // Determine pointer, return false if out of range
        $p = ($iy - 1973) * 12 + $im - 2;
        if ($p < 0) {
            return false;
        }

        // Load file, and get max line count
        $file  = new \SplFileObject($this->storage('deltat.data'));
        $mLine = count(file($file->getRealPath())) - 1;

        // Reset pointer if within INTERP_COUNT on lower bound
        if ($p < static::INTERP_C) {
            $p = static::INTERP_C;
        }

        // Reset pointer if within INTERP_COUNT on upper bound
        if ($p + static::INTERP_C > $mLine) {
            $p = $mLine - static::INTERP_C;
        }

        // Compile dataset
        $ds = [];
        for ($i = $p - static::INTERP_C; $i < $p + static::INTERP_C; $i++) {
            // Seek pointer and get line
            $file->seek($i);
            $line = $file->getCurrentLine();

            // YMD -> JD
            $y = (int)substr($line, 1, 4);
            $m = (int)substr($line, 6, 2);
            $d = (int)substr($line, 9, 2);
            static::iauCal2jd($y, $m, $d, $djm0, $djm);

            // Parse value of ΔT
            $dT = (float)substr($line, 13, 7);

            // Insert data
            $ds[$i - $p + static::INTERP_C]['x'] = $djm0 + $djm;
            $ds[$i - $p + static::INTERP_C]['y'] = $dT;
        }

        // Interpolate value of ΔT
        return $this->lagrangeInterp($this->jd, $ds);
    }

    /**
     * Interpolates the value of ΔΤ for a future date
     *
     * @return bool|float Returns false if an error has occured
     * @throws \Exception
     */
    private function deltaT_predict()
    {
        // Get Julian day count
        static::iauJd2cal(2400000.5, $this->mjd, $y, $m, $d, $fd);

        // Load file
        $file    = new \SplFileObject($this->storage('deltat.preds'));
        $maxLine = count(file($file->getRealPath())) - 1;

        // Determine pointer, return false if out of range
        $file->seek(4);
        $fyear = (double)substr($file->current(), 1, 7);
        $quart = ($fyear - (int)$fyear) * 4;
        $p     = ($y - (int)$fyear) * 4 + $quart - 2;
        if ($p < 0) {
            return false;
        }

        // Reset pointer if within INTERP_COUNT on lower bound
        if ($p <= static::INTERP_C) {
            $p = static::INTERP_C + 3;
        }

        if ($p > $maxLine - static::INTERP_C) {
            throw  new \Exception("No ΔΤ data for JD={$this->jd}");
        }

        // Reset pointer if within INTERP_COUNT on upper bound
        if ($p + static::INTERP_C > $maxLine) {
            $p = $maxLine - static::INTERP_C;
        }

        // Compile dataset
        $ds = [];
        for ($i = $p - static::INTERP_C; $i < $p + static::INTERP_C; $i++) {
            // Seek pointer and get line
            $file->seek($i);
            $line = $file->getCurrentLine();

            // Year as fraction and ΔT value
            $y  = (float)substr($line, 1, 7);
            $ΔT = (float)substr($line, 14, 6);

            // Determine month for fractional year
            $m;
            if ($y - intval($y) == 0) {
                $m = 1;
            } elseif ($y - intval($y) <= 0.25) {
                $m = 3;
            } elseif ($y - intval($y) <= 0.5) {
                $m = 6;
            } elseif ($y - intval($y) <= 0.75) {
                $m = 9;
            }

            // YMD -> JD
            static::iauCal2jd(intval($y), $m, 1, $djm0, $djm);

            // Insert data
            $ds[$i - $p + static::INTERP_C]['x'] = $djm0 + $djm;
            $ds[$i - $p + static::INTERP_C]['y'] = $ΔT;
        }

        // Interpolate value of ΔT
        return $this->lagrangeInterp($this->jd, $ds);
    }

    /**
     * Returns if all needed remote data files defined in FILES exist locally
     *
     * @return boolean
     */
    private function filesExist()
    {
        foreach (static::FILES as $file) {
            if (!file_exists($this->storage($file))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Connects to the first available IERS server mirror and returns an FTP
     * connection resource
     *
     * @return resource FTP resource
     * @throws \Exception
     */
    private function ftp()
    {
        $servers = static::SERVERS;  // Server list
        // Try each server till success
        $ftp;
        for ($i = 0; $i < count($servers); $i++) {
            $ftpServer = $servers[$i]['domain'];
            $ftp       = ftp_connect($ftpServer);

            // Return resource on connected
            if ($ftp) {
                if (ftp_login($ftp, 'anonymous', null)) {
                    if (ftp_chdir($ftp, $servers[$i]['path'])) {
                        if (ftp_pasv($ftp, true)) {
                            return $ftp;
                        }
                    }
                }
            }
        }

        // Unable to connect to a server
        throw new \Exception('Unable to connect to server to download data');
    }

    /**
     * Returns the number of hours since last updating the local data
     *
     * @return float
     */
    private function hoursSinceUpdate()
    {
        $file = $this->storage('.updated');  // Last updated file
        // Return 0 if no file
        if (!file_exists($file)) {
            return static::UPDATE_INTVL_H;
        }

        // Return hours since last update
        return (time() - file_get_contents($file)) / 3600;
    }

    /**
     * Interpolates the y-value at a given x-value within a dataset using the
     * Lagrange interpolation algorithm
     *
     * @param  float $x     x-value to interpolate
     * @param  array $table Dataset
     *
     * @return float        interpolated value of y
     */
    private function lagrangeInterp($x, $table)
    {
        $sum = 0;
        for ($i = 0; $i < count($table); $i++) {
            $xi   = $table[$i]['x'];
            $prod = 1;

            for ($j = 0; $j < count($table); $j++) {
                if ($i != $j) {
                    $xj = $table[$j]['x'];
                    $prod *= ($x - $xj) / ($xi - $xj);
                }
            }

            $sum += $table[$i]['y'] * $prod;
        }

        return $sum;
    }

    /**
     * Logs activity, used for recording remote file updates
     *
     * @param string $data
     */
    private function log($data)
    {
        $data = date(DATE_RSS, time()) . "\t$data\n";
        file_put_contents($this->storage('.log'), $data, FILE_APPEND);
    }

    /**
     * Saves to disk the last remote file update timestamp using the current
     * time
     */
    private function setUpdatedNow()
    {
        file_put_contents($this->storage('.updated'), time());
    }

    /**
     * Returns a file from local storage, and creates the directory and
     * initializes default IERS data in the event that it does not exist.
     *
     * @param  string $file Filename
     *
     * @return string       Full relative path to the file
     */
    private function storage($file = null)
    {
        $folder = static::STORAGE_DIR;
        $path   = __DIR__ . "/../../../$folder";

        if (!file_exists($path)) {
            mkdir($path);
            $this->initDefaultData(realpath($path));
        }

        if (!file_exists("$path/.gitignore")) {
            file_put_contents("$path/.gitignore", '*');
        }

        return $file ? "$path/$file" : "$path";
    }

    /**
     * Checks if the local files need to be updated and updates them. This
     * occurs if a local file is missing, or if the update time interval has
     * been exceeded. While updating local file sizes are compared to remote
     * file sizes and the files are only updated if they differ in size
     *
     * @return array|bool An array of files that were updated
     * @throws \Exception
     */
    private function performUpdate()
    {
        // Check if everything is ok locally
        if ($this->filesExist()) {
            if ($this->hoursSinceUpdate() < static::UPDATE_INTVL_H) {
                return false;
            }
        }

        $filesUpdated = [];

        // Log FTP diff procedure
        $ftp = $this->ftp();
        foreach (static::FILES as $file) {
            // Local file path
            $lFile = $this->storage($file);

            // Remote and local file sizes
            $rSize = ftp_size($ftp, $file);
            $lSize = file_exists($lFile) ? filesize($lFile) : 0;

            // Check if file sizes differ
            if ($lSize != $rSize) {
                // Download files if the sizes differ
                ftp_get($ftp, $lFile, $file, FTP_ASCII);
                $filesUpdated[] = $file;
            }
        }

        // Flag current last update time to now
        $this->setUpdatedNow();

        return $filesUpdated;
    }

    /**
     * Converts a Julian day count to calendar date.
     *
     * @param  float $dj1 JD part 1
     * @param  float $dj2 JD part 2
     * @param  float $iy  Year
     * @param  float $im  Month
     * @param  float $id  Day
     * @param  float $fd  Day fraction
     *
     * @return int        Status code
     *
     */
    private static function iauJd2cal($dj1, $dj2, &$iy, &$im, &$id, &$fd)
    {
        /* Minimum and maximum allowed JD */
        $DJMIN = -68569.5;
        $DJMAX = 1e9;
        $jd;
        $l;
        $n;
        $i;
        $k;
        $dj;
        $d1;
        $d2;
        $f1;
        $f2;
        $f;
        $d;
        /* Verify date is acceptable. */
        $dj = $dj1 + $dj2;
        if ($dj < $DJMIN || $dj > $DJMAX) {
            return -1;
        }
        /* Copy the date, big then small, and re-align to midnight. */
        if ($dj1 >= $dj2) {
            $d1 = $dj1;
            $d2 = $dj2;
        } else {
            $d1 = $dj2;
            $d2 = $dj1;
        }
        $d2 -= 0.5;
        /* Separate day and fraction. */
        $f1 = fmod($d1, 1.0);
        $f2 = fmod($d2, 1.0);
        $f  = fmod($f1 + $f2, 1.0);
        if ($f < 0.0) {
            $f += 1.0;
        }
        $d  = floor($d1 - $f1) + floor($d2 - $f2) + floor($f1 + $f2 - $f);
        $jd = floor($d) + 1;
        /* Express day in Gregorian calendar. */
        // Integer division parts of this block was modified in the PHP translation
        $l = $jd + 68569;
        $n = intval((4 * $l) / 146097);
        $l -= intval((146097 * $n + 3) / 4);
        $i = intval((4000 * ($l + 1)) / 1461001);
        $l -= ((1461 * $i) / 4) - 31;
        $k  = intval((80 * $l) / 2447);
        $id = round($l - (2447 * $k) / 80);
        $l  = intval($k / 11);
        $im = (int)($k + 2 - 12 * $l);
        $iy = (int)(100 * ($n - 49) + $i + $l);
        $fd = $f;

        return 0;
    }

    /**
     * Converts a calendar date to a Julian day count
     *
     * @param  int   $iy   Year
     * @param  int   $im   Month
     * @param  int   $id   Day
     * @param  float $djm0 JD part 1
     * @param  float $djm  JD part 2
     *
     * @return int
     */
    private static function iauCal2jd($iy, $im, $id, &$djm0, &$djm)
    {
        $j;
        $ly;
        $my;
        $iypmy;
        /* Earliest year allowed (4800BC) */
        $IYMIN = -4799;
        /* Month lengths in days */
        $mtab = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        /* Preset status. */
        $j = 0;
        /* Validate year and month. */
        if ($iy < $IYMIN) {
            return -1;
        }
        if ($im < 1 || $im > 12) {
            return -2;
        }
        /* If February in a leap year, 1, otherwise 0. */
        $ly = (($im == 2) && !($iy % 4) && ($iy % 100 || !($iy % 400)));
        /* Validate day, taking into account leap years. */
        if (($id < 1) || ($id > ($mtab[$im - 1] + $ly))) {
            $j = -3;
        }
        /* Return result. */
        $my    = intval(($im - 14) / 12);
        $iypmy = intval($iy + $my);
        $djm0  = 2400000.5;
        $djm   = (double)(intval((1461 * ($iypmy + 4800)) / 4) +
          intval((367 * intval($im - 2 - 12 * $my)) / 12) -
          intval((3 * (intval(($iypmy + 4900) / 100))) / 4) +
          intval($id - 2432076));

        /* Return status. */

        return $j;
    }

    /**
     * Initializes the package with default cached data from IERS so that it
     * can be run without ever having to query the remote servers, however...
     * if the update command is run the of course the data will be updated.
     *
     * @param $path
     */
    private function initDefaultData($path)
    {
        $dir = __DIR__;
        foreach (static::FILES as $file) {
            copy("{$dir}/default/{$file}", "{$path}/{$file}");
        }
    }

}
