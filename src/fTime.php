<?php
/**
 * Represents a time of day as a value object.
 *
 * @copyright  Copyright (c) 2008-2010 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 *
 * @see       http://flourishlib.com/fTime
 *
 * @version    1.0.0b9
 * @changes    1.0.0b9  Added the `$simple` parameter to ::getFuzzyDifference() [wb, 2010-03-15]
 * @changes    1.0.0b8  Added a call to fTimestamp::callUnformatCallback() in ::__construct() for localization support [wb, 2009-06-01]
 * @changes    1.0.0b7  Backwards compatibility break - Removed ::getSecondsDifference(), added ::eq(), ::gt(), ::gte(), ::lt(), ::lte() [wb, 2009-03-05]
 * @changes    1.0.0b6  Fixed an outdated fCore method call [wb, 2009-02-23]
 * @changes    1.0.0b5  Updated for new fCore API [wb, 2009-02-16]
 * @changes    1.0.0b4  Fixed ::__construct() to properly handle the 5.0 to 5.1 change in strtotime() [wb, 2009-01-21]
 * @changes    1.0.0b3  Added support for CURRENT_TIMESTAMP and CURRENT_TIME SQL keywords [wb, 2009-01-11]
 * @changes    1.0.0b2  Removed the adjustment amount check from ::adjust() [wb, 2008-12-31]
 * @changes    1.0.0b   The initial implementation [wb, 2008-02-12]
 */
class fTime
{
    /**
     * A timestamp of the time.
     *
     * @var int
     */
    private $time;

    /**
     * Creates the time to represent, no timezone is allowed since times don't have timezones.
     *
     * @param fTime|int|object|string $time The time to represent, `NULL` is interpreted as now
     *
     * @throws fValidationException When `$time` is not a valid time
     *
     * @return fTime
     */
    public function __construct($time = null)
    {
        if ($time === null) {
            $timestamp = time();
        } elseif (is_numeric($time) && ctype_digit($time)) {
            $timestamp = (int) $time;
        } elseif (is_string($time) && in_array(strtoupper($time), ['CURRENT_TIMESTAMP', 'CURRENT_TIME'])) {
            $timestamp = time();
        } else {
            if (is_object($time) && is_callable([$time, '__toString'])) {
                $time = $time->__toString();
            } elseif (is_numeric($time) || is_object($time)) {
                $time = (string) $time;
            }

            $time = fTimestamp::callUnformatCallback($time);

            $timestamp = strtotime($time);
        }

        $is_51 = fCore::checkVersion('5.1');
        $is_valid = ($is_51 && $timestamp !== false) || (! $is_51 && $timestamp !== -1);

        if (! $is_valid) {
            throw new fValidationException(
                'The time specified, %s, does not appear to be a valid time',
                $time
            );
        }

        $this->time = strtotime(date('1970-01-01 H:i:s', $timestamp));
    }

    /**
     * All requests that hit this method should be requests for callbacks.
     *
     * @internal
     *
     * @param string $method The method to create a callback for
     *
     * @return callable The callback for the method requested
     */
    public function __get($method)
    {
        return [$this, $method];
    }

    /**
     * Returns this time in `'H:i:s'` format.
     *
     * @return string The `'H:i:s'` format of this time
     */
    public function __toString()
    {
        return date('H:i:s', $this->time);
    }

    /**
     * Changes the time by the adjustment specified, only adjustments of `'hours'`, `'minutes'`, and `'seconds'` are allowed.
     *
     * @param string $adjustment The adjustment to make
     *
     * @throws fValidationException When `$adjustment` is not a valid relative time measurement
     *
     * @return fTime The adjusted time
     */
    public function adjust($adjustment)
    {
        $timestamp = strtotime($adjustment, $this->time);

        if ($timestamp === false || $timestamp === -1) {
            throw new fValidationException(
                'The adjustment specified, %s, does not appear to be a valid relative time measurement',
                $adjustment
            );
        }

        return new self($timestamp);
    }

    /**
     * If this time is equal to the time passed.
     *
     * @param fTime|int|object|string $other_time The time to compare with, `NULL` is interpreted as today
     *
     * @return bool If this time is equal to the one passed
     */
    public function eq($other_time = null)
    {
        $other_time = new self($other_time);

        return $this->time == $other_time->time;
    }

    /**
     * Formats the time.
     *
     * @param string $format The [http://php.net/date date()] function compatible formatting string, or a format name from fTimestamp::defineFormat()
     *
     * @throws fValidationException When a non-time formatting character is included in `$format`
     *
     * @return string The formatted time
     */
    public function format($format)
    {
        $format = fTimestamp::translateFormat($format);

        $restricted_formats = 'cdDeFIjlLmMnNoOPrStTUwWyYzZ';
        if (preg_match('#(?!\\\\).['.$restricted_formats.']#', $format)) {
            throw new fProgrammerException(
                'The formatting string, %1$s, contains one of the following non-time formatting characters: %2$s',
                $format,
                implode(', ', str_split($restricted_formats))
            );
        }

        return fTimestamp::callFormatCallback(date($format, $this->time));
    }

    /**
     * Returns the approximate difference in time, discarding any unit of measure but the least specific.
     *
     * The output will read like:
     *
     *  - "This time is `{return value}` the provided one" when a time it passed
     *  - "This time is `{return value}`" when no time is passed and comparing with the current time
     *
     * Examples of output for a time passed might be:
     *
     *  - `'5 minutes after'`
     *  - `'2 hours before'`
     *  - `'at the same time'`
     *
     * Examples of output for no time passed might be:
     *
     *  - `'5 minutes ago'`
     *  - `'2 hours ago'`
     *  - `'right now'`
     *
     * You would never get the following output since it includes more than one unit of time measurement:
     *
     *  - `'5 minutes and 28 seconds'`
     *  - `'1 hour, 15 minutes'`
     *
     * Values that are close to the next largest unit of measure will be rounded up:
     *
     *  - `'55 minutes'` would be represented as `'1 hour'`, however `'45 minutes'` would not
     *
     * @param null|self $other_time
     * @param bool                    $simple     When `TRUE`, the returned value will only include the difference in the two times, but not `from now`, `ago`, `after` or `before`
     * @param bool                     :$simple
     */
    public function getFuzzyDifference(self|null $other_time = null, bool $simple = false): string
    {
        if (is_bool($other_time)) {
            $simple = $other_time;
            $other_time = null;
        }

        $relative_to_now = false;
        if ($other_time === null) {
            $relative_to_now = true;
        }
        $other_time = new self($other_time);

        $diff = $this->time - $other_time->time;

        if (abs($diff) < 10) {
            if ($relative_to_now) {
                return self::compose('right now');
            }

            return self::compose('at the same time');
        }

        static $break_points = [];
        if (! $break_points) {
            $break_points = [
                // 45 seconds
                45 => [1,     self::compose('second'), self::compose('seconds')],
                // 45 minutes
                2700 => [60,    self::compose('minute'), self::compose('minutes')],
                // 18 hours
                64800 => [3600,  self::compose('hour'),   self::compose('hours')],
                // 5 days
                432000 => [86400, self::compose('day'),    self::compose('days')],
            ];
        }

        foreach ($break_points as $break_point => $unit_info) {
            if (abs($diff) > $break_point) {
                continue;
            }

            $unit_diff = round(abs($diff) / $unit_info[0]);
            $units = fGrammar::inflectOnQuantity($unit_diff, $unit_info[1], $unit_info[2]);

            break;
        }

        if ($simple) {
            return self::compose('%1$s %2$s', $unit_diff, $units);
        }

        if ($relative_to_now) {
            if ($diff > 0) {
                return self::compose('%1$s %2$s from now', $unit_diff, $units);
            }

            return self::compose('%1$s %2$s ago', $unit_diff, $units);
        }

        if ($diff > 0) {
            return self::compose('%1$s %2$s after', $unit_diff, $units);
        }

        return self::compose('%1$s %2$s before', $unit_diff, $units);
    }

    /**
     * If this time is greater than the time passed.
     *
     * @param fTime|int|object|string $other_time The time to compare with, `NULL` is interpreted as now
     *
     * @return bool If this time is greater than the one passed
     */
    public function gt($other_time = null)
    {
        $other_time = new self($other_time);

        return $this->time > $other_time->time;
    }

    /**
     * If this time is greater than or equal to the time passed.
     *
     * @param fTime|int|object|string $other_time The time to compare with, `NULL` is interpreted as now
     *
     * @return bool If this time is greater than or equal to the one passed
     */
    public function gte($other_time = null)
    {
        $other_time = new self($other_time);

        return $this->time >= $other_time->time;
    }

    /**
     * If this time is less than the time passed.
     *
     * @param fTime|int|object|string $other_time The time to compare with, `NULL` is interpreted as today
     *
     * @return bool If this time is less than the one passed
     */
    public function lt($other_time = null)
    {
        $other_time = new self($other_time);

        return $this->time < $other_time->time;
    }

    /**
     * If this time is less than or equal to the time passed.
     *
     * @param fTime|int|object|string $other_time The time to compare with, `NULL` is interpreted as today
     *
     * @return bool If this time is less than or equal to the one passed
     */
    public function lte($other_time = null)
    {
        $other_time = new self($other_time);

        return $this->time <= $other_time->time;
    }

    /**
     * Modifies the current time, creating a new fTime object.
     *
     * The purpose of this method is to allow for easy creation of a time
     * based on this time. Below are some examples of formats to
     * modify the current time:
     *
     *  - `'17:i:s'` to set the hour of the time to 5 PM
     *  - 'H:00:00'` to set the time to the beginning of the current hour
     *
     * @param string $format The current time will be formatted with this string, and the output used to create a new object
     *
     * @return fTime The new time
     */
    public function modify($format)
    {
        return new self($this->format($format));
    }

    /**
     * Composes text using fText if loaded.
     *
     * @param string $message   The message to compose
     * @param mixed  $component A string or number to insert into the message
     * @param  mixed   ...
     *
     * @return string The composed and possible translated message
     */
    protected static function compose($message)
    {
        $args = array_slice(func_get_args(), 1);

        if (class_exists('fText', false)) {
            return call_user_func_array(
                ['fText', 'compose'],
                [$message, $args]
            );
        }

        return vsprintf($message, $args);
    }
}

/*
 * Copyright (c) 2008-2010 Will Bond <will@flourishlib.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
