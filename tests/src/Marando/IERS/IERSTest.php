<?php

/*
 * Copyright (C) 2015 ashley
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
 * Generated by PHPUnit_SkeletonGenerator on 2015-11-14 at 01:45:44.
 */
class IERSTest extends \PHPUnit_Framework_TestCase {

  /**
   * @covers Marando\IERS\IERS::jd
   */
  public function testJd() {
    $iers = IERS::jd(2451545.5);
    $this->assertEquals(51545, $iers->mjd);
  }

  /**
   * @covers Marando\IERS\IERS::mjd
   */
  public function testMjd() {
    $iers = IERS::mjd(51545);
    $this->assertEquals(2451545.5, $iers->jd);
  }

  /**
   * @covers Marando\IERS\IERS::dut1
   */
  public function testDut1() {
    $tests = [
        [43880, 0.5776000],
        [50116, 0.4964100],
        [57746, null],
        [57646, 0.2065257],
        [48411, 0.2730300],
    ];

    foreach ($tests as $t) {
      $mjd  = $t[0];
      $dut1 = $t[1];

      $iers = IERS::mjd($mjd);
      $this->assertEquals($dut1, $iers->dut1());
    }
  }

  /**
   * @covers Marando\IERS\IERS::x
   */
  public function testX() {
    $tests = [
        [43880, +0.143000],
        [50116, -0.222200],
        [57746, null],
        [57646, +0.211645],
        [48411, -0.124100],
    ];

    foreach ($tests as $t) {
      $mjd  = $t[0];
      $dut1 = $t[1];

      $iers = IERS::mjd($mjd);
      $this->assertEquals($dut1, $iers->x());
    }
  }

  /**
   * @covers Marando\IERS\IERS::y
   */
  public function testY() {
    $tests = [
        [43880, +0.069000],
        [50116, +0.298700],
        [57746, null],
        [57646, +0.015793],
        [48411, +0.508500],
    ];

    foreach ($tests as $t) {
      $mjd  = $t[0];
      $dut1 = $t[1];

      $iers = IERS::mjd($mjd);
      $this->assertEquals($dut1, $iers->y());
    }
  }

  /**
   * @covers Marando\IERS\IERS::deltaT
   */
  public function testDeltaT() {
    $tests = [
        [2456566.5, 67.1717],
        [2449384.5, 60.0564],
        [2441714.5, 43.4724],
        [2041714.5, false],
        [2416846.5, 3.92],
        [2457754.5, 68.6],
        [2459366.5, 71.0],
    ];

    foreach ($tests as $t) {
      $mjd = $t[0];
      $ΔT  = $t[1];

      $iers = IERS::jd($mjd);
      $this->assertEquals($ΔT, $iers->deltaT(), $mjd);
    }
  }

  /**
   * @covers Marando\IERS\IERS::leapSec
   */
  public function testLeapSec() {
    $tests = [
        [2441317.5, 10.0],
        [2438334.5, 1.9458580],
        [2456109.5, 35],
        [2456109.4, 35],
    ];

    foreach ($tests as $t) {
      $jd   = $t[0];
      $lSec = $t[1];
      $this->assertEquals($lSec, IERS::jd($jd)->leapSec(), $jd);
    }
  }

  public function benchmark() {
    $iterations = 10;

    for ($j = 0; $j < 5; $j++) {
      // Method A
      $t = microtime(true);
      for ($i = 0; $i < $iterations; $i++) {
        IERS::jd(2451545.5 + $i)->deltaT();
        IERS::jd(2451545.5 + $i)->dut1();
        IERS::jd(2451545.5 + $i)->leapSec();
        IERS::jd(2451545.5 + $i)->x();
        IERS::jd(2451545.5 + $i)->y();
      }
      echo "\n\nA  -> " . round(microtime(true) - $t, 3) . ' seconds';

      // Method B
      $t = microtime(true);
      for ($i = 0; $i < $iterations; $i++) {
        $iers = IERS::jd(2451545.5 + $i);
        $iers->deltaT();
        $iers->dut1();
        $iers->leapSec();
        $iers->x();
        $iers->y();
      }
      echo "\nB  -> " . round(microtime(true) - $t, 3) . ' seconds';

      // Bethod B-1
      $t = microtime(true);
      for ($i = 0; $i < $iterations; $i++) {
        $iers = IERS::jd(2451545.5 + $i);
        $iers->deltaT();
        $iers->dut1();
        $iers->leapSec();
        $iers->x();
        $iers->y();
        $iers = null;
      }
      echo "\nB1 -> " . round(microtime(true) - $t, 3) . ' seconds';
    }
  }

}
